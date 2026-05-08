<?php
/**
 * Contrato de persistência para itens de vocabulário (TD-07).
 *
 * @package Ibram\ParticipeIbram\Domain\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Vocabulario;

/**
 * Repositório de domínio. Implementações vivem em
 * {@see \Ibram\ParticipeIbram\Infrastructure\Repository}.
 *
 * Notas:
 *  - `listByTipo()` deve retornar ordenado por `ordem ASC, rotulo ASC`.
 *  - `validar()` consulta o cache (via `listByTipo`) — não toca o BD se já cacheado.
 *  - `save()` faz upsert por chave única (`tipo`, `valor`).
 */
interface VocabularioRepository
{
    /**
     * Recupera por PK.
     */
    public function findById(int $id): ?ItemVocabulario;

    /**
     * Recupera por par único (tipo, valor).
     */
    public function findByValor(string $tipo, string $valor): ?ItemVocabulario;

    /**
     * Lista os itens de um tipo. Por default só os ativos.
     *
     * @return array<int,ItemVocabulario>
     */
    public function listByTipo(string $tipo, bool $apenasAtivos = true): array;

    /**
     * Persiste (insert ou update). Retorna o id gravado.
     */
    public function save(ItemVocabulario $item): int;

    /**
     * Desativa (soft-disable) por id. Sem efeito se já estiver inativo.
     */
    public function desativar(int $id): void;

    /**
     * Verifica se um par (tipo, valor) é um item ATIVO conhecido.
     *
     * Útil para validação de formulários antes de gravar referências em
     * `wp_pi_agentes_*` e `wp_pi_agente_vocabularios`.
     */
    public function validar(string $tipo, string $valor): bool;
}
