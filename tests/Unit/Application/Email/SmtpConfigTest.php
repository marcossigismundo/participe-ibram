<?php
/**
 * Testes do {@see SmtpConfig} (round-trip de password cifrada).
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Email;

use Ibram\ParticipeIbram\Application\Email\SmtpConfig;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Email\SmtpConfig
 */
final class SmtpConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_options'] = [];

        // Define constantes de chave caso ainda não existam (para SodiumCipher).
        if (!defined('PI_ENC_KEY_V1')) {
            $key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
            define('PI_ENC_KEY_V1', $key);
        }
        if (!defined('PI_ENC_KEY_CURRENT')) {
            define('PI_ENC_KEY_CURRENT', 'v1');
        }
    }

    public function test_password_cifrada_round_trip(): void
    {
        $cipher = new SodiumCipher();
        $logger = new SecureLogger(static function (): void {});
        $smtp   = new SmtpConfig($cipher, $logger);

        $smtp->save([
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'encryption' => 'tls',
            'user'       => 'noreply@museus.gov.br',
            'password'   => 'super-secret-pwd-123',
            'from_email' => 'noreply@museus.gov.br',
            'from_name'  => 'Participe Ibram',
        ]);

        // Snapshot público NUNCA expõe password.
        $snap = $smtp->snapshotPublic();
        $this->assertSame('smtp.example.com', $snap['host']);
        $this->assertSame(587, $snap['port']);
        $this->assertSame('tls', $snap['encryption']);
        $this->assertTrue($snap['password_set']);
        $this->assertArrayNotHasKey('password', $snap);

        // O valor armazenado em pi_smtp_password_enc DEVE ter prefixo de versão.
        $stored = $GLOBALS['__pi_test_options']['pi_smtp_password_enc'] ?? '';
        $this->assertIsString($stored);
        $this->assertStringStartsWith('v1:', $stored);
        $this->assertNotSame('super-secret-pwd-123', $stored);

        // Decrypt manual via SodiumCipher devolve o claro original.
        $this->assertSame('super-secret-pwd-123', $cipher->decrypt($stored));
    }

    public function test_save_sem_password_nao_sobrescreve_existente(): void
    {
        $cipher = new SodiumCipher();
        $logger = new SecureLogger(static function (): void {});
        $smtp   = new SmtpConfig($cipher, $logger);

        // Configura inicialmente.
        $smtp->save([
            'host'     => 'smtp.example.com',
            'password' => 'pwd-original',
        ]);
        $original = $GLOBALS['__pi_test_options']['pi_smtp_password_enc'] ?? '';

        // Save sem password (vazio).
        $smtp->save([
            'host'     => 'smtp.example.com',
            'password' => '',
        ]);
        $afterEmpty = $GLOBALS['__pi_test_options']['pi_smtp_password_enc'] ?? '';
        $this->assertSame($original, $afterEmpty);

        // Save sem chave password.
        $smtp->save([
            'host' => 'smtp.example.com',
        ]);
        $afterNoKey = $GLOBALS['__pi_test_options']['pi_smtp_password_enc'] ?? '';
        $this->assertSame($original, $afterNoKey);
    }

}
