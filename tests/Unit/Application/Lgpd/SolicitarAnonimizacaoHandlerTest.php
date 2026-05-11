<?php
/**
 * Unit tests for {@see SolicitarAnonimizacaoHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Lgpd\AnonimizacaoTokenizer;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoCommand;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoHandler
 */
final class SolicitarAnonimizacaoHandlerTest extends TestCase
{
    public function testRejeitaQuandoExisteAnonimizacaoEmAndamento(): void
    {
        $repo = $this->createMock(SolicitacaoTitularRepository::class);
        $aberta = SolicitacaoTitular::protocolar(
            42,
            SolicitacaoTitular::TIPO_ANONIMIZACAO,
            'já em andamento'
        )->withId(7);
        $repo->method('findAbertasPorAgente')->willReturn([$aberta]);

        $audit = $this->createMock(AuditLogger::class);
        $logger = new SecureLogger(static function () { /* noop */ });
        $tokenizer = new AnonimizacaoTokenizer();

        $handler = new SolicitarAnonimizacaoHandler($repo, $tokenizer, $audit, $logger);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/anonimiza/');

        $handler->handle(new SolicitarAnonimizacaoCommand(42, 100, 'preciso sair', null, null));
    }

    public function testGeraTokenHmacValidoComExpiracao(): void
    {
        $repo = $this->createMock(SolicitacaoTitularRepository::class);
        $repo->method('findAbertasPorAgente')->willReturn([]);
        $repo->method('save')->willReturn(123);

        $audit = $this->createMock(AuditLogger::class);
        $audit->expects($this->atLeast(2))->method('log'); // intent + token emitido
        $logger = new SecureLogger(static function () { /* noop */ });
        $tokenizer = new AnonimizacaoTokenizer();

        $handler = new SolicitarAnonimizacaoHandler($repo, $tokenizer, $audit, $logger);

        $resp = $handler->handle(new SolicitarAnonimizacaoCommand(42, 100, 'motivo', null, null));
        $this->assertSame(123, $resp['solicitacao_id']);
        $this->assertNotEmpty($resp['token']);
        $this->assertNotEmpty($resp['expira_em']);

        // Token deve ser verificável com expiração futura.
        $sid = 0;
        $aid = 0;
        $exp = null;
        $this->assertTrue($tokenizer->verify((string) $resp['token'], $sid, $aid, $exp));
        $this->assertSame(123, $sid);
        $this->assertSame(42, $aid);
        $this->assertInstanceOf(DateTimeImmutable::class, $exp);
        $this->assertGreaterThan((new DateTimeImmutable('now'))->getTimestamp(), $exp->getTimestamp());
    }

    public function testAuditaCriacaoComMotivoRedacted(): void
    {
        $repo = $this->createMock(SolicitacaoTitularRepository::class);
        $repo->method('findAbertasPorAgente')->willReturn([]);
        $repo->method('save')->willReturn(123);

        $captured = [];
        $audit = $this->createMock(AuditLogger::class);
        $audit->method('log')->willReturnCallback(
            function (string $entidade, ?int $id, string $acao, $antes, $depois, $ator) use (&$captured): void {
                $captured[] = compact('entidade', 'id', 'acao', 'antes', 'depois', 'ator');
            }
        );

        $logger = new SecureLogger(static function () { /* noop */ });
        $tokenizer = new AnonimizacaoTokenizer();
        $handler = new SolicitarAnonimizacaoHandler($repo, $tokenizer, $audit, $logger);

        $handler->handle(new SolicitarAnonimizacaoCommand(42, 100, 'meu motivo confidencial', null, null));

        // Espera 2 eventos: anonimizacao_solicitada + anonimizacao_token_emitido.
        $acoes = array_column($captured, 'acao');
        $this->assertContains('anonimizacao_solicitada', $acoes);
        $this->assertContains('anonimizacao_token_emitido', $acoes);

        // O motivo NUNCA pode aparecer em claro nos audit logs.
        foreach ($captured as $evt) {
            $serialized = (string) json_encode($evt['depois']);
            $this->assertStringNotContainsString('meu motivo confidencial', $serialized);
        }
    }
}
