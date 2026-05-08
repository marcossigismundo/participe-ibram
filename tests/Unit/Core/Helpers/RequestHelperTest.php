<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Helpers\RequestHelper}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Helpers;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

if (!function_exists('wp_unslash')) {
    /**
     * Mimic WP's `wp_unslash`: stripslashes_deep equivalent.
     *
     * @param mixed $value
     * @return mixed
     */
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map(__NAMESPACE__ . '\\wp_unslash', $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        $str = is_string($str) ? $str : '';
        return trim(preg_replace('/[\r\n\t\0\x0B]+/', ' ', strip_tags($str)));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return is_string($email) ? trim($email) : '';
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        return is_string($str) ? trim(strip_tags($str)) : '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return is_string($key) ? strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key)) : '';
    }
}

if (!function_exists('absint')) {
    function absint($value)
    {
        return abs((int) $value);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($value)
    {
        return is_string($value) ? strip_tags($value, '<p><br><strong><em><a>') : '';
    }
}

final class RequestHelperTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST   = [];
        $_GET    = [];
        $_SERVER = array_filter(
            $_SERVER,
            static fn(string $k) => strpos($k, 'HTTP_') !== 0,
            ARRAY_FILTER_USE_KEY
        );
    }

    public function test_post_returns_default_when_missing(): void
    {
        $this->assertNull(RequestHelper::post('foo'));
        $this->assertSame('default', RequestHelper::post('foo', 'sanitize_text_field', 'default'));
    }

    public function test_post_unslashes_before_sanitizing(): void
    {
        // WP boot adds slashes to superglobals; helper must remove them.
        $_POST['name'] = "O\\'Brien";
        $this->assertSame("O'Brien", RequestHelper::post('name'));
    }

    public function test_post_applies_whitelisted_sanitizer(): void
    {
        $_POST['email'] = '  agente@museus.gov.br  ';
        $this->assertSame('agente@museus.gov.br', RequestHelper::post('email', 'sanitize_email'));
    }

    public function test_post_rejects_arbitrary_callable(): void
    {
        $_POST['x'] = 'foo';
        $this->expectException(InvalidArgumentException::class);
        RequestHelper::post('x', 'system');
    }

    public function test_get_unslashes(): void
    {
        $_GET['q'] = "tom\\'s";
        $this->assertSame("tom's", RequestHelper::get('q'));
    }

    public function test_request_falls_back_to_get(): void
    {
        $_GET['q'] = 'fromget';
        $this->assertSame('fromget', RequestHelper::request('q'));
    }

    public function test_request_prefers_post(): void
    {
        $_POST['q'] = 'frompost';
        $_GET['q']  = 'fromget';
        $this->assertSame('frompost', RequestHelper::request('q'));
    }

    public function test_post_array_sanitizes_each_item(): void
    {
        $_POST['ids'] = ['1', '2', "3\\'"];
        $this->assertSame(['1', '2', "3'"], RequestHelper::postArray('ids'));
    }

    public function test_post_array_returns_empty_when_not_array(): void
    {
        $_POST['ids'] = 'scalar';
        $this->assertSame([], RequestHelper::postArray('ids'));
    }

    public function test_header_reads_http_prefixed_server_var(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'abc-123';
        $this->assertSame('abc-123', RequestHelper::header('X-Request-Id', 'sanitize_text_field'));
    }

    public function test_header_returns_null_when_absent(): void
    {
        $this->assertNull(RequestHelper::header('X-Missing'));
    }
}
