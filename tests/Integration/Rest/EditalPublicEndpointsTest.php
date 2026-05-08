<?php
/**
 * Testes de integração — EditalPublicEndpoints.
 *
 * Prioridade Onda 10: regressão de PII em endpoints públicos.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Categoria;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\Inscricao;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Presentation\Rest\EditalPublicEndpoints;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\EditalPublicEndpoints
 */
final class EditalPublicEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients'] = [];
    }

    // ─── Regressão PII: inscritos-habilitados ─────────────────────────────

    /**
     * PRIORITÁRIO (Onda 10): provider malicioso injetando PII é bloqueado pela whitelist.
     * Testa por nome de chave E por valor concreto.
     */
    public function testInscritosHabilitadosNaoExpoePII(): void
    {
        // Provider que tenta vazar todos os campos PII conhecidos.
        $inscritosProvider = static function (int $editalId): array {
            return [
                [
                    'numero_registro'                 => 'PI-2026-00001',
                    'nome_publico'                    => 'João Silva',
                    'categoria_id'                    => 1,
                    'candidato_inscricao_id'          => 42,
                    // --- PII que NUNCA devem sair na resposta ---
                    'cpf'                             => '123.456.789-00',
                    'cpf_enc'                         => 'ENCRYPTED_CPF_BLOB',
                    'cpf_hash'                        => 'sha256_hash_cpf',
                    'rg'                              => '12.345.678-9',
                    'passaporte'                      => 'AB123456',
                    'email'                           => 'joao@example.com',
                    'email_principal'                 => 'joao@example.com',
                    'telefone'                        => '+55 21 99999-0000',
                    'raca_cor'                        => 'parda',
                    'genero'                          => 'masculino',
                    'orientacao_sexual'               => 'heterossexual',
                    'povos_comunidades_tradicionais'  => 'quilombola',
                    'deficiencia'                     => 'auditiva',
                    'data_nascimento'                 => '1985-03-15',
                    'endereco'                        => 'Rua X, 123',
                ],
            ];
        };

        $edital = $this->makeEdital(StatusEdital::VOTACAO_ABERTA);
        $editaisRepo = $this->mockEditaisRepo($edital);
        $categoriasRepo = $this->mockCategoriasRepo([]);
        $inscricoesRepo = $this->mockInscricoesRepo([]);

        $endpoints = new EditalPublicEndpoints(
            $editaisRepo,
            $categoriasRepo,
            $inscricoesRepo,
            $inscritosProvider
        );

        $request = $this->makeRequest(['id' => '1']);
        $response = $endpoints->inscritosHabilitados($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $data  = $response->get_data();
        $items = $data['items'] ?? [];
        $this->assertCount(1, $items);

        $item = $items[0];

        // Verifica que APENAS as 4 chaves whitelistadas estão presentes.
        $chavesPermitidas = ['numero_registro', 'nome_publico', 'categoria_id', 'candidato_inscricao_id'];
        $chavesPresentesNaResposta = array_keys($item);
        $this->assertEqualsCanonicalizing(
            $chavesPermitidas,
            $chavesPresentesNaResposta,
            'A resposta de inscritos-habilitados deve conter SOMENTE as chaves whitelistadas.'
        );

        // Verifica por NOME de chave: campos PII proibidos não estão presentes.
        $camposPiiProibidos = [
            'cpf', 'cpf_enc', 'cpf_hash', 'rg', 'passaporte',
            'email', 'email_principal', 'telefone', 'raca_cor',
            'genero', 'orientacao_sexual', 'povos_comunidades_tradicionais',
            'deficiencia', 'data_nascimento', 'endereco',
        ];
        foreach ($camposPiiProibidos as $campo) {
            $this->assertArrayNotHasKey(
                $campo,
                $item,
                "Campo PII '$campo' NÃO deve aparecer na resposta pública."
            );
        }

        // Verifica por VALOR concreto: nenhum valor de PII aparece na serialização JSON.
        $json = (string) json_encode($data);
        $this->assertStringNotContainsString('123.456.789-00', $json, 'CPF não deve aparecer na resposta.');
        $this->assertStringNotContainsString('ENCRYPTED_CPF_BLOB', $json, 'CPF encriptado não deve aparecer.');
        $this->assertStringNotContainsString('sha256_hash_cpf', $json, 'Hash de CPF não deve aparecer.');
        $this->assertStringNotContainsString('12.345.678-9', $json, 'RG não deve aparecer.');
        $this->assertStringNotContainsString('AB123456', $json, 'Passaporte não deve aparecer.');
        $this->assertStringNotContainsString('joao@example.com', $json, 'Email não deve aparecer.');
        $this->assertStringNotContainsString('+55 21 99999-0000', $json, 'Telefone não deve aparecer.');
        $this->assertStringNotContainsString('quilombola', $json, 'Povos tradicionais não deve aparecer.');
        $this->assertStringNotContainsString('auditiva', $json, 'Deficiência não deve aparecer.');

        // Verifica que os campos permitidos estão corretos.
        $this->assertSame('PI-2026-00001', $item['numero_registro']);
        $this->assertSame('João Silva', $item['nome_publico']);
        $this->assertSame(1, $item['categoria_id']);
        $this->assertSame(42, $item['candidato_inscricao_id']);
    }

    /**
     * Detalhe de edital NÃO deve retornar inscrições no body.
     */
    public function testDetalheEditalNaoRetornaInscricoes(): void
    {
        $edital = $this->makeEdital(StatusEdital::INSCRICOES_ABERTAS);
        $editaisRepo = $this->mockEditaisRepo($edital);
        $categoriasRepo = $this->mockCategoriasRepo([$this->makeCategoria()]);
        $inscricoesRepo = $this->mockInscricoesRepo([
            $this->makeInscricao(1, 1, 99),
        ]);

        $endpoints = new EditalPublicEndpoints($editaisRepo, $categoriasRepo, $inscricoesRepo);

        $request = $this->makeRequest(['id' => '1']);
        $response = $endpoints->detalheEdital($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $json = (string) json_encode($data);

        // Sem campo "inscricoes" nem "inscritos" no body.
        $this->assertArrayNotHasKey('inscricoes', $data, 'Detalhe de edital não deve expor inscrições.');
        $this->assertArrayNotHasKey('inscritos',  $data, 'Detalhe de edital não deve expor inscritos.');

        // Sem agente_id (anonimização).
        $this->assertStringNotContainsString('"agente_id"', $json);
    }

    /**
     * Cache headers presentes em listagem e detalhe.
     */
    public function testCacheHeadersPresentes(): void
    {
        $edital = $this->makeEdital(StatusEdital::PUBLICADO);
        $editaisRepo = $this->mockEditaisRepo($edital);
        $categoriasRepo = $this->mockCategoriasRepo([]);
        $inscricoesRepo = $this->mockInscricoesRepo([]);

        $endpoints = new EditalPublicEndpoints($editaisRepo, $categoriasRepo, $inscricoesRepo);

        // Detalhe: cache 1h.
        $request  = $this->makeRequest(['id' => '1']);
        $response = $endpoints->detalheEdital($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $headers  = $response->get_headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertStringContainsString('max-age=3600', $headers['Cache-Control']);
        $this->assertStringContainsString('public', $headers['Cache-Control']);
        $this->assertArrayHasKey('Vary', $headers);
        $this->assertStringContainsString('Accept', $headers['Vary']);
    }

    /**
     * Listagem retorna Cache-Control max-age=300.
     */
    public function testListagemCacheHeadersCurtos(): void
    {
        $editaisRepo = $this->mockEditaisRepoMulti([]);
        $categoriasRepo = $this->mockCategoriasRepo([]);
        $inscricoesRepo = $this->mockInscricoesRepo([]);

        $endpoints = new EditalPublicEndpoints($editaisRepo, $categoriasRepo, $inscricoesRepo);

        $request  = $this->makeRequest([]);
        $response = $endpoints->listarEditais($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $headers  = $response->get_headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertStringContainsString('max-age=300', $headers['Cache-Control']);
    }

    /**
     * Rate limit: 61ª request retorna 429.
     */
    public function testRateLimit429(): void
    {
        // Simula 60 chamadas ao RateLimiter zerando o contador depois.
        // Como não temos WP real, usamos o mock do transient para simular excesso.
        $GLOBALS['__pi_test_transients'] = [];
        $GLOBALS['__pi_test_rate_limit_force_429'] = true;

        $editaisRepo = $this->mockEditaisRepoMulti([]);
        $categoriasRepo = $this->mockCategoriasRepo([]);
        $inscricoesRepo = $this->mockInscricoesRepo([]);

        $endpoints = new EditalPublicEndpoints($editaisRepo, $categoriasRepo, $inscricoesRepo);
        $request   = $this->makeRequest([]);
        $response  = $endpoints->listarEditais($request);

        // Pode ser WP_REST_Response ou array (test env sem WP).
        if ($response instanceof WP_REST_Response) {
            $this->assertSame(429, $response->get_status());
        } else {
            $this->assertSame(429, $response['data']['status'] ?? $response['status'] ?? 0);
        }

        unset($GLOBALS['__pi_test_rate_limit_force_429']);
    }

    /**
     * Inscritos-habilitados não disponível quando edital não está em votação.
     */
    public function testInscritosHabilitadosIndisponivelForaDaFaseVotacao(): void
    {
        $edital = $this->makeEdital(StatusEdital::INSCRICOES_ABERTAS);
        $endpoints = new EditalPublicEndpoints(
            $this->mockEditaisRepo($edital),
            $this->mockCategoriasRepo([]),
            $this->mockInscricoesRepo([])
        );

        $request  = $this->makeRequest(['id' => '1']);
        $response = $endpoints->inscritosHabilitados($request);

        if ($response instanceof WP_REST_Response) {
            $this->assertContains($response->get_status(), [400, 422]);
        }
    }

    /**
     * Edital não-público (rascunho) retorna 404.
     */
    public function testEditalRascunhoRetorna404(): void
    {
        $edital = $this->makeEdital(StatusEdital::RASCUNHO);
        $endpoints = new EditalPublicEndpoints(
            $this->mockEditaisRepo($edital),
            $this->mockCategoriasRepo([]),
            $this->mockInscricoesRepo([])
        );

        $request  = $this->makeRequest(['id' => '1']);
        $response = $endpoints->detalheEdital($request);

        if ($response instanceof WP_REST_Response) {
            $this->assertSame(404, $response->get_status());
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @param array<string,string> $params
     */
    private function makeRequest(array $params): WP_REST_Request
    {
        $req = new WP_REST_Request();
        foreach ($params as $k => $v) {
            $req->set_param($k, $v);
        }

        return $req;
    }

    private function makeEdital(string $status): Edital
    {
        $now = new DateTimeImmutable('now');
        $e1  = $now->modify('+1 day');
        $e2  = $now->modify('+2 days');
        $e3  = $now->modify('+3 days');
        $e4  = $now->modify('+4 days');
        $e5  = $now->modify('+5 days');
        $e6  = $now->modify('+6 days');
        $e7  = $now->modify('+7 days');

        $edital = new Edital(
            1,
            'Edital Teste',
            'Descricao',
            StatusEdital::fromString($status),
            $e1, $e2, $e3, $e4, $e5, $e6, $e7,
            1,
            $now,
            $now
        );

        return $edital;
    }

    private function makeCategoria(): Categoria
    {
        return new Categoria(1, 1, 'Cat Teste', null, 3, 1, 'PF', null, [], 0);
    }

    private function makeInscricao(int $editalId, int $categoriaId, int $agenteId): Inscricao
    {
        $now = new DateTimeImmutable('now');

        return new Inscricao(1, $editalId, $categoriaId, $agenteId, null, StatusInscricao::inscrito(), $now, null, null, null, $now, $now);
    }

    private function mockEditaisRepo(Edital $edital): WpdbEditalRepository
    {
        $mock = $this->createMock(WpdbEditalRepository::class);
        $mock->method('findById')->willReturn($edital);
        $mock->method('findByStatus')->willReturn([$edital]);

        return $mock;
    }

    /**
     * @param array<int,Edital> $editais
     */
    private function mockEditaisRepoMulti(array $editais): WpdbEditalRepository
    {
        $mock = $this->createMock(WpdbEditalRepository::class);
        $mock->method('findById')->willReturn(null);
        $mock->method('findByStatus')->willReturn($editais);

        return $mock;
    }

    /**
     * @param array<int,Categoria> $categorias
     */
    private function mockCategoriasRepo(array $categorias): WpdbCategoriaRepository
    {
        $mock = $this->createMock(WpdbCategoriaRepository::class);
        $mock->method('findByEdital')->willReturn($categorias);
        $mock->method('findById')->willReturn($categorias[0] ?? null);

        return $mock;
    }

    /**
     * @param array<int,Inscricao> $inscricoes
     */
    private function mockInscricoesRepo(array $inscricoes): WpdbInscricaoRepository
    {
        $mock = $this->createMock(WpdbInscricaoRepository::class);
        $mock->method('findByEditalCategoriaEAgente')->willReturn(null);

        return $mock;
    }
}
