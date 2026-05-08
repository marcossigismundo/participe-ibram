<?php
/**
 * DTO de comando para upload de documento.
 *
 * @package Ibram\ParticipeIbram\Application\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Documento;

use InvalidArgumentException;

/**
 * Comando imutável que carrega os parâmetros de um upload.
 *
 * Construa via `new UploadDocumentoCommand(...)` no controller/handler de
 * Presentation; o handler ({@see UploadDocumentoHandler}) consome o objeto.
 */
final class UploadDocumentoCommand
{
    private int $agenteId;

    private ?int $inscricaoId;

    private int $tipoDocumentoId;

    private string $tmpPath;

    private string $originalName;

    private int $uploadedBy;

    /**
     * @param int         $agenteId        ID do agente proprietário (>= 0; 0 quando ainda não criado).
     * @param int|null    $inscricaoId     ID da inscrição (opcional).
     * @param int         $tipoDocumentoId ID do tipo de documento (> 0).
     * @param string      $tmpPath         Caminho do arquivo temporário no servidor.
     * @param string      $originalName    Nome original informado pelo browser.
     * @param int         $uploadedBy      WP user id do uploader.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $agenteId,
        ?int $inscricaoId,
        int $tipoDocumentoId,
        string $tmpPath,
        string $originalName,
        int $uploadedBy
    ) {
        if ($agenteId < 0) {
            throw new InvalidArgumentException('UploadDocumentoCommand.agenteId nao pode ser negativo.');
        }
        if ($inscricaoId !== null && $inscricaoId <= 0) {
            throw new InvalidArgumentException('UploadDocumentoCommand.inscricaoId deve ser positivo quando informado.');
        }
        if ($tipoDocumentoId <= 0) {
            throw new InvalidArgumentException('UploadDocumentoCommand.tipoDocumentoId deve ser positivo.');
        }
        if (trim($tmpPath) === '') {
            throw new InvalidArgumentException('UploadDocumentoCommand.tmpPath nao pode ser vazio.');
        }
        if (trim($originalName) === '') {
            throw new InvalidArgumentException('UploadDocumentoCommand.originalName nao pode ser vazio.');
        }
        if ($uploadedBy <= 0) {
            throw new InvalidArgumentException('UploadDocumentoCommand.uploadedBy deve ser positivo.');
        }

        $this->agenteId        = $agenteId;
        $this->inscricaoId     = $inscricaoId;
        $this->tipoDocumentoId = $tipoDocumentoId;
        $this->tmpPath         = $tmpPath;
        $this->originalName    = $originalName;
        $this->uploadedBy      = $uploadedBy;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function inscricaoId(): ?int
    {
        return $this->inscricaoId;
    }

    public function tipoDocumentoId(): int
    {
        return $this->tipoDocumentoId;
    }

    public function tmpPath(): string
    {
        return $this->tmpPath;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function uploadedBy(): int
    {
        return $this->uploadedBy;
    }
}
