<?php
/**
 * Unit tests para {@see Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosHandler}.
 *
 * CRÍTICO: assert voto secreto — `candidato_inscricao_id` NUNCA pode aparecer
 * em nenhuma forma (chave OU valor numérico no payload serializado).
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\MinhaConta;

use Ibram\ParticipeIbram\Application\MinhaConta\HistoricoVotosPort;
use Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosCommand;
use Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosHandler;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use PHPUnit\Framework\TestCase;

/**
 * Fake do {@see HistoricoVotosPort} — controlado pelo teste para simular
 * comportamentos sem depender de wpdb.
 *
 * IMPORTANTE: este fake é o que um implementador honesto faria — ele NUNCA
 * retorna `candidato_inscricao_id` em {@see listarFatosVoto()}. O teste valida
 * que mesmo se um implementador *desonesto* mandasse o campo, o handler
 * filtraria (defesa em profundidade — testamos esse cenário no
 * MaliciousPortReturnsCandidatoTest).
 */
final class FakeHistoricoVotosPort implements HistoricoVotosPort
{
    /** @var array<string,list<array<string,mixed>>> */
    public array $fatosPorHash = [];

    /** @var array<string,array<string,mixed>> */
    public array $dadosRecibo = [];

    /**
     * Se true, esta fake é "desonesta" e ADICIONA `candidato_inscricao_id` ao
     * retorno — exercita defesa em profundidade do handler/REST.
     */
    public bool $vazarCandidato = false;

    public int $candidatoVazadoId = 999;

    public function listarFatosVoto(string $eleitorHash): array
    {
        $base = $this->fatosPorHash[$eleitorHash] ?? [];
        if (!$this->vazarCandidato) {
            return $base;
        }

        $out = [];
        foreach ($base as $row) {
            $row['candidato_inscricao_id'] = $this->candidatoVazadoId;
            $out[] = $row;
        }

        return $out;
    }

