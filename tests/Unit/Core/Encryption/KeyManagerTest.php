<?php
/**
 * Unit tests for KeyManager.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Encryption
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Encryption;

use Ibram\ParticipeIbram\Core\Encryption\KeyManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Core\Encryption\KeyManager
 */
final class KeyManagerTest extends TestCase
{
    public function testGenerateInstructionsReturnsExpectedShape(): void
    {
        $instr = KeyManager::generateInstructions();

        self::assertArrayHasKey('command_unix', $instr);
        self::assertArrayHasKey('constants', $instr);
        self::assertArrayHasKey('wp_config_snippet', $instr);
        self::assertContains('PI_ENC_KEY_V1', $instr['constants']);
        self::assertContains('PI_HMAC_KEY', $instr['constants']);
        self::assertContains('PI_IP_PEPPER', $instr['constants']);
        self::assertStringContainsString('random_bytes(32)', $instr['command_unix']);
    }

    public function testVerifyKeysConfiguredReturnsListWhenConstantsValid(): void
    {
        // Constants are defined by SodiumCipherTest::setUpBeforeClass when it runs;
        // when this test runs first, ensure baseline.
        if (!\defined('PI_ENC_KEY_V1')) {
            \define('PI_ENC_KEY_V1', base64_encode(random_bytes(32)));
        }
        if (!\defined('PI_ENC_KEY_CURRENT')) {
            \define('PI_ENC_KEY_CURRENT', 'v1');
        }
        if (!\defined('PI_HMAC_KEY')) {
            \define('PI_HMAC_KEY', base64_encode(random_bytes(32)));
        }
        if (!\defined('PI_IP_PEPPER')) {
            \define('PI_IP_PEPPER', base64_encode(random_bytes(16)));
        }

        $problems = KeyManager::verifyKeysConfigured();

        // It must not flag the equality check between V1 and HMAC.
        self::assertIsArray($problems);
        self::assertSame([], $problems, 'Expected no problems with valid keys, got: ' . implode(' | ', $problems));
    }
}
