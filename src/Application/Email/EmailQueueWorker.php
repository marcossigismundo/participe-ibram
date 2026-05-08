<?php
/**
 * Worker assíncrono que processa `wp_pi_email_queue` via WP-Cron.
 *
 * @package Ibram\ParticipeIbram\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Domain\Email\MensagemEnfileirada;
use Throwable;

/**
 * Worker WP-Cron de envio de e-mail.
 *
 * Pipeline por tick (a cada 5 minutos):
 *  1. Busca até `BATCH_SIZE` mensagens com status=pendente AND
 *     agendado_para <= NOW().
 *  2. Para cada uma, tenta `marcarEnviando` ATOMIC. Se falha (outro worker
 *     ganhou), pula. (R5 B-09)
 *  3. Envia via `wp_mail`. Em sucesso → `marcarEnviado`. Em falha → calcula
 *     próxima tentativa (backoff exponencial) ou marca falhou permanente
 *     após 5 tentativas.
 *  4. **NÃO chama sleep()** dentro do loop (R5 B-08). Distribuição de carga
 *     é feita via `agendado_para` (broadcast espalhado). O cron próximo pega
 *     o restante.
 *
 * Chamadas wp_mail são síncronas e podem ser custosas — mas dentro de um
 * único tick há limite duro de 50 envios; o resto fica para o próximo tick.
 */
final class EmailQueueWorker
{
    public const CRON_HOOK     = 'pi_email_queue_tick';
    public const CRON_SCHEDULE = 'pi_every_5_minutes';

    /** Mensagens processadas por tick. */
    private const BATCH_SIZE = 50;

    /** Máximo de tentativas (após o que vira falhou permanente). */
    private const MAX_TENTATIVAS = 5;

    /**
     * Backoff em segundos por tentativa (índice 0 = 1ª retry). Após esgotar,
     * fallback para o último valor.
     *
     * @var array<int,int>
     */
    private const BACKOFF_SECONDS = [
        60,        // 1 min
        5 * 60,    // 5 min
        30 * 60,   // 30 min
        2 * 3600,  // 2 h
        12 * 3600, // 12 h
    ];

    private EmailQueueRepository $fila;
    private SecureLogger $logger;

    /**
     * Sender override (testes). Default = wp_mail.
     *
     * Signature: `function(string $to, string $subject, string $message, array $headers): bool`.
     *
     * @var callable|null
     */
    private $sender;

    public function __construct(
        EmailQueueRepository $fila,
        SecureLogger $logger,
        ?callable $sender = null
    ) {
        $this->fila   = $fila;
        $this->logger = $logger;
        $this->sender = $sender;
    }

    /**
     * Registra schedule, hook do cron e custom interval.
     *
     * Chame em boot do plugin (init).
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_filter') || !function_exists('add_action')) {
            return;
        }

        add_filter('cron_schedules', [$this, 'addCronSchedule']);
        add_action(self::CRON_HOOK, [$this, 'tick']);

        if (function_exists('wp_next_scheduled') && !\wp_next_scheduled(self::CRON_HOOK)) {
            if (function_exists('wp_schedule_event')) {
                \wp_schedule_event(time() + 60, self::CRON_SCHEDULE, self::CRON_HOOK);
            }
        }
    }

    /**
     * Filtro `cron_schedules` adicionando intervalo custom de 5 minutos.
     *
     * @param array<string,array{interval:int,display:string}> $schedules
     *
     * @return array<string,array{interval:int,display:string}>
     */
    public function addCronSchedule(array $schedules): array
    {
        $schedules[self::CRON_SCHEDULE] = [
            'interval' => 5 * 60,
            'display'  => 'Participe Ibram - a cada 5 minutos',
        ];

        return $schedules;
    }

    /**
     * Executa um tick. Processa o batch e RETORNA. NÃO bloqueia (R5 B-08).
     */
    public function tick(): void
    {
        try {
            $now    = new DateTimeImmutable('now');
            $batch  = $this->fila->proximasParaEnvio(self::BATCH_SIZE, $now);
        } catch (Throwable $e) {
            $this->logger->error('email.worker.fetch_falhou', ['erro' => $e->getMessage()]);
            return;
        }

        if ($batch === []) {
            return;
        }

        foreach ($batch as $mensagem) {
            $id = (int) $mensagem->id();
            if ($id <= 0) {
                continue;
            }

            // Atomic: só prossegue quem ganhar o lock.
            $ganhou = false;
            try {
                $ganhou = $this->fila->marcarEnviando($id);
            } catch (Throwable $e) {
                $this->logger->warning('email.worker.lock_falhou', [
                    'queue_id' => $id,
                    'erro'     => $e->getMessage(),
                ]);
                continue;
            }
            if (!$ganhou) {
                continue;
            }

            $this->processarMensagem($mensagem);
        }
    }

