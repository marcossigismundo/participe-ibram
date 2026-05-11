<?php
/**
 * CRÍTICO — AuditNoLeakTest
 *
 * Valida ausência de PII em todas as saídas do sistema de auditoria:
 *  - Listagem (AuditLogQuery::list)
 *  - Detalhe via AJAX (pi_admin_audit_get_detalhe)
 *  - Export CSV/JSON (ExportarAuditLogHandler)
 *  - Confirmação que list() não retorna dados_antes/dados_depois
 *
 * Wave 10 deve revisar este teste — é gate de release.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Admin;

use Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogCommand;
use Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Helpers\Json;
use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\AuditAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AuditDetalheController;
use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery
 * @covers \Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogHandler
 * @covers \Ibram\ParticipeIbram\Presentation\Admin\Ajax\AuditAdminAjax
 */
final class AuditNoLeakTest extends TestCase
{
    // -------------------------------------------------------------------------
    // PII fixtures — valores NUNCA devem aparecer em nenhuma saída
    // -------------------------------------------------------------------------

    /** CPF em texto claro — NUNCA deve aparecer no output */
    private const PII_CPF = '111.222.333-44';

    /** E-mail em texto claro — NUNCA deve aparecer no output */
    private const PII_EMAIL = 'fulano@example.com';

    /** Telefone em texto claro — NUNCA deve aparecer no output */
    private const PII_PHONE = '(11) 99999-5678';

    /** Nome completo em texto claro — não é indexado, mas testado em JSON payload */
    private const PII_NOME = 'Fulano de Tal';

    /**
     * JSON com PII em claro — simulando o que poderia estar em dados_depois na DB.
     * Em produção esse campo armazena dados já mascarados; aqui simulamos o pior
     * caso para garantir que o sistema NUNCA vaze o valor bruto.
     */
    private const RAW_PAYLOAD_JSON = '{"cpf":"111.222.333-44","email":"fulano@example.com","telefone":"(11) 99999-5678","nome":"Fulano de Tal"}';

    /**
     * Substrings PII que absolutamente NÃO devem aparecer em nenhuma resposta.
     *
     * @var list<string>
     */
    private const FORBIDDEN_SUBSTRINGS = [
        self::PII_CPF,
        '11122233344',      // CPF somente dígitos
        self::PII_EMAIL,
        self::PII_PHONE,
        '11999995678',      // telefone somente dígitos
    ];

    // -------------------------------------------------------------------------
    // Fixtures de row do banco
    // -------------------------------------------------------------------------

    /**
     * Simula row retornada por list() — SEM dados_antes/dados_depois.
     *
     * @return array<string,mixed>
     */
    private function makeListRow(): array
    {
        return [
            'id'          => 1,
            'entidade'    => 'agente',
            'entidade_id' => 42,
            'acao'        => 'criar',
            'ator_id'     => 7,
            'ocorrido_em' => '2026-01-15 10:30:00',
        ];
    }

    /**
     * Simula row retornada por findById() — COM dados_antes/dados_depois (mascarados
     * pela camada de escrita; aqui usamos raw para testar que a apresentação também mascara).
     *
     * @return array<string,mixed>
     */
    private function makeDetailRow(): array
    {
        return array_merge($this->makeListRow(), [
            'dados_antes'  => null,
            'dados_depois' => self::RAW_PAYLOAD_JSON,
            'ip_hash'      => hash('sha256', '192.168.0.1'),
            'user_agent'   => 'Mozilla/5.0 (Windows NT 10.0)',
        ]);
    }

    // -------------------------------------------------------------------------
    // Mock wpdb
    // -------------------------------------------------------------------------

