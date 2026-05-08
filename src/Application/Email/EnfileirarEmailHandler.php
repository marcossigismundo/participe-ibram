<?php
/**
 * Handler que enfileira (single ou broadcast) um e-mail em `wp_pi_email_queue`.
 *
 * @package Ibram\ParticipeIbram\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Email\Templates\EmailRenderer;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Domain\Email\EventoEmail;
use Ibram\ParticipeIbram\Domain\Email\MensagemEnfileirada;
use Throwable;

/**
 * Renderiza o template e cria a linha de fila.
 *
 * Single-target: cria 1 linha imediata (`agendado_para = now`).
 *
 * Broadcast: pagina via {@see AgenteBroadcastQuery::iterar}, criando 1 linha por
 * destinatário com `agendado_para` espaçado em segundos para distribuir carga
 * (evita avalanche de wp_mail no primeiro tick do worker).
 */
final class EnfileirarEmailHandler
{
    private EmailQueueRepository $fila;
    private EmailRenderer $renderer;
    private SecureLogger $logger;
    private ?AgenteBroadcastQuery $broadcastQuery;

    /**
     * Distância em segundos entre envios broadcast consecutivos.
     */
    private const BROADCAST_SPACING_SECONDS = 1;

    public function __construct(
        EmailQueueRepository $fila,
        EmailRenderer $renderer,
        SecureLogger $logger,
        ?AgenteBroadcastQuery $broadcastQuery = null
    ) {
        $this->fila            = $fila;
        $this->renderer        = $renderer;
        $this->logger          = $logger;
        $this->broadcastQuery  = $broadcastQuery;
    }

    /**
     * Enfileira UM e-mail.
     */
    public function handle(EnfileirarEmailCommand $command): int
    {
        $evento     = EventoEmail::fromString($command->evento());
        $rendered   = $this->renderer->render($evento->template(), $command->vars());

        $now           = new DateTimeImmutable('now');
        $agendadoPara  = $command->agendadoPara() ?? $now;

        $mensagem = MensagemEnfileirada::paraEnfileirar(
            $evento->value(),
            $command->agenteId(),
            $command->destinatario(),
            $rendered['assunto'],
            $rendered['html'],
            self::buildPayload($command, $rendered),
            $agendadoPara,
            $now
        );

        $id = $this->fila->enfileirar($mensagem);

        $this->logger->info('email.enfileirado', [
            'evento'       => $evento->value(),
            'destinatario' => PiiMasker::maskEmail($command->destinatario()),
            'agente_id'    => $command->agenteId(),
            'queue_id'     => $id,
        ]);

        return $id;
    }

    /**
     * Enfileira BROADCAST: itera destinatários e cria 1 linha por um.
     *
     * @param array<string,mixed>   $vars         Vars genéricas (sem PII).
     * @param DateTimeImmutable|null $inicio      Quando null = NOW.
     *
     * @return int Quantidade de mensagens enfileiradas.
     */
    public function broadcast(string $evento, array $vars, ?DateTimeImmutable $inicio = null): int
    {
        if ($this->broadcastQuery === null) {
            $this->logger->warning('email.broadcast.sem_query', ['evento' => $evento]);
            return 0;
        }

        $eventoVo = EventoEmail::fromString($evento);
        // Garante que o renderer aceita as vars sem PII; falha cedo se template
        // estiver quebrado.
        $rendered = $this->renderer->render($eventoVo->template(), $vars);

        $now      = new DateTimeImmutable('now');
        $start    = $inicio ?? $now;
        $offsetSec = 0;
        $count    = 0;

        foreach ($this->broadcastQuery->iterar(100) as $row) {
            $email     = isset($row['email']) ? (string) $row['email'] : '';
            $agenteId  = isset($row['agente_id']) ? (int) $row['agente_id'] : 0;
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $agenteId < 1) {
                continue;
            }

            try {
                $when = $start->modify('+' . $offsetSec . ' seconds');
                $msg  = MensagemEnfileirada::paraEnfileirar(
                    $eventoVo->value(),
                    $agenteId,
                    $email,
                    $rendered['assunto'],
                    $rendered['html'],
                    [
                        'broadcast' => true,
                        'evento'    => $eventoVo->value(),
                    ],
                    $when,
                    $now
                );
                $this->fila->enfileirar($msg);
                $count++;

                if (($count % 50) === 0) {
                    // Espalha mais a cada 50 envios.
                    $offsetSec += self::BROADCAST_SPACING_SECONDS;
                }
            } catch (Throwable $e) {
                $this->logger->warning('email.broadcast.falha_item', [
                    'evento' => $eventoVo->value(),
                    'erro'   => $e->getMessage(),
                ]);
                continue;
            }
        }

        $this->logger->info('email.broadcast.concluido', [
            'evento' => $eventoVo->value(),
            'total'  => $count,
        ]);

        return $count;
    }

    /**
     * Payload mínimo gravado em payload_json (sem PII).
     *
     * @param array{assunto:string,html:string,text:string} $rendered
     *
     * @return array<string,mixed>
     */
    private static function buildPayload(EnfileirarEmailCommand $command, array $rendered): array
    {
        // NUNCA inclui CPF/RG/dados sensíveis. Apenas vars usadas na renderização
        // já passadas sem PII pelos handlers.
        $payload = [
            'evento'       => $command->evento(),
            'agente_id'    => $command->agenteId(),
            'text_preview' => mb_substr($rendered['text'], 0, 200),
        ];

        return $payload;
    }
}
