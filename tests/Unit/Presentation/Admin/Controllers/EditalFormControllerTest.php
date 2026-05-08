<?php
/**
 * Unit tests for EditalFormController — validação cronológica e rascunho.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Controllers;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EditalFormController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Testa regras de validação do controller sem passar por HTTP/wp_die.
 *
 * Foco:
 *  1. Datas em ordem inválida → DomainException propagada (server sempre revalida).
 *  2. Rascunho sem datas → permitido (todas as 7 são opcionais em rascunho).
 *  3. parseDatas() com datas faltando parcialmente → exceção user-friendly.
 */
final class EditalFormControllerTest extends TestCase
{
    private function buildController(): EditalFormController
    {
        return new EditalFormController(
            $this->createMock(WpdbEditalRepository::class),
            $this->createMock(AuditLogger::class)
        );
    }

    /**
     * Acessa método privado via reflexão para testar parseDatas isoladamente.
     */
    private function invokeParseDatas(EditalFormController $ctrl, array $postData): ?array
    {
        // Prime $_POST.
        foreach ($postData as $k => $v) {
            $_POST[$k] = $v;
        }
        $ref    = new ReflectionClass($ctrl);
        $method = $ref->getMethod('parseDatas');
        $method->setAccessible(true);
        return $method->invoke($ctrl);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    /* ===================== Datas em ordem inválida ===================== */

    public function testDatasCronologiaInvalidaLancaDomainException(): void
    {
        $this->expectException(DomainException::class);

        $ctrl = $this->buildController();

        // abertura DEPOIS de encerramento_inscricoes — inválido.
        $postData = [
            'abertura'                   => '2026-12-31T10:00',
            'encerramento_inscricoes'    => '2026-11-01T10:00', // anterior à abertura
            'publicacao_habilitacao'     => '2026-12-05T10:00',
            'prazo_recurso_inabilitacao' => '2026-12-10T10:00',
            'abertura_votacao'           => '2026-12-15T10:00',
            'encerramento_votacao'       => '2026-12-20T10:00',
            'publicacao_resultado'       => '2026-12-25T10:00',
        ];

        // parseDatas retorna array; a exceção cronológica ocorre ao chamar programarDatas
        // ou diretamente ao instanciar o Edital. Testamos a mensagem user-friendly
        // usando a reflexão do método privado.
        $this->invokeParseDatas($ctrl, $postData);

        // Cria edital com datas inválidas para confirmar que a entidade rejeita.
        $now  = new DateTimeImmutable('now');
        new Edital(
            null,
            'Teste',
            null,
            StatusEdital::rascunho(),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-11-01'), // inválido
            new DateTimeImmutable('2026-12-05'),
            new DateTimeImmutable('2026-12-10'),
            new DateTimeImmutable('2026-12-15'),
            new DateTimeImmutable('2026-12-20'),
            new DateTimeImmutable('2026-12-25'),
            1,
            $now,
            $now
        );
    }

    /* ===================== Rascunho sem datas é permitido ===================== */

    public function testRascunhoSemDatasPermite(): void
    {
        $ctrl = $this->buildController();
        // Nenhuma data no POST.
        $result = $this->invokeParseDatas($ctrl, []);
        $this->assertNull($result, 'parseDatas deve retornar null quando nenhuma data é enviada (rascunho)');
    }

    /* ===================== Datas parciais lançam exceção ===================== */

    public function testDatasParciaisLancaDomainException(): void
    {
        $this->expectException(DomainException::class);

        $ctrl = $this->buildController();
        // Só abertura preenchida — restantes vazias.
        $this->invokeParseDatas($ctrl, [
            'abertura' => '2026-10-01T10:00',
        ]);
    }

    /* ===================== Mensagem amigável para erro cronológico ===================== */

    public function testFriendlyDomainErrorCronologia(): void
    {
        $ctrl = $this->buildController();
        $ref  = new ReflectionClass($ctrl);
        $method = $ref->getMethod('friendlyDomainError');
        $method->setAccessible(true);

        $e   = new DomainException('Cronologia invalida: encerramento_inscricoes deve ser...');
        $msg = (string) $method->invoke($ctrl, $e);

        $this->assertStringContainsString('cronológica', $msg, 'Mensagem deve mencionar cronologia');
    }

    /* ===================== Entidade aceita datas válidas ===================== */

    public function testEditalAceitaDatasValidas(): void
    {
        $now = new DateTimeImmutable('now');
        $edital = new Edital(
            null,
            'Edital Válido',
            null,
            StatusEdital::rascunho(),
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-15'),
            new DateTimeImmutable('2026-10-20'),
            new DateTimeImmutable('2026-10-25'),
            new DateTimeImmutable('2026-11-01'),
            new DateTimeImmutable('2026-11-10'),
            new DateTimeImmutable('2026-11-15'),
            1,
            $now,
            $now
        );

        $this->assertSame(StatusEdital::RASCUNHO, $edital->status()->value());
        $this->assertNotNull($edital->abertura());
    }
}
