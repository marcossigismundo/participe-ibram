<?php
/**
 * Testes unitários do OwnershipResolver.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Presentation\Public\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Presentation\Public\MinhaConta;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipDeniedException;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver
 */
final class OwnershipResolverTest extends TestCase
{
    /** @var array<int,array<string,mixed>> */
    private array $auditEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditEvents = [];
        $GLOBALS['__pi_test_current_user_id'] = 0;
    }

    public function testUserSemCadastroRetornaNull(): void
    {
        $resolver = new OwnershipResolver($this->makeRepo([]), $this->makeAuditSpy());

        $this->assertNull($resolver->resolveAgenteIdByUserId(99));
    }

    public function testUserComCadastroRetornaAgenteId(): void
    {
        $agente = $this->makeAgente(42, 7);
        $resolver = new OwnershipResolver($this->makeRepo([7 => $agente]), $this->makeAuditSpy());

        $this->assertSame(42, $resolver->resolveAgenteIdByUserId(7));
    }

    public function testUserIdInvalidoRetornaNull(): void
    {
        $resolver = new OwnershipResolver($this->makeRepo([]), $this->makeAuditSpy());

        $this->assertNull($resolver->resolveAgenteIdByUserId(0));
        $this->assertNull($resolver->resolveAgenteIdByUserId(-1));
    }

    public function testCurrentUserAgenteIdLeDoGlobal(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $agente = $this->makeAgente(42, 7);
        $resolver = new OwnershipResolver($this->makeRepo([7 => $agente]), $this->makeAuditSpy());

        $this->assertSame(42, $resolver->currentUserAgenteId());
    }

    public function testAssertOwnershipNoOwnerComMismatchLancaEAudita(): void
    {
        $agente = $this->makeAgente(42, 7);
        $resolver = new OwnershipResolver($this->makeRepo([7 => $agente]), $this->makeAuditSpy());

        try {
            $resolver->assertOwnership(8, 42);
            $this->fail('Esperado OwnershipDeniedException');
        } catch (OwnershipDeniedException $e) {
            $this->assertSame('Acesso negado.', $e->getMessage());
        }

        $this->assertCount(1, $this->auditEvents);
        $event = $this->auditEvents[0];
        $this->assertSame('ownership_denied', $event['acao']);
        $this->assertSame('agente', $event['entidade']);
        $this->assertSame(42, $event['entidadeId']);
        $this->assertSame(8, $event['atorId']);
        $this->assertSame(8, $event['dadosDepois']['tentou_user_id']);
        $this->assertSame(42, $event['dadosDepois']['tentou_agente_id']);
    }

    public function testAssertOwnershipQuandoCorretoNaoAuditaNemLanca(): void
    {
        $agente = $this->makeAgente(42, 7);
        $resolver = new OwnershipResolver($this->makeRepo([7 => $agente]), $this->makeAuditSpy());

        $resolver->assertOwnership(7, 42);
        $this->assertCount(0, $this->auditEvents);
    }

    public function testUserSemCadastroTentandoAssertOwnershipLanca(): void
    {
        $resolver = new OwnershipResolver($this->makeRepo([]), $this->makeAuditSpy());

        $this->expectException(OwnershipDeniedException::class);
        $resolver->assertOwnership(9, 42);
    }

    /**
     * @param array<int,Agente> $byUserId
     */
    private function makeRepo(array $byUserId): AgenteRepository
    {
        return new class($byUserId) implements AgenteRepository {
            /** @var array<int,Agente> */
            private array $byUserId;

            public function __construct(array $byUserId)
            {
                $this->byUserId = $byUserId;
            }

            public function findById(int $id): ?Agente
            {
                foreach ($this->byUserId as $a) {
                    if ($a->getId() === $id) {
                        return $a;
                    }
                }
                return null;
            }
            public function findByNumeroRegistro(string $numero): ?Agente { return null; }
            public function findByCpf(string $cpfPlain): ?Agente { return null; }
            public function findByCnpj(string $cnpjPlain): ?Agente { return null; }
            public function findByUserId(int $userId): ?Agente
            {
                return $this->byUserId[$userId] ?? null;
            }
            public function findByEmail(string $email): ?Agente { return null; }
            public function save(Agente $agente, object $detalhes, array $representantes = []): int { return 0; }
            public function softDelete(int $id): void {}
            public function listByStatus(string $status, int $page = 1, int $perPage = 25): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
            }
        };
    }

    private function makeAgente(int $id, ?int $userId): Agente
    {
        $now = new DateTimeImmutable('now');
        return new Agente(
            $id,
            TipoAgente::pf(),
            null,
            StatusCadastro::rascunho(),
            $userId,
            'user' . $id . '@example.org',
            null,
            null,
            null,
            null,
            $now,
            $now,
            null
        );
    }

    /**
     * Cria um mock de AuditLogger que captura cada `log()` em `$this->auditEvents`.
     * Final class -> PHPUnit suporta mockar com getMockBuilder + disableOriginalConstructor.
     */
    private function makeAuditSpy(): AuditLogger
    {
        $spy = $this->getMockBuilder(AuditLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['log'])
            ->getMock();
        $eventsRef = &$this->auditEvents;
        $spy->method('log')->willReturnCallback(
            function (
                string $entidade,
                ?int $entidadeId,
                string $acao,
                ?array $dadosAntes,
                ?array $dadosDepois,
                ?int $atorId = null
            ) use (&$eventsRef): void {
                $eventsRef[] = compact('entidade', 'entidadeId', 'acao', 'dadosAntes', 'dadosDepois', 'atorId');
            }
        );

        return $spy;
    }
}