    public function obterDadosParaRecibo(int $votacaoId, string $eleitorHash): ?array
    {
        $key = $votacaoId . '|' . $eleitorHash;

        return $this->dadosRecibo[$key] ?? null;
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosHandler
 */
final class ListarHistoricoVotosHandlerTest extends TestCase
{
    private EleitorHasher $hasher;
    private FakeHistoricoVotosPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        // Secret determinístico para reproduzir hashes nos asserts.
        $this->hasher = new EleitorHasher(
            base64_encode(str_repeat("\x42", SODIUM_CRYPTO_GENERICHASH_KEYBYTES))
        );
        $this->port = new FakeHistoricoVotosPort();
    }

    /**
     * Agente com 2 votos em 2 votações distintas — handler retorna 2 itens
     * com whitelist correta. NUNCA inclui candidato.
     */
    public function testListaDoisVotosSemCandidato(): void
    {
        $agenteId = 42;

        $hash1 = $this->hasher->hash($agenteId, 100);
        $hash2 = $this->hasher->hash($agenteId, 200);

        $this->port->fatosPorHash[$hash1] = [
            ['votacao_id' => 100, 'categoria_id' => 11, 'votado_em' => '2026-06-10 12:00:00'],
        ];
        $this->port->fatosPorHash[$hash2] = [
            ['votacao_id' => 200, 'categoria_id' => 21, 'votado_em' => '2026-07-10 12:00:00'],
        ];

        $handler = new ListarHistoricoVotosHandler(
            $this->hasher,
            $this->port,
            static fn (int $a) => [100, 200],
            function (int $v) {
                if ($v === 100) {
                    return ['edital_titulo' => 'Edital A', 'categorias' => [11 => 'Cat Norte']];
                }
                if ($v === 200) {
                    return ['edital_titulo' => 'Edital B', 'categorias' => [21 => 'Cat Sul']];
                }

                return null;
            }
        );

        $items = $handler->handle(new ListarHistoricoVotosCommand($agenteId));

        self::assertCount(2, $items);

        // CRÍTICO: assert chaves de cada item — apenas whitelist.
        foreach ($items as $it) {
            self::assertSame(
                ['votacao_id', 'edital_titulo', 'categoria_nome', 'votado_em', 'recibo_recuperavel'],
                array_keys($it),
                'Cada item deve ter apenas as chaves da whitelist.'
            );
            self::assertArrayNotHasKey('candidato_inscricao_id', $it);
            self::assertArrayNotHasKey('eleitor_hash', $it);
            self::assertArrayNotHasKey('candidato', $it);
        }

        // Valores corretos
        self::assertSame(100, $items[0]['votacao_id']);
        self::assertSame('Edital A', $items[0]['edital_titulo']);
        self::assertSame('Cat Norte', $items[0]['categoria_nome']);
        self::assertTrue($items[0]['recibo_recuperavel']);
    }

    /**
     * CRÍTICO (defesa em profundidade): mesmo se o Port retornar
     * `candidato_inscricao_id` por bug, o handler **não propaga**.
     */
    public function testNaoPropagaCandidatoMesmoSePortVazar(): void
    {
        $agenteId = 42;
        $hash     = $this->hasher->hash($agenteId, 100);

        $this->port->vazarCandidato     = true;
        $this->port->candidatoVazadoId  = 999777;
        $this->port->fatosPorHash[$hash] = [
            ['votacao_id' => 100, 'categoria_id' => 11, 'votado_em' => '2026-06-10 12:00:00'],
        ];

        $handler = new ListarHistoricoVotosHandler(
            $this->hasher,
            $this->port,
            static fn (int $a) => [100],
            static fn (int $v) => ['edital_titulo' => 'E', 'categorias' => [11 => 'C']]
        );

        $items = $handler->handle(new ListarHistoricoVotosCommand($agenteId));
        self::assertCount(1, $items);

        // Assert por chaves
        self::assertArrayNotHasKey('candidato_inscricao_id', $items[0]);

        // Assert MAIS FORTE: o número 999777 NUNCA pode aparecer no payload
        // serializado (mesmo embutido em outro campo). Encoded como string.
        $json = (string) json_encode($items);
        self::assertStringNotContainsString('999777', $json);
        self::assertStringNotContainsString('candidato_inscricao_id', $json);
        self::assertStringNotContainsString('candidato', $json);
    }

    /**
     * Eleitor_hash deve ser determinístico — mesma (agenteId, votacaoId)
     * sempre gera mesmo hash. Verifica que o handler usa esse hash de forma
     * coerente: dois handles devolvem o mesmo conjunto de votos.
     */
    public function testEleitorHashDeterministico(): void
    {
        $agenteId = 42;
        $hash     = $this->hasher->hash($agenteId, 100);

        // Garante hash determinístico — invariante do hasher.
        $hash2 = $this->hasher->hash($agenteId, 100);
        self::assertTrue(hash_equals($hash, $hash2));

        $this->port->fatosPorHash[$hash] = [
            ['votacao_id' => 100, 'categoria_id' => 11, 'votado_em' => '2026-06-10 12:00:00'],
        ];

        $handler = new ListarHistoricoVotosHandler(
            $this->hasher,
            $this->port,
            static fn (int $a) => [100],
            static fn (int $v) => ['edital_titulo' => 'E', 'categorias' => [11 => 'C']]
        );

        $items1 = $handler->handle(new ListarHistoricoVotosCommand($agenteId));
        $items2 = $handler->handle(new ListarHistoricoVotosCommand($agenteId));
        self::assertSame($items1, $items2);
    }

    /**
     * Agente sem votos → array vazio.
     */
    public function testAgenteSemVotosRetornaVazio(): void
    {
        $handler = new ListarHistoricoVotosHandler(
            $this->hasher,
            $this->port,
            static fn (int $a) => [100, 200],
            static fn (int $v) => ['edital_titulo' => 'E', 'categorias' => []]
        );

        $items = $handler->handle(new ListarHistoricoVotosCommand(42));
        self::assertSame([], $items);
    }

    /**
     * Agente sem nenhuma votação elegível → array vazio (não chama o port).
     */
    public function testSemVotacoesElegiveisRetornaVazio(): void
    {
        $handler = new ListarHistoricoVotosHandler(
            $this->hasher,
            $this->port,
            static fn (int $a) => [],
            static fn (int $v) => null
        );

        $items = $handler->handle(new ListarHistoricoVotosCommand(42));
        self::assertSame([], $items);
    }

    /**
     * Port retorna fato com `votacao_id` diferente do esperado → handler
     * descarta (defesa contra port bagunçado).
     */
    public function testIgnoraFatosDeVotacaoErrada(): void
    {
        $agenteId = 42;
        $hash     = $this->hasher->hash($agenteId, 100);

        $this->port->fatosPorHash[$hash] = [
            ['votacao_id' => 999, 'categoria_id' => 11, 'votado_em' => '2026-06-10 12:00:00'],
        ];

        $handler = new ListarHistoricoVotosHandler(
            $this->hasher,
            $this->port,
            static fn (int $a) => [100],
            static fn (int $v) => ['edital_titulo' => 'E', 'categorias' => []]
        );

        $items = $handler->handle(new ListarHistoricoVotosCommand($agenteId));
        self::assertSame([], $items);
    }
}
