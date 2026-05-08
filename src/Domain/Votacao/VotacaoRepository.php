<?php
/**
 * Repositório (interface) para a entidade Votacao.
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

/**
 * Contrato de persistência de {@see Votacao}.
 *
 * Implementação concreta vive em `Infrastructure/Repository/WpdbVotacaoRepository.php`.
 */
interface VotacaoRepository
{
    /**
     * Busca por id.
     *
     * @throws VotacaoNotFound Quando o id não existe.
     */
    public function findById(int $id): Votacao;

    /**
     * Busca a votação associada a um edital. Retorna null se não houver
     * (UNIQUE(edital_id) garante no máximo uma).
     */
    public function findByEdital(int $editalId): ?Votacao;

    /**
     * Persiste (insert ou update). Retorna o id resultante.
     */
    public function save(Votacao $votacao): int;
}
