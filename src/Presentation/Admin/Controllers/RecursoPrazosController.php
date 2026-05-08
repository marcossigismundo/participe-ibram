<?php
/**
 * Controller admin — listagem priorizada de prazos de recurso.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosPrazosListTable;
use Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\RecursoListQuery;

/**
 * Tela apenas de leitura. Mostra cards de KPI no topo (vencendo hoje,
 * vencidos sem decisão, total abertos) e a tabela ordenada por prazo.
 *
 * Não expõe nenhum POST. Cada linha linka para a tela de decisão da fase
 * apropriada (retratacao ou presidencia) — onde o capability check
 * é feito de novo.
 */
final class RecursoPrazosController
{
    private RecursoListQuery $listQuery;
    private string $templatesDir;

    public function __construct(RecursoListQuery $listQuery, string $templatesDir)
    {
        $this->listQuery    = $listQuery;
        $this->templatesDir = rtrim($templatesDir, '/\\');
    }

    public function render(): void
    {
        if (!function_exists('current_user_can') || !\current_user_can(RecursoMenuRegistry::CAP_PRAZOS)) {
            if (function_exists('wp_die')) {
                \wp_die(\esc_html__('Permissão negada.', 'participe-ibram'), 403);
            }
            return;
        }

        $listTable = new RecursosPrazosListTable($this->listQuery);
        $listTable->prepare_items();

        $kpis = $this->listQuery->dashboard();

        $template = $this->templatesDir . '/prazos-lista.php';
        if (!is_file($template)) {
            return;
        }
        include $template;
    }
}
