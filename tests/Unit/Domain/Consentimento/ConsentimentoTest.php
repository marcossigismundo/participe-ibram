<?php
/**
 * Unit tests for {@see Consentimento}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Consentimento;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Domain\Consentimento\Consentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\StatusConsentimento;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Consentimento\Consentimento
 */
final class ConsentimentoTest extends TestCase
{
    public function test_registrar_creates_aceito_consentimento(): void
    {
        $c = Consentimento::registrar(
            42,
            7,
            Finalidade::fromString(Finalidade::IDENTIFICACAO),
            StatusConsentimento::aceito(),
            null,
            'Mozilla/5.0'
        );

        self::assertSame(42, $c->agenteId());
        self::assertSame(7, $c->termoId());
        self::assertSame(Finalidade::IDENTIFICACAO, $c->finalidade()->value());
        self::assertTrue($c->status()->isAceito());
        self::assertNull($c->revogadoEm());
    }

    public function test_registrar_rejects_status_revogado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::COMUNICACAO),
            StatusConsentimento::revogado(),
            null,
            null
        );
    }

    public function test_revogar_from_aceito_succeeds(): void
    {
        $c = Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::VOTACAO),
            StatusConsentimento::aceito(),
            null,
            null,
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        );

        $c->revogar(new DateTimeImmutable('2026-02-01T00:00:00Z'));

        self::assertTrue($c->status()->isRevogado());
        self::assertNotNull($c->revogadoEm());
    }

    public function test_revogar_from_already_revoked_throws(): void
    {
        $c = Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::VOTACAO),
            StatusConsentimento::aceito(),
            null,
            null,
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        );
        $c->revogar(new DateTimeImmutable('2026-01-15T00:00:00Z'));

        $this->expectException(DomainException::class);
        $c->revogar(new DateTimeImmutable('2026-02-01T00:00:00Z'));
    }

    public function test_revogar_from_negado_throws(): void
    {
        $c = Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::VOTACAO),
            StatusConsentimento::negado(),
            null,
            null
        );

        $this->expectException(DomainException::class);
        $c->revogar(new DateTimeImmutable('2026-02-01T00:00:00Z'));
    }

    public function test_invalid_ip_hash_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::COMUNICACAO),
            StatusConsentimento::aceito(),
            'not-a-hex-hash',
            null
        );
    }

    public function test_valid_ip_hash_accepted(): void
    {
        $hash = hash('sha256', '127.0.0.1');
        $c = Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::COMUNICACAO),
            StatusConsentimento::aceito(),
            $hash,
            'agent'
        );

        self::assertSame($hash, $c->ipHash());
    }

    public function test_revogar_rejects_date_before_registro(): void
    {
        $c = Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::VOTACAO),
            StatusConsentimento::aceito(),
            null,
            null,
            new DateTimeImmutable('2026-05-01T00:00:00Z')
        );

        $this->expectException(DomainException::class);
        $c->revogar(new DateTimeImmutable('2025-01-01T00:00:00Z'));
    }

    public function test_user_agent_truncated_to_1024(): void
    {
        $longUa = str_repeat('a', 2000);
        $c = Consentimento::registrar(
            1,
            1,
            Finalidade::fromString(Finalidade::COMUNICACAO),
            StatusConsentimento::aceito(),
            null,
            $longUa
        );

        self::assertNotNull($c->userAgent());
        self::assertSame(1024, strlen($c->userAgent()));
    }
}
