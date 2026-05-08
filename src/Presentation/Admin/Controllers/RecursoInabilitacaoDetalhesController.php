<?php
/**
 * Controller admin — decisão de Recurso de Inabilitação (W5-C).
 *
 * Análogo ao RecursoRetratacaoController (W4-B) para wp_pi_recursos_inabilitacao.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Edital\DecidirRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Edital\RecursoInabilitacao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbRecursoInabilitacaoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;
use Throwable;

/**
 * Renderiza a tela de decisão de um recurso de inabilitação e processa o POST.
 *
 * Form: radio "deferir" / "manter" + textarea `decisao_md` (min 50 chars).
 * Cap: `pi_decidir_habilitacao`.
 */
final class RecursoInabilitacaoDetalhesController
{
    public const NONCE_ACTION = 'pi_admin_decidir_recurso_inabilitacao';

    private DecidirRecursoInabilitacaoHandler $handler;
    private WpdbRecursoInabilitacaoRepository $recursosRepo;
    private WpdbInscricaoRepository $inscricoesRepo;
    private AuditLogger $audit;
    private string $templatesDir;

    public function __construct(
        DecidirRecursoInabilitacaoHandler $handler,
        WpdbRecursoInabilitacaoRepository $recursosRepo,
        WpdbInscricaoRepository $inscricoesRepo,
        AuditLogger $audit,
        string $templatesDir
    ) {
        $this->handler        = $handler;
        $this->recursosRepo   = $recursosRepo;
        $this->inscricoesRepo = $inscricoesRepo;
        $this->audit          = $audit;
        $this->templatesDir   = rtrim($templatesDir, '/\\');
    }

    public function render(int $recursoId): void
    {
        $this->guardCap();

        $recurso = $this->recursosRepo->findById($recursoId);
        if ($recurso === null || $recurso->isDecidido()) {
            $this->renderNotFound();
            return;
        }

        $inscricao = $this->inscricoesRepo->findById($recurso->inscricaoId());

        $flash    = $this->popFlash();
        $nonce    = function_exists('wp_create_nonce') ? \wp_create_nonce(self::NONCE_ACTION) : '';
        $listaUrl = function_exists('admin_url')
            ? \admin_url('admin.php?page=' . HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO)
            : '';

        // Prazo recursal — usa inscrito_em como referência; a regra de deadline real
        // vem do edital (prazoRecursoInabilitacao) mas para countdown usamos protocolado_em.
        $protocoladoEm  = $recurso->protocoladoEm();
        $now            = new \DateTimeImmutable('now');
        $prazoDiff      = $now->diff($protocoladoEm);

        $template = $this->templatesDir . '/recursos-inabilitacao/detalhe.php';
        if (!is_file($template)) {
            return;
        }

        include $template;
    }

    public function handlePostAction(): void
    {
        $piAction = (string) RequestHelper::post('pi_action', 'sanitize_key', '');
        if ($piAction !== 'decidir_recurso_inabilitacao') {
            return;
        }
        $this->guardCap();

        if (!function_exists('wp_verify_nonce') || !\wp_verify_nonce(
            (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', ''),
            self::NONCE_ACTION
        )) {
            $this->setFlash('error', \__('Nonce inválido.', 'participe-ibram'));
            $this->redirectToList();
            return;
        }

        $recursoId = (int) RequestHelper::post('recurso_id', 'absint', 0);
        $decisao   = (string) RequestHelper::post('decisao', 'sanitize_key', '');
        $decisaoMd = (string) RequestHelper::post('decisao_md', 'wp_kses_post', '');

        if ($recursoId <= 0) {
            $this->setFlash('error', \__('Recurso inválido.', 'participe-ibram'));
            $this->redirectToList();
            return;
        }
        if (!in_array($decisao, [RecursoInabilitacao::DECISAO_DEFERIR, RecursoInabilitacao::DECISAO_MANTER], true)) {
            $this->setFlash('error', \__('Selecione uma decisão válida.', 'participe-ibram'));
            $this->redirectToDetalhe($recursoId);
            return;
        }
        if (mb_strlen(trim(strip_tags($decisaoMd))) < 50) {
            $this->setFlash('error', \__('Forneça uma decisão com pelo menos 50 caracteres.', 'participe-ibram'));
            $this->redirectToDetalhe($recursoId);
            return;
        }

        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->setFlash('error', \__('Sessão expirada.', 'participe-ibram'));
            $this->redirectToList();
            return;
        }

        try {
            $this->handler->handle($recursoId, $decisao, $decisaoMd, $userId);

            $deferido = $decisao === RecursoInabilitacao::DECISAO_DEFERIR;
            $this->audit->log(
                'recurso_inabilitacao',
                $recursoId,
                'admin_decidir_recurso_inabilitacao',
                null,
                ['decisao' => $decisao],
                $userId
            );

            if (function_exists('do_action')) {
                \do_action('pi_recurso_inabilitacao_decidido', $recursoId, $deferido);
            }

            $msg = $deferido
                ? \__('Recurso deferido — inscrição habilitada.', 'participe-ibram')
                : \__('Inabilitação mantida.', 'participe-ibram');
            $this->setFlash('success', $msg);
            $this->redirectToList();
        } catch (Throwable $e) {
            $message = $e instanceof \InvalidArgumentException || $e instanceof \DomainException
                ? $e->getMessage()
                : \__('Erro interno ao decidir o recurso.', 'participe-ibram');
            $this->setFlash('error', $message);
            $this->redirectToDetalhe($recursoId);
        }
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
            . \esc_html__('Recurso não encontrado ou já decidido.', 'participe-ibram')
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

    private function redirectToList(): void
    {
        if (!function_exists('wp_safe_redirect') || !function_exists('admin_url')) {
            return;
        }
        \wp_safe_redirect(\admin_url('admin.php?page=' . HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO));
        exit;
    }

    private function redirectToDetalhe(int $recursoId): void
    {
        if ($recursoId <= 0 || !function_exists('wp_safe_redirect') || !function_exists('admin_url')) {
            $this->redirectToList();
            return;
        }
        \wp_safe_redirect(\admin_url('admin.php?page=' . HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO . '&action=view&recurso_id=' . $recursoId));
        exit;
    }
}
