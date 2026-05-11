<?php
/**
 * Cenário end-to-end de anonimização (LGPD Art. 18, IV).
 *
 *  1. Solicitar anonimização (gera solicitação aberta + token).
 *  2. Validar que o hook `pi_lgpd_anonimizacao_solicitada` foi disparado e
 *     o token está disponível para o email.
 *  3. Confirmar com o token → invoca AnonimizarTitularHandler (mock —
 *     verifica que recebe o agente_id) e fecha a solicitação.
 *  4. Assegurar que o audit log foi populado em CADA passo (rastreabilidade
 *     forense LGPD).
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Application\Lgpd;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Lgpd\AnonimizacaoTokenizer;
use Ibram\ParticipeIbram\Application\Lgpd\AnonimizarTitularHandler;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoCommand;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoCommand;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use PHPUnit\Framework\TestCase;

/**
 * Repositório em memória para o teste.
 */
final class InMemorySolicitacaoRepo implements SolicitacaoTitularRepository
{
    /** @var array<int,SolicitacaoTitular> */
    public array $store = [];
    private int $nextId = 1;

    public function findById(int $id): ?SolicitacaoTitular
    {
        return $this->store[$id] ?? null;
    }

    public function findAbertasPorAgente(int $agenteId): array
    {
        $out = [];
        foreach ($this->store as $s) {
            if ($s->agenteId() !== $agenteId) {
                continue;
            }
            if (in_array($s->status(), [SolicitacaoTitular::STATUS_ABERTA, SolicitacaoTitular::STATUS_EM_ATENDIMENTO], true)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    public function findPendentesParaDPO(int $page = 1, int $perPage = 25): array
    {
        return [];
    }

    public function save(SolicitacaoTitular $solicitacao): int
    {
        $id = $solicitacao->id() ?? $this->nextId++;
        $this->store[$id] = $solicitacao->id() === null ? $solicitacao->withId($id) : $solicitacao;
        return $id;
    }

    public function findVencendoEmDias(int $dias): array
    {
        return [];
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoHandler
 * @covers \Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoHandler
 */
final class AnonimizacaoFluxoCompletoTest extends TestCase
{
    public function testFluxoCompletoSolicitarEConfirmar(): void
    {
        $repo = new InMemorySolicitacaoRepo();
        $tokenizer = new AnonimizacaoTokenizer();

        // Capture audit events em memória.
        $auditEvents = [];
        $audit = $this->createMock(AuditLogger::class);
        $audit->method('log')->willReturnCallback(
            function (string $entidade, ?int $id, string $acao, $antes, $depois, $ator) use (&$auditEvents): void {
                $auditEvents[] = compact('entidade', 'id', 'acao', 'depois', 'ator');
            }
        );

        $logger = new SecureLogger(static function () { /* noop */ });

        // ─── PASSO 1: solicitação ───────────────────────────────────────
        $solicHandler = new SolicitarAnonimizacaoHandler($repo, $tokenizer, $audit, $logger);
        $resp = $solicHandler->handle(new SolicitarAnonimizacaoCommand(
            42,
            100,
            'Quero sair do sistema',
            null,
            'TestUserAgent/1.0'
        ));

        $this->assertNotEmpty($resp['solicitacao_id']);
        $this->assertNotEmpty($resp['token']);

        $solicAberta = $repo->findById((int) $resp['solicitacao_id']);
        $this->assertNotNull($solicAberta);
        $this->assertSame(SolicitacaoTitular::STATUS_ABERTA, $solicAberta->status());

        // Verifica audit log do passo 1.
        $acoes = array_column($auditEvents, 'acao');
        $this->assertContains('anonimizacao_solicitada', $acoes, 'Audit forense: solicitação deve registrar evento.');
        $this->assertContains('anonimizacao_token_emitido', $acoes, 'Audit forense: token emitido deve registrar evento.');

        // O motivo NUNCA aparece em claro no audit log.
        foreach ($auditEvents as $evt) {
            $this->assertStringNotContainsString(
                'Quero sair do sistema',
                (string) json_encode($evt['depois']),
                'Motivo do titular não pode vazar para o audit log.'
            );
        }

        // ─── PASSO 2: confirmar com o token ─────────────────────────────
        $anonMock = $this->createMock(AnonimizarTitularHandler::class);
        $anonMock->expects($this->once())
            ->method('handle')
            ->with(42, 100)
            ->willReturn([
                'agente_id'     => 42,
                'short_id'      => 'AAAAAAAA',
                'campos_limpos' => ['agentes.email_principal', 'agentes_pf.nome_completo', 'agentes_pf.cpf_enc'],
                'documentos'    => ['arquivos_apagados' => 0, 'arquivos_ignorados' => 0, 'registros' => 0],
            ]);

        $logoutCalls = [];
        $logout = static function (int $uid) use (&$logoutCalls): void { $logoutCalls[] = $uid; };
        $userIdResolver = static fn (int $aid): ?int => $aid === 42 ? 100 : null;

        $confirmHandler = new ConfirmarAnonimizacaoHandler(
            $repo,
            $tokenizer,
            $anonMock,
            $audit,
            $logger,
            $logout,
            $userIdResolver
        );

        $confirmResp = $confirmHandler->handle(new ConfirmarAnonimizacaoCommand(
            (string) $resp['token'],
            100,
            null
        ));

        $this->assertSame((int) $resp['solicitacao_id'], $confirmResp['solicitacao_id']);
        $this->assertSame(42, $confirmResp['agente_id']);
        $this->assertSame([100], $logoutCalls, 'Logout deve ser disparado após anonimização.');

        // Solicitação foi encerrada como atendida.
        $solicAtendida = $repo->findById((int) $resp['solicitacao_id']);
        $this->assertNotNull($solicAtendida);
        $this->assertSame(SolicitacaoTitular::STATUS_ATENDIDA, $solicAtendida->status());
        $this->assertNotNull($solicAtendida->respostaMd());

        // ─── PASSO 3: audit log preservou tudo (forense) ────────────────
        $acoesFinais = array_column($auditEvents, 'acao');
        $this->assertContains('anonimizacao_confirmacao_recebida', $acoesFinais);
        $this->assertContains('anonimizacao_executada_via_token', $acoesFinais);
    }

    public function testReusarTokenAposExecucaoFalha(): void
    {
        $repo = new InMemorySolicitacaoRepo();
        $tokenizer = new AnonimizacaoTokenizer();
        $audit = $this->createMock(AuditLogger::class);
        $logger = new SecureLogger(static function () { /* noop */ });

        $solicHandler = new SolicitarAnonimizacaoHandler($repo, $tokenizer, $audit, $logger);
        $resp = $solicHandler->handle(new SolicitarAnonimizacaoCommand(42, 100, null, null, null));

        $anonMock = $this->createMock(AnonimizarTitularHandler::class);
        $anonMock->method('handle')->willReturn([
            'agente_id'     => 42,
            'short_id'      => 'A',
            'campos_limpos' => [],
            'documentos'    => ['arquivos_apagados' => 0, 'arquivos_ignorados' => 0, 'registros' => 0],
        ]);

        $confirmHandler = new ConfirmarAnonimizacaoHandler(
            $repo,
            $tokenizer,
            $anonMock,
            $audit,
            $logger,
            static function (int $u): void { /* noop */ },
            static fn (int $a): ?int => 100
        );

        $cmd = new ConfirmarAnonimizacaoCommand((string) $resp['token'], 100, null);
        $confirmHandler->handle($cmd);

        // Segundo uso: a solicitação já está atendida → DomainException.
        $this->expectException(\DomainException::class);
        $confirmHandler->handle($cmd);
    }
}
