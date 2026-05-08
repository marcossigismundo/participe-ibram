<?php
/**
 * Unit tests for SodiumCipher.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Encryption
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Encryption;

use Ibram\ParticipeIbram\Core\Encryption\EncryptionException;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Core\Encryption\SodiumCipher
 */
final class SodiumCipherTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!\defined('PI_ENC_KEY_V1')) {
            \define('PI_ENC_KEY_V1', base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        }
        if (!\defined('PI_ENC_KEY_CURRENT')) {
            \define('PI_ENC_KEY_CURRENT', 'v1');
        }
        if (!\defined('PI_HMAC_KEY')) {
            \define('PI_HMAC_KEY', base64_encode(random_bytes(SODIUM_CRYPTO_GENERICHASH_KEYBYTES)));
        }
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $cipher    = new SodiumCipher();
        $plaintext = '123.456.789-09';

        $stored = $cipher->encrypt($plaintext);

        self::assertStringStartsWith('v1:', $stored);
        self::assertNotSame($plaintext, $stored);
        self::assertSame('123.456.789-09', $cipher->decrypt($stored));
    }

    public function testCiphertextIsNonDeterministic(): void
    {
        $cipher = new SodiumCipher();
        $a      = $cipher->encrypt('abc');
        $b      = $cipher->encrypt('abc');

        self::assertNotSame($a, $b, 'Each encryption must use a fresh nonce.');
    }

    public function testSearchHashIsDeterministicAndNormalises(): void
    {
        $cipher = new SodiumCipher();

        $hashLower = $cipher->searchHash('FULANO@example.org');
        $hashTrim  = $cipher->searchHash('  fulano@example.org ');

        self::assertSame($hashLower, $hashTrim);
        self::assertSame(64, strlen($hashLower));
    }

    public function testSearchHashDiffersForDifferentInputs(): void
    {
        $cipher = new SodiumCipher();

        self::assertNotSame(
            $cipher->searchHash('11111111111'),
            $cipher->searchHash('22222222222')
        );
    }

    public function testRejectsInvalidStorageFormat(): void
    {
        $cipher = new SodiumCipher();

        $this->expectException(EncryptionException::class);
        $cipher->decrypt('not-a-valid-payload');
    }

    public function testRejectsBadBase64(): void
    {
        $cipher = new SodiumCipher();

        $this->expectException(EncryptionException::class);
        $cipher->decrypt('v1:!!!not-base64!!!');
    }

    public function testRejectsTruncatedPayload(): void
    {
        $cipher = new SodiumCipher();

        $this->expectException(EncryptionException::class);
        $cipher->decrypt('v1:' . base64_encode('short'));
    }

    public function testRejectsUnknownKeyVersion(): void
    {
        $cipher = new SodiumCipher();

        $this->expectException(EncryptionException::class);
        $cipher->decrypt('v9:' . base64_encode(str_repeat('a', 64)));
    }

    public function testRewrapProducesValidCiphertext(): void
    {
        $cipher = new SodiumCipher();
        $stored = $cipher->encrypt('payload');

        $rewrapped = $cipher->rewrap($stored);

        self::assertNotSame($stored, $rewrapped);
        self::assertSame('payload', $cipher->decrypt($rewrapped));
    }

    public function testOnDecryptCallbackIsInvoked(): void
    {
        $calls  = [];
        $cipher = new SodiumCipher(static function (string $version) use (&$calls): void {
            $calls[] = $version;
        });

        $stored = $cipher->encrypt('valor');
        $cipher->decrypt($stored);

        self::assertSame(['v1'], $calls);
    }
}
