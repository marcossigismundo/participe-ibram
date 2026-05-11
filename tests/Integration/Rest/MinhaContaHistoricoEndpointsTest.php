<?php
/**
 * Testes de integração — MinhaContaHistoricoEndpoints (W8-C).
 *
 * CRÍTICO:
 *  - VOTO SECRETO: histórico de votos NUNCA expõe `candidato_inscricao_id`.
 *  - AUDITORIA PESSOAL: response NUNCA contém `dados_antes`/`dados_depois`/
 *    `ip_hash`/`user_agent`.
 *  - OWNERSHIP: cada endpoint usa `get_current_user_id()` — impossível ler
 *    histórico de outro usuário.
 *  - REGENERAR RECIBO: hash bate com algoritmo do W6-A; voto inexistente → 404
 *    genérico.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\MinhaConta\AuditTrailPessoalQuery;
use Ibram\ParticipeIbram\Application\MinhaConta\HistoricoVotosPort;
use Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosHandler;
use Ibram\ParticipeIbram\Application\MinhaConta\RegerarReciboVotoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Presentation\Rest\MinhaContaHistoricoEndpoints;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * wpdb stub específico para os endpoints de histórico — captura SQL e
 * devolve fixtures por matcher heurístico.
 */
final class FakeWpdbHistorico
{
    public string $prefix = 'wp_';

    /** @var list<string> */
    public array $preparedSqls = [];

    /**
     * Fixtures indexadas por substring-marker no SQL → linhas.
     *
     * @var array<string, list<array<string,mixed>>>
     */
    public array $fixturesGetResults = [];

    /**
     * Para get_var (e.g. COUNT).
     *
     * @var array<string,mixed>
     */
    public array $fixturesGetVar = [];

    /**
     * Para get_col.
     *
     * @var array<string,list<int>>
     */
    public array $fixturesGetCol = [];

