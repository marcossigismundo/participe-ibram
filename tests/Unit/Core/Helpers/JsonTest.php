<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Helpers\Json}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Helpers;

use Ibram\ParticipeIbram\Core\Helpers\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;

if (!function_exists('wp_json_encode')) {
    /**
     * Stub for WP `wp_json_encode`.
     *
     * @param mixed $data
     * @param int   $options
     */
    function wp_json_encode($data, $options = 0)
    {
        return json_encode($data, $options);
    }
}

final class JsonTest extends TestCase
{
    public function test_encode_for_script_escapes_dangerous_chars(): void
    {
        $payload = ['html' => '<script>alert("xss")</script>'];
        $encoded = Json::encodeForScript($payload);

        $this->assertStringNotContainsString('<', $encoded);
        $this->assertStringNotContainsString('>', $encoded);
        $this->assertStringNotContainsString("'", $encoded);
        // Note: the dangerous ASCII " is hex-escaped to "; raw quotes
        // around the JSON string also escape via JSON_HEX_QUOT.
    }

    public function test_encode_for_script_keeps_unicode_unescaped(): void
    {
        $encoded = Json::encodeForScript(['msg' => 'Olá, IBRAM']);
        $this->assertStringContainsString('Olá', $encoded);
    }

    public function test_encode_emits_clean_json_for_response(): void
    {
        $encoded = Json::encode(['ok' => true]);
        $this->assertSame('{"ok":true}', $encoded);
    }

    public function test_decode_parses_array(): void
    {
        $decoded = Json::decode('{"a":1,"b":"x"}');
        $this->assertSame(['a' => 1, 'b' => 'x'], $decoded);
    }

    public function test_decode_throws_on_invalid_json(): void
    {
        $this->expectException(RuntimeException::class);
        Json::decode('{not json');
    }

    public function test_decode_throws_when_root_is_scalar(): void
    {
        $this->expectException(RuntimeException::class);
        Json::decode('42');
    }
}
