<?php
/**
 * Testes de integração — VotacaoPublicEndpoints.
 *
 * Foco em: hash imutável publicado, recálculo bate (constant-time),
 * resultado público não lista eleitores.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use Ibram\ParticipeIbram\Presentation\Rest\VotacaoPublicEndpoints;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeResultadoRepository;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotacaoRepository;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotoRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/../../Unit/Application/Votacao/Fakes.php';

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\VotacaoPublicEndpoints
 */
final class VotacaoPublicEndpointsTest extends TestCase
{
    private FakeVotacaoRepository $votacoesRepo;
    private FakeVotoRepository $votosRepo;
    private FakeResultadoRepository $resultadosRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients'] = [];
        $GLOBALS['__pi_test_options']    = [];

        $this->votacoesRepo   = new FakeVotacaoRepository();
        $this->votosRepo      = new FakeVotoRepository();
        $this->resultadosRepo = new FakeResultadoRepository();
    }

    public function testDetalheAntesDoEncerramentoOcultaHashETotal(): void
    {
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2030-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $seeded = $this->votacoesRepo->seed($votacao);

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['id' => $seeded->id()]);
        $response  = $endpoints->detalhePublico($request);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertSame('aberta', $data['status']);
        self::assertNull($data['hash_pre_apuracao']);
        self::assertNull($data['total_votos']);
    }

    public function testDetalheAposEncerramentoExpoeHashETotal(): void
    {
        $hash = str_repeat('a', 64);
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            $hash
        );
        $seeded = $this->votacoesRepo->seed($votacao);

        // Adiciona 3 votos.
        $this->seedVoto($seeded->id(), 11, 'b', 202);
        $this->seedVoto($seeded->id(), 11, 'c', 202);
        $this->seedVoto($seeded->id(), 11, 'd', 203);

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['id' => $seeded->id()]);
        $response  = $endpoints->detalhePublico($request);

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertSame('encerrada', $data['status']);
        self::assertSame($hash, $data['hash_pre_apuracao']);
        self::assertSame(3, $data['total_votos']);
    }

    public function testAuditoriaAntesDoEncerramentoMarcaIndisponivel(): void
    {
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2030-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $seeded = $this->votacoesRepo->seed($votacao);

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->auditoriaPublica(new WP_REST_Request(['id' => $seeded->id()]));

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertFalse($data['disponivel']);
        self::assertNull($data['hash_pre_apuracao']);
    }

    public function testAuditoriaAposEncerramentoBateComRecalculo(): void
    {
        // Calcula hash determinístico via FakeVotoRepository::gerarHashPreApuracao.
        $hashCalculado = $this->votosRepo->gerarHashPreApuracao(1);
        // Insere 2 votos antes do encerramento; recalcula hash.
        $this->seedVoto(1, 11, 'b', 202);
        $this->seedVoto(1, 11, 'c', 203);
        $hashFinal = $this->votosRepo->gerarHashPreApuracao(1);

        $votacao = new Votacao(
            1,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            $hashFinal
        );
        $this->votacoesRepo->seed($votacao);

        // Simula publicação em wp_options.
        $GLOBALS['__pi_test_options']['pi_votacao_1_hash'] = [
            'hash_pre_apuracao' => $hashFinal,
            'total_votos'       => 2,
            'calculado_em'      => '2026-06-10T18:00:00+00:00',
        ];

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->auditoriaPublica(new WP_REST_Request(['id' => 1]));

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertTrue($data['disponivel']);
        self::assertSame($hashFinal, $data['hash_pre_apuracao']);

        // Recálculo bate (constant-time fora do response — replica aqui para garantir
        // que o método público é determinístico e auditor pode reproduzir).
        $reCalculado = $this->votosRepo->gerarHashPreApuracao(1);
        self::assertTrue(
            hash_equals($data['hash_pre_apuracao'], $reCalculado),
            'Hash publicado deve bater com o recálculo (constant-time).'
        );
    }

    public function testResultadoPublicoNaoListaEleitores(): void
    {
        $votacao = new Votacao(
            1,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::apurada(),
            ModoVotacao::porCategoria(),
            str_repeat('a', 64),
            new DateTimeImmutable('2026-06-10 19:00:00', new DateTimeZone('UTC'))
        );
        $this->votacoesRepo->seed($votacao);

        $now = new DateTimeImmutable('2026-06-10 19:00:00', new DateTimeZone('UTC'));
        $this->resultadosRepo->resultados[] = new Resultado(null, 1, 11, 202, 50, 1, true, false, $now);
        $this->resultadosRepo->resultados[] = new Resultado(null, 1, 11, 203, 30, 2, false, true, $now);
        $this->resultadosRepo->resultados[] = new Resultado(null, 1, 11, 204, 10, 3, false, false, $now);

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->resultadoPublico(new WP_REST_Request(['id' => 1]));

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        self::assertSame(1, $data['votacao_id']);
        self::assertCount(1, $data['categorias']);
        $cat = $data['categorias'][0];
        self::assertSame(11, $cat['categoria_id']);
        self::assertCount(1, $cat['eleitos']);
        self::assertCount(1, $cat['suplentes']);

        // ANTI-RASTREIO: resultado não pode conter listas de eleitores nem hashes.
        $payload = (string) json_encode($data);
        self::assertStringNotContainsString('"eleitor_hash"', $payload);
        self::assertStringNotContainsString('"eleitores"', $payload);
        self::assertStringNotContainsString('"agente_id"', $payload);
        self::assertStringNotContainsString('"user_id"', $payload);

        // Apenas chaves esperadas em cada item.
        self::assertSame(
            ['numero_registro', 'nome_publico', 'candidato_inscricao_id', 'total_votos', 'posicao'],
            array_keys($cat['eleitos'][0])
        );
    }

    public function testResultadoAntesDeApurarRetorna409(): void
    {
        $votacao = new Votacao(
            1,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            str_repeat('a', 64)
        );
        $this->votacoesRepo->seed($votacao);

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->resultadoPublico(new WP_REST_Request(['id' => 1]));
        self::assertSame(409, $response->get_status());
    }

    private function makeEndpoints(): VotacaoPublicEndpoints
    {
        return new VotacaoPublicEndpoints(
            $this->votacoesRepo,
            $this->votosRepo,
            $this->resultadosRepo,
            static function (int $candidatoId): ?array {
                return [
                    'numero_registro' => 'PI-2026-' . str_pad((string) $candidatoId, 5, '0', STR_PAD_LEFT),
                    'nome_publico'    => 'Candidato ' . $candidatoId,
                ];
            },
            static fn (int $catId): ?string => 'Categoria ' . $catId
        );
    }

    private function seedVoto(int $votacaoId, int $categoriaId, string $hashSeed, int $candidato): void
    {
        $eleitorHash = str_pad($hashSeed, 64, $hashSeed);
        $voto        = new Voto(
            null,
            $votacaoId,
            $categoriaId,
            $eleitorHash,
            $candidato,
            new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'))
        );
        $this->votosRepo->salvarVoto($voto);
    }
}
