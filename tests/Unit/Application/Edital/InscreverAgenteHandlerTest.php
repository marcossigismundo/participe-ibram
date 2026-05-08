<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Edital\InscreverAgenteHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Edital;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Edital\AgenteLookupPort;
use Ibram\ParticipeIbram\Application\Edital\InscreverAgenteCommand;
use Ibram\ParticipeIbram\Application\Edital\InscreverAgenteHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Categoria;
use Ibram\ParticipeIbram\Domain\Edital\CategoriaInvalida;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\InscricaoDuplicada;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use PHPUnit\Framework\TestCase;

final class InscreverAgenteHandlerTest extends TestCase
{
    public function test_rejeita_inscricao_em_categoria_que_nao_aceita_tipo(): void
    {
        $handler = $this->makeHandler(
            $this->editalAberto(10),
            $this->categoria(20, 10, 'PF'), // só aceita PF
            ['exists' => true, 'deferido' => true, 'tipo' => 'OR'],
            null
        );

        $this->expectException(CategoriaInvalida::class);
        $handler->handle(new InscreverAgenteCommand(10, 20, 99, null));
    }

    public function test_rejeita_quando_edital_nao_esta_em_inscricoes_abertas(): void
    {
        $handler = $this->makeHandler(
            $this->editalEm(10, StatusEdital::PUBLICADO),
            $this->categoria(20, 10, 'PF,OR,SM'),
            ['exists' => true, 'deferido' => true, 'tipo' => 'PF'],
            null
        );

        $this->expectException(DomainException::class);
        $handler->handle(new InscreverAgenteCommand(10, 20, 99, null));
    }

    public function test_rejeita_quando_categoria_nao_pertence_ao_edital(): void
    {
        $handler = $this->makeHandler(
            $this->editalAberto(10),
            $this->categoria(20, 999, 'PF,OR,SM'), // pertence a outro edital
            ['exists' => true, 'deferido' => true, 'tipo' => 'PF'],
            null
        );

        $this->expectException(CategoriaInvalida::class);
        $handler->handle(new InscreverAgenteCommand(10, 20, 99, null));
    }

    public function test_rejeita_quando_agente_nao_deferido(): void
    {
        $handler = $this->makeHandler(
            $this->editalAberto(10),
            $this->categoria(20, 10, 'PF'),
            ['exists' => true, 'deferido' => false, 'tipo' => 'PF'],
            null
        );

        $this->expectException(DomainException::class);
        $handler->handle(new InscreverAgenteCommand(10, 20, 99, null));
    }

    public function test_rejeita_inscricao_duplicada(): void
    {
        $existing = \Ibram\ParticipeIbram\Domain\Edital\Inscricao::novoRascunho(10, 20, 99);
        $handler  = $this->makeHandler(
            $this->editalAberto(10),
            $this->categoria(20, 10, 'PF'),
            ['exists' => true, 'deferido' => true, 'tipo' => 'PF'],
            $existing
        );

        $this->expectException(InscricaoDuplicada::class);
        $handler->handle(new InscreverAgenteCommand(10, 20, 99, null));
    }

    public function test_persiste_inscricao_no_caminho_feliz(): void
    {
        $editaisRepo    = $this->createMock(WpdbEditalRepository::class);
        $categoriasRepo = $this->createMock(WpdbCategoriaRepository::class);
        $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $audit          = $this->createMock(AuditLogger::class);

        $editaisRepo->method('findById')->willReturn($this->editalAberto(10));
        $categoriasRepo->method('findById')->willReturn($this->categoria(20, 10, 'PF'));
        $inscricoesRepo->method('findByEditalCategoriaEAgente')->willReturn(null);
        $inscricoesRepo->expects($this->once())->method('save')->willReturn(123);

        $handler = new InscreverAgenteHandler(
            $editaisRepo,
            $categoriasRepo,
            $inscricoesRepo,
            $this->lookup(['exists' => true, 'deferido' => true, 'tipo' => 'PF']),
            $audit
        );

        $id = $handler->handle(new InscreverAgenteCommand(10, 20, 99, 'meu portfolio'));
        $this->assertSame(123, $id);
    }

    /**
     * @param array{exists:bool,deferido:bool,tipo:string} $agenteInfo
     */
    private function makeHandler(
        Edital $edital,
        Categoria $categoria,
        array $agenteInfo,
        ?\Ibram\ParticipeIbram\Domain\Edital\Inscricao $existingInscricao
    ): InscreverAgenteHandler {
        $editaisRepo    = $this->createMock(WpdbEditalRepository::class);
        $categoriasRepo = $this->createMock(WpdbCategoriaRepository::class);
        $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $audit          = $this->createMock(AuditLogger::class);

        $editaisRepo->method('findById')->willReturn($edital);
        $categoriasRepo->method('findById')->willReturn($categoria);
        $inscricoesRepo->method('findByEditalCategoriaEAgente')->willReturn($existingInscricao);

        return new InscreverAgenteHandler(
            $editaisRepo,
            $categoriasRepo,
            $inscricoesRepo,
            $this->lookup($agenteInfo),
            $audit
        );
    }

    private function editalAberto(int $id): Edital
    {
        return $this->editalEm($id, StatusEdital::INSCRICOES_ABERTAS);
    }

    private function editalEm(int $id, string $status): Edital
    {
        $now = new DateTimeImmutable('now');

        return new Edital(
            $id,
            'Edital Teste',
            null,
            StatusEdital::fromString($status),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            1,
            $now,
            $now
        );
    }

    private function categoria(int $id, int $editalId, string $tipos): Categoria
    {
        return new Categoria($id, $editalId, 'Categoria X', null, 1, 0, $tipos, null, [], 0);
    }

    /**
     * @param array{exists:bool,deferido:bool,tipo:string} $info
     */
    private function lookup(array $info): AgenteLookupPort
    {
        return new class ($info) implements AgenteLookupPort {
            /** @var array{exists:bool,deferido:bool,tipo:string} */
            private array $info;

            /**
             * @param array{exists:bool,deferido:bool,tipo:string} $info
             */
            public function __construct(array $info)
            {
                $this->info = $info;
            }

            public function lookup(int $agenteId): array
            {
                return $this->info;
            }
        };
    }
}
