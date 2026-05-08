<?php
/**
 * FilaAnaliseController — admin page for the analysis queue.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Cadastro\AssumirAnaliseHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Presentation\Admin\Helpers\AgenteSummary;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\FilaAnaliseListTable;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\CadastroListQuery;
use Throwable;

/**
 * Renders the queue page and processes inline / bulk actions.
 *
 * Capability gating (R5 V-06):
 *  - render():  pi_listar_cadastros
 *  - actions:   pi_analisar_cadastro
 *
 * All POST actions are gated by:
 *  - check_admin_referer with action `pi_admin_<action>_<userId>`.
 *  - current_user_can(<cap>).
 *  - $_POST scrubbed via RequestHelper (wp_unslash + sanitizer).
 */
final class FilaAnaliseController
{
    public const CAP_LISTAR  = 'pi_listar_cadastros';
    public const CAP_ANALISAR = 'pi_analisar_cadastro';

    private CadastroListQuery $query;
    private AssumirAnaliseHandler $assumirAnalise;
    private AuditLogger $audit;

    public function __construct(
        CadastroListQuery $query,
        AssumirAnaliseHandler $assumirAnalise,
        AuditLogger $audit
    ) {
        $this->query          = $query;
        $this->assumirAnalise = $assumirAnalise;
        $this->audit          = $audit;
    }

    /**
     * Renders the page. Called by MenuRegistry.
     */
    public function render(): void
    {
        if (!self::userCan(self::CAP_LISTAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        // Process inline actions if requested via GET (?pi_action=…).
        $this->handleGetAction();
        // Process POST bulk actions.
        $this->handleBulkAction();

        $listTable = new FilaAnaliseListTable($this->query);
        $listTable->prepare_items();

        $statuses = FilaAnaliseListTable::DEFAULT_STATUSES;
        $resumo   = $this->query->contagensPorStatus($statuses);
        $tempoMedio = $this->query->tempoMedioEmAnaliseDias();

        $template = self::templatePath('cadastros/fila-analise.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        $listLabels = AgenteSummary::statusLabels();
        $flash      = $this->consumeFlash();

        // Vars usadas pelo template incluído.
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    /**
     * GET inline action handler.
     */
    private function handleGetAction(): void
    {
        if (!isset($_GET['pi_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $action = (string) RequestHelper::get('pi_action', 'sanitize_key', '');
        if ($action === '') {
            return;
        }
        $userId = function_exists('get_current_user_id')
            ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->setFlash('error', self::tr('Sessão expirada.'));
            return;
        }

        // Verify nonce.
        $nonceAction = 'pi_admin_' . $action . '_' . $userId;
        $nonce       = (string) RequestHelper::get('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->setFlash('error', self::tr('Nonce inválido. Operação cancelada.'));
            return;
        }

        if (!self::userCan(self::CAP_ANALISAR)) {
            $this->setFlash('error', self::tr('Permissão negada para esta ação.'));
            return;
        }

        $agenteId = (int) RequestHelper::get('agente_id', 'absint', 0);
        if ($agenteId <= 0) {
            $this->setFlash('error', self::tr('Cadastro inválido.'));
            return;
        }

        try {
            if ($action === 'assumir_analise') {
                $this->assumirAnalise->handle($agenteId, $userId);
                $this->setFlash('success', self::tr('Análise assumida com sucesso.'));
            } else {
                $this->setFlash('error', self::tr('Ação não suportada.'));
            }
        } catch (Throwable $e) {
            $this->audit->log('agente', $agenteId, 'admin_action_error', null, ['action' => $action], $userId);
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->setFlash('error', $debug ? $e->getMessage() : self::tr('Falha ao processar a ação.'));
        }

        // Redirect to drop the action params (PRG pattern).
        self::redirect(MenuRegistry::urlFilaAnalise());
    }

    /**
     * POST bulk action handler.
     */
    private function handleBulkAction(): void
    {
        $action = (string) RequestHelper::post('action', 'sanitize_key', '');
        if ($action === '' || $action === '-1') {
            $action = (string) RequestHelper::post('action2', 'sanitize_key', '');
        }
        if ($action === '' || $action === '-1') {
            return;
        }
        if (!in_array($action, ['assumir_analise', 'liberar_analise'], true)) {
            return;
        }

        $userId = function_exists('get_current_user_id')
            ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->setFlash('error', self::tr('Sessão expirada.'));
            return;
        }
        $nonceAction = 'bulk-pi_cadastros';
        $nonce       = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->setFlash('error', self::tr('Nonce inválido. Operação cancelada.'));
            return;
        }
        if (!self::userCan(self::CAP_ANALISAR)) {
            $this->setFlash('error', self::tr('Permissão negada para a ação em lote.'));
            return;
        }

        $ids = RequestHelper::postArray('agente_ids', 'absint');
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0));
        if ($ids === []) {
            $this->setFlash('error', self::tr('Nenhum cadastro selecionado.'));
            return;
        }

        $ok    = 0;
        $errs  = 0;
        foreach ($ids as $agenteId) {
            try {
                if ($action === 'assumir_analise') {
                    $this->assumirAnalise->handle($agenteId, $userId);
                    $ok++;
                } elseif ($action === 'liberar_analise') {
                    // Liberar não está no Wave 4-A handler set; auditamos
                    // explicitamente sem aplicar transição (a transição volta
                    // para SUBMETIDO requer Wave dedicado / handler novo).
                    $this->audit->log(
                        'agente',
                        $agenteId,
                        'liberar_analise_solicitado',
                        null,
                        ['nota' => 'pendente — handler dedicado em wave futura'],
                        $userId
                    );
                    $ok++;
                }
            } catch (Throwable $e) {
                $errs++;
                $this->audit->log('agente', $agenteId, 'admin_bulk_error', null, ['action' => $action], $userId);
            }
        }

        if ($ok > 0) {
            $this->setFlash(
                'success',
                sprintf(
                    /* translators: %d: número de cadastros processados */
                    self::tr('%d cadastro(s) processado(s) com sucesso.'),
                    $ok
                )
            );
        }
        if ($errs > 0) {
            $this->setFlash(
                'error',
                sprintf(
                    /* translators: %d: número de erros */
                    self::tr('%d cadastro(s) falharam.'),
                    $errs
                )
            );
        }

        self::redirect(MenuRegistry::urlFilaAnalise());
    }

    /* ----------------------- Flash storage ----------------------- */

    private function setFlash(string $type, string $message): void
    {
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        \set_transient(
            'pi_admin_flash_' . $userId,
            ['type' => $type, 'message' => $message],
            60
        );
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeFlash(): ?array
    {
        if (!function_exists('get_transient') || !function_exists('get_current_user_id')) {
            return null;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return null;
        }
        $key  = 'pi_admin_flash_' . $userId;
        $data = \get_transient($key);
        if (!is_array($data) || !isset($data['type'], $data['message'])) {
            return null;
        }
        if (function_exists('delete_transient')) {
            \delete_transient($key);
        }
        return ['type' => (string) $data['type'], 'message' => (string) $data['message']];
    }

    /* ------------------------ helpers --------------------------- */

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && \current_user_can($cap);
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function wpDie(string $message): void
    {
        if (function_exists('wp_die')) {
            \wp_die(self::escHtml($message));
        } else {
            echo $message;
            exit;
        }
    }

    private static function redirect(string $url): void
    {
        if (function_exists('wp_safe_redirect')) {
            \wp_safe_redirect($url);
            if (function_exists('exit')) {
                exit;
            }
        }
    }

    private static function templatePath(string $relative): ?string
    {
        $base = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
