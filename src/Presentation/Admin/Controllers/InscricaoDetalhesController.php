<?php
/**
 * Controller admin — visualização de inscrição individual (W5-C).
 *
 * Dados sensíveis do agente NÃO são exibidos aqui; um link "Ver dados do agente"
 * redireciona para agente-detalhes.php (W4-A), onde a revelação é gated por
 * `pi_visualizar_dados_sensiveis` + audit.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;

/**
 * Visualização de inscrição: header, tabs (Resumo | Portfolio | Documentos | Histórico),
 * ações habilitar / inabilitar com modal de confirmação ARIA.
 */
final class InscricaoDetalhesController
{
    public const NONCE_ACTION = 'pi_admin_avaliar_habilitacao';

    private WpdbInscricaoRepository $inscricoesRepo;
    private AuditLogger $audit;
    private string $templatesDir;

    public function __construct(
        WpdbInscricaoRepository $inscricoesRepo,
        AuditLogger $audit,
        string $templatesDir
    ) {
        $this->inscricoesRepo = $inscricoesRepo;
        $this->audit          = $audit;
        $this->templatesDir   = rtrim($templatesDir, '/\\');
    }

    public function render(int $inscricaoId): void
    {
        $this->guardCap();

        $inscricao = $this->inscricoesRepo->findById($inscricaoId);
        if ($inscricao === null) {
            $this->renderNotFound();
            return;
        }

        $flash    = $this->popFlash();
        $nonce    = function_exists('wp_create_nonce') ? \wp_create_nonce(self::NONCE_ACTION) : '';
        $listaUrl = function_exists('admin_url')
            ? \admin_url('admin.php?page=' . HabilitacaoMenuRegistry::SLUG_HABILITACOES)
            : '';

        $userId        = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        $podeDecidirId = $userId;

        // Link para agente-detalhes (revelação gated por cap pi_visualizar_dados_sensiveis).
        $agenteDetalhesUrl = function_exists('admin_url')
            ? \admin_url('admin.php?page=participe-ibram_agentes&agente_id=' . $inscricao->agenteId())
            : '';

        $template = $this->templatesDir . '/habilitacoes/inscricao-detalhes.php';
        if (!is_file($template)) {
            return;
        }

        include $template;
    }

    private function guardCap(): void
    {
        if (!function_exists('current_user_can') || !\current_user_can(HabilitacaoMenuRegistry::CAP_HABILITACAO)) {
            if (function_exists('wp_die')) {
                \wp_die(\esc_html__('Permissão negada.', 'participe-ibram'), 403);
            }
            throw new \RuntimeException('forbidden');
        }
    }

    private function renderNotFound(): void
    {
        echo '<div class="participe-ibram-scope wrap"><div class="pi-alert pi-alert--danger" role="alert">'
            . \esc_html__('Inscrição não encontrada.', 'participe-ibram')
            . '</div></div>';
    }

    private function setFlash(string $level, string $message): void
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0 || !function_exists('set_transient')) {
            return;
        }
        \set_transient('pi_flash_' . $userId, ['level' => $level, 'message' => $message], 60);
    }

    /**
     * @return array{level:string,message:string}|null
     */
    private function popFlash(): ?array
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0 || !function_exists('get_transient')) {
            return null;
        }
        $value = \get_transient('pi_flash_' . $userId);
        if (function_exists('delete_transient')) {
            \delete_transient('pi_flash_' . $userId);
        }
        if (!is_array($value) || !isset($value['level'], $value['message'])) {
            return null;
        }
        return ['level' => (string) $value['level'], 'message' => (string) $value['message']];
    }
}
