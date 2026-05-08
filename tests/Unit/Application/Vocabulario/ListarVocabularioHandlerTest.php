<?php
/**
 * Unit tests for ListarVocabularioHandler.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Vocabulario;

use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioHandler;
use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioQuery;
use Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\VocabularioRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioHandler
 * @covers \Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioQuery
 */
final class ListarVocabularioHandlerTest extends TestCase
{
    public function testHandleReturnsArrayShapedForUiAndJson(): void
    {
        $repo = new class implements VocabularioRepository {
            /** @var array<int,ItemVocabulario> */
            private array $items;

            public function __construct()
            {
                $this->items = [
                    new ItemVocabulario(1, TipoVocabulario::TIPOS_COLETIVO, 'rede', 'Rede', 'desc rede', 1, true, ['k' => 'v']),
                    new ItemVocabulario(2, TipoVocabulario::TIPOS_COLETIVO, 'ong', 'ONG', null, 2, true, null),
                ];
            }
            public function findById(int $id): ?ItemVocabulario
            {
                return null;
            }
            public function findByValor(string $tipo, string $valor): ?ItemVocabulario
            {
                return null;
            }
            public function listByTipo(string $tipo, bool $apenasAtivos = true): array
            {
                return $this->items;
            }
            public function save(ItemVocabulario $item): int
            {
                return 0;
            }
            public function desativar(int $id): void
            {
            }
            public function validar(string $tipo, string $valor): bool
            {
                return false;
            }
        };

        $handler = new ListarVocabularioHandler($repo);
        $result  = $handler->handle(new ListarVocabularioQuery(TipoVocabulario::TIPOS_COLETIVO));

        self::assertCount(2, $result);
        self::assertSame(
            [
                'value'     => 'rede',
                'label'     => 'Rede',
                'descricao' => 'desc rede',
                'ordem'     => 1,
                'metadata'  => ['k' => 'v'],
            ],
            $result[0]
        );
        self::assertSame(
            [
                'value'     => 'ong',
                'label'     => 'ONG',
                'descricao' => null,
                'ordem'     => 2,
                'metadata'  => null,
            ],
            $result[1]
        );
    }

    public function testQueryRejectsUnknownTipo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ListarVocabularioQuery('inexistente');
    }

    public function testHandlePassesApenasAtivosFlagThrough(): void
    {
        $captured = (object) ['tipo' => null, 'apenasAtivos' => null];
        $repo     = new class ($captured) implements VocabularioRepository {
            /** @var \stdClass */
            private $captured;

            public function __construct(\stdClass $captured)
            {
                $this->captured = $captured;
            }
            public function findById(int $id): ?ItemVocabulario
            {
                return null;
            }
            public function findByValor(string $tipo, string $valor): ?ItemVocabulario
            {
                return null;
            }
            public function listByTipo(string $tipo, bool $apenasAtivos = true): array
            {
                $this->captured->tipo         = $tipo;
                $this->captured->apenasAtivos = $apenasAtivos;
                return [];
            }
            public function save(ItemVocabulario $item): int
            {
                return 0;
            }
            public function desativar(int $id): void
            {
            }
            public function validar(string $tipo, string $valor): bool
            {
                return false;
            }
        };

        $handler = new ListarVocabularioHandler($repo);
        $handler->handle(new ListarVocabularioQuery(TipoVocabulario::ABRANGENCIAS, false));

        self::assertSame(TipoVocabulario::ABRANGENCIAS, $captured->tipo);
        self::assertFalse($captured->apenasAtivos);
    }
}
