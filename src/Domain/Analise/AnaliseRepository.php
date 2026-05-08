<?php
/**
 * Contrato de persistência para a entidade Análise.
 *
 * @package Ibram\ParticipeIbram\Domain\Analise
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Analise;

/**
 * Repositório de domínio. Implementação concreta em
 * `Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAnaliseRepository`.
 */
interface AnaliseRepository
{
    public function findById(int $id): ?Analise;

    /**
     * Lista todas as análises de um agente em ordem cronológica.
     *
     * @return list<Analise>
     */
    public function findByAgente(int $agenteId): array;

    /**
     * Persiste a análise (INSERT). Retorna o id gravado.
     */
    public function save(Analise $analise): int;

    /**
     * Marca a análise como publicada (Art. 8º Portaria 3230) — atualiza
     * `publicado_em`, `url_publicacao`, `hash_publicacao` em uma transição
     * única e auditável.
     */
    public function marcarPublicada(int $id, string $url, string $hash): void;
}
