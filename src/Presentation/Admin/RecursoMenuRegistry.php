<?php
/**
 * Registra os submenus admin de Recursos (W4-B).
 *
 * Mantido em arquivo separado do registry de Cadastros (W4-A) para evitar
 * conflitos de merge entre agentes paralelos. Hooks `admin_menu` e
 * `admin_init` são adicionados pelo bootstrap do plugin.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoPrazosController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoPresidenciaController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoRetratacaoController;

/**
 * Submenus administrativos:
 *
 *  - `participe-ibram_recursos_retratacao` (cap `pi_analisar_cadastro`).
 *  - `participe-ibram_recursos_presidencia` (cap `pi_decidir_recurso_presidencia`).
 *  - `participe-ibram_recursos_prazos` (cap `pi_listar_cadastros`).
 *
 * O menu pai (`participe-ibram`) é criado por W4-A. Aqui usamos
 * {@see add_submenu_page()} idempotente; se o pai não existir ainda,
 * o WordPress aceita o slug e os submenus aparecem como itens órfãos
 * (situação de testes / boot incompleto).
 */
final class RecursoMenuRegistry
{
    public const PARENT_SLUG = 'participe-ibram';

    public const SLUG_RETRATACAO  = 'participe-ibram_recursos_retratacao';
    public const SLUG_PRESIDENCIA = 'participe-ibram_recursos_presidencia';
    public const SLUG_PRAZOS      = 'participe-ibram_recursos_prazos';

    public const CAP_RETRATACAO  = 'pi_analisar_cadastro';
    public const CAP_PRESIDENCIA = 'pi_decidir_recurso_presidencia';
    public const CAP_PRAZOS      = 'pi_listar_cadastros';

    private RecursoRetratacaoController $retratacaoController;
    private RecursoPresidenciaController $presidenciaController;
    private RecursoPrazosController $prazosController;

    public function __construct(
        RecursoRetratacaoController $retratacaoController,
        RecursoPresidenciaController $presidenciaController,
        RecursoPrazosController $prazosController
    ) {
        $this->retratacaoController  = $retratacaoController;
        $this->presidenciaController = $presidenciaController;
        $this->prazosController      = $prazosController;
    }

    /**
     * Registra os hooks WP. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        // W11-A IA: prioridade 11 — fecha o grupo "Análise de cadastros"
        // logo após MenuRegistry (prio 10). Ver docs/refactor/W11-IA.md.
        \add_action('admin_menu', [$this, 'registerMenu'], 11);
        \add_action('admin_init', [$this, 'maybeHandlePost']);
    }

    /**
     * Registra os submenus (executar apenas em `admin_menu`).
     */
    public function registerMenu(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        // W11-A IA: grupo "Análise de cadastros", posições 12–14.
        // Ver docs/refactor/W11-IA.md.
        \add_submenu_page(
            self::PARENT_SLUG,
            \__('Recursos — Em Retratação', 'participe-ibram'),
            \__('Recursos — Retratação', 'participe-ibram'),
            self::CAP_RETRATACAO,
            self::SLUG_RETRATACAO,
            [$this->retratacaoController, 'dispatch'],
            12
        );

        \add_submenu_page(
            self::PARENT_SLUG,
            \__('Recursos — Presidência', 'participe-ibram'),
            \__('Recursos — Presidência', 'participe-ibram'),
            self::CAP_PRESIDENCIA,
            self::SLUG_PRESIDENCIA,
            [$this->presidenciaController, 'dispatch'],
            13
        );

        \add_submenu_page(
            self::PARENT_SLUG,
            \__('Recursos — Prazos Vencendo', 'participe-ibram'),
            \__('Recursos — Prazos', 'participe-ibram'),
            self::CAP_PRAZOS,
            self::SLUG_PRAZOS,
            [$this->prazosController, 'render'],
            14
        );
    }

    /**
     * Em `admin_init`, observa o slug atual e dispara o action handler quando
     * a página é submetida via POST (controlador de detalhe).
     */
    public function maybeHandlePost(): void
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
            return;
        }
        $page = (string) RequestHelper::get('page', 'sanitize_key', '');
        if ($page === '') {
            return;
        }
        if ($page === self::SLUG_RETRATACAO) {
            $this->retratacaoController->handlePostAction();
            return;
        }
        if ($page === self::SLUG_PRESIDENCIA) {
            $this->presidenciaController->handlePostAction();
            return;
        }
    }
}
