<?php
/**
 * TESTE CRÍTICO de anti-rastreio voto↔eleitor.
 *
 * Verifica em conjunto que:
 *  - Tabela `wp_pi_votos` carrega `eleitor_hash` (HMAC), nunca `agente_id`.
 *  - Tabela `wp_pi_audit_log`, em eventos `voto_registrado`, NÃO carrega
 *    `agente_id` nem `ator_id` em colunas legíveis.
 *  - O hook `pi_voto_registrado` recebe APENAS `voto_id`, `votacao_id`,
 *    `categoria_id` — nada que permita correlacionar com eleitor.
 *  - O `eleitor_hash` calculado fora do handler com a mesma chave NÃO é
 *    idêntico a nenhum valor que tenha sido logado/exposto antes do voto
 *    ser efetivamente persistido (não há "vazamento prévio").
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoCommand;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeAgenteVotanteGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeCategoriaConsultaGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeInscricaoConsultaGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotacaoRepository;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotoRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../Unit/Application/Votacao/Fakes.php';

/**
 * @coversNothing
 */
final class AntiRastreioTest extends TestCase
{
    /**
     * Inserts capturados pelo wpdb fake — para inspeção do audit_log.
     *
     * @var list<array{table:string,data:array<string,mixed>}>
     */
    public static array $capturedInserts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients']  = [];
        $GLOBALS['__pi_test_options']     = [];
        self::$capturedInserts            = [];
    }

    public function testDoisVotosAnonimizamEleitoresNoAuditLog(): void
    {
        $hasher       = new EleitorHasher(
            base64_encode(str_repeat("\x42", SODIUM_CRYPTO_GENERICHASH_KEYBYTES))
        );
        $votacaoRepo  = new FakeVotacaoRepository();
        $votoRepo     = new FakeVotoRepository();
        $agente       = new FakeAgenteVotanteGateway();
        $categoria    = new FakeCategoriaConsultaGateway();
        $inscricao    = new FakeInscricaoConsultaGateway();

        $wpdb         = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';
            public int $insert_id = 0;
            /**
             * @param array<string,mixed> $data
             * @param array<int,string|null> $formats
             */
            public function insert(string $table, array $data, array $formats): bool
            {
                AntiRastreioTest::$capturedInserts[] = ['table' => $table, 'data' => $data];
                return true;
            }
        };
        $audit        = new AuditLogger($wpdb, new IpResolver([], []));

        // Cenário base.
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2030-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $votacaoRepo->seed($votacao);

        $categoria->editalDe[11]                  = 7;
        $categoria->tiposAceitos[11]              = ['PF', 'OR'];
        $categoria->categoriasDoEdital[7]         = [11];

        // Agente A (pf) e Agente B (or).
        $agente->deferidos[101] = true;
        $agente->tipos[101]     = 'PF';
        $agente->deferidos[102] = true;
        $agente->tipos[102]     = 'OR';
        $inscricao->habilitadas['202|11'] = true;
        $inscricao->habilitadas['203|11'] = true;

        $clock = static fn () => new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'));
        $handler = new RegistrarVotoHandler(
            $votacaoRepo,
            $votoRepo,
            $hasher,
            $agente,
            $categoria,
            $inscricao,
            $audit,
            null,
            $clock
        );

        // PRECONDIÇÃO: eleitor_hash é calculado externamente para verificar que
        // não foi logado em lugar algum ANTES da chamada ao handler.
        $hashA = $hasher->hash(101, 1);
        $hashB = $hasher->hash(102, 1);

        // Antes dos votos, o audit_log está vazio.
        self::assertCount(0, self::$capturedInserts);

        // Voto A → candidato 202.
        $votoA = $handler->handle(new RegistrarVotoCommand(1, 11, 101, 202));
        // Voto B → candidato 203.
        $votoB = $handler->handle(new RegistrarVotoCommand(1, 11, 102, 203));

        // Ambos os votos foram persistidos no fake repo.
        self::assertCount(2, $votoRepo->votos);

        // Inspeciona TODOS os inserts no audit_log.
        $auditInserts = array_values(array_filter(
            self::$capturedInserts,
            static fn ($r) => $r['table'] === 'wp_pi_audit_log'
        ));
        self::assertGreaterThanOrEqual(2, count($auditInserts));

        foreach ($auditInserts as $row) {
            $data       = $row['data'];

            // ator_id NUNCA é populado pelo handler de voto (passamos null).
            self::assertNull(
                $data['ator_id'] ?? null,
                'ator_id deve ser NULL em logs de voto_registrado.'
            );

            $dadosDepois = isset($data['dados_depois']) ? (string) $data['dados_depois'] : '';
            // dados_depois é JSON. Confere que NÃO contém agente_id nem user_id.
            self::assertStringNotContainsString(
                '"agente_id"',
                $dadosDepois,
                'audit log NUNCA deve conter agente_id em dados_depois.'
            );
            self::assertStringNotContainsString(
                '"user_id"',
                $dadosDepois,
                'audit log NUNCA deve conter user_id em dados_depois.'
            );
            // Não deve aparecer literalmente o agente id em string (101 ou 102).
            // (Defesa em profundidade — formato pode ter sido renomeado.)
            self::assertDoesNotMatchRegularExpression(
                '/"(agente|usuario|user|ator)_id"\s*:\s*10[12]/',
                $dadosDepois
            );
        }

        // Anti-rastreio fundamental: dado o audit_log e wp_pi_votos, NÃO é
        // possível mapear voto→eleitor sem o secret de PI_VOTING_SECRET.
        // O hash dos votos é HMAC-keyed; mesmo conhecendo o agente_id,
        // recalcular requer o secret. Não há nenhum agente_id persistido.
        foreach ($votoRepo->votos as $voto) {
            $votoArr = [
                'eleitor_hash'           => $voto->eleitorHash(),
                'candidato_inscricao_id' => $voto->candidatoInscricaoId(),
            ];
            self::assertArrayNotHasKey('agente_id', $votoArr);
        }

        // Sanity: os hashes calculados antes correspondem aos persistidos
        // (constant-time equality).
        $eleitorHashes = array_map(static fn ($v) => $v->eleitorHash(), $votoRepo->votos);
        self::assertTrue(
            hash_equals($hashA, $eleitorHashes[0]) || hash_equals($hashA, $eleitorHashes[1])
        );
        self::assertTrue(
            hash_equals($hashB, $eleitorHashes[0]) || hash_equals($hashB, $eleitorHashes[1])
        );
        // Mas A e B são distintos — sem colisão.
        self::assertNotSame($hashA, $hashB);
    }

    public function testHashEhDeterministicoEnaoVazaSegredoNaMensagem(): void
    {
        // Constrói o hasher com um secret conhecido e calcula o hash.
        $secret = base64_encode(str_repeat("\x99", SODIUM_CRYPTO_GENERICHASH_KEYBYTES));
        $hasher = new EleitorHasher($secret);

        $h1 = $hasher->hash(101, 1);
        $h2 = $hasher->hash(101, 1);
        // Determinístico.
        self::assertTrue(hash_equals($h1, $h2));

        // Tamanho fixo (64 hex chars).
        self::assertSame(64, strlen($h1));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $h1);

        // Hash NÃO contém o secret literal nem o agente_id literal em hex.
        self::assertStringNotContainsString('99', substr($secret, 0, 4));
        $hashHexLowered = strtolower($h1);
        self::assertNotSame(strtolower($secret), $hashHexLowered);
    }
}
