<?php
/**
 * VotacaoListController — listagem admin de votações.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Presentation\Admin\ListTables\VotacoesListTable;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoListQuery;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/**
 * Renderiza a fila de votações. Cap mínima: pi_apurar_votacao.
 *
 * R5 V-06: capability check no topo de render().
 */
final class VotacaoListController
{
    public const CAP_LISTAR = VotacaoMenuRegistry::CAP_APURAR;

    private VotacaoListQuery $query;

    public function __construct(VotacaoListQuery $query)
    {
        $this->query = $query;
    }

    public function render(): void
    {
        if (!self::userCan(self::CAP_LISTAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $listTable = new VotacoesListTable($this->query);
        $listTable->prepare_items();

        $resumo = $this->query->contagensPorStatus();
        $flash  = $this->consumeFlash();

        $template = self::templatePath('votacoes/lista.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    public function setFlash(string $type, string $message): void
    {
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        \set_transient('pi_admin_votacao_flash_' . $userId, ['type' => $type, 'message' => $message], 60);
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
        $key  = 'pi_admin_votacao_flash_' . $userId;
        $data = \get_transient($key);
        if (!is_array($data) || !isset($data['type'], $data['message'])) {
            return null;
        }
        if (function_exists('delete_transient')) {
            \delete_transient($key);
        }
        return ['type' => (string) $data['type'], 'message' => (string) $data['message']];
    }

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
