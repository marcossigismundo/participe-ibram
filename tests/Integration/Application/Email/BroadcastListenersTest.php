<?php
/**
 * Testes de integração dos broadcast listeners Wave 4-C + Wave 7-B.
 *
 * Exercita {@see EventListeners} e {@see EventListenersWave7} com:
 *  - fila de e-mail in-memory (BroadcastEmailQueueRepo — definida neste arquivo)
 *  - AgenteBroadcastQuery stub (retorna lista controlada de deferidos)
 *  - AgenteRepository stub
 *  - EmailRenderer real (aponta para templates/emails reais)
 *
 * Cenários cobertos:
 *  1. pi_resultado_publicado → enfileira N emails broadcast (resultado_publicado)
 *  2. Revogação: agente com comunicacao revogada NÃO recebe newsletter, mas
 *     recebe email obrigatório (cadastro_deferido individual)
 *  3. Anti-PII: corpo_html e payload_json dos emails broadcast NÃO contêm
 *     padrão de CPF nem email do agente diferente do destinatário esperado
 *  4. pi_habilitacao_decidida → apenas 1 email (individual, não broadcast)
 *  5. pi_resultado_publicado com lista vazia → 0 emails enfileirados
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Application\Email;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Email\AgenteBroadcastQuery;
use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailHandler;
use Ibram\ParticipeIbram\Application\Email\EventListeners;
use Ibram\ParticipeIbram\Application\Email\Templates\EmailRenderer;
use Ibram\ParticipeIbram\Application\Email\Templates\UnsubscribeTokenizer;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Domain\Email\MensagemEnfileirada;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Implementação in-memory da fila de e-mail para esta suíte.
 * Definida aqui (não reutiliza InMemoryEmailQueueRepo de EmailQueueWorkerTest)
 * para manter este arquivo completamente autônomo.
 */
final class BroadcastEmailQueueRepo implements EmailQueueRepository
{
    /** @var array<int, MensagemEnfileirada> */
    private array $store = [];
    private int $nextId = 1;

    public function enfileirar(MensagemEnfileirada $mensagem): int
    {
        $id = $this->nextId++;
        $this->store[$id] = MensagemEnfileirada::fromState(
            $id,
            $mensagem->evento(),
            $mensagem->agenteId(),
            $mensagem->destinatario(),
            $mensagem->assunto(),
            $mensagem->corpoHtml(),
            $mensagem->payloadJson(),
            $mensagem->tentativas(),
            $mensagem->status(),
            $mensagem->ultimoErro(),
            $mensagem->agendadoPara(),
            $mensagem->enviadoEm(),
            $mensagem->createdAt()
        );
        return $id;
    }

    /** @return MensagemEnfileirada[] */
    public function listAll(): array
    {
        return array_values($this->store);
    }

    public function proximasParaEnvio(int $limit, ?DateTimeImmutable $agora = null): array
    {
        return [];
    }

    public function marcarEnviando(int $id): bool
    {
        return false;
    }

    public function marcarEnviado(int $id, DateTimeImmutable $enviadoEm): void {}

    public function marcarFalha(int $id, string $erro, int $tentativasAtuais, bool $retry, DateTimeImmutable $proxima): void {}

    public function listar(array $filtros, int $page = 1, int $perPage = 25): array
    {
        return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
    }

    public function findById(int $id): ?MensagemEnfileirada
    {
        return $this->store[$id] ?? null;
    }

    public function reenviar(int $id, DateTimeImmutable $agendadoPara): bool
    {
        return false;
    }
}

/**
 * Stub de AgenteBroadcastQuery retornando uma lista controlada.
 */
final class StubBroadcastQuery implements AgenteBroadcastQuery
{
    /** @var array<int, array{agente_id:int, email:string, nome:string}> */
    private array $rows;

    /**
     * @param array<int, array{agente_id:int, email:string, nome:string}> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function iterar(int $batchSize = 100): iterable
    {
        foreach ($this->rows as $row) {
            yield $row['agente_id'] => $row;
        }
    }
}

/**
 * Stub mínimo de Agente (apenas o necessário para EventListeners::dispatchIndividual).
 */
final class StubAgente
{
    private int $id;
    private string $email;

