<?php
/**
 * @package Ibram\ParticipeIbram\Tests\Unit\Infrastructure\Storage
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Infrastructure\Storage;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Infrastructure\Storage\PrivateFileStorage;
use Ibram\ParticipeIbram\Infrastructure\Storage\StorageException;
use PHPUnit\Framework\TestCase;

/**
 * Testa o storage privado:
 *  - round-trip de store + read.
 *  - rejeição de path traversal e arquivos fora do base dir.
 *  - garantia de presença dos arquivos de proteção (.htaccess, web.config, index.php).
 *
 * Observação: a validação de MIME por finfo é feita NO STORE; o handler de
 * Application valida o MIME contra a lista permitida do TipoDocumento. Aqui
 * verificamos que `mime_real` retornado é fiel ao conteúdo (não à extensão).
 */
final class PrivateFileStorageTest extends TestCase
{
    private string $baseDir;

    /** @var \wpdb */
    private $wpdbStub;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('fileinfo')) {
            $this->markTestSkipped('ext-fileinfo nao disponivel.');
        }
        $this->baseDir = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'pi-storage-test-' . bin2hex(random_bytes(6));
        $this->wpdbStub = $this->makeWpdbStub();
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->baseDir);
        parent::tearDown();
    }

    public function test_diretorio_base_e_arquivos_de_protecao_criados(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        $this->assertDirectoryExists($this->baseDir);
        $this->assertFileExists($this->baseDir . DIRECTORY_SEPARATOR . '.htaccess');
        $this->assertFileExists($this->baseDir . DIRECTORY_SEPARATOR . 'web.config');
        $this->assertFileExists($this->baseDir . DIRECTORY_SEPARATOR . 'index.php');

        $htaccess = (string) file_get_contents($this->baseDir . DIRECTORY_SEPARATOR . '.htaccess');
        $this->assertStringContainsString('Deny from all', $htaccess);

        $unused = $storage; // evita "unused"
        $this->assertNotNull($unused);
    }

    public function test_store_e_read_roundtrip(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        $tmp = $this->writeTmpPdf('Hello-PDF');
        $stored = $storage->store($tmp, 'meu-doc.pdf', 42);

        $this->assertArrayHasKey('path', $stored);
        $this->assertArrayHasKey('hash', $stored);
        $this->assertArrayHasKey('mime', $stored);
        $this->assertArrayHasKey('size', $stored);
        $this->assertSame('application/pdf', $stored['mime']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $stored['hash']);
        $this->assertGreaterThan(0, (int) $stored['size']);
        $this->assertStringContainsString('/42/', (string) $stored['path'], 'agente_id usado como ownerKey');

        // Round-trip: read deve retornar o mesmo conteúdo gravado.
        $contents = $storage->read((string) $stored['path'], 99);
        $this->assertSame((int) $stored['size'], strlen($contents));
        $this->assertSame((string) $stored['hash'], hash('sha256', $contents));
    }

    public function test_store_renomeia_para_uuid_independente_da_extensao(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        $tmp = $this->writeTmpPdf('PDF-content');
        $stored = $storage->store($tmp, 'arquivo-perigoso.exe', 42);

        // Mesmo com nome ".exe", o MIME real foi PDF e a extensão final é pdf.
        $this->assertSame('application/pdf', $stored['mime']);
        $this->assertMatchesRegularExpression('#\\.pdf$#', (string) $stored['path']);
    }

    public function test_read_bloqueia_path_traversal(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        $this->expectException(StorageException::class);
        $storage->read('../../../../etc/passwd', 99);
    }

    public function test_read_bloqueia_path_absoluto(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        $this->expectException(StorageException::class);
        $storage->read('/etc/passwd', 99);
    }

    public function test_delete_idempotente(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        $tmp     = $this->writeTmpPdf('to-delete');
        $stored  = $storage->store($tmp, 'a.pdf', 7);
        $this->assertTrue($storage->delete((string) $stored['path']));
        $this->assertFalse($storage->delete((string) $stored['path']), 'arquivo ja removido nao re-lanca');
    }

    public function test_store_rejeita_arquivo_inexistente(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        $this->expectException(StorageException::class);
        $storage->store('/nao/existe/arquivo.pdf', 'x.pdf', 1);
    }

    public function test_mime_real_nao_confia_em_extensao(): void
    {
        $logger  = $this->makeAuditLogger();
        $storage = new PrivateFileStorage($logger, $this->baseDir);

        // Conteúdo de texto puro, mas nome "arquivo.pdf".
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fake-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($tmp, "isso aqui nao e um pdf, eh texto puro\n");

        try {
            $stored = $storage->store($tmp, 'arquivo.pdf', 1);
            $this->assertNotSame('application/pdf', $stored['mime'], 'MIME real reflete conteudo, nao extensao');
            $this->assertStringStartsWith('text/', (string) $stored['mime']);
        } finally {
            @unlink($tmp);
        }
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    private function makeAuditLogger(): AuditLogger
    {
        $ipResolver = new IpResolver([], []);
        return new AuditLogger($this->wpdbStub, $ipResolver);
    }

    /**
     * Stub mínimo de \wpdb que aceita insert sem efeito real.
     */
    private function makeWpdbStub()
    {
        return new class {
            /** @var string */
            public $prefix = 'wp_';

            /**
             * @param string               $table
             * @param array<string,mixed>  $row
             * @param array<int,string>    $formats
             *
             * @return int|false
             */
            public function insert($table, $row, $formats)
            {
                unset($table, $row, $formats);
                return 1;
            }
        };
    }

    /**
     * Escreve um PDF mínimo válido em tmp e retorna o path.
     *
     * O cabeçalho `%PDF-` é o suficiente para finfo classificar como
     * application/pdf.
     */
    private function writeTmpPdf(string $marker): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf-' . bin2hex(random_bytes(4)) . '.bin';
        $body = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj <<>> endobj\n%%EOF\n" . $marker;
        file_put_contents($path, $body);
        return $path;
    }

    /**
     * Recursive remove diretório (uso em tearDown).
     */
    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $it;
            if (is_dir($full)) {
                $this->rmrf($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($dir);
    }
}
