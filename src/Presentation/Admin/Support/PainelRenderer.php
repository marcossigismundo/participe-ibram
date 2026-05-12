<?php
/**
 * PainelRenderer — coleta KPIs e renderiza o template do Painel (W11-A).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Reúne todos os contadores numéricos para o Painel (Visão Geral) e dispatcha
 * o template `templates/admin/painel.php`. Nada de PII — apenas inteiros
 * agregados via `COUNT(*)` direto em `$wpdb` (cacheado por request).
 *
 * Por que estática + closures de COUNT:
 *  - mantém zero dependência forte com os repositories (que não expõem count);
 *  - cada COUNT é uma única query prepared, sem SELECT *.
 *
 * Caching: por request (memoize). Não invalida cache do core — usa o
 * `pi_dashboard` group da {@see DashboardMetricsQuery} apenas onde já existe.
 */
final class PainelRenderer
{
    /** @var array<string,int>|null */
    private static ?array $cachedKpis = null;

    /**
     * Renderiza o painel completo. Chamada pelo `Plugin::renderRootStub`
     * (fallback) e por `MenuRegistry::renderDashboard`.
     */
    public static function render(): void
    {
        if (!function_exists('current_user_can') || !\current_user_can('pi_listar_cadastros')) {
            if (function_exists('wp_die')) {
                \wp_die(
                    function_exists('esc_html__')
                        ? (string) \esc_html__('Sem permissão para acessar esta página.', 'participe-ibram')
                        : 'Acesso negado.',
                    403
                );
            }
            return;
        }

        $kpis = self::collectKpis();
        $proximoPasso = self::nextStepForCurrentUser($kpis);

        $templatePath = self::templatePath();
        if ($templatePath === null) {
            self::renderFallback($kpis, $proximoPasso);
            return;
        }

        // Inject as locals visible to the template via include scope.
        $kpi_cadastros_pendentes = (int) $kpis['cadastros_pendentes'];
        $kpi_editais_publicados  = (int) $kpis['editais_publicados'];
        $kpi_recursos_abertos    = (int) $kpis['recursos_abertos'];
        $kpi_votacoes_em_curso   = (int) $kpis['votacoes_em_curso'];
        $kpi_lgpd_pendentes      = (int) $kpis['lgpd_pendentes'];
        $kpi_emails_pendentes    = (int) $kpis['emails_pendentes'];
        $proximo_passo           = $proximoPasso;

        include $templatePath;
    }

