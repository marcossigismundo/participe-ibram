<?php
/**
 * Helper for operators to bootstrap and verify encryption keys.
 *
 * @package Ibram\ParticipeIbram\Core\Encryption
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Encryption;

/**
 * Operator-facing helper.
 *
 * - Generates copy-pasteable instructions for the admin (no auto-write to wp-config).
 * - Verifies that every required constant is present, base64-decodable and the
 *   correct length, returning a list of human-readable problems.
 *
 * This class never reads or logs key material — it only inspects presence and
 * length of decoded bytes.
 */
final class KeyManager
{
    private const REQUIRED_BYTES = 32;

    /**
     * Build the activation guidance shown in admin notices / health checks.
     *
     * @return array{
     *   command_unix: string,
     *   command_windows: string,
     *   constants: list<string>,
     *   wp_config_snippet: string,
     *   notes: list<string>
     * }
     */
    public static function generateInstructions(): array
    {
        $constants = [
            'PI_ENC_KEY_V1',
            'PI_ENC_KEY_CURRENT',
            'PI_HMAC_KEY',
            'PI_IP_PEPPER',
        ];

        $snippet = <<<'PHP'
// Participe Ibram — chaves criptograficas (LGPD).
// Gere cada chave com: php -r "echo base64_encode(random_bytes(32));"
// NUNCA commitar este trecho com valores reais.
define( 'PI_ENC_KEY_V1',     getenv( 'PI_ENC_KEY_V1' )     ?: '' );
define( 'PI_ENC_KEY_CURRENT', 'v1' );
define( 'PI_HMAC_KEY',       getenv( 'PI_HMAC_KEY' )       ?: '' );
define( 'PI_IP_PEPPER',      getenv( 'PI_IP_PEPPER' )      ?: '' );
// Opcional (lista CSV de IPs/CIDR de proxies confiaveis):
// define( 'PI_TRUSTED_PROXIES', '10.0.0.0/8,127.0.0.1' );
PHP;

        return [
            'command_unix'      => 'php -r "echo base64_encode(random_bytes(32));"',
            'command_windows'   => 'php -r "echo base64_encode(random_bytes(32));"',
            'constants'         => $constants,
            'wp_config_snippet' => $snippet,
            'notes'             => [
                'Cada chave deve ser independente: NAO reutilizar PI_ENC_KEY_V1 como PI_HMAC_KEY ou PI_IP_PEPPER.',
                'PI_ENC_KEY_CURRENT controla a versao em uso para novas escritas (rotacao).',
                'Em producao, prefira variaveis de ambiente; wp-config.php deve ter permissao 0640.',
                'Nunca versione chaves no Git nem grave em banco de dados.',
            ],
        ];
    }

    /**
     * Verify every encryption-related constant is present and well-formed.
     *
     * @return list<string> Empty when configuration is valid; otherwise a list
     *                     of operator-readable problem descriptions.
     */
    public static function verifyKeysConfigured(): array
    {
        $problems = [];

        // Encryption keys: at least V1 must exist.
        if (!\defined('PI_ENC_KEY_V1')) {
            $problems[] = 'Constante PI_ENC_KEY_V1 nao definida em wp-config.php.';
        } else {
            $problems = array_merge(
                $problems,
                self::checkBase64Constant('PI_ENC_KEY_V1', (string) \PI_ENC_KEY_V1, self::REQUIRED_BYTES)
            );
        }

        // Optional V2/V3 must, when present, be valid.
        foreach (['PI_ENC_KEY_V2', 'PI_ENC_KEY_V3', 'PI_ENC_KEY_V4'] as $optional) {
            if (\defined($optional)) {
                /** @var mixed $val */
                $val = constant($optional);
                if (is_string($val) && $val !== '') {
                    $problems = array_merge(
                        $problems,
                        self::checkBase64Constant($optional, $val, self::REQUIRED_BYTES)
                    );
                }
            }
        }

        // Current version pointer.
        if (!\defined('PI_ENC_KEY_CURRENT')) {
            $problems[] = 'Constante PI_ENC_KEY_CURRENT nao definida em wp-config.php.';
        } else {
            $current = (string) \PI_ENC_KEY_CURRENT;
            $constName = 'PI_ENC_KEY_' . strtoupper($current);
            if (!\defined($constName)) {
                $problems[] = sprintf(
                    'PI_ENC_KEY_CURRENT="%s" mas a constante %s nao existe.',
                    $current,
                    $constName
                );
            }
        }

        // HMAC key for searchHash (must be distinct material from encryption key).
        if (!\defined('PI_HMAC_KEY')) {
            $problems[] = 'Constante PI_HMAC_KEY nao definida em wp-config.php.';
        } else {
            $problems = array_merge(
                $problems,
                self::checkBase64Constant('PI_HMAC_KEY', (string) \PI_HMAC_KEY, self::REQUIRED_BYTES)
            );
            if (\defined('PI_ENC_KEY_V1')
                && (string) \PI_HMAC_KEY !== ''
                && (string) \PI_HMAC_KEY === (string) \PI_ENC_KEY_V1
            ) {
                $problems[] = 'PI_HMAC_KEY nao pode ter o mesmo valor de PI_ENC_KEY_V1 (LGPD R2 §4.6).';
            }
        }

        // IP pepper for audit log hashing.
        if (!\defined('PI_IP_PEPPER')) {
            $problems[] = 'Constante PI_IP_PEPPER nao definida em wp-config.php.';
        } else {
            $val = (string) \PI_IP_PEPPER;
            if ($val === '') {
                $problems[] = 'PI_IP_PEPPER esta vazia; defina um base64 de pelo menos 16 bytes.';
            }
        }

        return $problems;
    }

    /**
     * Validate that a base64 constant decodes to the expected raw length.
     *
     * @return list<string>
     */
    private static function checkBase64Constant(string $name, string $value, int $expectedBytes): array
    {
        if ($value === '') {
            return [sprintf('Constante %s esta vazia.', $name)];
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return [sprintf('Constante %s nao e base64 valido.', $name)];
        }
        if (strlen($decoded) !== $expectedBytes) {
            return [sprintf(
                'Constante %s tem %d bytes apos decodificacao (esperado %d).',
                $name,
                strlen($decoded),
                $expectedBytes
            )];
        }

        return [];
    }
}
