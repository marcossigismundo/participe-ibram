<?php
/**
 * Unit tests for AuditLogQuery.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Support;

use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery
 */
final class AuditLogQueryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Cria um stub de wpdb que captura a SQL passada a prepare().
     *
     * @return array{0: object, 1: string|null} [$wpdb, &$capturedSql]
     */
    private function makeWpdbCapture(): array
    {
        $captured = null;
        $wpdb = new class ($captured) {
            public string $prefix = 'wp_';

            /** @var string|null */
            private ?string $capturedSql;

            /** @var string|null reference holder */
            private string|null $dummy; // unused — only for phpstan

            public function __construct(?string &$ref)
            {
                $this->capturedSql = &$ref;
            }

            /** @param mixed ...$args */
            public function prepare(string $sql, ...$args): string
            {
                // Simula substituição simples de placeholders para verificação.
                $this->capturedSql = vsprintf(
                    str_replace(['%s', '%d'], ['\'%s\'', '%d'], $sql),
                    array_map(static fn ($v): string => (string) $v, $args)
                );

                return $this->capturedSql;
            }

            /** @return list<array<string,mixed>> */
            public function get_results(string $sql, int $output = ARRAY_A): array
            {
                return [];
            }

            /** @return int|string|null */
            public function get_var(string $sql)
            {
                return '0';
            }

            /** @return array<string,mixed>|null */
            public function get_row(string $sql, int $output = ARRAY_A): ?array
            {
                return null;
            }
        };

        return [$wpdb, &$captured];
    }

    // -------------------------------------------------------------------------
    // Caso 1 — orderby inválido cai para ocorrido_em
    // -------------------------------------------------------------------------

    public function testInvalidOrderbyFallsToOcorridoEm(): void
    {
        [$wpdb, $capturedSql] = $this->makeWpdbCapture();
        $query = new AuditLogQuery($wpdb);

        $query->list(['orderby' => 'invalid_field', 'order' => 'DESC']);

        $this->assertNotNull($capturedSql, 'prepare() deve ser chamado');
        $this->assertStringContainsString(
            'ocorrido_em DESC',
            (string) $capturedSql,
            'orderby inválido deve cair para ocorrido_em'
        );
        $this->assertStringNotContainsString(
            'invalid_field',
            (string) $capturedSql,
            'campo inválido não deve aparecer na SQL'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 2 — order inválido cai para DESC
    // -------------------------------------------------------------------------

    public function testInvalidOrderFallsToDesc(): void
    {
        [$wpdb, $capturedSql] = $this->makeWpdbCapture();
        $query = new AuditLogQuery($wpdb);

        $query->list(['orderby' => 'id', 'order' => 'HACK; DROP TABLE wp_pi_audit_log; --']);

        $this->assertNotNull($capturedSql);
        $this->assertStringContainsString(
            'id DESC',
            (string) $capturedSql,
            'order inválido deve cair para DESC'
        );
        $this->assertStringNotContainsString(
            'HACK',
            (string) $capturedSql,
            'valor injetado não deve aparecer na SQL'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 3 — data_de/data_ate corretos geram cláusula WHERE
    // -------------------------------------------------------------------------

    public function testDataDeDataAteFiltersAppearInSql(): void
    {
        [$wpdb, $capturedSql] = $this->makeWpdbCapture();
        $query = new AuditLogQuery($wpdb);

        $query->list([
            'data_de'  => '2026-01-01',
            'data_ate' => '2026-03-31',
        ]);

        $this->assertStringContainsString(
            'ocorrido_em >=',
            (string) $capturedSql,
            'data_de deve gerar cláusula >=',
        );
        $this->assertStringContainsString(
            'ocorrido_em <=',
            (string) $capturedSql,
            'data_ate deve gerar cláusula <=',
        );
        $this->assertStringContainsString(
            '2026-01-01 00:00:00',
            (string) $capturedSql,
            'data_de deve ser expandida com 00:00:00'
        );
        $this->assertStringContainsString(
            '2026-03-31 23:59:59',
            (string) $capturedSql,
            'data_ate deve ser expandida com 23:59:59'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 4 — count() retorna int (mock wpdb)
    // -------------------------------------------------------------------------

    public function testCountReturnsInteger(): void
    {
        $wpdb = $this->createStub(\stdClass::class);

        // Cria wpdb mínimo via anonymous class
        $wpdbMock = new class {
            public string $prefix = 'wp_';

            /** @param mixed ...$args */
            public function prepare(string $sql, ...$args): string
            {
                return $sql;
            }

            /** @return string */
            public function get_var(string $sql): string
            {
                return '42';
            }
        };

        $query  = new AuditLogQuery($wpdbMock);
        $result = $query->count([]);

        $this->assertIsInt($result, 'count() deve retornar int');
        $this->assertSame(42, $result);
    }

    // -------------------------------------------------------------------------
    // Caso 5 — findById(0) retorna null sem disparar query
    // -------------------------------------------------------------------------

    public function testFindByIdZeroReturnsNullWithoutQuery(): void
    {
        $wpdbSpy = new class {
            public string $prefix = 'wp_';
            public int    $prepareCalled = 0;

            /** @param mixed ...$args */
            public function prepare(string $sql, ...$args): string
            {
                $this->prepareCalled++;
                return $sql;
            }

            /** @return array<string,mixed>|null */
            public function get_row(string $sql, int $output = ARRAY_A): ?array
            {
                return null;
            }
        };

        $query  = new AuditLogQuery($wpdbSpy);
        $result = $query->findById(0);

        $this->assertNull($result, 'findById(0) deve retornar null');
        $this->assertSame(
            0,
            $wpdbSpy->prepareCalled,
            'findById(0) não deve chamar prepare()'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 6 — SQL injection em filtro `acao` é tratado via prepare()
    // -------------------------------------------------------------------------

    public function testAcaoFilterIsPassedThroughPrepare(): void
    {
        $sqlInjection = "criar' OR '1'='1";
        $preparedArgs = [];

        $wpdbCaptor = new class ($preparedArgs) {
            public string $prefix = 'wp_';

            /** @var array<int,mixed> */
            private array $args;

            /** @param array<int,mixed> $args */
            public function __construct(array &$args)
            {
                $this->args = &$args;
            }

            /** @param mixed ...$passed */
            public function prepare(string $sql, ...$passed): string
            {
                foreach ($passed as $v) {
                    $this->args[] = $v;
                }
                return $sql; // retorna sql bruta; o importante é capturar os args
            }

            /** @return list<array<string,mixed>> */
            public function get_results(string $sql, int $output = ARRAY_A): array
            {
                return [];
            }
        };

        $query = new AuditLogQuery($wpdbCaptor);
        $query->list(['acao' => $sqlInjection]);

        // O valor de injeção deve aparecer como parâmetro separado (não embutido na SQL)
        $this->assertContains(
            $sqlInjection,
            $preparedArgs,
            'Valor de acao deve ser passado como parâmetro para prepare(), não interpolado na SQL'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 7 — list() não retorna dados_antes / dados_depois
    // -------------------------------------------------------------------------

    public function testListColumnsDoNotIncludePayloadFields(): void
    {
        $capturedSql = null;

        $wpdb = new class ($capturedSql) {
            public string $prefix = 'wp_';

            /** @var string|null */
            private ?string $capturedSql;

            public function __construct(?string &$ref)
            {
                $this->capturedSql = &$ref;
            }

            /** @param mixed ...$args */
            public function prepare(string $sql, ...$args): string
            {
                $this->capturedSql = $sql;
                return $sql;
            }

            /** @return list<array<string,mixed>> */
            public function get_results(string $sql, int $output = ARRAY_A): array
            {
                return [];
            }
        };

        $query = new AuditLogQuery($wpdb);
        $query->list([]);

        $sql = (string) $capturedSql;
        $this->assertStringNotContainsString('dados_antes', $sql, 'list() não deve selecionar dados_antes');
        $this->assertStringNotContainsString('dados_depois', $sql, 'list() não deve selecionar dados_depois');
    }

    // -------------------------------------------------------------------------
    // Caso 8 — orderby whitelisted fields passam corretamente
    // -------------------------------------------------------------------------

    public function testWhitelistedOrderbyPassesThrough(): void
    {
        $allowed = ['id', 'ocorrido_em', 'entidade', 'acao'];

        foreach ($allowed as $field) {
            [$wpdb, $capturedSql] = $this->makeWpdbCapture();
            $query = new AuditLogQuery($wpdb);
            $query->list(['orderby' => $field, 'order' => 'ASC']);

            $this->assertStringContainsString(
                $field . ' ASC',
                (string) $capturedSql,
                "Campo whitelisted '{$field}' deve aparecer na ORDER BY"
            );
        }
    }
}
