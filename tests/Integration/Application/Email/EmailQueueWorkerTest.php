<?php
/**
 * Testes "integração" (sem MySQL real) do {@see EmailQueueWorker}.
 *
 * Usa um repositório em memória (in-memory fake) que implementa
 * {@see EmailQueueRepository}, exercitando atomicidade do marcarEnviando,
 * backoff e marcação de falha permanente.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Application\Email;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Email\EmailQueueWorker;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Domain\Email\MensagemEnfileirada;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Email\EmailQueueWorker
 */
final class EmailQueueWorkerTest extends TestCase
{
    public function test_marcar_enviando_eh_atomico_entre_dois_workers(): void
    {
        $repo = new InMemoryEmailQueueRepo();
        $msg  = MensagemEnfileirada::paraEnfileirar(
            'cadastro_submetido',
            10,
            'a@example.com',
            'X',
            '<p>x</p>',
            null,
            new DateTimeImmutable('-1 minute'),
            new DateTimeImmutable('-1 minute')
        );
        $id = $repo->enfileirar($msg);

        $first  = $repo->marcarEnviando($id);
        $second = $repo->marcarEnviando($id);
        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function test_envio_sucesso_marca_enviado(): void
    {
        $repo = new InMemoryEmailQueueRepo();
        $msg  = MensagemEnfileirada::paraEnfileirar(
            'cadastro_submetido',
            10,
            'b@example.com',
            'X',
            '<p>x</p>',
            null,
            new DateTimeImmutable('-1 minute'),
            new DateTimeImmutable('-1 minute')
        );
        $id = $repo->enfileirar($msg);

        $sent = [];
        $sender = static function (string $to, string $subject, string $html, array $headers) use (&$sent): bool {
            $sent[] = compact('to', 'subject');
            return true;
        };

        $worker = new EmailQueueWorker($repo, new SecureLogger(static function (): void {}), $sender);
        $worker->tick();

        $msgPersisted = $repo->findById($id);
        $this->assertNotNull($msgPersisted);
        $this->assertSame(MensagemEnfileirada::STATUS_ENVIADO, $msgPersisted->status());
        $this->assertCount(1, $sent);
    }

    public function test_falha_temporaria_aplica_backoff(): void
    {
        $repo = new InMemoryEmailQueueRepo();
        $msg  = MensagemEnfileirada::paraEnfileirar(
            'cadastro_submetido',
            10,
            'c@example.com',
            'X',
            '<p>x</p>',
            null,
            new DateTimeImmutable('-1 minute'),
            new DateTimeImmutable('-1 minute')
        );
        $id = $repo->enfileirar($msg);

        // Sender retorna false sempre.
        $sender = static fn () => false;
        $worker = new EmailQueueWorker($repo, new SecureLogger(static function (): void {}), $sender);
        $worker->tick();

        $msgPersisted = $repo->findById($id);
        $this->assertNotNull($msgPersisted);
        // Após 1 falha, mensagem volta a "pendente" com agendado_para futuro.
        $this->assertSame(MensagemEnfileirada::STATUS_PENDENTE, $msgPersisted->status());
        $this->assertGreaterThan(
            (new DateTimeImmutable('now'))->getTimestamp(),
            $msgPersisted->agendadoPara()->getTimestamp()
        );
        $this->assertSame(1, $msgPersisted->tentativas());
    }

    public function test_apos_5_falhas_marca_falhou_permanente(): void
    {
        $repo = new InMemoryEmailQueueRepo();
        $msg  = MensagemEnfileirada::paraEnfileirar(
            'cadastro_submetido',
            10,
            'd@example.com',
            'X',
            '<p>x</p>',
            null,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('-1 hour')
        );
        $id = $repo->enfileirar($msg);

        $sender = static fn () => false;
        $worker = new EmailQueueWorker($repo, new SecureLogger(static function (): void {}), $sender);

        for ($i = 0; $i < 5; $i++) {
            // Cada tick precisa que agendado_para já esteja vencido.
            $repo->forcarAgendamentoNoPassado($id);
            $worker->tick();
        }

        $final = $repo->findById($id);
        $this->assertNotNull($final);
        $this->assertSame(MensagemEnfileirada::STATUS_FALHOU, $final->status());
        $this->assertSame(5, $final->tentativas());
    }
}

/**
 * Implementação in-memory para uso só nos testes desta suíte.
 */
final class InMemoryEmailQueueRepo implements EmailQueueRepository
{
    /** @var array<int, array<string,mixed>> */
    private array $rows = [];
    private int $nextId = 1;

