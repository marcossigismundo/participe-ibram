<?php
/**
 * Implementação WPDB de {@see AgenteBroadcastQuery} — lista destinatários
 * broadcast sem expor PII sensível.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Application\Email\AgenteBroadcastQuery;

/**
 * Itera agentes deferidos elegíveis para comunicações broadcast.
 *
 * Whitelist defensiva — SELECT explícito (NUNCA SELECT *).
 * Campos retornados: agente_id, email, nome  — NUNCA CPF/CNPJ/dados sensíveis.
 *
 * Distinção entre comunicação obrigatória e opcional:
 *  - `iterar()` e `listarDeferidosPaginado()` retornam TODOS os deferidos;
 *    filtro de revogação de finalidade `comunicacao` é feito em
 *    `listarComConsentimentoNewsletter()` para comunicações OPCIONAIS.
 *  - Comunicações obrigatórias (edital publicado, resultado publicado per
 *    Despacho 98/2025 IBRAM item 7) chamam `iterar()` — respeitam política pública.
 *  - Comunicações opcionais (newsletter, pesquisas) chamam
 *    `listarComConsentimentoNewsletter()` — respeitam revogação individual.
 *
 * Agentes com status `revogado` na finalidade `comunicacao` SÃO removidos da
 * lista `listarComConsentimentoNewsletter()` (revogação opcional respeitada).
 */
final class WpdbAgenteBroadcastQuery implements AgenteBroadcastQuery
{
    /** @var array<int,string> Status que qualificam agente como deferido. */
    private const STATUS_DEFERIDOS = [
        'deferido',
        'deferido_em_retratacao',
        'deferido_em_recurso',
    ];

    /** @var \wpdb */
    private $wpdb;

    private string $tAgentes;
    private string $tConsentimentos;

    /**
     * @param \wpdb       $wpdb
     * @param string|null $prefixOverride Para testes.
     */
    public function __construct($wpdb, ?string $prefixOverride = null)
    {
        $this->wpdb = $wpdb;
        $prefix     = $prefixOverride
            ?? (isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_');

        $this->tAgentes        = $prefix . 'pi_agentes';
        $this->tConsentimentos = $prefix . 'pi_consentimentos';
    }

    /**
     * Implementa {@see AgenteBroadcastQuery::iterar}.
     *
     * Retorna todos os deferidos em batches de $batchSize.
     * Usado por comunicações obrigatórias (Despacho 98/2025 item 7).
     *
     * @return iterable<int, array{agente_id:int, email:string, nome:string}>
     */
    public function iterar(int $batchSize = 100): iterable
    {
        $batchSize = max(1, min(500, $batchSize));
        $page      = 1;

        while (true) {
            $rows = $this->listarDeferidosPaginado($page, $batchSize);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                yield $row['agente_id'] => [
                    'agente_id' => (int) $row['agente_id'],
                    'email'     => (string) $row['email'],
                    'nome'      => (string) $row['nome'],
                ];
            }
            if (count($rows) < $batchSize) {
                break;
            }
            $page++;
        }
    }

    /**
     * Lista agentes deferidos paginado.
     *
     * Retorna somente: agente_id, email (email_principal), nome (nome_publico resumido).
     * NUNCA retorna CPF/CNPJ/RG/passaporte ou qualquer dado sensível.
     *
     * @return array<int, array{agente_id:int, email:string, nome:string}>
     */
    public function listarDeferidosPaginado(int $page = 1, int $perPage = 100): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset  = ($page - 1) * $perPage;