    private function processarMensagem(MensagemEnfileirada $mensagem): void
    {
        $id          = (int) $mensagem->id();
        $tentativas  = $mensagem->tentativas() + 1; // foi incrementado pelo marcarEnviando
        $headers     = $this->buildHeaders();

        try {
            $ok = $this->send(
                $mensagem->destinatario(),
                $mensagem->assunto(),
                $mensagem->corpoHtml(),
                $headers
            );
        } catch (Throwable $e) {
            $ok = false;
            $erroMsg = self::sanitizeError($e->getMessage());
            $this->logger->warning('email.worker.exception', [
                'queue_id'   => $id,
                'evento'     => $mensagem->evento(),
                'tentativas' => $tentativas,
                'erro'       => $erroMsg,
            ]);
            $this->reagendarOuFalhar($id, $tentativas, $erroMsg);
            return;
        }

        if ($ok === true) {
            try {
                $this->fila->marcarEnviado($id, new DateTimeImmutable('now'));
            } catch (Throwable $e) {
                $this->logger->error('email.worker.persist_falhou', [
                    'queue_id' => $id,
                    'erro'     => $e->getMessage(),
                ]);
            }
            return;
        }

        $erroMsg = 'wp_mail_returned_false';
        $this->logger->warning('email.worker.envio_falhou', [
            'queue_id'   => $id,
            'evento'     => $mensagem->evento(),
            'tentativas' => $tentativas,
            'destinatario' => PiiMasker::maskEmail($mensagem->destinatario()),
        ]);
        $this->reagendarOuFalhar($id, $tentativas, $erroMsg);
    }

    private function reagendarOuFalhar(int $id, int $tentativas, string $erro): void
    {
        $podeRetentar = $tentativas < self::MAX_TENTATIVAS;
        if (!$podeRetentar) {
            try {
                $this->fila->marcarFalha($id, $erro, $tentativas, false, new DateTimeImmutable('now'));
            } catch (Throwable $e) {
                $this->logger->error('email.worker.falha_persist_falhou', [
                    'queue_id' => $id,
                    'erro'     => $e->getMessage(),
                ]);
            }
            return;
        }

        $proxima = $this->calcularProxima($tentativas);
        try {
            $this->fila->marcarFalha($id, $erro, $tentativas, true, $proxima);
        } catch (Throwable $e) {
            $this->logger->error('email.worker.retry_persist_falhou', [
                'queue_id' => $id,
                'erro'     => $e->getMessage(),
            ]);
        }
    }

    private function calcularProxima(int $tentativas): DateTimeImmutable
    {
        $idx = max(0, min(count(self::BACKOFF_SECONDS) - 1, $tentativas - 1));
        $sec = self::BACKOFF_SECONDS[$idx];

        return (new DateTimeImmutable('now'))->modify('+' . $sec . ' seconds');
    }

    /**
     * @param array<int,string> $headers
     */
    private function send(string $to, string $subject, string $html, array $headers): bool
    {
        if ($this->sender !== null) {
            $sender = $this->sender;
            return (bool) $sender($to, $subject, $html, $headers);
        }
        if (!function_exists('wp_mail')) {
            throw new \RuntimeException('wp_mail nao disponivel.');
        }

        return (bool) \wp_mail($to, $subject, $html, $headers);
    }

    /**
     * @return array<int,string>
     */
    private function buildHeaders(): array
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if (function_exists('get_option')) {
            $fromEmail = (string) \get_option('pi_smtp_from_email', '');
            $fromName  = (string) \get_option('pi_smtp_from_name', 'Participe Ibram');
            if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $headers[] = sprintf('From: %s <%s>', $fromName, $fromEmail);
            }
        }
        $headers[] = 'X-Auto-Response-Suppress: All';
        $headers[] = 'Auto-Submitted: auto-generated';

        return $headers;
    }

    private static function sanitizeError(string $msg): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $msg) ?? '';
        $clean = trim((string) $clean);
        if (strlen($clean) > 500) {
            $clean = substr($clean, 0, 500);
        }

        return $clean;
    }
}
