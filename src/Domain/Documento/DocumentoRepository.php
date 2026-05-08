<?php
/**
 * Repositório (interface) para a entidade Documento.
 *
 * @package Ibram\ParticipeIbram\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Documento;

/**
 * Contrato de persistência da entidade {@see Documento}.
 *
 * Implementações concretas vivem em `Infrastructure/Repository`. A interface
 * existe para permitir testes com fakes/mocks e respeitar Dependency Inversion.
 */
interface DocumentoRepository
{
    /**
     * Recupera um documento pelo ID.
     *
     * @throws DocumentoNotFound Se o registro não existe.
     */
    public function findById(int $id): Documento;

    /**
     * Lista documentos vinculados a um agente.
     *
     * @return list<Documento>
     */
    public function findByAgente(int $agenteId): array;

    /**
     * Lista documentos vinculados a uma inscrição.
     *
     * @return list<Documento>
     */
    public function findByInscricao(int $inscricaoId): array;

    /**
     * Persiste um documento (insert ou update). Retorna o ID gerado/atualizado.
     */
    public function save(Documento $documento): int;

    /**
     * Remove o registro lógico no banco.
     *
     * IMPORTANTE: o caller é responsável por excluir o arquivo físico via
     * {@see \Ibram\ParticipeIbram\Infrastructure\Storage\PrivateFileStorage::delete()}.
     */
    public function delete(int $id): void;
}
