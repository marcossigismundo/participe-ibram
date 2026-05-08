<?php
/**
 * Repositório de Termos (interface de domínio).
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

/**
 * Contrato para persistência de {@see Termo}.
 */
interface TermoRepository
{
    public function findById(int $id): ?Termo;

    public function findByVersao(string $versao): ?Termo;

    /**
     * Termo vigente no momento da chamada (ativo_em <= now < inativo_em).
     */
    public function findAtivoCorrente(): ?Termo;

    /**
     * Persiste (INSERT ou UPDATE conforme presença de id) e retorna o id final.
     */
    public function save(Termo $termo): int;

    /**
     * Marca como inativos todos os termos exceto o id informado, gravando
     * `inativo_em = NOW()`. Usado quando um novo termo é publicado.
     */
    public function inativarAnterior(int $exceptoId): void;
}
