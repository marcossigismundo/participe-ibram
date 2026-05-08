<?php
/**
 * Handler de upload de documento.
 *
 * @package Ibram\ParticipeIbram\Application\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Documento;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Documento\Documento;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoInvalido;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumento;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Storage\PrivateFileStorage;

/**
 * Orquestra o fluxo completo de upload:
 *  1. Carrega e valida o tipo de documento (existe e está ativo).
 *  2. Move o arquivo para storage privado, detectando MIME real (R5 V-15).
 *  3. Valida MIME ∈ permitidos e tamanho ≤ limite (R5 V-04 — DoS upload).
 *  4. Persiste a entidade {@see Documento} via {@see DocumentoRepository}.
 *  5. Audita o evento (TD-14).
 *
 * Falhas de validação levantam {@see DocumentoInvalido}; falhas de I/O
 * propagam {@see \Ibram\ParticipeIbram\Infrastructure\Storage\StorageException}.
 */
final class UploadDocumentoHandler
{
    private TipoDocumentoRepository $tiposRepo;

    private DocumentoRepository $documentosRepo;

    private PrivateFileStorage $storage;

    private AuditLogger $auditLogger;

    public function __construct(
        TipoDocumentoRepository $tiposRepo,
        DocumentoRepository $documentosRepo,
        PrivateFileStorage $storage,
        AuditLogger $auditLogger
    ) {
        $this->tiposRepo      = $tiposRepo;
        $this->documentosRepo = $documentosRepo;
        $this->storage        = $storage;
        $this->auditLogger    = $auditLogger;
    }

    /**
     * Processa o comando de upload.
     *
     * @return int ID do documento persistido.
     *
     * @throws DocumentoInvalido
     * @throws \Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound
     */
    public function handle(UploadDocumentoCommand $command): int
    {
        if ($command->agenteId() <= 0 && $command->inscricaoId() === null) {
            throw DocumentoInvalido::semVinculo();
        }

        $tipo = $this->tiposRepo->findById($command->tipoDocumentoId());
        if (!$tipo->isAtivo()) {
            throw DocumentoInvalido::tipoInativo($command->tipoDocumentoId());
        }

        $this->preValidateSize($command->tmpPath(), $tipo);

        $stored = $this->storage->store(
            $command->tmpPath(),
            $command->originalName(),
            $command->agenteId()
        );

        // Pós-store: valida MIME real e tamanho efetivamente gravado.
        if (!$tipo->permiteMime((string) $stored['mime'])) {
            // Limpa o arquivo recém-criado para não acumular lixo.
            try {
                $this->storage->delete((string) $stored['path']);
            } catch (\Throwable $ignored) {
                // Falha no cleanup não deve mascarar o erro original.
            }
            throw DocumentoInvalido::mimeNaoPermitido((string) $stored['mime'], $tipo->codigo());
        }

        $sizeBytes = (int) $stored['size'];
        if ($sizeBytes > $tipo->tamanhoMaxBytes()) {
            try {
                $this->storage->delete((string) $stored['path']);
            } catch (\Throwable $ignored) {
                // ignored
            }
            throw DocumentoInvalido::tamanhoExcedido($sizeBytes, $tipo->tamanhoMaxBytes());
        }

        $sanitizedOriginal = function_exists('sanitize_file_name')
            ? (string) \sanitize_file_name($command->originalName())
            : self::fallbackSanitizeName($command->originalName());

        $documento = new Documento(
            null,
            $command->agenteId() > 0 ? $command->agenteId() : null,
            $command->inscricaoId(),
            $tipo->id() ?? $command->tipoDocumentoId(),
            (string) $stored['path'],
            $sanitizedOriginal,
            (string) $stored['mime'],
            $sizeBytes,
            (string) $stored['hash'],
            $command->uploadedBy(),
            new DateTimeImmutable('now')
        );

        $id = $this->documentosRepo->save($documento);

        // Audit (TD-14): registra o upload sem PII além de IDs e metadados.
        $this->auditLogger->log(
            'documento',
            $id,
            'upload',
            null,
            [
                'tipo_documento_id' => $tipo->id(),
                'tipo_codigo'       => $tipo->codigo(),
                'agente_id'         => $command->agenteId() > 0 ? $command->agenteId() : null,
                'inscricao_id'     => $command->inscricaoId(),
                'mime_real'         => (string) $stored['mime'],
                'tamanho_bytes'     => $sizeBytes,
                'hash_sha256'       => (string) $stored['hash'],
            ],
            $command->uploadedBy()
        );

        return $id;
    }

    /**
     * Validação preliminar de tamanho ANTES de copiar para storage privado
     * (evita preencher disco com arquivos grandes só para rejeitar depois).
     */
    private function preValidateSize(string $tmpPath, TipoDocumento $tipo): void
    {
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            throw DocumentoInvalido::arquivoIlegivel();
        }
        $size = filesize($tmpPath);
        if ($size === false) {
            throw DocumentoInvalido::arquivoIlegivel();
        }
        if ((int) $size > $tipo->tamanhoMaxBytes()) {
            throw DocumentoInvalido::tamanhoExcedido((int) $size, $tipo->tamanhoMaxBytes());
        }
    }

    /**
     * Sanitização mínima quando WordPress não está carregado.
     */
    private static function fallbackSanitizeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/', '_', $name) ?? '';
        $name = preg_replace('#[\\\\/]+#', '_', $name) ?? '';
        $name = trim($name);
        if ($name === '') {
            $name = 'arquivo';
        }

        return substr($name, 0, 200);
    }
}