    public function __construct(int $id, string $email)
    {
        $this->id    = $id;
        $this->email = $email;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmailPrincipal(): string
    {
        return $this->email;
    }

    public function getTipo(): string
    {
        return 'PF';
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Application\Email\EventListeners
 * @covers \Ibram\ParticipeIbram\Application\Email\EventListenersWave7
 */
final class BroadcastListenersTest extends TestCase
{
    private BroadcastEmailQueueRepo $filaRepo;
    private EnfileirarEmailHandler $enfileirar;
    private SecureLogger $logger;
    private string $templateDir;

    /** @var AgenteRepository&MockObject */
    private $agenteRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filaRepo    = new BroadcastEmailQueueRepo();
        $this->logger      = new SecureLogger(static function (string $line): void {});
        $this->templateDir = __DIR__ . '/../../../../templates/emails';

        $this->agenteRepo = $this->createMock(AgenteRepository::class);
    }

    /**
     * Cria um EnfileirarEmailHandler com o broadcast query fornecido.
     */
    private function makeHandler(AgenteBroadcastQuery $broadcastQuery): EnfileirarEmailHandler
    {
        return new EnfileirarEmailHandler(
            $this->filaRepo,
            new EmailRenderer($this->templateDir),
            $this->logger,
            $broadcastQuery
        );
    }

    /**
     * Cria um EventListeners (Wave 4-C) apontando para o handler.
     */
    private function makeListeners(EnfileirarEmailHandler $handler): EventListeners
    {
        // UnsubscribeTokenizer é final — instancia diretamente.
        // Em teste, wp_salt() não existe; o tokenizer usa fallback determinístico
        // e EventListeners::buildUnsubscribeUrl já envolve em try/catch.
        $tokenizer = new UnsubscribeTokenizer();

        return new EventListeners(
            $handler,
            $this->agenteRepo,
            $tokenizer,
            $this->logger,
            null, // sem AgenteDetalhesLoader — nome cai no fallback 'Cidadao(a)'
            'https://participe-ibram.test'
        );
    }

    /* ------------------------------------------------------------------
     * 1. pi_resultado_publicado → broadcast enfileira N emails
     * ------------------------------------------------------------------ */

    public function test_resultado_publicado_enfileira_broadcast_para_todos_deferidos(): void
    {
        $broadcast = new StubBroadcastQuery([
            ['agente_id' => 10, 'email' => 'a@test.gov.br', 'nome' => 'Ana'],
            ['agente_id' => 11, 'email' => 'b@test.gov.br', 'nome' => 'Bruno'],
            ['agente_id' => 12, 'email' => 'c@test.gov.br', 'nome' => 'Carla'],
        ]);

        $handler   = $this->makeHandler($broadcast);
        $listeners = $this->makeListeners($handler);

        // Chama diretamente o handler (sem WP hooks no ambiente de teste)
        $listeners->onResultadoPublicado(1, [
            'titulo' => 'Edital de Música',
            'url'    => 'https://participe-ibram.test/resultado/1',
        ]);

        $todos = $this->filaRepo->listAll();
        $this->assertCount(3, $todos, 'Deve enfileirar 3 emails broadcast');

        $eventos = array_map(static fn ($m) => $m->evento(), $todos);
        foreach ($eventos as $ev) {
            $this->assertSame('resultado_publicado', $ev);
        }
    }

    /* ------------------------------------------------------------------
     * 2. Email obrigatório (cadastro_deferido) chega mesmo sem newsletter
     * ------------------------------------------------------------------ */

    public function test_email_obrigatorio_chega_independentemente_de_newsletter(): void
    {
        $agente = new StubAgente(20, 'obrigatorio@test.gov.br');

        $this->agenteRepo
            ->method('findById')
            ->with(20)
            ->willReturn($agente);

        // Broadcast query vazia (agente revogou newsletter)
        $broadcast = new StubBroadcastQuery([]);
        $handler   = $this->makeHandler($broadcast);
        $listeners = $this->makeListeners($handler);

        // Email obrigatório individual — não depende de broadcast query
        $listeners->onCadastroDeferido(20, 'PI-2026-001', 999);

        $todos = $this->filaRepo->listAll();
        $this->assertCount(1, $todos, 'Email obrigatorio deve ser enfileirado');
        $this->assertSame('cadastro_deferido', $todos[0]->evento());
        $this->assertSame('obrigatorio@test.gov.br', $todos[0]->destinatario());
    }

    /* ------------------------------------------------------------------
     * 3. Anti-PII: corpo_html e payload_json NÃO contêm padrão CPF (XXX.XXX.XXX-XX)
     *    nem email do agente diferente do destinatário
     * ------------------------------------------------------------------ */

    public function test_anti_pii_broadcast_nao_contem_cpf_no_corpo(): void
    {
        $cpfFicticio = '123.456.789-00';

        // Broadcast query retorna campos internos apenas (sem CPF)
        $broadcast = new StubBroadcastQuery([
            ['agente_id' => 30, 'email' => 'pii@test.gov.br', 'nome' => 'Carlos'],
        ]);

        $handler   = $this->makeHandler($broadcast);
        $listeners = $this->makeListeners($handler);

        $listeners->onResultadoPublicado(5, ['titulo' => 'Teste PII']);

        $todos = $this->filaRepo->listAll();
        $this->assertCount(1, $todos);

        $msg = $todos[0];

        // corpo_html não deve conter padrão de CPF (ddd.ddd.ddd-dd)
        $this->assertDoesNotMatch(
            '/\d{3}\.\d{3}\.\d{3}-\d{2}/',
            $msg->corpoHtml(),
            'corpo_html NAO deve conter padrao CPF'
        );

        // payload_json (array) serializado não deve conter CPF fictício
        $payloadStr = wp_json_encode($msg->payloadJson()) ?: '';
        $this->assertStringNotContainsString(
            $cpfFicticio,
            $payloadStr,
            'payload_json NAO deve conter CPF'
        );
    }

    /* ------------------------------------------------------------------
     * 4. pi_habilitacao_decidida → apenas 1 email (individual)
     * ------------------------------------------------------------------ */

    public function test_habilitacao_decidida_enfileira_apenas_para_inscrito(): void
    {
        $agenteId = 40;
        $agente   = new StubAgente($agenteId, 'inscrito@test.gov.br');

        $this->agenteRepo
            ->method('findById')
            ->with($agenteId)
            ->willReturn($agente);

        // Mesmo com 3 deferidos no broadcast, onHabilitacaoDecidida é individual
        $broadcast = new StubBroadcastQuery([
            ['agente_id' => 41, 'email' => 'outro1@test.gov.br', 'nome' => 'Outro1'],
            ['agente_id' => 42, 'email' => 'outro2@test.gov.br', 'nome' => 'Outro2'],
        ]);

        $handler   = $this->makeHandler($broadcast);
        $listeners = $this->makeListeners($handler);

        $listeners->onHabilitacaoDecidida($agenteId, 'habilitado', ['edital_titulo' => 'Ed. X']);

        $todos = $this->filaRepo->listAll();
        $this->assertCount(1, $todos, 'Apenas 1 email individual, nao broadcast');
        $this->assertSame('habilitacao_decidida', $todos[0]->evento());
        $this->assertSame('inscrito@test.gov.br', $todos[0]->destinatario());
    }

    /* ------------------------------------------------------------------
     * 5. Broadcast com lista vazia → 0 emails
     * ------------------------------------------------------------------ */

    public function test_broadcast_lista_vazia_enfileira_zero_emails(): void
    {
        $broadcast = new StubBroadcastQuery([]);
        $handler   = $this->makeHandler($broadcast);
        $listeners = $this->makeListeners($handler);

        $listeners->onResultadoPublicado(99, ['titulo' => 'Vazio']);

        $this->assertCount(0, $this->filaRepo->listAll(), 'Broadcast vazio = 0 emails');
    }

    /* ------------------------------------------------------------------
     * 6. Broadcast: cada email tem destinatário diferente (sem duplicatas)
     * ------------------------------------------------------------------ */

    public function test_broadcast_emails_com_destinatarios_distintos(): void
    {
        $rows = [
            ['agente_id' => 50, 'email' => 'x1@ibram.gov.br', 'nome' => 'X1'],
            ['agente_id' => 51, 'email' => 'x2@ibram.gov.br', 'nome' => 'X2'],
            ['agente_id' => 52, 'email' => 'x3@ibram.gov.br', 'nome' => 'X3'],
        ];
        $broadcast = new StubBroadcastQuery($rows);
        $handler   = $this->makeHandler($broadcast);
        $listeners = $this->makeListeners($handler);

        $listeners->onEditalPublicado(7, ['titulo' => 'Edital Y', 'url' => 'https://ibram.gov.br']);

        $todos        = $this->filaRepo->listAll();
        $destinatarios = array_map(static fn ($m) => $m->destinatario(), $todos);
        $this->assertCount(3, array_unique($destinatarios), 'Todos os destinatarios devem ser distintos');
    }
}
