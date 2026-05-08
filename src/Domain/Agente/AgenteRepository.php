<?php
/**
 * Contrato de persistência para o agregado Agente.
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

/**
 * Porta da camada de domínio. Implementação concreta em
 * `Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteRepository`.
 *
 * Buscas por CPF/CNPJ DEVEM usar `cpf_hash`/`cnpj_hash` (HMAC determinístico
 * via `SodiumCipher::searchHash`) — nunca decifrar todas as linhas para
 * comparar (R2 §4.6, R5 lição #11).
 */
interface AgenteRepository
{
    /**
     * Busca por id interno.
     *
     * @return Agente|null Null quando não encontrado ou soft-deleted.
     */
    public function findById(int $id): ?Agente;

    /**
     * Busca pelo número de registro canônico (TD-02).
     */
    public function findByNumeroRegistro(string $numero): ?Agente;

    /**
     * Busca por CPF em claro (PF). Internamente calcula `searchHash` e procura
     * em `wp_pi_agentes_pf.cpf_hash`.
     *
     * @param string $cpfPlain CPF em claro (com ou sem máscara).
     */
    public function findByCpf(string $cpfPlain): ?Agente;

    /**
     * Busca por CNPJ em claro (OR). Análogo a {@see findByCpf}.
     */
    public function findByCnpj(string $cnpjPlain): ?Agente;

    /**
     * Busca por user_id do WordPress.
     */
    public function findByUserId(int $userId): ?Agente;

    /**
     * Busca por email principal (índice único).
     */
    public function findByEmail(string $email): ?Agente;

    /**
     * Persiste o agregado completo (Agente + sub-tabela do tipo + representantes).
     *
     * Se `$agente` é novo (id null) emite INSERT; caso contrário UPDATE.
     * Cifra CPF/RG/Passaporte/CNPJ via SodiumCipher antes de persistir; gera
     * `*_hash` para busca; audita via `AuditLogger`. Quando a transição
     * detectada implica deferimento, gera o número de registro via
     * `SequenceGenerator`.
     *
     * O parâmetro `$detalhes` é tipado como `object` por compatibilidade com
     * PHP 7.4 (sem union types). Implementações DEVEM aceitar instâncias de
     * {@see AgentePF}, {@see AgenteOR} ou {@see AgenteSM} coerentes com
     * `$agente->getTipo()` e rejeitar (lançando `\InvalidArgumentException`)
     * qualquer outra coisa.
     *
     * @param Agente                                 $agente       Agregado raiz.
     * @param AgentePF|AgenteOR|AgenteSM             $detalhes     Detalhes da tipologia.
     * @param array<int,Representante>               $representantes Lista de representantes (OR/SM).
     *
     * @return int Id do agregado após persistência.
     *
     * @throws DuplicateCpfException   Quando CPF colide com outro registro.
     * @throws DuplicateCnpjException  Quando CNPJ colide.
     */
    public function save(Agente $agente, object $detalhes, array $representantes = []): int;

    /**
     * Soft-delete (`deleted_at = NOW()`). Não remove documentos relacionados.
     *
     * @throws AgenteNotFound Quando o id não existe.
     */
    public function softDelete(int $id): void;

    /**
     * Lista paginada por status (TD-05).
     *
     * @param string $status   Valor de {@see StatusCadastro}.
     * @param int    $page     Página (1-based).
     * @param int    $perPage  Itens por página (1..100; default 25).
     *
     * @return array{items: array<int,Agente>, total: int, page: int, per_page: int}
     */
    public function listByStatus(string $status, int $page = 1, int $perPage = 25): array;
}
