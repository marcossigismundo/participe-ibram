<?php
/**
 * Handler: lista itens de um vocabulário em formato pronto para UI/JSON.
 *
 * @package Ibram\ParticipeIbram\Application\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Vocabulario;

use Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\VocabularioRepository;

/**
 * Caso de uso somente-leitura. Recebe um {@see ListarVocabularioQuery} e
 * devolve uma lista de arrays normalizados (`value`, `label`, `descricao`,
 * `ordem`, `metadata`) prontos para serializar em JSON e popular `<select>`.
 */
final class ListarVocabularioHandler
{
    private VocabularioRepository $repository;

    public function __construct(VocabularioRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<int, array{value:string,label:string,descricao:?string,ordem:int,metadata:?array<string,mixed>}>
     */
    public function handle(ListarVocabularioQuery $query): array
    {
        $items = $this->repository->listByTipo($query->tipo(), $query->apenasAtivos());

        return array_map(
            static function (ItemVocabulario $item): array {
                return [
                    'value'     => $item->valor(),
                    'label'     => $item->rotulo(),
                    'descricao' => $item->descricao(),
                    'ordem'     => $item->ordem(),
                    'metadata'  => $item->metadata(),
                ];
            },
            $items
        );
    }
}
