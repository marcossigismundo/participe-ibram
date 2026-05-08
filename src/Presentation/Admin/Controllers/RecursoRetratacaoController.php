<?php
/**
 * Controller admin — decisão de Recurso em fase Retratação.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Cadastro\DecidirRetratacaoCommand;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRetratacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosRetratacaoListTable;
use Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\RecursoListQuery;
use Throwable;

/**
 * Renderiza listagem e tela de decisão de retratação. As ações de POST são
 * roteadas pelo MenuRegistry para `handlePostAction()` antes do header() das
 * páginas.
 */
final class RecursoRetratacaoController
{
    public const NONCE_ACTION = 'pi_admin_decidir_retratacao';

    private DecidirRetratacaoHandler $handler;
    private RecursoRepository $recursos;
    private AnaliseRepository $analises;
    private AgenteRepository $agentes;
    private RecursoListQuery $listQuery;
    private AuditLogger $audit;
    private string $templatesDir;

    public function __construct(
        DecidirRetratacaoHandler $handler,
        RecursoRepository $recursos,
        AnaliseRepository $analises,
        AgenteRepository $agentes,
        RecursoListQuery $listQuery,
        AuditLogger $audit,
        string $templatesDir
    ) {
        $this->handler      = $handler;
        $this->recursos     = $recursos;
        $this->analises     = $analises;
        $this->agentes      = $agentes;
        $this->listQuery    = $listQuery;
        $this->audit        = $audit;
        $this->templatesDir = rtrim($templatesDir, '/\\');
    }

    /**
     * Entrypoint do submenu — escolhe entre listagem e detalhe.
     */
    public function dispatch(): void
    {
        $this->guardCap();

        $action = (string) RequestHelper::get('action', 'sanitize_key', 'list');
        $recursoId = (int) RequestHelper::get('recurso_id', 'absint', 0);
        if ($action === 'view' && $recursoId > 0) {
            $this->renderDetalhe($recursoId);
            return;
        }
        $this->render();
    }

    public function render(): void
    {
        $this->guardCap();
        $listTable = new RecursosRetratacaoListTable($this->listQuery);
        $listTable->prepare_items();

        $template = $this->templatesDir . '/retratacao-lista.php';
        if (!is_file($template)) {
            return;
        }
        /** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosRetratacaoListTable $listTable */
        include $template;
    }

    public function renderDetalhe(int $recursoId): void
    {
        $this->guardCap();

        $recurso = $this->recursos->findById($recursoId);
        if ($recurso === null || !$recurso->isFaseRetratacao()) {
            $this->renderNotFound();
            return;
        }

        $analise = $this->analises->findById($recurso->analiseId());
        $agente  = $analise !== null ? $this->agentes->findById($analise->agenteId()) : null;

        $flash = $this->popFlash();

        $nonce = function_exists('wp_create_nonce') ? \wp_create_nonce(self::NONCE_ACTION) : '';
        $template = $this->templatesDir . '/retratacao-detalhe.php';
        if (!is_file($template)) {
            return;
        }
        $listaUrl = function_exists('admin_url') ? \admin_url('admin.php?page=' . RecursoMenuRegistry::SLUG_RETRATACAO) : '';

        include $template;
    }

    public function handlePostAction(): void
    {
        $action = (string) RequestHelper::post('pi_action', 'sanitize_key', '');
        if ($action !== 'decidir_retratacao') {
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

        $recursoId    = (int) RequestHelper::post('recurso_id', 'absint', 0);
        $reconsiderar = ((int) RequestHelper::post('reconsiderar', 'absint', 0)) === 1;
        $decisaoMd    = (string) RequestHelper::post('decisao_md', 'wp_kses_post', '');

        if ($recursoId <= 0 || mb_strlen(trim(strip_tags($decisaoMd))) < 50) {
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
            $command = new DecidirRetratacaoCommand($recursoId, $userId, $reconsiderar, $decisaoMd);
            $this->handler->handle($command);

            $this->audit->log('recurso', $recursoId, 'admin_decidir_retratacao', null, [
                'reconsiderar' => $reconsiderar,
            ], $userId);

            if (function_exists('do_action')) {
                \do_action(
                    'pi_recurso_decidido',
                    $recursoId,
                    Recurso::FASE_RETRATACAO,
                    $reconsiderar ? Recurso::DECISAO_RECONSIDERAR : Recurso::DECISAO_MANTER
                );
            }

            $this->setFlash(
                'success',
                $reconsiderar
                    ? \__('Recurso reconsiderado e cadastro deferido.', 'participe-ibram')
                    : \__('Indeferimento mantido. Recurso seguiu para a Presidência.', 'participe-ibram')
            );
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
        if (!function_exists('current_user_can') || !\current_user_can(RecursoMenuRegistry::CAP_RETRATACAO)) {
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
        if ($userId <= 0) {
            return;
        }
        if (function_exists('set_transient')) {
            \set_transient('pi_flash_' . $userId, ['level' => $level, 'message' => $message], 60);
        }
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
        \wp_safe_redirect(\admin_url('admin.php?page=' . RecursoMenuRegistry::SLUG_RETRATACAO));
        exit;
    }

    private function redirectToDetalhe(int $recursoId): void
    {
        if ($recursoId <= 0 || !function_exists('wp_safe_redirect') || !function_exists('admin_url')) {
            $this->redirectToList();
            return;
        }
        \wp_safe_redirect(\admin_url('admin.php?page=' . RecursoMenuRegistry::SLUG_RETRATACAO . '&action=view&recurso_id=' . $recursoId));
        exit;
    }
}
