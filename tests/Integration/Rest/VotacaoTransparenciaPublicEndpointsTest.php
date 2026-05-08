<?php
/**
 * Testes de integração — VotacaoTransparenciaPublicEndpoints.
 *
 * Prioridade Onda 10: ausência de PII em endpoint de auditoria pública.
 *
 * Casos:
 *  1. Antes do encerramento, `hash_pre_apuracao` é null e total_votos é null.
 *  2. Após encerrar, dados consistentes (hash + total).
 *  3. Endpoint `audit-public` NÃO contém `agente_id`, `user_id`, `ator_id`.
 *  4. Rate limit dispara após N requisições.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */
declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoAuditQuery;
use Ibram\ParticipeIbram\Presentation\Rest\VotacaoTransparenciaPublicEndpoints;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotacaoRepository;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotoRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/../../Unit/Application/Votacao/Fakes.php';

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\VotacaoTransparenciaPublicEndpoints
 */
final class VotacaoTransparenciaPublicEndpointsTest extends TestCase
{
    // 64 chars hex (sha256-shape).
    private const HASH = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients']      = [];
        $GLOBALS['__pi_test_current_user_id'] = 0;
    }

    public function testTransparenciaAntesDoEncerramentoNaoExpoeHash(): void
    {
        // Votação aberta — sem hash, sem total.
        [$repoVotacoes, $votacaoId] = $this->seedVotacao(StatusVotacao::aberta(), null, null);
        $repoVotos = new FakeVotoRepository();

        $auditQuery = $this->createMock(VotacaoAuditQuery::class);
        $editaisRepo = $this->makeEditaisRepo($this->makeEdital());

        $endpoints = new VotacaoTransparenciaPublicEndpoints(
            $repoVotacoes, $repoVotos, $editaisRepo, $auditQuery
        );

        $req = $this->makeRequest(['id' => (string) $votacaoId]);
        $response = $endpoints->transparencia($req);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertNull($data['hash_pre_apuracao'] ?? 'NOT_NULL');
        self::assertNull($data['total_votos'] ?? 'NOT_NULL', 'total_votos deve ser null antes do encerramento.');
        self::assertSame('sha256', $data['algoritmo'] ?? '');
        self::assertArrayHasKey('tie_break_rule', $data);
    }

    public function testTransparenciaAposEncerramentoExpoeHash(): void
    {
        $apuradoEm = new DateTimeImmutable('2026-06-12 10:00:00', new DateTimeZone('UTC'));
        [$repoVotacoes, $votacaoId] = $this->seedVotacao(StatusVotacao::apurada(), self::HASH, $apuradoEm);
        $repoVotos = $this->createMock(\Ibram\ParticipeIbram\Domain\Votacao\VotoRepository::class);
        $repoVotos->method('contarTotalDaVotacao')->willReturn(123);

        $auditQuery = $this->createMock(VotacaoAuditQuery::class);
        $editaisRepo = $this->makeEditaisRepo($this->makeEdital());

        $endpoints = new VotacaoTransparenciaPublicEndpoints(
            $repoVotacoes, $repoVotos, $editaisRepo, $auditQuery
        );

        $req = $this->makeRequest(['id' => (string) $votacaoId]);
        $response = $endpoints->transparencia($req);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        self::assertSame(self::HASH, $data['hash_pre_apuracao'] ?? '');
        self::assertSame(123, (int) ($data['total_votos'] ?? 0));
        self::assertNotEmpty($data['apurado_em'] ?? '');
        self::assertSame('sha256', $data['algoritmo'] ?? '');
    }

    public function testAuditPublicNaoContemPii(): void
    {
        $apuradoEm = new DateTimeImmutable('2026-06-12 10:00:00', new DateTimeZone('UTC'));
        [$repoVotacoes, $votacaoId] = $this->seedVotacao(StatusVotacao::encerrada(), self::HASH, null);

        $repoVotos = $this->createMock(\Ibram\ParticipeIbram\Domain\Votacao\VotoRepository::class);
        $repoVotos->method('contarTotalDaVotacao')->willReturn(2);

        // Audit query: retorna eventos com "vazamentos" tentados pelo provider —
        // o endpoint deve filtrar pela whitelist e não deixar passar.
        $auditQuery = $this->createMock(VotacaoAuditQuery::class);
        $auditQuery->method('listarVotos')->willReturn([
            [
                'ocorrido_em'             => '2026-06-10 12:30:00',
                'categoria_id'            => 11,
                'eleitor_hash'            => str_repeat('a', 64),
                'candidato_inscricao_id'  => 202,
                'ip_hash'                 => str_repeat('b', 64),
                // tentativas de vazamento (devem ser filtradas):
                'agente_id'               => 99,
                'user_id'                 => 7,
                'ator_id'                 => 7,
                'cpf'                     => '12345678901',
                'email'                   => 'leak@x.com',
            ],
        ]);

        $editaisRepo = $this->makeEditaisRepo($this->makeEdital());

        $endpoints = new VotacaoTransparenciaPublicEndpoints(
            $repoVotacoes, $repoVotos, $editaisRepo, $auditQuery
        );

        $req = $this->makeRequest(['id' => (string) $votacaoId]);
        $response = $endpoints->auditPublic($req);
        self::assertInstanceOf(WP_REST_Response::class, $response);

        $data  = $response->get_data();
        $items = $data['items'] ?? [];
        self::assertCount(1, $items);

        $item = $items[0];

        // Apenas as 5 chaves whitelistadas são permitidas.
        $allowed = ['ocorrido_em', 'categoria_id', 'eleitor_hash', 'candidato_inscricao_id', 'ip_hash'];
        self::assertEqualsCanonicalizing($allowed, array_keys($item));

        // Verifica nominalmente que campos proibidos NÃO estão presentes.
        foreach (['agente_id', 'user_id', 'ator_id', 'cpf', 'email'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $item, "Campo {$forbidden} vazou em audit-public.");
        }

        // E pelo conteúdo serializado: nenhum valor de PII presente.
        $json = (string) json_encode($items);
        self::assertStringNotContainsString('12345678901', $json);
        self::assertStringNotContainsString('leak@x.com', $json);
        self::assertStringNotContainsString('"agente_id"', $json);
    }

    public function testAuditPublicAntesDoEncerramentoBloqueado(): void
    {
        [$repoVotacoes, $votacaoId] = $this->seedVotacao(StatusVotacao::aberta(), null, null);

        $repoVotos = $this->createMock(\Ibram\ParticipeIbram\Domain\Votacao\VotoRepository::class);
        $auditQuery = $this->createMock(VotacaoAuditQuery::class);
        $editaisRepo = $this->makeEditaisRepo($this->makeEdital());

        $endpoints = new VotacaoTransparenciaPublicEndpoints(
            $repoVotacoes, $repoVotos, $editaisRepo, $auditQuery
        );

        $req = $this->makeRequest(['id' => (string) $votacaoId]);
        $response = $endpoints->auditPublic($req);

        // Validation 400 (bloqueio antes do encerramento — coerção de eleitor).
        if ($response instanceof WP_REST_Response) {
            self::assertSame(400, $response->get_status());
        }
    }

    public function testRateLimit(): void
    {
        $apuradoEm = new DateTimeImmutable('2026-06-12 10:00:00', new DateTimeZone('UTC'));
        [$repoVotacoes, $votacaoId] = $this->seedVotacao(StatusVotacao::apurada(), self::HASH, $apuradoEm);
        $repoVotos = $this->createMock(\Ibram\ParticipeIbram\Domain\Votacao\VotoRepository::class);
        $repoVotos->method('contarTotalDaVotacao')->willReturn(0);
        $auditQuery = $this->createMock(VotacaoAuditQuery::class);
        $editaisRepo = $this->makeEditaisRepo($this->makeEdital());

        $endpoints = new VotacaoTransparenciaPublicEndpoints(
            $repoVotacoes, $repoVotos, $editaisRepo, $auditQuery
        );

        $hit429 = false;
        for ($i = 0; $i < 35; $i++) {
            $req = $this->makeRequest(['id' => (string) $votacaoId]);
            $response = $endpoints->transparencia($req);
            if ($response instanceof WP_REST_Response && $response->get_status() === 429) {
                $hit429 = true;
                break;
            }
        }
        self::assertTrue($hit429, 'Rate limit deve disparar 429 após 30 req/min.');
    }

    /* ===================== helpers ===================== */

    /**
     * @return array{0:FakeVotacaoRepository,1:int}
     */
    private function seedVotacao(StatusVotacao $status, ?string $hash, ?DateTimeImmutable $apuradoEm): array
    {
        $repo  = new FakeVotacaoRepository();
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            $status,
            ModoVotacao::porCategoria(),
            $hash,
            $apuradoEm
        );
        $seeded = $repo->seed($votacao);
        return [$repo, (int) $seeded->id()];
    }

    private function makeEdital(): Edital
    {
        $now = new DateTimeImmutable('now');
        return new Edital(
            7,
            'Edital de Auditoria',
            null,
            StatusEdital::fromString(StatusEdital::VOTACAO_ENCERRADA),
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $now->modify('+3 days'),
            $now->modify('+4 days'),
            $now->modify('+5 days'),
            $now->modify('+6 days'),
            $now->modify('+7 days'),
            1,
            $now,
            $now
        );
    }

    private function makeEditaisRepo(Edital $edital): WpdbEditalRepository
    {
        $mock = $this->createMock(WpdbEditalRepository::class);
        $mock->method('findById')->willReturn($edital);
        return $mock;
    }

    /**
     * @param array<string,string> $params
     */
    private function makeRequest(array $params): WP_REST_Request
    {
        // O stub de WP_REST_Request em tests/bootstrap.php aceita params via construtor.
        return new WP_REST_Request($params);
    }
}
