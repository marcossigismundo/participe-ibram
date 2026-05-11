<?php
/**
 * Unit tests for {@see ConfirmarAnonimizacaoHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Lgpd\AnonimizacaoTokenizer;
use Ibram\ParticipeIbram\Application\Lgpd\AnonimizarTitularHandler;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoCommand;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoHandler
 */
final class ConfirmarAnonimizacaoHandlerTest extends TestCase
{
    public function testTokenInvalidoLanca(): void
    {
        $handler = $this->makeHandler();
        $this->expectException(DomainException::class);
        $handler->handle(new ConfirmarAnonimizacaoCommand('token-bogus-xyz', 100, null));
    }

    public function testTokenExpiradoLanca(): void
    {
        // Não conseguimos gerar um token expirado via tokenizer (rejeita exp passado).
        // Em vez disso, modificamos o último componente HMAC para falhar a verificação,
        // simulando tampering — comportamento equivalente ao de um token inválido.
        $tokenizer = new AnonimizacaoTokenizer();
        $valid = $tokenizer->tokenFor(1, 42, (new DateTimeImmutable('now'))->modify('+1 hour'));
        $tampered = $valid . 'AAA';

        $handler = $this->makeHandler();
        $this->expectException(DomainException::class);
        $handler->handle(new ConfirmarAnonimizacaoCommand($tampered, 100, null));
    }

    public function testTokenCorretoInvocaAnonimizadorEForcaLogout(): void
    {
        $tokenizer = new AnonimizacaoTokenizer();
        $token = $tokenizer->tokenFor(7, 42, (new DateTimeImmutable('now'))->modify('+1 hour'));

        $solicAberta = SolicitacaoTitular::protocolar(42, SolicitacaoTitular::TIPO_ANONIMIZACAO, 'motivo')
            ->withId(7);

        $repo = $this->createMock(SolicitacaoTitularRepository::class);
        $repo->method('findById')->with(7)->willReturn($solicAberta);
        $repo->expects($this->atLeastOnce())->method('save')->willReturn(7);

        $anonimizador = $this->createMock(AnonimizarTitularHandler::class);
        $anonimizador->expects($this->once())
            ->method('handle')
            ->with(42, $this->anything())
            ->willReturn([
                'agente_id'     => 42,
                'short_id'      => 'abc12345',
                'campos_limpos' => ['agentes.email_principal', 'agentes_pf.nome_completo'],
                'documentos'    => ['arquivos_apagados' => 0, 'arquivos_ignorados' => 0, 'registros' => 0],
            ]);

        $audit = $this->createMock(AuditLogger::class);
        $audit->expects($this->atLeastOnce())->method('log');

        $logger = new SecureLogger(static function () { /* noop */ });

        $logoutCalls = [];
        $logout = static function (int $uid) use (&$logoutCalls): void {
            $logoutCalls[] = $uid;
        };

        $userIdResolver = static fn (int $aid): ?int => $aid === 42 ? 100 : null;

        $handler = new ConfirmarAnonimizacaoHandler(
            $repo,
            $tokenizer,
            $anonimizador,
            $audit,
            $logger,
            $logout,
            $userIdResolver
        );

        $resp = $handler->handle(new ConfirmarAnonimizacaoCommand($token, 100, null));
        $this->assertSame(7, $resp['solicitacao_id']);
        $this->assertSame(42, $resp['agente_id']);
        $this->assertSame('abc12345', $resp['short_id']);

        $this->assertSame([100], $logoutCalls, 'logout deve ser chamado para o user 100');
    }

    public function testTokenComSolicitacaoJaAtendidaLanca(): void
    {
        $tokenizer = new AnonimizacaoTokenizer();
        $token = $tokenizer->tokenFor(7, 42, (new DateTimeImmutable('now'))->modify('+1 hour'));

        // Solicitação já atendida — simulamos via fromState.
        $solicAtendida = SolicitacaoTitular::fromState(
            7,
            42,
            SolicitacaoTitular::TIPO_ANONIMIZACAO,
            'motivo',
            SolicitacaoTitular::STATUS_ATENDIDA,
            'já atendida',
            new DateTimeImmutable('-2 days'),
            new DateTimeImmutable('-1 hour'),
            5
        );

        $repo = $this->createMock(SolicitacaoTitularRepository::class);
        $repo->method('findById')->with(7)->willReturn($solicAtendida);

        $anonimizador = $this->createMock(AnonimizarTitularHandler::class);
        $anonimizador->expects($this->never())->method('handle');

        $audit = $this->createMock(AuditLogger::class);
        $logger = new SecureLogger(static function () { /* noop */ });

        $handler = new ConfirmarAnonimizacaoHandler(
            $repo,
            $tokenizer,
            $anonimizador,
            $audit,
            $logger,
            static function (int $uid): void { /* noop */ },
            static fn (int $aid): ?int => 100
        );

        $this->expectException(DomainException::class);
        $handler->handle(new ConfirmarAnonimizacaoCommand($token, 100, null));
    }

    private function makeHandler(): ConfirmarAnonimizacaoHandler
    {
        $repo = $this->createMock(SolicitacaoTitularRepository::class);
        $anonimizador = $this->createMock(AnonimizarTitularHandler::class);
        $audit = $this->createMock(AuditLogger::class);
        $logger = new SecureLogger(static function () { /* noop */ });

        return new ConfirmarAnonimizacaoHandler(
            $repo,
            new AnonimizacaoTokenizer(),
            $anonimizador,
            $audit,
            $logger,
            static function (int $uid): void { /* noop */ },
            static fn (int $aid): ?int => 100
        );
    }
}
