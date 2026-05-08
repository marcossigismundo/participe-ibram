<?php
/**
 * Unit tests for {@see AnonimizarTitularHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd;

use Ibram\ParticipeIbram\Application\Lgpd\AnonimizarTitularHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Stub minimal de wpdb com captura de operações.
 */
final class FakeWpdb
{
    public string $prefix = 'wp_';

    /** @var array<int,array{table:string,data:array<string,mixed>,where:array<string,mixed>}> */
    public array $updates = [];

    /** @var array<int,array<string,mixed>>|false */
    public $documentRows = [];

    private ?string $lastSelectAgenteId = null;

    public function update($table, $data, $where, $formats = null, $whereFormats = null): int
    {
        $this->updates[] = ['table' => (string) $table, 'data' => $data, 'where' => $where];

        return 1;
    }

    public function prepare(string $sql, ...$args): string
    {
        // Captura só para o caso "documentos do agente".
        if (stripos($sql, 'pi_documentos') !== false && isset($args[0])) {
            $this->lastSelectAgenteId = (string) $args[0];
        }

        return $sql;
    }

    /**
     * @return array<int,array<string,mixed>>|false
     */
    public function get_results(string $sql, $output = null)
    {
        return $this->documentRows;
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Application\Lgpd\AnonimizarTitularHandler
 */
final class AnonimizarTitularHandlerTest extends TestCase
{
    private FakeWpdb $wpdb;

    /** @var AuditLogger&MockObject */
    private $audit;

    private SecureLogger $logger;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb   = new FakeWpdb();
        $this->audit  = $this->createMock(AuditLogger::class);
        $this->logger = new SecureLogger(static function (string $line): void {
            // silent
        });
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pi-anon-test-' . uniqid();
        mkdir($this->tmpDir, 0750, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_handle_clears_expected_agent_columns(): void
    {
        $this->wpdb->documentRows = [];

        $this->audit->expects(self::once())->method('log');

        $h = new AnonimizarTitularHandler(
            $this->wpdb,
            $this->audit,
            $this->logger,
            $this->tmpDir
        );

        $result = $h->handle(123, 99);

        self::assertSame(123, $result['agente_id']);
        self::assertContains('agentes.email_principal', $result['campos_limpos']);
        self::assertContains('agentes.telefone', $result['campos_limpos']);
        self::assertContains('agentes.deleted_at', $result['campos_limpos']);
        self::assertContains('agentes_pf.cpf_enc', $result['campos_limpos']);
        self::assertContains('agentes_pf.cpf_hash', $result['campos_limpos']);
        self::assertContains('agentes_pf.rg_enc', $result['campos_limpos']);
        self::assertContains('agentes_pf.passaporte_enc', $result['campos_limpos']);
        self::assertContains('agentes_pf.nome_completo', $result['campos_limpos']);
    }

    public function test_handle_writes_anon_email_format(): void
    {
        $this->wpdb->documentRows = [];

        $h = new AnonimizarTitularHandler(
            $this->wpdb,
            $this->audit,
            $this->logger,
            $this->tmpDir
        );
        $h->handle(123, 99);

        $agentesUpdate = null;
        foreach ($this->wpdb->updates as $up) {
            if ($up['table'] === 'wp_pi_agentes') {
                $agentesUpdate = $up;
                break;
            }
        }
        self::assertNotNull($agentesUpdate);
        self::assertSame('anon-123@participe-ibram.local', $agentesUpdate['data']['email_principal']);
        self::assertNull($agentesUpdate['data']['telefone']);
        self::assertNotNull($agentesUpdate['data']['deleted_at']);
    }

    public function test_handle_nullifies_pf_encrypted_fields(): void
    {
        $h = new AnonimizarTitularHandler(
            $this->wpdb,
            $this->audit,
            $this->logger,
            $this->tmpDir
        );
        $h->handle(123, 99);

        $pfUpdate = null;
        foreach ($this->wpdb->updates as $up) {
            if ($up['table'] === 'wp_pi_agentes_pf') {
                $pfUpdate = $up;
                break;
            }
        }
        self::assertNotNull($pfUpdate);
        self::assertNull($pfUpdate['data']['cpf_enc']);
        self::assertNull($pfUpdate['data']['cpf_hash']);
        self::assertNull($pfUpdate['data']['rg_enc']);
        self::assertNull($pfUpdate['data']['passaporte_enc']);
        self::assertStringStartsWith('[ANON-', (string) $pfUpdate['data']['nome_completo']);
        self::assertStringEndsWith(']', (string) $pfUpdate['data']['nome_completo']);
    }

    public function test_handle_deletes_document_files_keeping_records(): void
    {
        // Cria arquivo físico simulado.
        $relPath = 'agentes/123/doc1.pdf';
        $absPath = $this->tmpDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        @mkdir(dirname($absPath), 0750, true);
        file_put_contents($absPath, 'PDF content');

        $this->wpdb->documentRows = [
            ['id' => 11, 'arquivo_path' => $relPath],
        ];

        $h = new AnonimizarTitularHandler(
            $this->wpdb,
            $this->audit,
            $this->logger,
            $this->tmpDir
        );
        $result = $h->handle(123, 99);

        self::assertFileDoesNotExist($absPath);
        self::assertSame(1, $result['documentos']['arquivos_apagados']);
        self::assertSame(1, $result['documentos']['registros']);

        // O registro de documento foi mantido (apenas atualizado), não DELETADO.
        $docUpdate = null;
        foreach ($this->wpdb->updates as $up) {
            if ($up['table'] === 'wp_pi_documentos') {
                $docUpdate = $up;
                break;
            }
        }
        self::assertNotNull($docUpdate);
        self::assertSame('', $docUpdate['data']['arquivo_path']);
        self::assertSame('[ANON]', $docUpdate['data']['nome_original']);
    }

    public function test_handle_audits_anonimizacao_executada(): void
    {
        $this->audit
            ->expects(self::once())
            ->method('log')
            ->with(
                'agente',
                123,
                'anonimizacao_executada',
                self::isNull(),
                self::callback(static function ($payload): bool {
                    return is_array($payload)
                        && isset($payload['campos_limpos'])
                        && is_array($payload['campos_limpos']);
                }),
                99
            );

        $h = new AnonimizarTitularHandler(
            $this->wpdb,
            $this->audit,
            $this->logger,
            $this->tmpDir
        );
        $h->handle(123, 99);
    }

    public function test_handle_rejects_invalid_agente_id(): void
    {
        $h = new AnonimizarTitularHandler(
            $this->wpdb,
            $this->audit,
            $this->logger,
            $this->tmpDir
        );

        $this->expectException(\InvalidArgumentException::class);
        $h->handle(0, 99);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