        $placeholders = implode(', ', array_fill(0, count(self::STATUS_DEFERIDOS), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $sql = $this->wpdb->prepare(
            // SELECT explícito — whitelist; nunca SELECT *
            "SELECT
                a.id          AS agente_id,
                a.email_principal AS email,
                COALESCE(NULLIF(SUBSTRING_INDEX(a.nome_publico, ' ', 1), ''), 'Participante') AS nome
             FROM {$this->tAgentes} AS a
             WHERE a.status_cadastro IN ({$placeholders})
               AND a.deleted_at IS NULL
               AND a.email_principal IS NOT NULL
               AND a.email_principal != ''
             ORDER BY a.id ASC
             LIMIT %d OFFSET %d",
            ...array_merge(self::STATUS_DEFERIDOS, [$perPage, $offset])
        );
        // phpcs:enable

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'agente_id' => (int) $row['agente_id'],
                'email'     => (string) $row['email'],
                'nome'      => (string) $row['nome'],
            ];
        }, $rows);
    }

    /**
     * Lista agentes deferidos elegíveis para uma categoria específica,
     * filtrando por `tipos_agente_elegivel` da categoria.
     *
     * Usado no broadcast de `pi_inscricoes_abertas` (Despacho 98/2025 item 7).
     * A categoria é consultada via JOIN inline (sem depender de WpdbCategoriaRepository
     * para manter independência de infraestrutura).
     *
     * NUNCA retorna CPF/dados sensíveis.
     *
     * @return array<int, array{agente_id:int, email:string, nome:string}>
     */
    public function listarDeferidosElegiveisCategoria(
        int $categoriaId,
        int $page = 1,
        int $perPage = 100
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset  = ($page - 1) * $perPage;

        $tCategorias  = str_replace('pi_agentes', 'pi_edital_categorias', $this->tAgentes);

        $placeholders = implode(', ', array_fill(0, count(self::STATUS_DEFERIDOS), '%s'));

        // Filtra pelo tipo de agente constando em tipos_agente_elegivel (CSV) da categoria.
        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $sql = $this->wpdb->prepare(
            "SELECT
                a.id          AS agente_id,
                a.email_principal AS email,
                COALESCE(NULLIF(SUBSTRING_INDEX(a.nome_publico, ' ', 1), ''), 'Participante') AS nome
             FROM {$this->tAgentes} AS a
             INNER JOIN {$tCategorias} AS cat ON cat.id = %d
             WHERE a.status_cadastro IN ({$placeholders})
               AND a.deleted_at IS NULL
               AND a.email_principal IS NOT NULL
               AND a.email_principal != ''
               AND FIND_IN_SET(UPPER(a.tipo), cat.tipos_agente_elegivel) > 0
             ORDER BY a.id ASC
             LIMIT %d OFFSET %d",
            ...array_merge([$categoriaId], self::STATUS_DEFERIDOS, [$perPage, $offset])
        );
        // phpcs:enable

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'agente_id' => (int) $row['agente_id'],
                'email'     => (string) $row['email'],
                'nome'      => (string) $row['nome'],
            ];
        }, $rows);
    }

    /**
     * Lista agentes que optaram por receber comunicações NÃO-ESSENCIAIS
     * (finalidade `comunicacao`, status `aceito` mais recente, SEM revogação).
     *
     * Usado apenas para comunicações OPCIONAIS (newsletter, pesquisas).
     * Agentes que revogaram a finalidade `comunicacao` SÃO removidos da lista.
     *
     * NUNCA retorna CPF/dados sensíveis.
     *
     * @return array<int, array{agente_id:int, email:string, nome:string}>
     */
    public function listarComConsentimentoNewsletter(): array
    {
        $placeholders = implode(', ', array_fill(0, count(self::STATUS_DEFERIDOS), '%s'));

        // Subquery: verifica que o consentimento mais recente de `comunicacao` é `aceito`.
        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $sql = $this->wpdb->prepare(
            "SELECT
                a.id          AS agente_id,
                a.email_principal AS email,
                COALESCE(NULLIF(SUBSTRING_INDEX(a.nome_publico, ' ', 1), ''), 'Participante') AS nome
             FROM {$this->tAgentes} AS a
             WHERE a.status_cadastro IN ({$placeholders})
               AND a.deleted_at IS NULL
               AND a.email_principal IS NOT NULL
               AND a.email_principal != ''
               AND EXISTS (
                   SELECT 1
                   FROM {$this->tConsentimentos} AS c
                   WHERE c.agente_id = a.id
                     AND c.finalidade = 'comunicacao'
                     AND c.status = 'aceito'
                     AND c.id = (
                         SELECT MAX(c2.id)
                         FROM {$this->tConsentimentos} AS c2
                         WHERE c2.agente_id = a.id
                           AND c2.finalidade = 'comunicacao'
                     )
               )
             ORDER BY a.id ASC",
            ...self::STATUS_DEFERIDOS
        );
        // phpcs:enable

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'agente_id' => (int) $row['agente_id'],
                'email'     => (string) $row['email'],
                'nome'      => (string) $row['nome'],
            ];
        }, $rows);
    }
}
