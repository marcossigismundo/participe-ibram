<?php
/**
 * Query: trilha de auditoria pessoal do titular (Art. 18 II LGPD).
 *
 * @package Ibram\ParticipeIbram\Application\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\MinhaConta;

use InvalidArgumentException;

/**
 * Lê `wp_pi_audit_log` filtrando estritamente pelos eventos do **próprio**
 * titular e devolve uma descrição AMIGÁVEL para a UI da aba "Histórico" da
 * minha conta.
 *
 * Defesa em profundidade — campos NUNCA expostos:
 *  - `dados_antes` / `dados_depois` — JSON pode conter PII de terceiros se mal
 *    redatado a montante; aqui só lemos `entidade`, `entidade_id`, `acao`,
 *    `ocorrido_em` no SELECT.
 *  - `ip_hash` — não relevante para o usuário e poderia auxiliar correlação
 *    cross-account.
 *  - `user_agent` — idem.
 *  - `ator_id` — em alguns eventos pode revelar a identidade de servidor
 *    (analista), o que não é objetivo do histórico pessoal do titular.
 *
 * O filtro de "pertencer ao próprio titular" usa dupla condição (OR):
 *  - `ator_id = userId` (eventos disparados pelo próprio user)
 *  - `entidade IN ('agente','inscricao','recurso','recurso_inabilitacao') AND
 *    entidade_id IN (...IDs do agente e suas inscrições/recursos...)`
 *
 * Como o cálculo de "minhas inscrições/recursos" é cross-domain, recebe-se via
 * construtor um resolver. Aqui apenas decoramos a query e o mapeamento de
 * `acao` → descrição amigável em pt_BR.
 */
