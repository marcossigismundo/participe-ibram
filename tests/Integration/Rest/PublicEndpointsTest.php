<?php
/**
 * Testes de integração: PublicEndpoints (sem autenticação).
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use Ibram\ParticipeIbram\Presentation\Rest\PublicEndpoints;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\PublicEndpoints
 */
final class PublicEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients'] = [];
    }

    public function testAgentesDeferidosNaoExpoeCpfNemEmail(): void
    {
        $endpoints = new PublicEndpoints(
            $this->stubTermoRepoComAtivo(),
            // Provedor injeta lixo intencional para validar a sanitização defensiva.
            static function (int $page, int $per): array {
                return [
                    'items' => [
                        [
                            'numero_registro' => 'PI-2026-00001',
                            'nome_publico'    => 'Maria',
                            'tipo_agente'     => 'PF',
                            'deferido_em'     => '2026-04-10T10:00:00Z',
                            // Tentativa maliciosa: provedor mal comportado.
                            'cpf'             => '123.456.789-00',
                            'cpf_enc'         => 'BLOB',
                            'email_principal' => 'maria@example.com',
                            'telefone'        => '+55 21 99999-0000',
                        ],
                    ],
                    'total' => 1,
                ];
            }
        );

        $response = $endpoints->listarDeferidos(new WP_REST_Request());

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $body = $response->get_data();
        $this->assertCount(1, $body['items']);
        $item = $body['items'][0];

        // Whitelist estrita.
        $this->assertSame(
            ['numero_registro', 'nome_publico', 'tipo_agente', 'deferido_em'],
            array_keys($item),
            'Resposta deve conter EXATAMENTE estas chaves — qualquer PII vaza o teste.'
        );
        $this->assertArrayNotHasKey('cpf', $item);
        $this->assertArrayNotHasKey('cpf_enc', $item);
        $this->assertArrayNotHasKey('email_principal', $item);
        $this->assertArrayNotHasKey('telefone', $item);

        // Cache 5 min.
        $this->assertSame('public, max-age=300', $response->get_headers()['Cache-Control']);
    }

    public function testTermoVigenteServeHashEVersao(): void
    {
        $endpoints = new PublicEndpoints($this->stubTermoRepoComAtivo());

        $response = $endpoints->termoVigente(new WP_REST_Request());

        $this->assertSame(200, $response->get_status());
        $body = $response->get_data();
        $this->assertSame('1.0.0', $body['versao']);
        $this->assertSame(64, strlen($body['hash_sha256']));
        $this->assertNotEmpty($body['conteudo_md']);
        $this->assertSame('public, max-age=3600', $response->get_headers()['Cache-Control']);
    }

    public function testTermoVigenteAusenteRetorna404(): void
    {
        $repo = new class implements TermoRepository {
            public function findById(int $id): ?Termo { return null; }
            public function findByVersao(string $versao): ?Termo { return null; }
            public function findAtivoCorrente(): ?Termo { return null; }
            public function save(Termo $termo): int { return 1; }
            public function inativarAnterior(int $exceptoId): void { }
        };
        $endpoints = new PublicEndpoints($repo);

        $response = $endpoints->termoVigente(new WP_REST_Request());

        $this->assertSame(404, $response->get_status());
        $this->assertSame('pi_not_found', $response->get_data()['code']);
    }

    public function testListarEditaisVazioQuandoSemProvedor(): void
    {
        $endpoints = new PublicEndpoints($this->stubTermoRepoComAtivo());

        $response = $endpoints->listarEditais(new WP_REST_Request());

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['items' => []], $response->get_data());
    }

    private function stubTermoRepoComAtivo(): TermoRepository
    {
        return new class implements TermoRepository {
            public function findById(int $id): ?Termo { return null; }
            public function findByVersao(string $versao): ?Termo { return null; }
            public function findAtivoCorrente(): ?Termo
            {
                return Termo::create('1.0.0', '# Política de Privacidade Participe Ibram', 1);
            }
            public function save(Termo $termo): int { return 1; }
            public function inativarAnterior(int $exceptoId): void { }
        };
    }
}
