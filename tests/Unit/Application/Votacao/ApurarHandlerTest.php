<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Votacao\ApurarHandler}.
 *
 * Foco:
 *  - Ordenação por total_votos DESC.
 *  - Tie-break por inscrito_em ASC (documentado).
 *  - Marcação eleito vs. suplente com base em numVagas / numSuplentes.
 *  - Falha quando votação não está com status `encerrada`.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\ApurarCommand;
use Ibram\ParticipeIbram\Application\Votacao\ApurarHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes.php';

/**
 * @covers \Ibram\ParticipeIbram\Application\Votacao\ApurarHandler
 */
final class ApurarHandlerTest extends TestCase
{
    private FakeVotacaoRepository $votacaoRepo;
    private FakeVotoRepository $votoRepo;
    private FakeResultadoRepository $resultadoRepo;
    private FakeCategoriaConsultaGateway $categoriaGateway;
    private FakeInscricaoConsultaGateway $inscricaoGateway;
    private AuditLogger $audit;

    private const HASH_VALIDO = 'a1b2c3d4e5f607182930415263748596a1b2c3d4e5f607182930415263748596';

    protected function setUp(): void
    {
        $GLOBALS['__pi_test_transients'] = [];

        $this->votacaoRepo      = new FakeVotacaoRepository();
        $this->votoRepo         = new FakeVotoRepository();
        $this->resultadoRepo    = new FakeResultadoRepository();
        $this->categoriaGateway = new FakeCategoriaConsultaGateway();
        $this->inscricaoGateway = new FakeInscricaoConsultaGateway();

        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';

            /**
             * @param array<string,mixed> $data
             * @param array<int,string|null> $formats
             */
            public function insert(string $table, array $data, array $formats): bool
            {
                return true;
            }
        };
        $this->audit = new AuditLogger($wpdb, new IpResolver([], []));
    }

    public function testApuraComOrdenacaoPorTotalDesc(): void
    {
        $votacao = $this->seedVotacaoEncerrada();
        $vid     = (int) $votacao->id();
        $cat     = 11;

        // 3 candidatos: 202=2 votos, 303=5 votos, 404=3 votos.
        $this->seedVotos($vid, $cat, [
            202 => 2,
            303 => 5,
            404 => 3,
        ]);

        $this->categoriaGateway->vagas[$cat]               = 1;
        $this->categoriaGateway->suplentes[$cat]           = 1;
        $this->categoriaGateway->categoriasDoEdital[7]     = [$cat];
        $this->setInscricaoData([202 => '2026-05-01', 303 => '2026-05-02', 404 => '2026-05-03']);

        $resultados = $this->makeHandler()->handle(new ApurarCommand($vid));

        // Ordem esperada: 303 (5), 404 (3), 202 (2).
        self::assertCount(3, $resultados);
        self::assertSame(303, $resultados[0]->candidatoInscricaoId());
        self::assertSame(1, $resultados[0]->posicao());
        self::assertTrue($resultados[0]->eleito());
        self::assertFalse($resultados[0]->suplente());

        self::assertSame(404, $resultados[1]->candidatoInscricaoId());
        self::assertSame(2, $resultados[1]->posicao());
        self::assertFalse($resultados[1]->eleito());
        self::assertTrue($resultados[1]->suplente());

        self::assertSame(202, $resultados[2]->candidatoInscricaoId());
        self::assertSame(3, $resultados[2]->posicao());
        self::assertFalse($resultados[2]->eleito());
        self::assertFalse($resultados[2]->suplente());
    }

    public function testTieBreakPorInscritoEmAsc(): void
    {
        $votacao = $this->seedVotacaoEncerrada();
        $vid     = (int) $votacao->id();
        $cat     = 11;

        // Empate em 3 votos para 2 candidatos.
        $this->seedVotos($vid, $cat, [
            202 => 3,
            303 => 3,
        ]);

        $this->categoriaGateway->vagas[$cat]           = 1;
        $this->categoriaGateway->suplentes[$cat]       = 0;
        $this->categoriaGateway->categoriasDoEdital[7] = [$cat];

        // 303 inscreveu-se ANTES de 202 — deve ficar à frente.
        $this->inscricaoGateway->inscritoEm[202] = new DateTimeImmutable('2026-05-10');
        $this->inscricaoGateway->inscritoEm[303] = new DateTimeImmutable('2026-05-01');

        $resultados = $this->makeHandler()->handle(new ApurarCommand($vid));

        self::assertCount(2, $resultados);
        self::assertSame(303, $resultados[0]->candidatoInscricaoId(), 'tie-break: inscrito_em ASC.');
        self::assertSame(1, $resultados[0]->posicao());
        self::assertTrue($resultados[0]->eleito());

        self::assertSame(202, $resultados[1]->candidatoInscricaoId());
        self::assertSame(2, $resultados[1]->posicao());
    }

    public function testTieBreakSecundarioPorCandidatoIdAsc(): void
    {
        $votacao = $this->seedVotacaoEncerrada();
        $vid     = (int) $votacao->id();
        $cat     = 11;

        $this->seedVotos($vid, $cat, [202 => 2, 303 => 2]);

        $this->categoriaGateway->vagas[$cat]           = 1;
        $this->categoriaGateway->suplentes[$cat]       = 0;
        $this->categoriaGateway->categoriasDoEdital[7] = [$cat];

        // Mesma data de inscrição -> tie-break secundário por id.
        $when = new DateTimeImmutable('2026-05-10');
        $this->inscricaoGateway->inscritoEm[202] = $when;
        $this->inscricaoGateway->inscritoEm[303] = $when;

        $resultados = $this->makeHandler()->handle(new ApurarCommand($vid));

        self::assertSame(202, $resultados[0]->candidatoInscricaoId());
        self::assertSame(303, $resultados[1]->candidatoInscricaoId());
    }

    public function testFalhaSeVotacaoNaoEncerrada(): void
    {
        $aberta = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00'),
            new DateTimeImmutable('2026-06-10 18:00:00'),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $seeded = $this->votacaoRepo->seed($aberta);

        $this->expectException(IllegalStateTransition::class);
        $this->makeHandler()->handle(new ApurarCommand((int) $seeded->id()));
    }

    public function testTransicionaParaApurada(): void
    {
        $votacao = $this->seedVotacaoEncerrada();
        $vid     = (int) $votacao->id();
        $cat     = 11;

        $this->seedVotos($vid, $cat, [202 => 1]);
        $this->categoriaGateway->vagas[$cat]           = 1;
        $this->categoriaGateway->categoriasDoEdital[7] = [$cat];
        $this->setInscricaoData([202 => '2026-05-01']);

        $this->makeHandler()->handle(new ApurarCommand($vid));

        $depois = $this->votacaoRepo->findById($vid);
        self::assertTrue($depois->status()->isApurada());
        self::assertNotNull($depois->apuradoEm());
    }

    private function makeHandler(): ApurarHandler
    {
        $clock = static fn () => new DateTimeImmutable('2026-06-11 09:00:00', new DateTimeZone('UTC'));
        return new ApurarHandler(
            $this->votacaoRepo,
            $this->votoRepo,
            $this->resultadoRepo,
            $this->categoriaGateway,
            $this->inscricaoGateway,
            $this->audit,
            $clock
        );
    }

    private function seedVotacaoEncerrada(): Votacao
    {
        $v = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            str_repeat('a', 64)
        );
        return $this->votacaoRepo->seed($v);
    }

    /**
     * Insere votos diretamente no fake (não passa pelo handler de registro).
     *
     * @param array<int,int> $contagem candidato_id => total_votos
     */
    private function seedVotos(int $votacaoId, int $categoriaId, array $contagem): void
    {
        $when = new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'));
        $i    = 0;
        foreach ($contagem as $candidatoId => $total) {
            for ($n = 0; $n < $total; $n++) {
                // Hash único por voto sintético.
                $hash = str_pad(dechex($i++ + 1), 64, '0', STR_PAD_LEFT);
                $this->votoRepo->salvarVoto(new Voto(
                    null,
                    $votacaoId,
                    $categoriaId,
                    $hash,
                    $candidatoId,
                    $when
                ));
            }
        }
    }

    /**
     * @param array<int,string> $map candidatoId => YYYY-MM-DD
     */
    private function setInscricaoData(array $map): void
    {
        foreach ($map as $candidatoId => $iso) {
            $this->inscricaoGateway->inscritoEm[$candidatoId] = new DateTimeImmutable($iso);
        }
    }
}
