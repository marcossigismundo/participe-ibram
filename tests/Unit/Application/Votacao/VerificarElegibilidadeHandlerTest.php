<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeHandler}.
 *
 * Foco em anti-rastreio: o agente_id resolvido NUNCA aparece no retorno.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeHandler;
use Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeQuery;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes.php';

/**
 * @covers \Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeHandler
 */
final class VerificarElegibilidadeHandlerTest extends TestCase
{
    private FakeVotacaoRepository $votacaoRepo;
    private FakeVotoRepository $votoRepo;
    private FakeAgenteVotanteGateway $agenteGateway;
    private FakeCategoriaConsultaGateway $categoriaGateway;
    private EleitorHasher $hasher;

    protected function setUp(): void
    {
        $GLOBALS['__pi_test_transients'] = [];

        $this->votacaoRepo      = new FakeVotacaoRepository();
        $this->votoRepo         = new FakeVotoRepository();
        $this->agenteGateway    = new FakeAgenteVotanteGateway();
        $this->categoriaGateway = new FakeCategoriaConsultaGateway();

        $this->hasher = new EleitorHasher(
            base64_encode(str_repeat("\x42", SODIUM_CRYPTO_GENERICHASH_KEYBYTES))
        );

        // Cenário base: votação aberta com 2 categorias (11 e 12).
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2030-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $this->votacaoRepo->seed($votacao);

        $this->categoriaGateway->editalDe[11]          = 7;
        $this->categoriaGateway->editalDe[12]          = 7;
        $this->categoriaGateway->tiposAceitos[11]      = ['PF', 'OR'];
        $this->categoriaGateway->tiposAceitos[12]      = ['PF'];
        $this->categoriaGateway->categoriasDoEdital[7] = [11, 12];

        $this->agenteGateway->deferidos[101] = true;
        $this->agenteGateway->tipos[101]     = 'PF';
    }

    public function testElegivelComDuasCategoriasERetornaJaVotouPorCategoria(): void
    {
        // Eleitor JÁ votou na categoria 11 (mas não na 12).
        $eleitorHash = $this->hasher->hash(101, 1);
        $voto        = new Voto(
            null,
            1,
            11,
            $eleitorHash,
            202,
            new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'))
        );
        $this->votoRepo->salvarVoto($voto);

        $handler = $this->makeHandler();
        $result  = $handler->handle(new VerificarElegibilidadeQuery(5, 1));

        self::assertTrue($result['elegivel']);
        self::assertNull($result['motivo']);
        self::assertCount(2, $result['categorias_elegiveis']);

        $cat11 = $this->findById($result['categorias_elegiveis'], 11);
        $cat12 = $this->findById($result['categorias_elegiveis'], 12);
        self::assertNotNull($cat11);
        self::assertNotNull($cat12);
        self::assertTrue($cat11['ja_votou'], 'Eleitor já votou na categoria 11.');
        self::assertFalse($cat12['ja_votou'], 'Eleitor não votou na categoria 12.');
    }

    public function testRespostaNaoExpoeAgenteIdNemEleitorHash(): void
    {
        $handler = $this->makeHandler();
        $result  = $handler->handle(new VerificarElegibilidadeQuery(5, 1));

        $payload = (string) json_encode($result);
        self::assertStringNotContainsString('agente_id', $payload);
        self::assertStringNotContainsString('eleitor_hash', $payload);
        self::assertStringNotContainsString('user_id', $payload);
        self::assertStringNotContainsString('"101"', $payload);

        // Whitelist estrita do top-level.
        self::assertSame(
            ['elegivel', 'motivo', 'votacao_status', 'categorias_elegiveis'],
            array_keys($result)
        );

        // Whitelist estrita por categoria.
        if (count($result['categorias_elegiveis']) > 0) {
            self::assertSame(
                ['id', 'nome', 'ja_votou'],
                array_keys($result['categorias_elegiveis'][0])
            );
        }
    }

    public function testNaoDeferidoRetornaMotivoCadastroNaoDeferido(): void
    {
        $this->agenteGateway->deferidos[101] = false;

        $handler = $this->makeHandler();
        $result  = $handler->handle(new VerificarElegibilidadeQuery(5, 1));

        self::assertFalse($result['elegivel']);
        self::assertSame(
            VerificarElegibilidadeHandler::MOTIVO_CADASTRO_NAO_DEFERIDO,
            $result['motivo']
        );
        self::assertSame([], $result['categorias_elegiveis']);
    }

    public function testWpUserSemAgenteAssociadoRetornaMotivo(): void
    {
        $handler = new VerificarElegibilidadeHandler(
            $this->votacaoRepo,
            $this->votoRepo,
            $this->agenteGateway,
            $this->categoriaGateway,
            $this->hasher,
            // Resolver retorna null para qualquer userId.
            static fn (int $uid): ?int => null
        );

        $result = $handler->handle(new VerificarElegibilidadeQuery(5, 1));
        self::assertFalse($result['elegivel']);
        self::assertSame(
            VerificarElegibilidadeHandler::MOTIVO_SEM_AGENTE_ASSOCIADO,
            $result['motivo']
        );
    }

    public function testVotacaoNaoExistenteRetornaMotivo(): void
    {
        $handler = $this->makeHandler();
        $result  = $handler->handle(new VerificarElegibilidadeQuery(5, 99999));

        self::assertFalse($result['elegivel']);
        self::assertSame(
            VerificarElegibilidadeHandler::MOTIVO_VOTACAO_INEXISTENTE,
            $result['motivo']
        );
    }

    public function testTipoIncompativelFiltraCategoriasMasMantemElegivel(): void
    {
        // Tipo SM só admitido em nenhuma categoria do cenário.
        $this->agenteGateway->tipos[101] = 'SM';

        $handler = $this->makeHandler();
        $result  = $handler->handle(new VerificarElegibilidadeQuery(5, 1));

        self::assertTrue($result['elegivel']);
        self::assertSame([], $result['categorias_elegiveis']);
    }

    private function makeHandler(): VerificarElegibilidadeHandler
    {
        return new VerificarElegibilidadeHandler(
            $this->votacaoRepo,
            $this->votoRepo,
            $this->agenteGateway,
            $this->categoriaGateway,
            $this->hasher,
            static function (int $userId): ?int {
                return $userId === 5 ? 101 : null;
            },
            static function (int $catId): ?string {
                return 'Categoria ' . $catId;
            }
        );
    }

    /**
     * @param list<array<string,mixed>> $items
     *
     * @return array<string,mixed>|null
     */
    private function findById(array $items, int $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? 0) === $id) {
                return $item;
            }
        }
        return null;
    }
}
