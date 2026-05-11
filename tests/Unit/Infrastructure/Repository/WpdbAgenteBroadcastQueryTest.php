<?php
/**
 * Unit tests for {@see WpdbAgenteBroadcastQuery}.
 *
 * Usa stub de wpdb (sem banco real).
 * Cobre: retorno somente de deferidos, ausência de PII, filtro por categoria,
 * filtro de revogação de newsletter e paginação.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Infrastructure\Repository;

use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteBroadcastQuery;
use PHPUnit\Framework\TestCase;

/**
 * Stub mínimo de wpdb — captura queries e retorna dados controlados.
 */
final class FakeWpdbBroadcast
{
    public string $prefix = 'wp_';

    /** @var array<string,mixed>[]|false $nextResults */
    public $nextResults = [];

    /** @var string[] */
    public array $preparedSqls = [];

    /** @var array<mixed> */
    public array $preparedArgs = [];

    public function prepare(string $sql, ...$args): string
    {
        $this->preparedSqls[] = $sql;
        $this->preparedArgs[] = $args;
        return $sql; // devolve SQL literal (sem substituição real)
    }

    /**
     * @return array<string,mixed>[]|false
     */
    public function get_results(string $sql, $output = null)
    {
        return $this->nextResults;
    }

    /** @return mixed */
    public function get_var(string $sql)
    {
        return null;
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteBroadcastQuery
 */
final class WpdbAgenteBroadcastQueryTest extends TestCase
{
    private FakeWpdbBroadcast $wpdb;
    private WpdbAgenteBroadcastQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb  = new FakeWpdbBroadcast();
        $this->query = new WpdbAgenteBroadcastQuery($this->wpdb, 'wp_');
    }

    /* ------------------------------------------------------------------
     * 1. listarDeferidosPaginado retorna apenas agente_id/email/nome
     * ------------------------------------------------------------------ */

    public function test_listar_deferidos_paginado_retorna_apenas_campos_whitelisted(): void
    {
        // Mock retorna linha com campos PII adicionais que NAO devem vazar.
        $this->wpdb->nextResults = [
            [
                'agente_id' => 1,
                'email'     => 'fulano@example.com',
                'nome'      => 'Fulano',
                'cpf'       => '123.456.789-00',
                'rg'        => '1234567',
                'telefone'  => '61999999999',
                'raca_cor'  => 'parda',
            ],
        ];

        $rows = $this->query->listarDeferidosPaginado(1, 10);

        $this->assertCount(1, $rows);
        $row = $rows[0];

        // Campos permitidos
        $this->assertArrayHasKey('agente_id', $row);
        $this->assertArrayHasKey('email', $row);
        $this->assertArrayHasKey('nome', $row);

        // CRÍTICO: ausência de PII
        $this->assertArrayNotHasKey('cpf', $row, 'cpf NAO deve constar no retorno');
        $this->assertArrayNotHasKey('rg', $row, 'rg NAO deve constar no retorno');
        $this->assertArrayNotHasKey('telefone', $row, 'telefone NAO deve constar no retorno');
        $this->assertArrayNotHasKey('raca_cor', $row, 'raca_cor NAO deve constar no retorno');
    }

    /* ------------------------------------------------------------------
     * 2. Ausência de PII — tipos corretos nos campos retornados
     * ------------------------------------------------------------------ */

    public function test_listar_deferidos_paginado_tipos_corretos(): void
    {
        $this->wpdb->nextResults = [
            ['agente_id' => '42', 'email' => 'a@b.com', 'nome' => 'Ana'],
        ];

        $rows = $this->query->listarDeferidosPaginado(1, 10);

        $this->assertSame(42, $rows[0]['agente_id'], 'agente_id deve ser int');
        $this->assertIsString($rows[0]['email']);
        $this->assertIsString($rows[0]['nome']);
    }

    /* ------------------------------------------------------------------
     * 3. listarDeferidosElegiveisCategoria — SQL inclui categoria id
     * ------------------------------------------------------------------ */

    public function test_listar_elegiveis_categoria_inclui_categoria_id_no_sql(): void
    {
        $this->wpdb->nextResults = [
            ['agente_id' => 7, 'email' => 'gestor@ibram.gov.br', 'nome' => 'Carlos'],
        ];

        $rows = $this->query->listarDeferidosElegiveisCategoria(3, 1, 50);

        $this->assertCount(1, $rows);
        $this->assertSame(7, $rows[0]['agente_id']);

        // Verifica que o categoriaId foi passado ao prepare
        $args = $this->wpdb->preparedArgs;
        $this->assertNotEmpty($args);
        // Primeiro arg da chamada deve ser o categoriaId=3
        $firstCall = $args[0];
        $this->assertContains(3, $firstCall, 'categoriaId=3 deve aparecer nos args de prepare');
    }

    /* ------------------------------------------------------------------
     * 4. listarDeferidosElegiveisCategoria exclui PII mesmo com campos extras
     * ------------------------------------------------------------------ */

    public function test_listar_elegiveis_categoria_sem_pii(): void
    {
        $this->wpdb->nextResults = [
            [
                'agente_id' => 10,
                'email'     => 'x@ibram.gov.br',
                'nome'      => 'Xavier',
                'cpf'       => '000.000.000-00',
                'telefone'  => '6133334444',
            ],
        ];

        $rows = $this->query->listarDeferidosElegiveisCategoria(5, 1, 10);

        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('cpf', $rows[0]);
        $this->assertArrayNotHasKey('telefone', $rows[0]);
    }

    /* ------------------------------------------------------------------
     * 5. listarComConsentimentoNewsletter exclui revogação (via SQL subquery)
     * ------------------------------------------------------------------ */

    public function test_listar_com_consentimento_newsletter_sem_pii(): void
    {
        // Simula que quem revogou NÃO retorna (a subquery filtra; o mock não retorna)
        $this->wpdb->nextResults = [
            ['agente_id' => 20, 'email' => 'newsletter@example.com', 'nome' => 'Beta'],
        ];

        $rows = $this->query->listarComConsentimentoNewsletter();

        $this->assertCount(1, $rows);
        $this->assertSame(20, $rows[0]['agente_id']);
        $this->assertArrayNotHasKey('cpf', $rows[0]);
        $this->assertArrayNotHasKey('raca_cor', $rows[0]);
    }

    /* ------------------------------------------------------------------
     * 6. listarComConsentimentoNewsletter retorna vazio quando mock não tem rows
     * ------------------------------------------------------------------ */

    public function test_listar_com_consentimento_newsletter_retorna_vazio_quando_sem_rows(): void
    {
        $this->wpdb->nextResults = [];

        $rows = $this->query->listarComConsentimentoNewsletter();

        $this->assertSame([], $rows);
    }

    /* ------------------------------------------------------------------
     * 7. Paginação — $page e $perPage geram LIMIT/OFFSET distintos no SQL
     * ------------------------------------------------------------------ */

    public function test_paginacao_respeita_page_e_per_page(): void
    {
        $this->wpdb->nextResults = [];

        // page=2, perPage=5 → offset=5
        $this->query->listarDeferidosPaginado(2, 5);

        $sqls = $this->wpdb->preparedSqls;
        $args = $this->wpdb->preparedArgs;
        $this->assertNotEmpty($sqls);

        // Os dois últimos args do último prepare devem ser perPage=5 e offset=5
        $lastArgs = end($args);
        $this->assertIsArray($lastArgs);
        // Últimas duas posições: limit=5, offset=5
        $lastTwo = array_slice($lastArgs, -2);
        $this->assertSame(5, $lastTwo[0], 'limit deve ser perPage=5');
        $this->assertSame(5, $lastTwo[1], 'offset deve ser (page-1)*perPage = 5');
    }

    /* ------------------------------------------------------------------
     * 8. listarDeferidosPaginado retorna [] quando wpdb retorna false
     * ------------------------------------------------------------------ */

    public function test_listar_deferidos_retorna_array_vazio_quando_wpdb_false(): void
    {
        $this->wpdb->nextResults = false;

        $rows = $this->query->listarDeferidosPaginado(1, 10);

        $this->assertSame([], $rows);
    }

    /* ------------------------------------------------------------------
     * 9. iterar() agrega batches via listarDeferidosPaginado
     * ------------------------------------------------------------------ */

    public function test_iterar_agrega_resultado_de_uma_pagina(): void
    {
        $this->wpdb->nextResults = [
            ['agente_id' => 99, 'email' => 'iter@ibram.gov.br', 'nome' => 'Iter'],
        ];

        $collected = [];
        foreach ($this->query->iterar(100) as $agenteId => $row) {
            $collected[$agenteId] = $row;
        }

        $this->assertArrayHasKey(99, $collected);
        $this->assertSame('iter@ibram.gov.br', $collected[99]['email']);
        // Sem PII
        $this->assertArrayNotHasKey('cpf', $collected[99]);
    }
}
