<?php
/**
 * EditalDetalhesController — visualização com tabs + ações de transição.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;

/**
 * Visualiza um edital com tabs:
 *   Resumo | Categorias | Inscrições | Histórico (audit)
 *
 * R5 V-06: current_user_can no topo do render.
 * Não exibe dados de agente (CPF, email) — AGENTS-PLAN ponto 1.
 */
final class EditalDetalhesController
{
    public const CAP_LISTAR   = 'pi_listar_cadastros';
    public const CAP_PUBLICAR = 'pi_publicar_edital';
    public const CAP_EDITAR   = 'pi_editar_edital';

    private WpdbEditalRepository $editaisRepo;
    private WpdbCategoriaRepository $categoriasRepo;
    private AuditLogger $audit;

    public function __construct(
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        AuditLogger $audit
    ) {
        $this->editaisRepo    = $editaisRepo;
        $this->categoriasRepo = $categoriasRepo;
        $this->audit          = $audit;
    }

    /**
     * Render da página de detalhes.
     */
    public function render(int $editalId): void
    {
        // R5 V-06.
        if (!self::userCan(self::CAP_LISTAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $edital = $this->editaisRepo->findById($editalId);
        if ($edital === null) {
            self::wpDie(self::tr('Edital não encontrado.'));
            return;
        }

        $categorias     = $this->categoriasRepo->findByEdital($editalId);
        $totalVagas     = array_sum(array_map(static fn ($c) => $c->numVagas(), $categorias));
        $podePublicar   = self::userCan(self::CAP_PUBLICAR) && $edital->status()->value() === StatusEdital::RASCUNHO;
        $podeAbrir      = self::userCan(self::CAP_PUBLICAR) && $edital->status()->value() === StatusEdital::PUBLICADO;
        $podeEditar     = self::userCan(self::CAP_EDITAR)   && $edital->status()->value() === StatusEdital::RASCUNHO;
        $userId         = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;

        $nonces = [
            'publicar' => function_exists('wp_create_nonce')
                ? \wp_create_nonce('pi_admin_publicar_edital_' . $userId)
                : '',
            'abrir'    => function_exists('wp_create_nonce')
                ? \wp_create_nonce('pi_admin_abrir_inscricoes_' . $userId)
                : '',
        ];

        $flash    = $this->consumeFlash();
        $template = self::templatePath('editais/edital-detalhes.php');
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
        $uid = (int) \get_current_user_id();
        if ($uid <= 0) {
            return;
        }
        \set_transient('pi_admin_edital_flash_' . $uid, ['type' => $type, 'message' => $message], 60);
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeFlash(): ?array
    {
        if (!function_exists('get_transient') || !function_exists('get_current_user_id')) {
            return null;
        }
        $uid = (int) \get_current_user_id();
        if ($uid <= 0) {
            return null;
        }
        $key  = 'pi_admin_edital_flash_' . $uid;
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