    public function enfileirar(MensagemEnfileirada $mensagem): int
    {
        $id              = $this->nextId++;
        $this->rows[$id] = [
            'id'            => $id,
            'evento'        => $mensagem->evento(),
            'agente_id'     => $mensagem->agenteId(),
            'destinatario'  => $mensagem->destinatario(),
            'assunto'       => $mensagem->assunto(),
            'corpo_html'    => $mensagem->corpoHtml(),
            'payload_json'  => $mensagem->payloadJson(),
            'tentativas'    => $mensagem->tentativas(),
            'status'        => $mensagem->status(),
            'ultimo_erro'   => $mensagem->ultimoErro(),
            'agendado_para' => $mensagem->agendadoPara(),
            'enviado_em'    => $mensagem->enviadoEm(),
            'created_at'    => $mensagem->createdAt(),
        ];

        return $id;
    }

    public function proximasParaEnvio(int $limit, ?DateTimeImmutable $agora = null): array
    {
        $now = $agora ?? new DateTimeImmutable('now');
        $out = [];
        foreach ($this->rows as $row) {
            if ($row['status'] !== MensagemEnfileirada::STATUS_PENDENTE) {
                continue;
            }
            /** @var DateTimeImmutable $when */
            $when = $row['agendado_para'];
            if ($when > $now) {
                continue;
            }
            $out[] = self::hydrate($row);
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public function marcarEnviando(int $id): bool
    {
        if (!isset($this->rows[$id])) {
            return false;
        }
        if ($this->rows[$id]['status'] !== MensagemEnfileirada::STATUS_PENDENTE) {
            return false;
        }
        $this->rows[$id]['status']     = MensagemEnfileirada::STATUS_ENVIANDO;
        $this->rows[$id]['tentativas'] = (int) $this->rows[$id]['tentativas'] + 1;

        return true;
    }

    public function marcarEnviado(int $id, DateTimeImmutable $enviadoEm): void
    {
        if (!isset($this->rows[$id])) {
            return;
        }
        $this->rows[$id]['status']      = MensagemEnfileirada::STATUS_ENVIADO;
        $this->rows[$id]['enviado_em']  = $enviadoEm;
        $this->rows[$id]['ultimo_erro'] = null;
    }

    public function marcarFalha(int $id, string $erro, int $tentativasAtuais, bool $retry, DateTimeImmutable $proxima): void
    {
        if (!isset($this->rows[$id])) {
            return;
        }
        if ($retry) {
            $this->rows[$id]['status']        = MensagemEnfileirada::STATUS_PENDENTE;
            $this->rows[$id]['ultimo_erro']   = $erro;
            $this->rows[$id]['agendado_para'] = $proxima;
            return;
        }
        $this->rows[$id]['status']      = MensagemEnfileirada::STATUS_FALHOU;
        $this->rows[$id]['ultimo_erro'] = $erro;
    }

    public function listar(array $filtros, int $page = 1, int $perPage = 25): array
    {
        $items = array_map([self::class, 'hydrate'], array_values($this->rows));
        return [
            'items'    => $items,
            'total'    => count($items),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    public function findById(int $id): ?MensagemEnfileirada
    {
        return isset($this->rows[$id]) ? self::hydrate($this->rows[$id]) : null;
    }

    public function reenviar(int $id, DateTimeImmutable $agendadoPara): bool
    {
        if (!isset($this->rows[$id])) {
            return false;
        }
        $this->rows[$id]['status']        = MensagemEnfileirada::STATUS_PENDENTE;
        $this->rows[$id]['ultimo_erro']   = null;
        $this->rows[$id]['agendado_para'] = $agendadoPara;
        return true;
    }

    /** Helper de teste: força agendamento para o passado. */
    public function forcarAgendamentoNoPassado(int $id): void
    {
        if (isset($this->rows[$id])) {
            $this->rows[$id]['agendado_para'] = new DateTimeImmutable('-1 hour');
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): MensagemEnfileirada
    {
        return MensagemEnfileirada::fromState(
            (int) $row['id'],
            (string) $row['evento'],
            $row['agente_id'] !== null ? (int) $row['agente_id'] : null,
            (string) $row['destinatario'],
            (string) $row['assunto'],
            (string) $row['corpo_html'],
            $row['payload_json'],
            (int) $row['tentativas'],
            (string) $row['status'],
            $row['ultimo_erro'] !== null ? (string) $row['ultimo_erro'] : null,
            $row['agendado_para'],
            $row['enviado_em'],
            $row['created_at']
        );
    }
}