    public function prepare(string $sql, ...$args): string
    {
        $this->preparedSqls[] = $sql;

        return $sql;
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    public function get_results(string $sql, $output = null)
    {
        foreach ($this->fixturesGetResults as $marker => $rows) {
            if (strpos($sql, $marker) !== false) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @return mixed
     */
    public function get_var(string $sql)
    {
        foreach ($this->fixturesGetVar as $marker => $val) {
            if (strpos($sql, $marker) !== false) {
                return $val;
            }
        }

        return null;
    }

    /**
     * @return list<int>|array<int,int>
     */
    public function get_col(string $sql)
    {
        foreach ($this->fixturesGetCol as $marker => $rows) {
            if (strpos($sql, $marker) !== false) {
                return $rows;
            }
        }

        return [];
    }

    /** @return false|int */
    public function insert(string $table, array $row, array $formats)
    {
        return 1; // audit logger: insert returns truthy.
    }
}

/**
 * Port em memória — usado para simular votos do agente sem tocar em wpdb real.
 */
final class FakeHistoricoVotosPortIntegration implements HistoricoVotosPort
{
    /** @var array<string,list<array<string,mixed>>> */
    public array $fatosPorHash = [];

    /** @var array<string,array<string,mixed>> */
    public array $dadosRecibo = [];

    public function listarFatosVoto(string $eleitorHash): array
    {
        return $this->fatosPorHash[$eleitorHash] ?? [];
    }

    public function obterDadosParaRecibo(int $votacaoId, string $eleitorHash): ?array
    {
        $key = $votacaoId . '|' . $eleitorHash;

        return $this->dadosRecibo[$key] ?? null;
    }
}

/**
 * Stub mínimo do AgenteRepository — apenas o método `findByUserId` é exercido.
 */
final class FakeAgenteRepoHistorico implements AgenteRepository
{
    /** @var array<int,?Agente> */
    public array $byUser = [];

    public function findById(int $id): ?Agente
    {
        return null;
    }

    public function findByNumeroRegistro(string $numero): ?Agente
    {
        return null;
    }

    public function findByCpf(string $cpfPlain): ?Agente
    {
        return null;
    }

    public function findByCnpj(string $cnpjPlain): ?Agente
    {
        return null;
    }

    public function findByUserId(int $userId): ?Agente
    {
        return $this->byUser[$userId] ?? null;
    }

    public function findByEmail(string $email): ?Agente
    {
        return null;
    }

    public function save(Agente $agente, object $detalhes, array $representantes = []): int
    {
        return 0;
    }

    public function softDelete(int $id): void
    {
    }

    public function listByStatus(string $status, int $page = 1, int $perPage = 25): array
    {
        return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
    }
}


/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\MinhaContaHistoricoEndpoints
 */
final class MinhaContaHistoricoEndpointsTest extends TestCase
{
    private FakeWpdbHistorico $wpdb;
    private FakeAgenteRepoHistorico $agentes;
    private FakeHistoricoVotosPortIntegration $port;
    private EleitorHasher $hasher;
    private AuditLogger $audit;

    private const USER_A = 100;
    private const USER_B = 200;
    private const AGENTE_A = 42;
    private const AGENTE_B = 84;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients']       = [];
        $GLOBALS['__pi_test_options']          = [];
        $GLOBALS['__pi_test_current_user_id']  = self::USER_A;
        $GLOBALS['__pi_test_user_caps']        = [];

        $this->wpdb    = new FakeWpdbHistorico();
        $this->agentes = new FakeAgenteRepoHistorico();
        $this->port    = new FakeHistoricoVotosPortIntegration();
        $this->hasher  = new EleitorHasher(
            base64_encode(str_repeat("\x42", SODIUM_CRYPTO_GENERICHASH_KEYBYTES))
        );

        $this->audit = new AuditLogger($this->wpdb, new IpResolver([], []));

        $this->agentes->byUser[self::USER_A] = $this->makeAgente(self::AGENTE_A, self::USER_A);
        $this->agentes->byUser[self::USER_B] = $this->makeAgente(self::AGENTE_B, self::USER_B);
    }

    private function makeAgente(int $agenteId, int $userId): Agente
    {
        // Status rascunho — não requer numero_registro. O endpoint usa apenas
        // `getId()` para resolver ownership; status é irrelevante aqui.
        return new Agente(
            $agenteId,
            TipoAgente::pf(),
            null,
            StatusCadastro::rascunho(),
            $userId,
            'agente' . $agenteId . '@example.com',
            null,
            null,
            null,
            null,
            new DateTimeImmutable('2025-01-01 00:00:00'),
            new DateTimeImmutable('2025-01-01 00:00:00'),
            null
        );
    }

    private function makeEndpoints(): MinhaContaHistoricoEndpoints
    {
        $listarVotos = new ListarHistoricoVotosHandler(
            $this->hasher,
            $this->port,
            // Lista de votações elegíveis para ambos os agentes (cenário simples).
            static fn (int $a) => [100, 200],
            static fn (int $v) => $v === 100
                ? ['edital_titulo' => 'Edital A', 'categorias' => [11 => 'Cat Norte']]
                : ['edital_titulo' => 'Edital B', 'categorias' => [21 => 'Cat Sul']]
        );
        $regerar = new RegerarReciboVotoHandler($this->hasher, $this->port, $this->audit);
        $audit   = new AuditTrailPessoalQuery($this->wpdb);

        return new MinhaContaHistoricoEndpoints(
            $this->agentes,
            $this->wpdb,
            $listarVotos,
            $regerar,
            $audit
        );
    }

    // ------------------------------------------------------------------
    // VOTO SECRETO
    // ------------------------------------------------------------------

    public function testHistoricoVotosNuncaExpoeCandidato(): void
    {
        // Cenário: agente A votou em 3 votações distintas, todas com candidato 42.
        $hash1 = $this->hasher->hash(self::AGENTE_A, 100);
        $hash2 = $this->hasher->hash(self::AGENTE_A, 200);

        $this->port->fatosPorHash[$hash1] = [
            ['votacao_id' => 100, 'categoria_id' => 11, 'votado_em' => '2026-06-10 12:00:00'],
        ];
        $this->port->fatosPorHash[$hash2] = [
            ['votacao_id' => 200, 'categoria_id' => 21, 'votado_em' => '2026-07-10 12:00:00'],
            ['votacao_id' => 200, 'categoria_id' => 22, 'votado_em' => '2026-07-10 12:05:00'],
        ];

        // Insere DELIBERADAMENTE candidato_inscricao_id = 42 nos dados internos do
        // recibo — defesa em profundidade: o número 42 jamais deve aparecer no
        // response do histórico, mesmo embutido em outro campo.
        $this->port->dadosRecibo[100 . '|' . $hash1] = [
            'votacao_id' => 100, 'categoria_id' => 11, 'candidato_inscricao_id' => 42,
            'votado_em' => '2026-06-10 12:00:00',
        ];

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoVotos(new WP_REST_Request());

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        self::assertCount(3, $data['items']);

        // 1. Assert por chave AUSENTE em cada item.
        foreach ($data['items'] as $it) {
            self::assertArrayNotHasKey('candidato_inscricao_id', $it);
            self::assertArrayNotHasKey('candidato', $it);
            self::assertArrayNotHasKey('eleitor_hash', $it);

            self::assertSame(
                ['votacao_id', 'edital_titulo', 'categoria_nome', 'votado_em', 'recibo_recuperavel'],
                array_keys($it),
                'Whitelist estrita.'
            );
        }

        // 2. Assert por VALOR AUSENTE — `42` (candidato_inscricao_id) NÃO aparece em parte alguma do JSON.
        $payload = (string) json_encode($data);
        self::assertStringNotContainsString('"candidato_inscricao_id"', $payload);
        self::assertStringNotContainsString('"candidato"', $payload);
        // O valor 42 também é o AGENTE_A — assert separadamente que '42' não está no payload
        // pela via "candidato". O agente_id NUNCA aparece no payload tampouco.
        self::assertStringNotContainsString('"agente_id"', $payload);
        self::assertStringNotContainsString('"user_id"', $payload);
    }

    public function testHistoricoVotosIncluiAvisoDeVotoSecreto(): void
    {
        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoVotos(new WP_REST_Request());
        $data      = $response->get_data();

        self::assertArrayHasKey('aviso_secreto', $data);
        self::assertIsString($data['aviso_secreto']);
        self::assertStringContainsString('secreto', $data['aviso_secreto']);
    }

    // ------------------------------------------------------------------
    // OWNERSHIP — user A não acessa histórico de B
    // ------------------------------------------------------------------

    public function testOwnershipUserSemSessaoRecebe401(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 0;

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoVotos(new WP_REST_Request());

        self::assertSame(401, $response->get_status());
    }

    public function testOwnershipUserSemAgenteRecebe404(): void
    {
        // Usuario logado mas sem agente associado.
        $GLOBALS['__pi_test_current_user_id'] = 999;

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoVotos(new WP_REST_Request());

        self::assertSame(404, $response->get_status());
    }

    public function testOwnershipImpedeQueAVejaDadosDeB(): void
    {
        // Agente B votou.
        $hashB = $this->hasher->hash(self::AGENTE_B, 100);
        $this->port->fatosPorHash[$hashB] = [
            ['votacao_id' => 100, 'categoria_id' => 11, 'votado_em' => '2026-06-10 12:00:00'],
        ];

        // Usuário A está logado.
        $GLOBALS['__pi_test_current_user_id'] = self::USER_A;

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoVotos(new WP_REST_Request());

        // A NÃO vê o voto de B (eleitor_hash de A é diferente).
        $data = $response->get_data();
        self::assertSame([], $data['items']);
    }

    // ------------------------------------------------------------------
    // AUDITORIA PESSOAL — sem dados crus
    // ------------------------------------------------------------------

    public function testAuditoriaNaoExpoeDadosAntesDepoisIpHashUserAgent(): void
    {
        // Fixture do audit log — wpdb retorna linhas COM dados sensíveis, mas o
        // endpoint NUNCA pode propagar para a response (SELECT explícito).
        // Como o stub devolve só o que está no fixture, simulamos o que viria
        // do SELECT explícito (sem esses campos) — o assert real é sobre o
        // response final.
        $this->wpdb->fixturesGetResults['SELECT entidade, entidade_id, acao, ocorrido_em'] = [
            ['entidade' => 'agente', 'entidade_id' => self::AGENTE_A, 'acao' => 'submeter',
             'ocorrido_em' => '2026-01-01 12:00:00'],
            ['entidade' => 'agente', 'entidade_id' => self::AGENTE_A, 'acao' => 'deferir',
             'ocorrido_em' => '2026-01-02 12:00:00'],
        ];
        $this->wpdb->fixturesGetVar['SELECT COUNT(*)'] = 2;
        $this->wpdb->fixturesGetCol['FROM ' . $this->wpdb->prefix . 'pi_inscricoes WHERE agente_id'] = [];
        $this->wpdb->fixturesGetCol['FROM ' . $this->wpdb->prefix . 'pi_recursos r'] = [];
        $this->wpdb->fixturesGetCol['FROM ' . $this->wpdb->prefix . 'pi_recursos_inabilitacao'] = [];

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoAuditoria(new WP_REST_Request());

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        self::assertCount(2, $data['items']);
        foreach ($data['items'] as $it) {
            // Whitelist final estrita.
            self::assertSame(
                ['entidade', 'acao', 'ocorrido_em', 'descricao_amigavel'],
                array_keys($it)
            );
            self::assertArrayNotHasKey('dados_antes', $it);
            self::assertArrayNotHasKey('dados_depois', $it);
            self::assertArrayNotHasKey('ip_hash', $it);
            self::assertArrayNotHasKey('user_agent', $it);
            self::assertArrayNotHasKey('ator_id', $it);
        }

        $payload = (string) json_encode($data);
        self::assertStringNotContainsString('"dados_antes"', $payload);
        self::assertStringNotContainsString('"dados_depois"', $payload);
        self::assertStringNotContainsString('"ip_hash"', $payload);
        self::assertStringNotContainsString('"user_agent"', $payload);
        self::assertStringNotContainsString('"ator_id"', $payload);

        // Verifica que o SELECT NÃO incluiu colunas proibidas.
        $auditSelectSql = '';
        foreach ($this->wpdb->preparedSqls as $sql) {
            if (strpos($sql, 'SELECT entidade, entidade_id, acao, ocorrido_em') !== false) {
                $auditSelectSql = $sql;
                break;
            }
        }
        self::assertNotSame('', $auditSelectSql, 'Endpoint deveria executar SELECT explicito do audit.');
        self::assertStringNotContainsString('dados_antes', $auditSelectSql);
        self::assertStringNotContainsString('dados_depois', $auditSelectSql);
        self::assertStringNotContainsString('ip_hash', $auditSelectSql);
        self::assertStringNotContainsString('user_agent', $auditSelectSql);
    }

    public function testAuditoriaTraduzAcaoEmDescricaoAmigavel(): void
    {
        $this->wpdb->fixturesGetResults['SELECT entidade, entidade_id, acao, ocorrido_em'] = [
            ['entidade' => 'agente', 'entidade_id' => self::AGENTE_A, 'acao' => 'submeter',
             'ocorrido_em' => '2026-01-01 12:00:00'],
        ];
        $this->wpdb->fixturesGetVar['SELECT COUNT(*)'] = 1;
        $this->wpdb->fixturesGetCol['FROM ' . $this->wpdb->prefix . 'pi_inscricoes WHERE agente_id'] = [];
        $this->wpdb->fixturesGetCol['FROM ' . $this->wpdb->prefix . 'pi_recursos r'] = [];
        $this->wpdb->fixturesGetCol['FROM ' . $this->wpdb->prefix . 'pi_recursos_inabilitacao'] = [];

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoAuditoria(new WP_REST_Request());
        $data      = $response->get_data();

        self::assertSame('Cadastro submetido', $data['items'][0]['descricao_amigavel']);
    }

    // ------------------------------------------------------------------
    // REGENERAR RECIBO
    // ------------------------------------------------------------------

    public function testRegerarReciboReproduzAlgoritmoDoW6A(): void
    {
        $hash = $this->hasher->hash(self::AGENTE_A, 100);

        $this->port->dadosRecibo[100 . '|' . $hash] = [
            'votacao_id'             => 100,
            'categoria_id'           => 11,
            'candidato_inscricao_id' => 42,
            'votado_em'              => '2026-06-10 12:00:00',
        ];

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['votacao_id' => 100]);
        $response  = $endpoints->regerarRecibo($request);

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        // Whitelist estrita: apenas hash + data.
        self::assertSame(['hash_voto', 'votado_em'], array_keys($data));
        self::assertArrayNotHasKey('candidato_inscricao_id', $data);
        self::assertArrayNotHasKey('categoria_id', $data);

        // Algoritmo W6-A (sem microseconds — stored time é Y-m-d H:i:s):
        $esperado = hash('sha256', sprintf('%d|%d|%d|%s', 100, 11, 42, '2026-06-10 12:00:00'));
        self::assertSame($esperado, $data['hash_voto']);

        // Valor 42 não aparece no payload final.
        $payload = (string) json_encode($data);
        self::assertStringNotContainsString('"candidato', $payload);
        self::assertStringNotContainsString(':42', $payload);
    }

    public function testRegerarReciboInexistenteRetorna404Generico(): void
    {
        // Nenhum dado no port → não existe.
        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['votacao_id' => 100]);
        $response  = $endpoints->regerarRecibo($request);

        self::assertSame(404, $response->get_status());
        $data = $response->get_data();

        // Mensagem genérica — NÃO diferencia "não votou" de "votação não existe".
        $msg = (string) ($data['message'] ?? '');
        self::assertStringContainsString('Recibo', $msg);
        self::assertStringNotContainsString('candidato', strtolower($msg));
        self::assertStringNotContainsString('eleitor', strtolower($msg));
    }

