<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoHandler}.
 *
 * Foco crítico (Onda 10 vai auditar):
 *  - ZIP contem `apuracao.json`, `apuracao.csv`, `metodologia.md`,
 *    `hash-pre-apuracao.txt`.
 *  - **AUSÊNCIA DE PII**: o JSON gerado NÃO contém cpf, email, telefone,
 *    raca_cor, genero, eleitor_hash, agente_id, ator_id, etc.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoCommand;
use Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use PHPUnit\Framework\TestCase;
use ZipArchive;

require_once __DIR__ . '/Fakes.php';

/**
 * @covers \Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoHandler
 */
final class ExportarRelatorioApuracaoHandlerTest extends TestCase
{
    private FakeVotacaoRepository $votacaoRepo;
    private FakeVotoRepository $votoRepo;
    private FakeResultadoRepository $resultadoRepo;
    private AuditLogger $audit;
    private string $tmpDir;

    private const PII_KEYS_PROIBIDAS = [
        'cpf', 'cpf_enc', 'cpf_hash', 'rg', 'passaporte', 'cnpj',
        'email', 'telefone',
        'raca_cor', 'genero', 'orientacao_sexual',
        'povos_comunidades_tradicionais', 'deficiencia',
        'eleitor_hash', 'ip_hash',
        'agente_id', 'user_id', 'ator_id',
    ];

