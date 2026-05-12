<?php
/**
 * Controller admin de E-mail (configuração SMTP, fila, logs, preview templates).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Email\SmtpConfig;
use Ibram\ParticipeIbram\Application\Email\Templates\EmailRenderer;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Domain\Email\EventoEmail;
use Ibram\ParticipeIbram\Domain\Email\MensagemEnfileirada;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\EmailLogsListTable;

/**
 * Submenu admin "Participe Ibram > E-mail" (capability `pi_administrar_email`).
 *
 * Tabs:
 *  - config:    formulário SMTP (password cifrada ao salvar)
 *  - fila:      lista de pendentes (read-only) com action "Reenviar"
 *  - logs:      log unificado (todos status)
 *  - templates: preview read-only com vars de exemplo
 *
 * Acessibilidade: WCAG 2.1 AA — labels associadas a inputs, navegação via
 * `<nav>` + `<a aria-current="page">`, contrast safe, sem cor sozinha.
 */
final class EmailController
{
    public const CAPABILITY  = 'pi_administrar_email';
    public const MENU_SLUG   = 'pi-email';
    public const PARENT_SLUG = 'participe-ibram';

    private SmtpConfig $smtp;
    private EmailQueueRepository $fila;
    private EmailRenderer $renderer;
    private string $templateBaseDir;

    public function __construct(
        SmtpConfig $smtp,
        EmailQueueRepository $fila,
        EmailRenderer $renderer,
        string $templateBaseDir
    ) {
        $this->smtp            = $smtp;
        $this->fila            = $fila;
        $this->renderer        = $renderer;
        $this->templateBaseDir = $templateBaseDir;
    }

    /**
     * Registra menu admin. Hook em admin_menu.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }
        \add_submenu_page(
            self::PARENT_SLUG,
            __('Ferramentas — E-mail', 'participe-ibram'),
            __('E-mail', 'participe-ibram'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    /**
     * Render principal — dispatcher por tab.
     */
    public function render(): void
    {
        if (!function_exists('current_user_can') || !\current_user_can(self::CAPABILITY)) {
            \wp_die(esc_html__('Permissao negada.', 'participe-ibram'));
        }

        $tab = (string) RequestHelper::get('tab', 'sanitize_key', 'config');
        if (!in_array($tab, ['config', 'fila', 'logs', 'templates'], true)) {
            $tab = 'config';
        }

        $data = [
            'tab'    => $tab,
            'tabs'   => $this->tabs(),
            'menu'   => self::MENU_SLUG,
        ];

        switch ($tab) {
            case 'config':
                $data['snapshot']     = $this->smtp->snapshotPublic();
                $data['nonce']        = function_exists('wp_create_nonce')
                    ? \wp_create_nonce('pi_admin_email_save_config')
                    : '';
                break;
            case 'fila':
                $data['list_table'] = $this->buildListTable(MensagemEnfileirada::STATUS_PENDENTE);
                break;
            case 'logs':
                $data['list_table'] = $this->buildListTable(null);
                $data['eventos']    = EventoEmail::values();
                break;
            case 'templates':
                $data['eventos']            = EventoEmail::values();
                $data['template_selected']  = (string) RequestHelper::get('template', 'sanitize_key', '');
                $data['preview']            = $this->renderPreview($data['template_selected']);
                break;
        }

        $tplFile = PI_PLUGIN_DIR . 'templates/admin/email/index.php';
        if (!is_file($tplFile)) {
            echo '<div class="wrap"><h1>'
                . esc_html__('Participe Ibram - E-mail', 'participe-ibram')
                . '</h1><p>'
                . esc_html__('Template admin nao encontrado.', 'participe-ibram')
                . '</p></div>';
            return;
        }
        $vars = $data;
        unset($data); // evita fuga acidental
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        include $tplFile;
    }

    /**
     * @return array<string,string> tab => label
     */
    private function tabs(): array
    {
        return [
            'config'    => __('Configuracao SMTP', 'participe-ibram'),
            'fila'      => __('Fila pendente', 'participe-ibram'),
            'logs'      => __('Logs', 'participe-ibram'),
            'templates' => __('Preview de templates', 'participe-ibram'),
        ];
    }

    /**
     * @param string|null $statusFiltro Quando null, mostra todos.
     */
    private function buildListTable(?string $statusFiltro): EmailLogsListTable
    {
        $page    = (int) RequestHelper::get('paged', 'absint', 1);
        $evento  = (string) RequestHelper::get('evento_filter', 'sanitize_key', '');
        $status  = (string) RequestHelper::get('status_filter', 'sanitize_key', $statusFiltro ?? '');

        $filtros = [];
        if ($evento !== '') {
            $filtros['evento'] = $evento;
        }
        if ($status !== '') {
            $filtros['status'] = $status;
        }

        $page = $page > 0 ? $page : 1;

        return new EmailLogsListTable($this->fila, $filtros, $page, 25);
    }

    /**
     * @return array{assunto:string, html:string, text:string}|null
     */
    private function renderPreview(string $template): ?array
    {
        if ($template === '') {
            return null;
        }
        try {
            $evento = EventoEmail::fromString($template);
        } catch (\Throwable $e) {
            return null;
        }

        // Vars de exemplo — sem PII real.
        $vars = [
            'nome'                => 'Maria Exemplo',
            'numero_registro'     => 'PI-PF-2025-000001',
            'data_submissao'      => '01/01/2025 10:00',
            'data_protocolo'      => '01/01/2025 10:00',
            'numero_protocolo'    => 'REC-2025-0001',
            'prazo_recurso'       => '10 dias corridos',
            'data_limite_recurso' => '11/01/2025',
            'data_limite'         => '03/01/2025',
            'dias_restantes'      => '2',
            'painel_url'          => 'https://museus.gov.br/painel/',
            'edital_titulo'       => 'Edital de Mostra Cultural 2025',
            'edital_resumo'       => 'Resumo do edital (exemplo).',
            'periodo_inscricao'   => '01/01/2025 a 31/01/2025',
            'edital_url'          => 'https://museus.gov.br/edital/2025-001',
            'periodo_votacao'     => '01/02/2025 a 15/02/2025',
            'votar_url'           => 'https://museus.gov.br/votacao/2025-001',
            'resultado_url'       => 'https://museus.gov.br/resultado/2025-001',
            'vaga'                => 'Vaga 1 - Suplente',
            'decisao'             => 'deferido',
            'instancia'           => 'analise',
            'unsubscribe_url'     => 'https://museus.gov.br/?pi_action=unsubscribe&token=EXEMPLO',
            'dpo_email'           => 'encarregado@museus.gov.br',
        ];
        try {
            return $this->renderer->render($evento->template(), $vars);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
