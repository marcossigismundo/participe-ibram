<?php
/**
 * Exception para falhas em PrivateFileStorage.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Storage
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Storage;

use RuntimeException;

/**
 * Erros operacionais de I/O em armazenamento privado.
 *
 * Cobre falhas de criação de diretório, permissões, tentativa de path
 * traversal, leitura/escrita do arquivo, etc.
 */
final class StorageException extends RuntimeException
{
    public static function dirCreationFailed(string $dir): self
    {
        return new self(sprintf('Nao foi possivel criar diretorio: %s', self::redactBase($dir)));
    }

    public static function pathTraversalDetected(): self
    {
        return new self('Tentativa de path traversal detectada.');
    }

    public static function fileNotReadable(): self
    {
        return new self('Arquivo nao pode ser lido.');
    }

    public static function fileNotWritable(): self
    {
        return new self('Arquivo nao pode ser gravado.');
    }

    public static function deleteFailed(): self
    {
        return new self('Falha ao excluir arquivo.');
    }

    public static function notInBaseDir(): self
    {
        return new self('Arquivo fora do diretorio permitido.');
    }

    /**
     * Remove path absoluto da mensagem para não vazar layout do servidor.
     */
    private static function redactBase(string $path): string
    {
        return basename($path);
    }
}
