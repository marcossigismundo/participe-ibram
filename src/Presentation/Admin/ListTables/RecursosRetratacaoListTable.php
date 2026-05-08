<?php
/**
 * List Table — Recursos em fase de retratação (analista decide reconsiderar / manter).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry;

final class RecursosRetratacaoListTable extends AbstractRecursosListTable
{
    protected function fase(): ?string
    {
        return 'retratacao';
    }

    protected function pageSlug(): string
    {
        return RecursoMenuRegistry::SLUG_RETRATACAO;
    }
}
