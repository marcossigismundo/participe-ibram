<?php
/**
 * PHPUnit bootstrap for the Participe Ibram plugin.
 *
 * Loads composer autoload (PSR-4 for `Ibram\\ParticipeIbram\\` => src/)
 * and primes minimal globals so tests can run without a WordPress install.
 *
 * @package Ibram\ParticipeIbram\Tests
 */

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Run `composer install` before executing the test suite.\n");
    exit(1);
}
require $autoload;

// Stub WordPress functions used by Core when running outside WP.
if (!function_exists('wp_unslash')) {
    /**
     * @param string|array<mixed> $value
     * @return string|array<mixed>
     */
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }
        if (!is_string($value)) {
            return $value;
        }

        return stripslashes($value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t\0\x0B]+/', ' ', $value) ?? '';

        return trim($value);
    }
}

if (!function_exists('wp_json_encode')) {
    /**
     * @param mixed $data
     */
    function wp_json_encode($data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['__pi_test_current_user_id'] ?? 0);
    }
}

if (!function_exists('get_option')) {
    /**
     * @param mixed $default
     * @return mixed
     */
    function get_option(string $option, $default = false)
    {
        if (isset($GLOBALS['__pi_test_options'][$option])) {
            return $GLOBALS['__pi_test_options'][$option];
        }
        return $default;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        // No-op em testes; handlers verificam function_exists para decidir.
    }
}

if (!function_exists('user_can')) {
    function user_can(int $userId, string $capability): bool
    {
        // Em testes, assumimos capability concedida quando o ator é positivo.
        return $userId > 0;
    }
}

if (!function_exists('update_option')) {
    /**
     * @param mixed $value
     * @param bool|string $autoload
     */
    function update_option(string $option, $value, $autoload = 'yes'): bool
    {
        $GLOBALS['__pi_test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    /**
     * @return mixed
     */
    function get_transient(string $key)
    {
        return $GLOBALS['__pi_test_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    /**
     * @param mixed $value
     */
    function set_transient(string $key, $value, int $expiration = 0): bool
    {
        $GLOBALS['__pi_test_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['__pi_test_transients'][$key]);
        return true;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        $value = strip_tags($value);
        return trim($value);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $value): string
    {
        // Stub: remove apenas tags realmente perigosas.
        return (string) preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $value);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return ((int) ($GLOBALS['__pi_test_current_user_id'] ?? 0)) > 0;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        $caps = $GLOBALS['__pi_test_user_caps'] ?? [];
        return in_array($capability, (array) $caps, true);
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): bool
    {
        $GLOBALS['__pi_test_rest_routes'][] = compact('namespace', 'route', 'args');
        return true;
    }
}

if (!class_exists('WP_REST_Response')) {
    final class WP_REST_Response
    {
        /** @var mixed */
        private $data;
        private int $status;
        /** @var array<string,string> */
        private array $headers = [];

        /** @param mixed $data */
        public function __construct($data, int $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        /** @return mixed */
        public function get_data()
        {
            return $this->data;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }

        /** @return array<string,string> */
        public function get_headers(): array
        {
            return $this->headers;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    final class WP_REST_Request
    {
        /** @var array<string,mixed> */
        private array $params;
        /** @var array<string,mixed> */
        private array $jsonParams;
        /** @var array<string,string> */
        private array $headers;

        /**
         * @param array<string,mixed>  $params
         * @param array<string,mixed>  $jsonParams
         * @param array<string,string> $headers
         */
        public function __construct(array $params = [], array $jsonParams = [], array $headers = [])
        {
            $this->params     = $params;
            $this->jsonParams = $jsonParams;
            $this->headers    = $headers;
        }

        /** @return mixed */
        public function get_param(string $key)
        {
            return $this->params[$key] ?? ($this->jsonParams[$key] ?? null);
        }

        /** @return array<string,mixed> */
        public function get_params(): array
        {
            return array_merge($this->params, $this->jsonParams);
        }

        /** @return array<string,mixed> */
        public function get_json_params(): array
        {
            return $this->jsonParams;
        }

        /** @return array<string,mixed> */
        public function get_url_params(): array
        {
            return $this->params;
        }

        public function get_header(string $key): ?string
        {
            $key = strtolower($key);
            return $this->headers[$key] ?? null;
        }
    }
}

if (!class_exists('WP_Error')) {
    final class WP_Error
    {
        public string $code;
        public string $message;
        public array $data;
        public function __construct(string $code, string $message = '', array $data = [])
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
    }
}