    protected function setUp(): void
    {
        $GLOBALS['__pi_test_transients'] = [];

        $this->votacaoRepo   = new FakeVotacaoRepository();
        $this->votoRepo      = new FakeVotoRepository();
        $this->resultadoRepo = new FakeResultadoRepository();

        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';
            public function insert(string $table, array $data, array $formats): bool { return true; }
        };
        $this->audit = new AuditLogger($wpdb, new IpResolver([], []));

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pi-export-test-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            self::rmrf($this->tmpDir);
        }
    }

    public function testZipContemArquivosEsperados(): void
    {
        $info = $this->runExport();

        self::assertFileExists($info['path']);
        self::assertGreaterThan(0, $info['bytes']);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($info['path']) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        self::assertContains('apuracao.json', $names);
        self::assertContains('apuracao.csv', $names);
        self::assertContains('metodologia.md', $names);
        self::assertContains('hash-pre-apuracao.txt', $names);
    }

    public function testJsonNaoContemPii(): void
    {
        // Provider mal-comportado tenta injetar PII — handler tem que filtrar.
        $info = $this->runExport(static function (int $candidatoId): array {
            return [
                'numero_registro' => 'REG-' . $candidatoId,
                'nome_publico'    => 'Candidato ' . $candidatoId,
                // Tentativas de injeção (devem ser filtradas pela whitelist).
                'cpf'             => '11111111111',
                'email'           => 'leak@example.com',
                'telefone'        => '+55 11 99999-9999',
                'raca_cor'        => 'parda',
                'genero'          => 'feminino',
                'agente_id'       => 99,
                'eleitor_hash'    => str_repeat('a', 64),
            ];
        });

        $zip = new ZipArchive();
        $zip->open($info['path']);
        $jsonStr = $zip->getFromName('apuracao.json');
        $zip->close();
        self::assertNotFalse($jsonStr);

        // Campo a campo: nenhuma chave proibida deve estar presente em qualquer
        // profundidade do JSON.
        $haystack = strtolower($jsonStr);
        foreach (self::PII_KEYS_PROIBIDAS as $piiKey) {
            self::assertStringNotContainsString(
                '"' . strtolower($piiKey) . '"',
                $haystack,
                'PII key "' . $piiKey . '" leaked in apuracao.json'
            );
        }

        // E não pode conter o valor literal de PII injetado.
        self::assertStringNotContainsString('11111111111', $jsonStr);
        self::assertStringNotContainsString('leak@example.com', $jsonStr);
        self::assertStringNotContainsString('parda', $jsonStr);

        // Mas DEVE conter os campos públicos esperados.
        $decoded = json_decode($jsonStr, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('hash_pre_apuracao', $decoded);
        self::assertArrayHasKey('algoritmo_hash', $decoded);
        self::assertSame('sha256', $decoded['algoritmo_hash']);
        self::assertArrayHasKey('tie_break_rule', $decoded);
        self::assertArrayHasKey('categorias', $decoded);

        self::assertNotEmpty($decoded['categorias']);
        $resultados = $decoded['categorias'][0]['resultados'];
        self::assertNotEmpty($resultados);
        $first = $resultados[0];
        self::assertArrayHasKey('candidato_inscricao_id', $first);
        self::assertArrayHasKey('numero_registro', $first);
        self::assertArrayHasKey('nome_publico', $first);
        self::assertArrayNotHasKey('cpf', $first);
        self::assertArrayNotHasKey('email', $first);
        self::assertArrayNotHasKey('agente_id', $first);
    }

    public function testCsvNaoContemPii(): void
    {
        $info = $this->runExport(static function (int $candidatoId): array {
            return [
                'numero_registro' => 'REG-' . $candidatoId,
                'nome_publico'    => 'Candidato ' . $candidatoId,
                'cpf'             => '22222222222',
                'email'           => 'leak2@example.com',
            ];
        });

        $zip = new ZipArchive();
        $zip->open($info['path']);
        $csv = $zip->getFromName('apuracao.csv');
        $zip->close();
        self::assertNotFalse($csv);

        self::assertStringNotContainsString('22222222222', $csv);
        self::assertStringNotContainsString('leak2@example.com', $csv);
        self::assertStringContainsString('numero_registro', $csv);
        self::assertStringContainsString('nome_publico', $csv);
        self::assertStringContainsString('REG-202', $csv);
    }

    public function testFalhaSeNaoEstaApurada(): void
    {
        $aberta = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $seeded = $this->votacaoRepo->seed($aberta);

        $handler = $this->makeHandler(static fn () => []);
        $this->expectException(\DomainException::class);
        $handler->handle(new ExportarRelatorioApuracaoCommand((int) $seeded->id()));
    }

    /* ===================== helpers ===================== */

    /**
     * @return array{path:string,url:string,filename:string,bytes:int,sha256:string}
     */
    private function runExport(?callable $lookup = null): array
    {
        $apuradoEm = new DateTimeImmutable('2026-06-12 10:00:00', new DateTimeZone('UTC'));

        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::apurada(),
            ModoVotacao::porCategoria(),
            str_repeat('a', 64),
            $apuradoEm
        );
        $seeded = $this->votacaoRepo->seed($votacao);
        $vid    = (int) $seeded->id();

        $cat        = 11;
        $resultados = [
            new Resultado(null, $vid, $cat, 202, 5, 1, true,  false, $apuradoEm),
            new Resultado(null, $vid, $cat, 303, 3, 2, false, true,  $apuradoEm),
            new Resultado(null, $vid, $cat, 404, 1, 3, false, false, $apuradoEm),
        ];
        $this->resultadoRepo->salvarResultados($vid, $resultados);

        return $this->makeHandler($lookup ?? static function (int $id): array {
            return [
                'numero_registro' => 'REG-' . $id,
                'nome_publico'    => 'Candidato ' . $id,
            ];
        })->handle(new ExportarRelatorioApuracaoCommand($vid, 1));
    }

    private function makeHandler(callable $lookup): ExportarRelatorioApuracaoHandler
    {
        return new ExportarRelatorioApuracaoHandler(
            $this->votacaoRepo,
            $this->resultadoRepo,
            $this->votoRepo,
            $this->audit,
            $lookup,
            $this->tmpDir,
            'http://example.test/uploads'
        );
    }

    private static function rmrf(string $dir): void
    {
        $items = is_dir($dir) ? @scandir($dir) : false;
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . DIRECTORY_SEPARATOR . $i;
            if (is_dir($p)) {
                self::rmrf($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }
}
