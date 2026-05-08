<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Votacao;

use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher
 */
final class EleitorHasherTest extends TestCase
{
    private string $secretBase64;

    protected function setUp(): void
    {
        // 32 bytes determinísticos (chave de teste — JAMAIS usar em produção).
        $raw                = str_repeat("\x42", SODIUM_CRYPTO_GENERICHASH_KEYBYTES);
        $this->secretBase64 = base64_encode($raw);
    }

    public function testHashIsDeterministicForSameInputs(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);

        $a = $hasher->hash(101, 555);
        $b = $hasher->hash(101, 555);

        self::assertSame($a, $b);
        self::assertSame(64, strlen($a));
        self::assertTrue(ctype_xdigit($a));
    }

    public function testHashChangesWhenAgenteIdDiffers(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);

        $a = $hasher->hash(101, 555);
        $b = $hasher->hash(102, 555);  // diferença mínima

        self::assertNotSame($a, $b);
    }

    public function testHashChangesWhenVotacaoIdDiffers(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);

        $a = $hasher->hash(101, 555);
        $b = $hasher->hash(101, 556);

        self::assertNotSame($a, $b);
    }

    public function testHashChangesWhenSecretDiffers(): void
    {
        $hasher1 = new EleitorHasher($this->secretBase64);

        $altRaw    = str_repeat("\x99", SODIUM_CRYPTO_GENERICHASH_KEYBYTES);
        $altBase64 = base64_encode($altRaw);
        $hasher2   = new EleitorHasher($altBase64);

        self::assertNotSame($hasher1->hash(1, 1), $hasher2->hash(1, 1));
    }

    public function testHashAvoidsConcatenationAmbiguity(): void
    {
        // Sem o separador "|", (12, 345) e (123, 45) colidiriam.
        $hasher = new EleitorHasher($this->secretBase64);

        self::assertNotSame($hasher->hash(12, 345), $hasher->hash(123, 45));
    }

    public function testVerifyAcceptsCorrectHash(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);
        $h      = $hasher->hash(7, 999);

        self::assertTrue($hasher->verify(7, 999, $h));
    }

    public function testVerifyRejectsWrongHash(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);
        $h      = $hasher->hash(7, 999);

        self::assertFalse($hasher->verify(7, 1000, $h));
        self::assertFalse($hasher->verify(8, 999, $h));
    }

    public function testVerifyRejectsMalformedHash(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);

        self::assertFalse($hasher->verify(1, 1, ''));
        self::assertFalse($hasher->verify(1, 1, 'short'));
        self::assertFalse($hasher->verify(1, 1, str_repeat('z', 64))); // não-hex
    }

    public function testVerifyUsesConstantTimeComparison(): void
    {
        // Smoke test: verify usa hash_equals — duas chamadas com hashes de tamanho
        // igual mas conteúdos diferentes não devem vazar via timing por curto-circuito.
        // A chamada padrão do PHP `===` faria curto-circuito no primeiro byte distinto;
        // hash_equals processa todos. Aqui só validamos que a função SE COMPORTA igual
        // a hash_equals e não lança em comparações de hashes equivalentes em tamanho.
        $hasher = new EleitorHasher($this->secretBase64);
        $valid  = $hasher->hash(1, 1);

        // Hash inválido com mesmo comprimento — não deve ser aceito.
        $bogus = str_repeat('a', 64);
        self::assertFalse($hasher->verify(1, 1, $bogus));
        self::assertNotSame($valid, $bogus);
    }

    public function testHashRejectsNonPositiveIds(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);

        $this->expectException(RuntimeException::class);
        $hasher->hash(0, 1);
    }

    public function testHashRejectsNonPositiveVotacaoId(): void
    {
        $hasher = new EleitorHasher($this->secretBase64);

        $this->expectException(RuntimeException::class);
        $hasher->hash(1, -1);
    }

    public function testRejectsInvalidSecret(): void
    {
        $this->expectException(RuntimeException::class);
        new EleitorHasher('not-base64!!!');
    }

    public function testRejectsEmptySecret(): void
    {
        $this->expectException(RuntimeException::class);
        new EleitorHasher('');
    }

    public function testRejectsSecretWithWrongLength(): void
    {
        $tooShort = base64_encode(str_repeat("\x01", 16));
        $this->expectException(RuntimeException::class);
        new EleitorHasher($tooShort);
    }
}
