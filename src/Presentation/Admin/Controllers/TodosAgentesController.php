<?php
/**
 * TodosAgentesController — admin page listing every cadastro.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Presentation\Admin\Helpers\AgenteSummary;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\TodosAgentesListTable;
use Ibram\ParticipeIbram\Presentation\Admin\Support\CadastroListQuery;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;

/**
 * Pagina admin para "Todos os agentes". Mostra todos os status com filtros.
 *
 * Capability: pi_listar_cadastros (R5 V-06).
 */
final class TodosAgentesController
{
    public const CAP_LISTAR = 'pi_listar_cadastros';

    private CadastroListQuery $query;

    public function __construct(CadastroListQuery $query)
    {
        $this->query = $query;
    }

    public function render(): void
    {
        if (!self::userCan(self::CAP_LISTAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $listTable = new TodosAgentesListTable($this->query);
        $listTable->prepare_items();

        $statuses   = array_values(array_filter(
            StatusCadastro::all(),
            static fn (string $s): bool => $s !== StatusCadastro::RASCUNHO
        ));
        $resumo     = $this->query->contagensPorStatus($statuses);
        $listLabels = AgenteSummary::statusLabels();

        $template = self::templatePath('cadastros/todos-agentes.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
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