final class AuditTrailPessoalQuery
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * Mapa `acao` → descrição amigável (pt_BR). Padrão LGPD: linguagem clara
     * para o titular (Art. 9º).
     *
     * @var array<string,string>
     */
    private const ACOES_AMIGAVEIS = [
        'criar'                      => 'Cadastro criado',
        'rascunho_salvo'             => 'Rascunho salvo',
        'submeter'                   => 'Cadastro submetido',
        'submeter_cadastro'          => 'Cadastro submetido',
        'deferir'                    => 'Cadastro deferido',
        'indeferir'                  => 'Cadastro indeferido',
        'assumir_analise'            => 'Análise do cadastro iniciada',
        'transicao_status'           => 'Status alterado',
        'atualizar'                  => 'Dados atualizados',
        'protocolar'                 => 'Recurso protocolado',
        'protocolar_recurso'         => 'Recurso protocolado',
        'decidir'                    => 'Recurso decidido',
        'decidir_retratacao'         => 'Recurso (retratação) decidido',
        'decidir_recurso_presidencia' => 'Recurso (presidência) decidido',
        'inscrever'                  => 'Inscrição em edital realizada',
        'salvar_rascunho_inscricao'  => 'Rascunho de inscrição salvo',
        'habilitar'                  => 'Inscrição habilitada',
        'inabilitar'                 => 'Inscrição inabilitada',
        'consentimento_registrado'   => 'Consentimento registrado',
        'consentimento_revogado'     => 'Consentimento revogado',
        'anonimizar'                 => 'Dados anonimizados',
        'export_solicitado'          => 'Exportação de dados solicitada',
        'visualizar_dado_sensivel'   => 'Acesso a dado sensível visualizado',
        'unsubscribe'                => 'Cancelamento de envios de e-mail',
        // Re-acesso ao recibo de voto — informativo (sem revelar candidato).
        'regerar'                    => 'Recibo de voto regenerado',
        'regerar_inexistente'        => 'Tentativa de regerar recibo inexistente',
    ];

    /**
     * Conjunto de `entidade` aceitas. Restringe a query e evita exibir eventos
     * de outros agregados que possam vir a parar no audit log do agente.
     *
     * @var array<int,string>
     */
    private const ENTIDADES_PESSOAIS = [
        'agente',
        'inscricao',
        'recurso',
        'recurso_inabilitacao',
        'consentimento',
        'documento',
        'recibo_voto',
        'solicitacao_titular',
    ];

    /**
     * @param \wpdb $wpdb
     */
    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_audit_log');
    }

    /**
     * @param list<int> $entidadeIdsDoAgente   IDs de `inscricoes`, `recursos`, etc., do agente.
     *                                          Permite ampliar a busca para eventos
     *                                          de entidades filhas do agente.
     *
     * @return array{
     *   items: list<array{entidade:string, acao:string, ocorrido_em:string, descricao_amigavel:string}>,
     *   total: int,
     *   page: int,
     *   per_page: int
     * }
     */
    public function listar(
        int $agenteId,
        int $userId,
        array $entidadeIdsDoAgente = [],
        int $page = 1,
        int $perPage = 20
    ): array {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AuditTrailPessoalQuery.listar: agenteId invalido.');
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('AuditTrailPessoalQuery.listar: userId invalido.');
        }

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        // Sanitiza lista de ids — defesa contra qualquer string que tenha chegado.
        $idsClean = [];
        foreach ($entidadeIdsDoAgente as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $idsClean[] = $id;
            }
        }
        $idsClean = array_values(array_unique($idsClean));

        // Coloca o próprio agente_id também (entidade=agente).
        $idsClean[] = $agenteId;
        $idsClean   = array_values(array_unique($idsClean));

        // Whitelist de entidades — interpolada estaticamente, sem input do user.
        $entidadesIn = "'" . implode("','", array_map(
            static fn (string $e): string => addslashes($e),
            self::ENTIDADES_PESSOAIS
        )) . "'";

        // Placeholders para IDs.
        $idsPh = implode(',', array_fill(0, count($idsClean), '%d'));

        // SELECT explícito — nunca dados_antes/dados_depois/ip_hash/user_agent/ator_id.
        $sqlBase = "FROM {$this->tableName}
                    WHERE (
                        ator_id = %d
                        OR (entidade IN ({$entidadesIn}) AND entidade_id IN ({$idsPh}))
                    )";

        // Args na ordem: userId, depois cada id.
        $args = array_merge([$userId], $idsClean);

        $totalSql = "SELECT COUNT(*) {$sqlBase}";
        $listSql  = "SELECT entidade, entidade_id, acao, ocorrido_em {$sqlBase}
                     ORDER BY ocorrido_em DESC, id DESC
                     LIMIT %d OFFSET %d";

        $totalPrepared = $this->wpdb->prepare($totalSql, ...$args);
        $total         = (int) $this->wpdb->get_var($totalPrepared);

        $listArgs     = array_merge($args, [$perPage, $offset]);
        $listPrepared = $this->wpdb->prepare($listSql, ...$listArgs);
        $rows         = $this->wpdb->get_results($listPrepared, ARRAY_A);
        if (!is_array($rows)) {
            $rows = [];
        }

        $items = [];
        foreach ($rows as $row) {
            $entidade = (string) ($row['entidade'] ?? '');
            $acao     = (string) ($row['acao'] ?? '');
            $items[]  = [
                'entidade'           => $entidade,
                'acao'               => $acao,
                'ocorrido_em'        => (string) ($row['ocorrido_em'] ?? ''),
                'descricao_amigavel' => self::descricaoAmigavel($entidade, $acao),
            ];
        }

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Traduz uma combinação `(entidade, acao)` para uma frase em pt_BR.
     *
     * Estratégia:
     *  - Tenta o mapa pelo `acao` direto.
     *  - Se a entidade for `voto` ou `recibo_voto`, NUNCA descreve candidato.
     *  - Fallback: "Evento: {entidade} — {acao}" (pluga i18n quando WP existe).
     */
    public static function descricaoAmigavel(string $entidade, string $acao): string
    {
        $acaoKey = strtolower(trim($acao));
        $entKey  = strtolower(trim($entidade));

        // CRÍTICO: para entidade voto, JAMAIS descrever candidato.
        if ($entKey === 'voto') {
            // Auditoria do voto é técnica — para o usuário só dizemos genérico.
            $fallback = 'Voto registrado (conteúdo do voto é secreto)';
            if (function_exists('__')) {
                /** @var string $traduzido */
                $traduzido = \__('Voto registrado (conteúdo do voto é secreto)', 'participe-ibram');

                return $traduzido;
            }

            return $fallback;
        }

        if (isset(self::ACOES_AMIGAVEIS[$acaoKey])) {
            $msg = self::ACOES_AMIGAVEIS[$acaoKey];
            if (function_exists('__')) {
                /** @var string $traduzido */
                $traduzido = \__($msg, 'participe-ibram');

                return $traduzido;
            }

            return $msg;
        }

        // Fallback genérico (textual, sem dados crus).
        $generico = sprintf('Evento: %s — %s', $entKey, $acaoKey);
        if (function_exists('__')) {
            /** @var string $traduzido */
            $traduzido = \__('Evento registrado', 'participe-ibram');

            return $traduzido . ': ' . $generico;
        }

        return $generico;
    }
}