    /**
     * Retorna os 6 KPIs do painel. Cacheado por request.
     *
     * @return array<string,int>
     */
    private static function collectKpis(): array
    {
        if (self::$cachedKpis !== null) {
            return self::$cachedKpis;
        }

        global $wpdb;

        $defaults = [
            'cadastros_pendentes' => 0,
            'editais_publicados'  => 0,
            'recursos_abertos'    => 0,
            'votacoes_em_curso'   => 0,
            'lgpd_pendentes'      => 0,
            'emails_pendentes'    => 0,
        ];

        if (!is_object($wpdb) || !method_exists($wpdb, 'get_var')) {
            self::$cachedKpis = $defaults;
            return $defaults;
        }

        $prefix = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';

        // Closures de COUNT: cada uma um SELECT prepared, com fallback 0 em erro.
        $count = static function (string $sql, array $args = []) use ($wpdb): int {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared = $args === [] ? $sql : $wpdb->prepare($sql, ...$args);
            if (!is_string($prepared)) {
                return 0;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $value = $wpdb->get_var($prepared);
            return is_numeric($value) ? (int) $value : 0;
        };

        $kpis = $defaults;

        $kpis['cadastros_pendentes'] = $count(
            "SELECT COUNT(*) FROM {$prefix}pi_agentes WHERE status_cadastro = %s",
            ['submetido']
        );

        $kpis['editais_publicados'] = $count(
            "SELECT COUNT(*) FROM {$prefix}pi_editais WHERE status = %s",
            ['publicado']
        );

        // Recursos "abertos" = decisao IS NULL (ver WpdbRecursoRepository::findVencendoEm).
        $kpis['recursos_abertos'] = $count(
            "SELECT COUNT(*) FROM {$prefix}pi_recursos WHERE decisao IS NULL"
        );

        // Votações em curso = status = 'aberta' E NOW() dentro da janela.
        $kpis['votacoes_em_curso'] = $count(
            "SELECT COUNT(*) FROM {$prefix}pi_votacoes
             WHERE status = %s AND abertura <= NOW() AND encerramento > NOW()",
            ['aberta']
        );

        // LGPD pendentes = solicitações em status 'aberta' ou 'em_atendimento'
        // (ver SolicitacaoTitular::STATUS_ABERTA / STATUS_EM_ATENDIMENTO).
        $kpis['lgpd_pendentes'] = $count(
            "SELECT COUNT(*) FROM {$prefix}pi_solicitacoes_titular
             WHERE status IN (%s, %s)",
            ['aberta', 'em_atendimento']
        );

        // Alertas de prazo = fila de e-mail com status 'pendente'.
        $kpis['emails_pendentes'] = $count(
            "SELECT COUNT(*) FROM {$prefix}pi_email_queue WHERE status = %s",
            ['pendente']
        );

        self::$cachedKpis = $kpis;
        return $kpis;
    }

    /**
     * Sugere o próximo passo mais relevante para o usuário atual, baseado
     * nas capabilities e nos KPIs. Retorna um array consumível pelo template.
     *
     * @param array<string,int> $kpis
     * @return array{titulo:string,descricao:string,url:?string,label:string}|null
     */
    private static function nextStepForCurrentUser(array $kpis): ?array
    {
        if (!function_exists('current_user_can') || !function_exists('admin_url')) {
            return null;
        }

        // Analista / Presidência: cadastros aguardando análise.
        if (\current_user_can('pi_analisar_cadastro') && $kpis['cadastros_pendentes'] > 0) {
            return [
                'titulo'    => __('Há cadastros aguardando análise', 'participe-ibram'),
                'descricao' => sprintf(
                    /* translators: %s: quantidade de cadastros submetidos. */
                    _n(
                        'Você tem %s cadastro aguardando análise. Comece pela fila para manter os prazos da Portaria 3230/2024.',
                        'Você tem %s cadastros aguardando análise. Comece pela fila para manter os prazos da Portaria 3230/2024.',
                        $kpis['cadastros_pendentes'],
                        'participe-ibram'
                    ),
                    number_format_i18n($kpis['cadastros_pendentes'])
                ),
                'url'       => admin_url('admin.php?page=participe-ibram_cadastros'),
                'label'     => __('Abrir fila de análise', 'participe-ibram'),
            ];
        }

        // Presidência: recursos para decisão final.
        if (\current_user_can('pi_decidir_recurso_presidencia') && $kpis['recursos_abertos'] > 0) {
            return [
                'titulo'    => __('Recursos aguardando decisão da Presidência', 'participe-ibram'),
                'descricao' => __('Existem recursos abertos. Verifique a fila da Presidência para decidir dentro do prazo.', 'participe-ibram'),
                'url'       => admin_url('admin.php?page=participe-ibram_recursos_presidencia'),
                'label'     => __('Ver recursos na Presidência', 'participe-ibram'),
            ];
        }

        // Gestor de edital: votações em curso (acompanhamento) ou criar edital.
        if (\current_user_can('pi_criar_edital')) {
            if ($kpis['votacoes_em_curso'] > 0) {
                return [
                    'titulo'    => __('Há votações em curso', 'participe-ibram'),
                    'descricao' => __('Acompanhe o andamento das votações abertas e suas habilitações.', 'participe-ibram'),
                    'url'       => admin_url('admin.php?page=participe-ibram_votacoes'),
                    'label'     => __('Ver votações', 'participe-ibram'),
                ];
            }
            return [
                'titulo'    => __('Comece publicando um edital', 'participe-ibram'),
                'descricao' => __('Editais publicados abrem o ciclo de inscrições, habilitação e votação.', 'participe-ibram'),
                'url'       => admin_url('admin.php?page=participe-ibram_edital_novo'),
                'label'     => __('Criar novo edital', 'participe-ibram'),
            ];
        }

        // Apuração: votações em curso.
        if (\current_user_can('pi_apurar_votacao') && $kpis['votacoes_em_curso'] > 0) {
            return [
                'titulo'    => __('Votações em curso aguardando apuração', 'participe-ibram'),
                'descricao' => __('Quando a janela de votação encerrar, execute a apuração para gerar resultados auditáveis.', 'participe-ibram'),
                'url'       => admin_url('admin.php?page=participe-ibram_votacoes'),
                'label'     => __('Abrir votações', 'participe-ibram'),
            ];
        }

        // DPO: solicitações pendentes.
        if (\current_user_can('pi_atender_solicitacao_titular') && $kpis['lgpd_pendentes'] > 0) {
            return [
                'titulo'    => __('Solicitações LGPD aguardando atendimento', 'participe-ibram'),
                'descricao' => sprintf(
                    /* translators: %s: número de solicitações pendentes. */
                    _n(
                        'Há %s solicitação de titular em aberto. O prazo legal é de 15 dias.',
                        'Há %s solicitações de titulares em aberto. O prazo legal é de 15 dias.',
                        $kpis['lgpd_pendentes'],
                        'participe-ibram'
                    ),
                    number_format_i18n($kpis['lgpd_pendentes'])
                ),
                'url'       => admin_url('admin.php?page=pi-dpo-config'),
                'label'     => __('Abrir painel do DPO', 'participe-ibram'),
            ];
        }

        // Auditor (sem outros papéis ativos): consultar logs.
        if (\current_user_can('pi_visualizar_audit_log')) {
            return [
                'titulo'    => __('Tudo em dia por aqui', 'participe-ibram'),
                'descricao' => __('Sem pendências nos seus painéis. Use a Auditoria para revisar os últimos eventos do plugin.', 'participe-ibram'),
                'url'       => admin_url('admin.php?page=participe-ibram_audit_log'),
                'label'     => __('Abrir log de auditoria', 'participe-ibram'),
            ];
        }

        // Fallback amigável.
        return [
            'titulo'    => __('Bem-vindo ao Participe Ibram', 'participe-ibram'),
            'descricao' => __('Use o menu à esquerda para navegar entre as áreas: Cadastros, Editais, Votações, Auditoria e Ferramentas.', 'participe-ibram'),
            'url'       => null,
            'label'     => '',
        ];
    }

    /**
     * Retorna o caminho absoluto do template, ou null se não localizado.
     */
    private static function templatePath(): ?string
    {
        if (defined('PI_PLUGIN_DIR')) {
            $base = (string) \PI_PLUGIN_DIR;
        } else {
            $base = dirname(__DIR__, 4);
        }
        $candidate = rtrim($base, '/\\') . '/templates/admin/painel.php';
        return file_exists($candidate) ? $candidate : null;
    }

    /**
     * Fallback minimalista caso o template não exista no disco.
     *
     * @param array<string,int>                                                                $kpis
     * @param array{titulo:string,descricao:string,url:?string,label:string}|null              $proximoPasso
     */
    private static function renderFallback(array $kpis, ?array $proximoPasso): void
    {
        echo '<div class="participe-ibram-scope wrap"><h1>'
            . esc_html__('Painel — Participe Ibram', 'participe-ibram')
            . '</h1>';
        echo '<p>' . esc_html__('Template do painel ausente. Mostrando contadores em texto simples.', 'participe-ibram') . '</p>';
        echo '<ul>';
        foreach ($kpis as $key => $value) {
            echo '<li><strong>' . esc_html((string) $key) . ':</strong> ' . (int) $value . '</li>';
        }
        echo '</ul>';
        if ($proximoPasso !== null) {
            echo '<p><em>' . esc_html((string) $proximoPasso['titulo']) . '</em> — '
                . esc_html((string) $proximoPasso['descricao']) . '</p>';
        }
        echo '</div>';
    }
}
