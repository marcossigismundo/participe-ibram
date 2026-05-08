<?php
/**
 * Exception: documento ou tipo de documento não localizado.
 *
 * @package Ibram\ParticipeIbram\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Documento;

use RuntimeException;

/**
 * Lançada por repositórios quando uma busca por ID/codigo não retorna registro.
 *
 * Sinaliza condição transitória/operacional (não conceitual) — daí estender
 * `RuntimeException` em vez de `DomainException`.
 */
final class DocumentoNotFound extends RuntimeException
{
    public static function comId(int $id): self
    {
        return new self(sprintf('Documento %d nao encontrado.', $id));
    }

    public static function tipoComId(int $id): self
    {
        return new self(sprintf('TipoDocumento %d nao encontrado.', $id));
    }

    public static function tipoComCodigo(string $codigo): self
    {
        return new self(sprintf('TipoDocumento com codigo "%s" nao encontrado.', $codigo));
    }
}
