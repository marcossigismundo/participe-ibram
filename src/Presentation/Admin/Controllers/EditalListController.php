<?php
/**
 * EditalListController — página de listagem de editais.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\EditaisListTable;
use Ibram\ParticipeIbram\Presentation\Admin\Support\EditalListQuery;

/**
 * Renderiza a fila de editais. Cap mínima: pi_listar_cadastros (visualizar).
 *
 * R5 V-06: capability check no topo de render().
 */
final class EditalListController
{
    public const CAP_LISTAR = 'pi_listar_cadastros';
    public const CAP_CRIAR  = 'pi_criar_edital';

    private EditalListQuery $query;

    public function __construct(EditalListQuery $query)
    {
        $this->query = $query;
    }

    /**
     * Render da página de listagem.
     */
    public function render(): void
    {
        // R5 V-06 — cap check at the top of every view.
        if (!self::userCan(self::CAP_LISTAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $listTable = new EditaisListTable($this->query);
        $listTable->prepare_items();

        $resumo      = $this->query->contagensPorStatus();
        $podeCriar   = self::userCan(self::CAP_CRIAR);
        $urlNovo     = EditalMenuRegistry::urlNovo();
        $flash       = $this->consumeFlash();

        $template = self::templatePath('editais/editais-lista.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    /* ----------------------- Flash ----------------------- */

    public function setFlash(string $type, string $message): void
    {
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        \set_transient('pi_admin_edital_flash_' . $userId, ['type' => $type, 'message' => $message], 60);
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
        $key  = 'pi_admin_edital_flash_' . $userId;
        $data = \get_transient($key);
        if (!is_array($data) || !isset($data['type'], $data['message'])) {
            return null;
        }
        if (function_exists('delete_transient')) {
            \delete_transient($key);
        }
        return ['type' => (string) $data['type'], 'message' => (string) $data['message']];
    }

    /* ----------------------- Helpers ----------------------- */

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

    private static function templatePath(string $relative): ?string
    {
        $base      = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
