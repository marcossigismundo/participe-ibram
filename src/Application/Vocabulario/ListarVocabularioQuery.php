<?php
/**
 * Query DTO: listar itens de um vocabulário.
 *
 * @package Ibram\ParticipeIbram\Application\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Vocabulario;

use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use InvalidArgumentException;

/**
 * Parâmetros imutáveis para {@see ListarVocabularioHandler}.
 */
final class ListarVocabularioQuery
{
    private string $tipo;

    private bool $apenasAtivos;

    /**
     * @throws InvalidArgumentException Quando $tipo não está em
     *                                  {@see TipoVocabulario::all()}.
     */
    public function __construct(string $tipo, bool $apenasAtivos = true)
    {
        if (!TipoVocabulario::isValid($tipo)) {
            throw new InvalidArgumentException(sprintf(
                'ListarVocabularioQuery: tipo "%s" invalido.',
                $tipo
            ));
        }
        $this->tipo         = strtolower(trim($tipo));
        $this->apenasAtivos = $apenasAtivos;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function apenasAtivos(): bool
    {
        return $this->apenasAtivos;
    }
}
