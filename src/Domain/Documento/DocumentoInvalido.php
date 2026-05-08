<?php
/**
 * Exception: violação de regra de domínio em upload/validação de documento.
 *
 * @package Ibram\ParticipeIbram\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Documento;

use DomainException;

/**
 * Erro de domínio: tipo inativo, MIME proibido, tamanho excedido, etc.
 *
 * Distinto de {@see DocumentoNotFound} — este indica regra de negócio violada
 * pelo input do usuário; aquele indica ausência de registro no repositório.
 */
final class DocumentoInvalido extends DomainException
{
    public static function tipoInativo(int $tipoId): self
    {
        return new self(sprintf('Tipo de documento %d esta inativo.', $tipoId));
    }

    public static function mimeNaoPermitido(string $mimeReal, string $codigoTipo): self
    {
        return new self(sprintf(
            'MIME "%s" nao permitido para o tipo de documento "%s".',
            $mimeReal,
            $codigoTipo
        ));
    }

    public static function tamanhoExcedido(int $bytes, int $limiteBytes): self
    {
        return new self(sprintf(
            'Tamanho do arquivo (%d bytes) excede o limite de %d bytes.',
            $bytes,
            $limiteBytes
        ));
    }

    public static function arquivoIlegivel(string $detalhe = ''): self
    {
        $msg = 'Arquivo enviado nao pode ser lido.';
        if ($detalhe !== '') {
            $msg .= ' ' . $detalhe;
        }

        return new self($msg);
    }

    public static function semVinculo(): self
    {
        return new self('Documento deve estar vinculado a um agente ou a uma inscricao.');
    }
}
