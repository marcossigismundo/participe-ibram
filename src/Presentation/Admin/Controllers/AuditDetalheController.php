<?php
/**
 * AuditDetalheController — exibe detalhes de um registro de audit log.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Presentation\Admin\AuditMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;

/**
 * Exibe o registro completo do audit log com dados_antes/dados_depois mascarados.
 *
 * Capability: pi_visualizar_audit_log.
 *
 * Mascaramento PII aplicado sobre payloads:
 *  - email    → PiiMasker::maskEmail
 *  - cpf      → PiiMasker::maskCpf
 *  - cnpj     → PiiMasker::maskCnpj
 *  - telefone → PiiMasker::maskPhone
 *  - Outras chaves passam direto.
 *
 * Audita o próprio acesso via AuditLogger (auditando a auditoria).
 */
final class AuditDetalheController
{
    public const CAP = 'pi_visualizar_audit_log';

    private AuditLogQuery $query;
    private AuditLogger $audit;

    public function __construct(AuditLogQuery $query, AuditLogger $audit)
    {
        $this->query = $query;
        $this->audit = $audit;
    }

    public function render(): void
    {
        if (!self::userCan(self::CAP)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $id = (int) RequestHelper::get('id', 'absint', 0);
        if ($id <= 0) {
            self::wpDie(self::tr('Registro inválido.'));
            return;
        }

        $record = $this->query->findById($id);
        if ($record === null) {
            self::wpDie(self::tr('Registro não encontrado.'));
            return;
        }

        // Audita o acesso ao detalhe (auditando a auditoria)
        $currentUserId = self::currentUserId();
        $this->audit->log(
            'audit_log',
            $id,
            'view_audit_detail',
            null,
            ['audit_record_id' => $id],
            $currentUserId > 0 ? $currentUserId : null
        );

        // Mascara PII nos payloads antes de exibir
        $dadosAntesMasked  = $this->maskPayload($record['dados_antes'] ?? null);
        $dadosDepoisMasked = $this->maskPayload($record['dados_depois'] ?? null);

        $backUrl  = function_exists('admin_url')
            ? \admin_url('admin.php?page=' . AuditMenuRegistry::SLUG_LOG)
            : 'admin.php?page=' . AuditMenuRegistry::SLUG_LOG;

        $template = self::templatePath('audit/detalhe.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }

        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    /**
     * Decodifica JSON e aplica mascaramento PII recursivamente.
     *
     * @param string|null $json
     * @return array<string,mixed>|null
     */
    public function maskPayload(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        return $this->maskArray($decoded);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function maskArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->maskArray($value);
                continue;
            }
            if (!is_string($value)) {
                $out[$key] = $value;
                continue;
            }
            $out[$key] = $this->maskField((string) $key, $value);
        }
        return $out;
    }

    private function maskField(string $key, string $value): string
    {
        $keyLower = strtolower($key);
        switch ($keyLower) {
            case 'email':
            case 'email_principal':
                return PiiMasker::maskEmail($value);
            case 'cpf':
            case 'cpf_enc':
            case 'cpf_hash':
                return PiiMasker::maskCpf($value);
            case 'cnpj':
            case 'cnpj_enc':
                return PiiMasker::maskCnpj($value);
            case 'telefone':
            case 'phone':
                return PiiMasker::maskPhone($value);
            default:
                return $value;
        }
    }

    private static function currentUserId(): int
    {
        return function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
    }

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && (bool) \current_user_can($cap);
    }

    private static function wpDie(string $message): void
    {
        if (function_exists('wp_die')) {
            \wp_die(self::escHtml($message));
        } else {
            echo $message;
            exit;
        }
    }

    private static function templatePath(string $relative): ?string
    {
        $base = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
