<?php
/**
 * ExportarAuditLogHandler — gera arquivo de export do log de auditoria.
 *
 * @package Ibram\ParticipeIbram\Application\Audit
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Audit;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\Json;
use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;
use RuntimeException;

/**
 * Handler para ExportarAuditLogCommand.
 *
 * Segurança:
 *  - Whitelist de colunas exportadas (NUNCA dados_antes/dados_depois crus).
 *  - Proteção DoS: rejeita filtros que gerariam > 100k registros.
 *  - Export paginado em batches de 1000 para não estourar memória.
 *  - URL signed via transient com TTL de 5 minutos.
 *  - Audita o próprio export via AuditLogger.
 *  - Arquivo gerado em diretório privado (fora do webroot acessível via URL direta).
 */
final class ExportarAuditLogHandler
{
    /** Limite de segurança: rejeita exports acima deste total. */
    private const MAX_ROWS = 100_000;

    /** Tamanho de cada batch de leitura. */
    private const BATCH_SIZE = 1_000;

    /** TTL da URL assinada em segundos (5 minutos). */
    private const SIGNED_URL_TTL = 300;

    /**
     * Colunas exportadas — whitelist defensiva.
     * NUNCA inclui dados_antes/dados_depois crus.
     */
    private const EXPORT_COLUMNS = ['id', 'entidade', 'entidade_id', 'acao', 'ator_id', 'ocorrido_em'];

    private AuditLogQuery $query;
    private AuditLogger $audit;
    private UuidGenerator $uuid;
    private Json $json;

    public function __construct(
        AuditLogQuery $query,
        AuditLogger $audit,
        UuidGenerator $uuid,
        Json $json
    ) {
        $this->query = $query;
        $this->audit = $audit;
        $this->uuid  = $uuid;
        $this->json  = $json;
    }

    /**
     * Executa o export e retorna URL temporária assinada para download.
     *
     * @throws RuntimeException Se o total ultrapassar MAX_ROWS ou se a escrita falhar.
     */
    public function handle(ExportarAuditLogCommand $command): string
    {
        $filters = $command->filters();
        $atorId  = $command->atorId();
        $format  = $command->format();

        // Proteção DoS: contar registros antes de gerar
        $total = $this->query->count($filters);
        if ($total > self::MAX_ROWS) {
            throw new RuntimeException(
                sprintf(
                    'Export abortado: %d registros excedem o limite de %d. Refine os filtros.',
                    $total,
                    self::MAX_ROWS
                )
            );
        }

        // Gera caminho do arquivo
        $uploadDir = $this->uploadDir();
        $userDir   = $uploadDir . '/' . $atorId;
        $this->ensureDir($userDir);

        $fileUuid = UuidGenerator::generate();
        $ext      = $format === 'json' ? 'json' : 'csv';
        $filename = $fileUuid . '.' . $ext;
        $filePath = $userDir . '/' . $filename;

        // Gera o arquivo em batches
        if ($format === 'json') {
            $this->writeJson($filePath, $filters, $total);
        } else {
            $this->writeCsv($filePath, $filters, $total);
        }

        // Gera URL signed via transient
        $signedToken = UuidGenerator::generate();
        $transientKey = 'pi_audit_export_' . md5($signedToken);

        if (function_exists('set_transient')) {
            \set_transient($transientKey, ['path' => $filePath, 'ator_id' => $atorId], self::SIGNED_URL_TTL);
        }

        // Audita o export
        $this->audit->log(
            'audit_log',
            null,
            'export_audit_log',
            null,
            [
                'formato'       => $format,
                'total_linhas'  => $total,
                'filtros'       => array_keys(array_filter($filters)),
            ],
            $atorId
        );

        // Retorna URL para download via endpoint AJAX
        $base = function_exists('admin_url') ? \admin_url('admin-ajax.php') : 'admin-ajax.php';
        return $base . '?' . http_build_query([
            'action' => 'pi_admin_audit_download',
            'token'  => $signedToken,
        ]);
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function writeJson(string $filePath, array $filters, int $total): void
    {
        $fp = fopen($filePath, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Falha ao criar arquivo de export JSON.');
        }

        fwrite($fp, '[');
        $written = 0;
        $page    = 1;

        while ($written < $total) {
            $rows = $this->query->list($filters, $page, self::BATCH_SIZE);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $safe = $this->whitelistRow($row);
                $json = function_exists('wp_json_encode')
                    ? \wp_json_encode($safe, JSON_UNESCAPED_UNICODE)
                    : json_encode($safe, JSON_UNESCAPED_UNICODE);
                if ($json === false || $json === null) {
                    continue;
                }
                if ($written > 0) {
                    fwrite($fp, ',');
                }
                fwrite($fp, "\n" . $json);
                $written++;
            }
            $page++;
        }

        fwrite($fp, "\n]");
        fclose($fp);
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function writeCsv(string $filePath, array $filters, int $total): void
    {
        $fp = fopen($filePath, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Falha ao criar arquivo de export CSV.');
        }

        // BOM UTF-8 para Excel
        fwrite($fp, "\xEF\xBB\xBF");

        // Cabeçalho — whitelist defensiva de colunas
        fputcsv($fp, self::EXPORT_COLUMNS, ',', '"', '\\');

        $written = 0;
        $page    = 1;

        while ($written < $total) {
            $rows = $this->query->list($filters, $page, self::BATCH_SIZE);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $safe = $this->whitelistRow($row);
                $csvRow = [];
                foreach (self::EXPORT_COLUMNS as $col) {
                    $csvRow[] = isset($safe[$col]) ? (string) $safe[$col] : '';
                }
                fputcsv($fp, $csvRow, ',', '"', '\\');
                $written++;
            }
            $page++;
        }

        fclose($fp);
    }

    /**
     * Aplica whitelist de colunas exportadas.
     * NUNCA exporta dados_antes/dados_depois crus.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function whitelistRow(array $row): array
    {
        $out = [];
        foreach (self::EXPORT_COLUMNS as $col) {
            $out[$col] = $row[$col] ?? null;
        }
        return $out;
    }

    private function uploadDir(): string
    {
        if (\defined('PI_PLUGIN_DIR')) {
            $base = dirname((string) \PI_PLUGIN_DIR, 3);
        } else {
            $base = defined('WP_CONTENT_DIR') ? (string) WP_CONTENT_DIR : dirname(__DIR__, 6);
        }
        return rtrim($base, '/\\') . '/uploads/participe-ibram-private/audit-exports';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar diretório: {$dir}");
        }

        // Garante que o diretório não seja navegável via web
        $htaccess = dirname($dir, 1) . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }
}