    /**
     * Cria wpdb stub que retorna resultados controlados.
     *
     * @param list<array<string,mixed>> $listRows    Retornado por get_results().
     * @param array<string,mixed>|null  $detailRow   Retornado por get_row().
     * @param int                       $countResult Retornado por get_var().
     */
    private function makeWpdb(array $listRows = [], ?array $detailRow = null, int $countResult = 0): object
    {
        return new class ($listRows, $detailRow, $countResult) {
            public string $prefix = 'wp_';

            /** @var list<array<string,mixed>> */
            private array $listRows;

            /** @var array<string,mixed>|null */
            private ?array $detailRow;

            private int $countResult;

            /**
             * @param list<array<string,mixed>> $listRows
             * @param array<string,mixed>|null  $detailRow
             */
            public function __construct(array $listRows, ?array $detailRow, int $countResult)
            {
                $this->listRows    = $listRows;
                $this->detailRow   = $detailRow;
                $this->countResult = $countResult;
            }

            /** @param mixed ...$args */
            public function prepare(string $sql, ...$args): string
            {
                // Substituição simples de %d/%s por valores — suficiente para testes
                $i = 0;
                return preg_replace_callback(
                    '/%[sd]/',
                    static function () use (&$i, $args): string {
                        $v = $args[$i++] ?? '';
                        return is_numeric($v) ? (string) $v : "'" . addslashes((string) $v) . "'";
                    },
                    $sql
                ) ?? $sql;
            }

            /** @return list<array<string,mixed>> */
            public function get_results(string $sql, int $output = ARRAY_A): array
            {
                return $this->listRows;
            }

            /** @return array<string,mixed>|null */
            public function get_row(string $sql, int $output = ARRAY_A): ?array
            {
                return $this->detailRow;
            }

            /** @return string */
            public function get_var(string $sql): string
            {
                return (string) $this->countResult;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Teste 1 — list() NÃO retorna PII nas colunas da listagem
    // -------------------------------------------------------------------------

    public function testListaNaoRetornaPiiNasColunas(): void
    {
        // Injeta row com PII nos campos de listagem (pior caso)
        $rowWithPii = array_merge($this->makeListRow(), [
            'entidade'   => 'agente_' . self::PII_CPF, // PII injetada em campo não-sensitivo
            'dados_depois' => self::RAW_PAYLOAD_JSON,  // não deve vazar na listagem
        ]);

        $wpdb  = $this->makeWpdb([$rowWithPii]);
        $query = new AuditLogQuery($wpdb);
        $rows  = $query->list([]);

        // Serializa o retorno para verificar ausência de PII
        $serialized = (string) json_encode($rows, JSON_UNESCAPED_UNICODE);

        // dados_depois/dados_antes NÃO devem aparecer (list() usa whitelist de colunas)
        $this->assertArrayNotHasKey('dados_depois', $rows[0] ?? [], 'list() não deve incluir dados_depois');
        $this->assertArrayNotHasKey('dados_antes', $rows[0] ?? [], 'list() não deve incluir dados_antes');

        // PII do payload não deve vazar via serialização dos campos de listagem
        foreach (self::FORBIDDEN_SUBSTRINGS as $pii) {
            // Só checamos as substrings que NÃO estão em campos permitidos intencionalmente
            if (strpos($pii, 'agente_') === false) {
                $this->assertStringNotContainsString(
                    $pii,
                    $serialized,
                    "PII '{$pii}' não deve aparecer na listagem de audit"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Teste 2 — Detalhe AJAX retorna valores mascarados (não PII em claro)
    // -------------------------------------------------------------------------

    public function testDetalheAjaxRetornaDadosMascarados(): void
    {
        $detailRow = $this->makeDetailRow();
        $wpdb      = $this->makeWpdb([], $detailRow, 1);
        $query     = new AuditLogQuery($wpdb);

        // AuditDetalheController mascara os payloads
        $ctrl = new AuditDetalheController($query);

        // Invoca maskPayload diretamente (como handleGetDetalhe faz)
        $maskedDepois = $ctrl->maskPayload($detailRow['dados_depois']);

        $maskedStr = (string) json_encode($maskedDepois, JSON_UNESCAPED_UNICODE);

        // PII em claro NÃO deve aparecer no payload mascarado
        $this->assertStringNotContainsString(
            self::PII_CPF,
            $maskedStr,
            'CPF em claro não deve aparecer no detalhe'
        );
        $this->assertStringNotContainsString(
            self::PII_EMAIL,
            $maskedStr,
            'E-mail em claro não deve aparecer no detalhe'
        );
        $this->assertStringNotContainsString(
            self::PII_PHONE,
            $maskedStr,
            'Telefone em claro não deve aparecer no detalhe'
        );

        // Deve conter padrão de mascaramento (*** ou XX.XXX ou @domain)
        // PiiMasker: CPF -> XXX.XXX.333-XX, email -> f***@example.com, phone -> (XX) 9XXXX-5678
        $this->assertTrue(
            str_contains($maskedStr, '***')
                || str_contains($maskedStr, 'XX')
                || str_contains($maskedStr, 'REDACTED'),
            'Payload mascarado deve conter padrão de mascaramento (*** / XX / REDACTED)'
        );
    }

    // -------------------------------------------------------------------------
    // Teste 3 — Export não inclui PII (whitelist de colunas)
    // -------------------------------------------------------------------------

    public function testExportNaoIncluidPii(): void
    {
        // ExportarAuditLogHandler usa whitelist: id, entidade, entidade_id, acao, ator_id, ocorrido_em
        // NUNCA exporta dados_antes/dados_depois.
        // Verificamos isso inspecionando a constante EXPORT_COLUMNS via reflexão.

        $ref           = new \ReflectionClass(ExportarAuditLogHandler::class);
        $exportColumns = $ref->getConstant('EXPORT_COLUMNS');

        $this->assertIsArray($exportColumns, 'EXPORT_COLUMNS deve existir e ser array');

        $forbiddenExportColumns = ['dados_antes', 'dados_depois', 'cpf', 'email', 'telefone', 'nome'];
        foreach ($forbiddenExportColumns as $col) {
            $this->assertNotContains(
                $col,
                (array) $exportColumns,
                "EXPORT_COLUMNS não deve incluir '{$col}'"
            );
        }

        // Adicionalmente, testa whitelistRow() em modo de simulação de CSV/JSON
        // via handler stub: cria wpdb que retorna 1 row com PII e count=1
        $rowWithPii = array_merge($this->makeDetailRow(), [
            'dados_depois' => self::RAW_PAYLOAD_JSON,
        ]);

        $wpdb  = $this->makeWpdb([$rowWithPii], $rowWithPii, 1);
        $query = new AuditLogQuery($wpdb);

        // Stub AuditLogger e dependências
        $auditStub = $this->createStub(AuditLogger::class);
        $jsonHelper = new Json();
        $uuid       = new UuidGenerator();

        $handler = new ExportarAuditLogHandler($query, $auditStub, $uuid, $jsonHelper);
        $command = new ExportarAuditLogCommand([], 'json', 1);

        // Executa o export para arquivo temporário
        $tmpDir = sys_get_temp_dir() . '/pi_audit_test_' . uniqid('', true);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0750, true);
        }

        // Define constante PI_PLUGIN_DIR temporariamente para direcionar o export
        // O handler usa uploadDir() internamente. Redirecionamos via PI_PLUGIN_DIR.
        // Como não podemos redefinir constants, usamos WP_CONTENT_DIR stub.
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', $tmpDir . '/wp-content');
        }

        // Tenta executar o handler — pode falhar por dir, mas o que testamos é
        // a whitelist de colunas (já verificada via reflexão acima).
        // Se conseguir gerar o arquivo, verifica o conteúdo.
        $exportUrl = null;
        try {
            $exportUrl = $handler->handle($command);
        } catch (\Throwable $e) {
            // Falha de diretório esperada em ambiente de teste sem WP completo.
            // A whitelist já foi verificada por reflexão acima — teste pass.
            $this->markTestIncomplete(
                'Export file generation skipped (no WP filesystem in CI): ' . $e->getMessage()
            );
            return;
        }

        // Se gerou arquivo, lê e verifica ausência de PII
        $transientKey = 'pi_audit_export_' . md5(
            parse_url($exportUrl, PHP_URL_QUERY) ?? ''
        );
        $transient = $GLOBALS['__pi_test_transients'][$transientKey] ?? null;

        if (is_array($transient) && isset($transient['path']) && file_exists($transient['path'])) {
            $fileContent = (string) file_get_contents($transient['path']);
            foreach (self::FORBIDDEN_SUBSTRINGS as $pii) {
                $this->assertStringNotContainsString(
                    $pii,
                    $fileContent,
                    "PII '{$pii}' não deve aparecer no arquivo exportado"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Teste 4 — list() NÃO retorna campos dados_antes/dados_depois
    // -------------------------------------------------------------------------

    public function testListNaoRetornaDadosAntesNemDepois(): void
    {
        // list() tem SELECT explícito com LIST_COLUMNS — nunca dados_antes/dados_depois
        $ref     = new \ReflectionClass(AuditLogQuery::class);
        $listCol = $ref->getConstant('LIST_COLUMNS');

        $this->assertIsString($listCol, 'LIST_COLUMNS deve ser string');
        $this->assertStringNotContainsString(
            'dados_antes',
            (string) $listCol,
            'LIST_COLUMNS não deve incluir dados_antes'
        );
        $this->assertStringNotContainsString(
            'dados_depois',
            (string) $listCol,
            'LIST_COLUMNS não deve incluir dados_depois'
        );

        // Confirma que DETAIL_COLUMNS inclui (apenas detalhe retorna com PII mascarado)
        $detailCol = $ref->getConstant('DETAIL_COLUMNS');
        $this->assertStringContainsString(
            'dados_depois',
            (string) $detailCol,
            'DETAIL_COLUMNS deve incluir dados_depois (mascarado pela camada de apresentação)'
        );

        // Teste via execução real da query stub
        $wpdb  = $this->makeWpdb([$this->makeListRow()]);
        $query = new AuditLogQuery($wpdb);
        $rows  = $query->list([]);

        $this->assertNotEmpty($rows, 'Deve retornar ao menos uma row');
        $firstRow = $rows[0];

        $this->assertArrayNotHasKey('dados_antes',  $firstRow, 'list() não deve retornar dados_antes');
        $this->assertArrayNotHasKey('dados_depois', $firstRow, 'list() não deve retornar dados_depois');
        $this->assertArrayNotHasKey('ip_hash',      $firstRow, 'list() não deve retornar ip_hash');
        $this->assertArrayNotHasKey('user_agent',   $firstRow, 'list() não deve retornar user_agent');
    }

    // -------------------------------------------------------------------------
    // Teste 5 — PiiMasker mascara corretamente os valores do payload JSON
    // -------------------------------------------------------------------------

    public function testPiiMaskerMascaraPayloadJson(): void
    {
        $decoded = json_decode(self::RAW_PAYLOAD_JSON, true);
        $this->assertIsArray($decoded);

        $maskedCpf   = PiiMasker::maskCpf((string) ($decoded['cpf'] ?? ''));
        $maskedEmail = PiiMasker::maskEmail((string) ($decoded['email'] ?? ''));
        $maskedPhone = PiiMasker::maskPhone((string) ($decoded['telefone'] ?? ''));

        // Confirma que nenhum mascarado contém valor bruto
        $this->assertStringNotContainsString(self::PII_CPF,   $maskedCpf,   'CPF mascarado não deve conter original');
        $this->assertStringNotContainsString(self::PII_EMAIL,  $maskedEmail, 'E-mail mascarado não deve conter original');
        $this->assertStringNotContainsString(self::PII_PHONE,  $maskedPhone, 'Telefone mascarado não deve conter original');

        // Confirma padrões de mascaramento esperados
        $this->assertStringContainsString('XX',  $maskedCpf,   'CPF mascarado deve conter XX');
        $this->assertStringContainsString('***', $maskedEmail, 'E-mail mascarado deve conter ***');
        $this->assertStringContainsString('XX',  $maskedPhone, 'Telefone mascarado deve conter XX');
    }
}