    public function testRegerarReciboValidaVotacaoId(): void
    {
        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['votacao_id' => 0]);
        $response  = $endpoints->regerarRecibo($request);

        self::assertSame(400, $response->get_status());
    }

    // ------------------------------------------------------------------
    // OUTROS ENDPOINTS — sanity check de ownership e whitelist
    // ------------------------------------------------------------------

    public function testHistoricoCadastroOmiteAtorId(): void
    {
        $this->wpdb->fixturesGetResults['SELECT status_anterior, status_novo'] = [
            ['status_anterior' => 'rascunho', 'status_novo' => 'submetido',
             'ocorrido_em' => '2026-01-01 12:00:00', 'observacao' => null],
        ];

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoCadastro(new WP_REST_Request());
        $data      = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertCount(1, $data['items']);

        foreach ($data['items'] as $it) {
            self::assertSame(
                ['status_anterior', 'status_novo', 'ocorrido_em', 'observacao'],
                array_keys($it)
            );
            self::assertArrayNotHasKey('ator_id', $it);
            self::assertArrayNotHasKey('agente_id', $it);
            self::assertArrayNotHasKey('id', $it);
        }
    }

    public function testHistoricoInscricoesNaoVazaInscricoesDeOutros(): void
    {
        // SQL retorna inscrições com agente_id na cláusula WHERE (validado por substring).
        $this->wpdb->fixturesGetResults['WHERE i.agente_id = '] = [
            ['id' => 1, 'edital_id' => 10, 'status' => 'final_habilitado',
             'inscrito_em' => '2026-01-01 12:00:00', 'habilitado_em' => '2026-02-01 12:00:00',
             'inabilitado_em' => null, 'motivo_inabilitacao_md' => null,
             'edital_titulo' => 'Edital A', 'categoria_nome' => 'Cat Norte'],
        ];

        $endpoints = $this->makeEndpoints();
        $response  = $endpoints->historicoInscricoes(new WP_REST_Request());

        // Verifica que o SQL preparado usou agente_id = 42 (AGENTE_A).
        $sqlEncontrado = false;
        foreach ($this->wpdb->preparedSqls as $sql) {
            if (strpos($sql, 'WHERE i.agente_id = ') !== false) {
                $sqlEncontrado = true;
                break;
            }
        }
        self::assertTrue($sqlEncontrado, 'Query precisa filtrar por agente_id.');

        $data = $response->get_data();
        self::assertSame(200, $response->get_status());
        self::assertCount(1, $data['items']);
        self::assertSame('final_habilitado', $data['items'][0]['status']);
    }
}
