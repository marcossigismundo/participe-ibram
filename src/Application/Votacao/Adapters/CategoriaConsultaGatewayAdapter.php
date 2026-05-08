<?php
/**
 * Adapter cross-domain: implementa {@see CategoriaConsultaGateway} consultando
 * o repositório de Categoria do domínio Edital.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Adapters
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Adapters;

use Ibram\ParticipeIbram\Application\Votacao\Ports\CategoriaConsultaGateway;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;

/**
 * Anti-corruption layer: o domínio Votação consulta categorias somente através
 * desta porta (string `tipoAgente`, IDs primários). Nada do domínio Edital
 * vaza para Votação além do que a interface declara.
 */
final class CategoriaConsultaGatewayAdapter implements CategoriaConsultaGateway
{
    private WpdbCategoriaRepository $repo;

    public function __construct(WpdbCategoriaRepository $repo)
    {
        $this->repo = $repo;
    }

    public function editalIdDaCategoria(int $categoriaId): ?int
    {
        if ($categoriaId <= 0) {
            return null;
        }
        $categoria = $this->repo->findById($categoriaId);
        if ($categoria === null) {
            return null;
        }

        return $categoria->editalId();
    }

    public function aceitaTipoAgente(int $categoriaId, string $tipoAgente): bool
    {
        if ($categoriaId <= 0) {
            return false;
        }
        $categoria = $this->repo->findById($categoriaId);
        if ($categoria === null) {
            return false;
        }

        return $categoria->aceitaTipoAgente($tipoAgente);
    }

    public function numVagas(int $categoriaId): int
    {
        if ($categoriaId <= 0) {
            return 0;
        }
        $categoria = $this->repo->findById($categoriaId);

        return $categoria !== null ? $categoria->numVagas() : 0;
    }

    public function numSuplentes(int $categoriaId): int
    {
        if ($categoriaId <= 0) {
            return 0;
        }
        $categoria = $this->repo->findById($categoriaId);

        return $categoria !== null ? $categoria->numSuplentes() : 0;
    }

    public function listarCategoriasDoEdital(int $editalId): array
    {
        if ($editalId <= 0) {
            return [];
        }
        $list = $this->repo->findByEdital($editalId);
        $out  = [];
        foreach ($list as $cat) {
            $id = $cat->id();
            if ($id !== null) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
