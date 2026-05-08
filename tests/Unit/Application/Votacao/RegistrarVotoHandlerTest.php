<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoCommand;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorInelegivel;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNaoAberta;
use Ibram\ParticipeIbram\Domain\Votacao\VotoDuplicado;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes.php';

/**
 * @covers \Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoHandler
 */
final class RegistrarVotoHandlerTest extends TestCase
{
    private FakeVotacaoRepository $votacaoRepo;
    private FakeVotoRepository $votoRepo;
    private FakeAgenteVotanteGateway $agenteGateway;
    private FakeCategoriaConsultaGateway $categoriaGateway;
    private FakeInscricaoConsultaGateway $inscricaoGateway;
    private EleitorHasher $hasher;
    private AuditLogger $audit;
    private DateTimeImmutable $clockNow;

    protected function setUp(): void
    {
        $GLOBALS['__pi_test_transients'] = [];

        $this->votacaoRepo      = new FakeVotacaoRepository();
        $this->votoRepo         = new FakeVotoRepository();
        $this->agenteGateway    = new FakeAgenteVotanteGateway();
        $this->categoriaGateway = new FakeCategoriaConsultaGateway();
        $this->inscricaoGateway = new FakeInscricaoConsultaGateway();

        $this->hasher = new EleitorHasher(
            base64_encode(str_repeat("\x42", SODIUM_CRYPTO_GENERICHASH_KEYBYTES))
        );

        $wpdb = $this->createWpdbStub();
        $this->audit = new AuditLogger($wpdb, new IpResolver([], []));

        $this->clockNow = new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'));

        // Cenário base: votação aberta para edital 7, categoria 11.
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $seeded = $this->votacaoRepo->seed($votacao);

        $this->categoriaGateway->editalDe[11]                 = 7;
        $this->categoriaGateway->tiposAceitos[11]             = ['PF', 'OR'];
        $this->categoriaGateway->categoriasDoEdital[7]        = [11];
        $this->categoriaGateway->vagas[11]                    = 1;
        $this->categoriaGateway->suplentes[11]                = 1;

        $this->agenteGateway->deferidos[101]                   = true;
        $this->agenteGateway->tipos[101]                       = 'PF';
        $this->inscricaoGateway->habilitadas['202|11']         = true;
    }

    public function testRegistroBemSucedido(): void
    {
        $handler = $this->makeHandler();
        $voto = $handler->handle(new RegistrarVotoCommand(1, 11, 101, 202));

        self::assertNotNull($voto->id());
        self::assertSame(1, $voto->votacaoId());
        self::assertSame(11, $voto->categoriaId());
        self::assertSame(202, $voto->candidatoInscricaoId());
        self::assertSame(64, strlen($voto->eleitorHash()));
    }

    public function testFalhaQuandoVotacaoNaoAberta(): void
    {
        // Sobrescreve com votação encerrada.
        $encerrada = new Votacao(
            1,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            str_repeat('a', 64)
        );
        $this->votacaoRepo->save($encerrada);

        $this->expectException(VotacaoNaoAberta::class);
        $this->makeHandler()->handle(new RegistrarVotoCommand(1, 11, 101, 202));
    }

    public function testFalhaQuandoForaDaJanela(): void
    {
        // "Agora" depois do encerramento.
        $this->clockNow = new DateTimeImmutable('2026-06-10 19:00:00', new DateTimeZone('UTC'));

        $this->expectException(VotacaoNaoAberta::class);
        $this->makeHandler()->handle(new RegistrarVotoCommand(1, 11, 101, 202));
    }

    public function testFalhaQuandoAgenteNaoDeferido(): void
    {
        $this->agenteGateway->deferidos[101] = false;

        $this->expectException(EleitorInelegivel::class);
        $this->makeHandler()->handle(new RegistrarVotoCommand(1, 11, 101, 202));
    }

    public function testFalhaQuandoTipoNaoAceito(): void
    {
        $this->categoriaGateway->tiposAceitos[11] = ['SM']; // só SM
        $this->agenteGateway->tipos[101] = 'PF';

        $this->expectException(EleitorInelegivel::class);
        $this->makeHandler()->handle(new RegistrarVotoCommand(1, 11, 101, 202));
    }

    public function testFalhaQuandoCategoriaNaoPertenceAoEditalDaVotacao(): void
    {
        $this->categoriaGateway->editalDe[11] = 999; // edital diferente

        $this->expectException(EleitorInelegivel::class);
        $this->makeHandler()->handle(new RegistrarVotoCommand(1, 11, 101, 202));
    }

    public function testFalhaQuandoCandidatoNaoFinalHabilitado(): void
    {
        $this->inscricaoGateway->habilitadas['202|11'] = false;

        $this->expectException(EleitorInelegivel::class);
        $this->makeHandler()->handle(new RegistrarVotoCommand(1, 11, 101, 202));
    }

    public function testFalhaEmVotoDuplicado(): void
    {
        $handler = $this->makeHandler();
        $handler->handle(new RegistrarVotoCommand(1, 11, 101, 202));

        $this->expectException(VotoDuplicado::class);
        $handler->handle(new RegistrarVotoCommand(1, 11, 101, 202));
    }

    public function testEleitorHashEhDeterministicoEntreChamadas(): void
    {
        $handler = $this->makeHandler();
        $voto1 = $handler->handle(new RegistrarVotoCommand(1, 11, 101, 202));

        // Reseta repositório de votos para permitir nova tentativa
        // (preservando que o cálculo de hash não muda).
        $this->votoRepo = new FakeVotoRepository();
        $handler        = $this->makeHandler();
        $voto2          = $handler->handle(new RegistrarVotoCommand(1, 11, 101, 202));

        self::assertSame($voto1->eleitorHash(), $voto2->eleitorHash());
    }

    private function makeHandler(): RegistrarVotoHandler
    {
        $clock = function () {
            return $this->clockNow;
        };

        return new RegistrarVotoHandler(
            $this->votacaoRepo,
            $this->votoRepo,
            $this->hasher,
            $this->agenteGateway,
            $this->categoriaGateway,
            $this->inscricaoGateway,
            $this->audit,
            null,  // ipResolver opcional
            $clock
        );
    }

    /**
     * Stub mínimo de wpdb que aceita inserts (AuditLogger usa apenas insert).
     *
     * @return object
     */
    private function createWpdbStub()
    {
        return new class {
            public string $prefix = 'wp_';
            public string $last_error = '';
            public int $insert_id = 0;

            /**
             * @param array<string,mixed> $data
             * @param array<int,string|null> $formats
             */
            public function insert(string $table, array $data, array $formats): bool
            {
                return true;
            }
        };
    }
}
