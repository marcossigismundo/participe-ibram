<?php
/**
 * AJAX handlers admin para o módulo de E-mail (test SMTP, resend, save config).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Email\SmtpConfig;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Throwable;

/**
 * Endpoints AJAX administrativos do módulo de E-mail.
 *
 *  - `pi_admin_email_save_config` — salva config SMTP (password cifrada).
 *  - `pi_admin_email_test_smtp`   — envia e-mail de teste para o admin atual.
 *  - `pi_admin_email_resend`      — reenfileira uma mensagem (status -> pendente).
 *
 * Pipeline padrão: nonce + capability `pi_administrar_email` + rate limit
 * + audit em todas as ações.
 */
final class EmailAdminAjax
{
    public const CAP = 'pi_administrar_email';

    private SmtpConfig $smtp;
    private EmailQueueRepository $fila;
    private AuditLogger $audit;
    private SecureLogger $logger;

    public function __construct(
        SmtpConfig $smtp,
        EmailQueueRepository $fila,
        AuditLogger $audit,
        SecureLogger $logger
    ) {
        $this->smtp   = $smtp;
        $this->fila   = $fila;
        $this->audit  = $audit;
        $this->logger = $logger;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_email_save_config', [$this, 'saveConfig']);
        \add_action('wp_ajax_pi_admin_email_test_smtp', [$this, 'testSmtp']);
        \add_action('wp_ajax_pi_admin_email_resend', [$this, 'resend']);
    }

    public function saveConfig(): void
    {
        try {
            $this->guard('pi_admin_email_save_config');

            $values = [
                'host'       => RequestHelper::post('host', 'sanitize_text_field', ''),
                'port'       => RequestHelper::post('port', 'absint', 587),
                'encryption' => RequestHelper::post('encryption', 'sanitize_key', ''),
                'user'       => RequestHelper::post('user', 'sanitize_text_field', ''),
                'from_email' => RequestHelper::post('from_email', 'sanitize_email', ''),
                'from_name'  => RequestHelper::post('from_name', 'sanitize_text_field', ''),
            ];
            $password = (string) RequestHelper::post('password', null, '');
            if ($password !== '') {
                $values['password'] = $password;
            }

            $this->smtp->save($values);

            // Audit (sem persistir password — Audit já redacta `password` por chave).
            $this->audit->log('email_smtp_config', null, 'salvar', null, [
                'host'       => $values['host'],
                'port'       => $values['port'],
                'encryption' => $values['encryption'],
                'user'       => $values['user'],
                'from_email' => $values['from_email'],
                'from_name'  => $values['from_name'],
                'password'   => $password === '' ? '[unchanged]' : '[REDACTED]',
            ]);

            $this->ok(['snapshot' => $this->smtp->snapshotPublic()]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function testSmtp(): void
    {
        try {
            $this->guard('pi_admin_email_test_smtp');

            $userId = (int) (function_exists('get_current_user_id') ? \get_current_user_id() : 0);
            $email  = '';
            if ($userId > 0 && function_exists('get_userdata')) {
                $user = \get_userdata($userId);
                if ($user && isset($user->user_email)) {
                    $email = (string) $user->user_email;
                }
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error(400, 'pi_invalid_email', 'E-mail do administrador invalido ou ausente.');
                return;
            }

            $ok = function_exists('wp_mail')
                ? \wp_mail(
                    $email,
                    'Participe Ibram - teste SMTP',
                    'Este e um e-mail de teste enviado pela aba de Configuracao SMTP.',
                    ['Content-Type: text/plain; charset=UTF-8']
                )
                : false;

            $this->audit->log('email_smtp_test', null, $ok ? 'sucesso' : 'falha', null, [
                'destinatario' => $email,
            ]);

            if ($ok) {
                $this->ok(['enviado' => true]);
            } else {
                $this->error(500, 'pi_smtp_test_falhou', 'Envio retornou false. Confira o log.');
            }
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function resend(): void
    {
        try {
            $this->guard('pi_admin_email_resend');

            $id = (int) RequestHelper::post('queue_id', 'absint', 0);
            if ($id <= 0) {
                $this->error(400, 'pi_validation', 'queue_id invalido.');
                return;
            }
            $existing = $this->fila->findById($id);
            if ($existing === null) {
                $this->error(404, 'pi_not_found', 'Mensagem nao encontrada.');
                return;
            }

            $ok = $this->fila->reenviar($id, new DateTimeImmutable('now'));

            $this->audit->log('email_queue', $id, 'reenviar', [
                'status_anterior' => $existing->status(),
            ], [
                'status' => 'pendente',
                'agendado_para' => 'now',
            ]);

            if ($ok) {
                $this->ok(['queue_id' => $id]);
            } else {
                $this->error(500, 'pi_resend_falhou', 'Falha ao reenfileirar.');
            }
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    private function guard(string $action): void
    {
        if (!function_exists('get_current_user_id')) {
            $this->error(401, 'pi_unauthorized', 'Auth indisponivel.');
            exit;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            $this->error(401, 'pi_unauthorized', 'Auth requerida.');
            exit;
        }
        $nonce = (string) RequestHelper::request('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $action)) {
            $this->error(403, 'pi_invalid_nonce', 'Nonce invalido.');
            exit;
        }
        if (!function_exists('current_user_can') || !\current_user_can(self::CAP)) {
            $this->error(403, 'pi_forbidden', 'Permissao negada.');
            exit;
        }

        $key = RateLimiter::keyForUser('admin_email_' . $action, $userId);
        if (!RateLimiter::check($key, 30, 60)) {
            $this->error(429, 'pi_rate_limited', 'Muitas requisicoes.');
            exit;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function ok(array $data): void
    {
        if (function_exists('wp_send_json_success')) {
            \wp_send_json_success($data);
            return;
        }
        $this->emit(['success' => true, 'data' => $data], 200);
    }

    private function error(int $status, string $code, string $message): void
    {
        if (function_exists('wp_send_json_error')) {
            \wp_send_json_error(['code' => $code, 'message' => $message], $status);
            return;
        }
        $this->emit(['success' => false, 'data' => ['code' => $code, 'message' => $message]], $status);
    }

    private function fail(Throwable $e): void
    {
        $this->logger->error('email.admin.exception', ['erro' => $e->getMessage()]);
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        $this->error(500, 'pi_internal', $debug ? $e->getMessage() : 'Erro interno.');
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function emit(array $payload, int $status): void
    {
        if (!headers_sent()) {
            header('HTTP/1.1 ' . $status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
