<?php
/**
 * List Table — Recursos em fase de presidência (instância final).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry;

final class RecursosPresidenciaListTable extends AbstractRecursosListTable
{
    protected function fase(): ?string
    {
        return 'presidencia';
    }

    protected function pageSlug(): string
    {
        return RecursoMenuRegistry::SLUG_PRESIDENCIA;
    }
}
