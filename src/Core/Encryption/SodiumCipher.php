<?php
/**
 * Encryption-at-rest service for personal sensitive data.
 *
 * @package Ibram\ParticipeIbram\Core\Encryption
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Encryption;

/**
 * Symmetric encryption (XSalsa20+Poly1305) with versioned keys.
 *
 * Storage format: "<key_version>:<base64( nonce || ciphertext )>".
 *
 * Keys are read from PHP constants defined in wp-config.php:
 *  - `PI_ENC_KEY_V1`        base64-encoded 32-byte key (v1).
 *  - `PI_ENC_KEY_V2` (opt.) base64-encoded 32-byte key (v2) once rotation begins.
 *  - `PI_ENC_KEY_CURRENT`   string with the current version label, e.g. `v1`.
 *  - `PI_HMAC_KEY`          base64-encoded 32-byte key for `searchHash()`.
 *
 * The HMAC/search key is INTENTIONALLY distinct from the encryption keys
 * (LGPD R2 §4.6, §5). Reusing the same secret for both primitives is forbidden.
 */
final class SodiumCipher
{
    /**
     * Map of `version => raw 32-byte key`.
     *
     * @var array<string,string>
     */
    private array $keys;

    /**
     * Currently active key version (for new ciphertexts).
     */
    private string $currentVersion;

    /**
     * Optional callback invoked on each successful decryption.
     *
     * Signature: `function(string $keyVersion): void`. Used to feed the
     * AccessTracker without coupling this class to it.
     *
     * @var callable|null
     */
    private $onDecryptCallback;

    /**
     * @param callable|null $onDecryptCallback Called after successful decrypt.
     *
     * @throws EncryptionException When required constants are missing or invalid.
     */
    public function __construct(?callable $onDecryptCallback = null)
    {
        $this->onDecryptCallback = $onDecryptCallback;
        $this->keys              = self::loadKeys();
        $this->currentVersion    = self::loadCurrentVersion($this->keys);
    }

    /**
     * Encrypt a UTF-8 plaintext into the storage format.
     *
     * The plaintext is wiped from memory after the operation via
     * `sodium_memzero` (best-effort; relies on libsodium semantics).
     *
     * @param string $plaintext Value to encrypt. Will be zeroed in-place.
     *
     * @return string Self-contained ciphertext: `vN:base64(nonce||cipher)`.
     *
     * @throws EncryptionException On internal sodium failure.
     */
    public function encrypt(string $plaintext): string
    {
        $version = $this->currentVersion;
        $key     = $this->keys[$version];

        try {
            $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        } catch (\Throwable $e) {
            // Never include plaintext / key in the message.
            throw new EncryptionException('Falha ao cifrar dado sensivel.');
        }

        $blob = base64_encode($nonce . $cipher);

        // Best-effort wipe; the original string may still live in caller scope.
        try {
            sodium_memzero($plaintext);
        } catch (\Throwable $e) {
            // Some PHP builds throw when the variable is not a reference;
            // ignore — memzero is best-effort hardening, not correctness.
        }

        return $version . ':' . $blob;
    }

    /**
     * Decrypt a value produced by {@see encrypt()} or {@see rewrap()}.
     *
     * @param string $stored Ciphertext in storage format.
     *
     * @return string Plaintext.
     *
     * @throws EncryptionException When the format is invalid or MAC fails.
     */
    public function decrypt(string $stored): string
    {
        $sep = strpos($stored, ':');
        if ($sep === false) {
            throw new EncryptionException('Formato invalido (sem prefixo de versao).');
        }

        $version = substr($stored, 0, $sep);
        $blobB64 = substr($stored, $sep + 1);

        if (!isset($this->keys[$version])) {
            throw new EncryptionException('Chave para a versao informada nao esta disponivel.');
        }

        $blob = base64_decode($blobB64, true);
        if ($blob === false) {
            throw new EncryptionException('Payload base64 invalido.');
        }

        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        $minLen   = $nonceLen + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
        if (strlen($blob) < $minLen) {
            throw new EncryptionException('Payload truncado.');
        }

        $nonce  = substr($blob, 0, $nonceLen);
        $cipher = substr($blob, $nonceLen);

        try {
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->keys[$version]);
        } catch (\Throwable $e) {
            throw new EncryptionException('Falha ao decifrar dado sensivel.');
        }

        if ($plain === false) {
            throw new EncryptionException('Falha de autenticacao (MAC invalido).');
        }

        if ($this->onDecryptCallback !== null) {
            try {
                ($this->onDecryptCallback)($version);
            } catch (\Throwable $e) {
                // Observation must never break the cryptographic path.
            }
        }

        return $plain;
    }

    /**
     * Deterministic keyed hash for exact-match lookups (no decryption).
     *
     * Uses BLAKE2b with `PI_HMAC_KEY`, a key DISTINCT from `PI_ENC_KEY_*`
     * (R2 §4.6). The input is normalised (trim + lower).
     *
     * @param string $value Cleartext value to hash.
     *
     * @return string Hex-encoded 32-byte hash (length = 64 chars).
     *
     * @throws EncryptionException When `PI_HMAC_KEY` is absent or invalid.
     */
    public function searchHash(string $value): string
    {
        if (!\defined('PI_HMAC_KEY')) {
            throw new EncryptionException('Constante PI_HMAC_KEY nao esta definida em wp-config.php.');
        }

        $hmacKey = base64_decode((string) \PI_HMAC_KEY, true);
        if ($hmacKey === false || strlen($hmacKey) !== SODIUM_CRYPTO_GENERICHASH_KEYBYTES) {
            throw new EncryptionException('PI_HMAC_KEY invalida (esperado base64 de 32 bytes).');
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($value), 'UTF-8')
            : strtolower(trim($value));

        try {
            $hash = sodium_crypto_generichash($normalized, $hmacKey, 32);
        } catch (\Throwable $e) {
            throw new EncryptionException('Falha ao calcular hash de busca.');
        }

        return sodium_bin2hex($hash);
    }

    /**
     * Re-encrypt a stored value with the current key version (rotation).
     *
     * @param string $stored Ciphertext in storage format.
     *
     * @return string Ciphertext re-wrapped with the active key.
     *
     * @throws EncryptionException When the input cannot be decrypted.
     */
    public function rewrap(string $stored): string
    {
        $plain   = $this->decrypt($stored);
        $rewrapped = $this->encrypt($plain);
        // memzero handled by encrypt(); be defensive.
        try {
            sodium_memzero($plain);
        } catch (\Throwable $e) {
            // ignore
        }

        return $rewrapped;
    }

    /**
     * Currently active key version (`v1`, `v2`, ...).
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    /**
     * Inspect every `PI_ENC_KEY_V*` constant and return decoded raw keys.
     *
     * @return array<string,string>
     *
     * @throws EncryptionException When no key is defined or any defined key is malformed.
     */
    private static function loadKeys(): array
    {
        $keys      = [];
        $expected  = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
        $constants = function_exists('get_defined_constants') ? get_defined_constants() : [];

        foreach ($constants as $name => $value) {
            if (!is_string($name) || strpos($name, 'PI_ENC_KEY_V') !== 0) {
                continue;
            }
            if (!is_string($value) || $value === '') {
                throw new EncryptionException(sprintf('Constante %s vazia.', $name));
            }
            $version = strtolower(substr($name, strlen('PI_ENC_KEY_')));
            $raw     = base64_decode($value, true);
            if ($raw === false || strlen($raw) !== $expected) {
                throw new EncryptionException(sprintf(
                    'Chave %s invalida (esperado base64 de %d bytes).',
                    $name,
                    $expected
                ));
            }
            $keys[$version] = $raw;
        }

        if ($keys === []) {
            throw new EncryptionException(
                'Nenhuma chave de criptografia definida (esperado pelo menos PI_ENC_KEY_V1 em wp-config.php).'
            );
        }

        return $keys;
    }

    /**
     * Validate and read `PI_ENC_KEY_CURRENT`.
     *
     * @param array<string,string> $keys
     *
     * @throws EncryptionException
     */
    private static function loadCurrentVersion(array $keys): string
    {
        if (!\defined('PI_ENC_KEY_CURRENT')) {
            throw new EncryptionException('Constante PI_ENC_KEY_CURRENT nao definida em wp-config.php.');
        }
        $current = (string) \PI_ENC_KEY_CURRENT;
        if (!isset($keys[$current])) {
            throw new EncryptionException('PI_ENC_KEY_CURRENT aponta para versao inexistente.');
        }

        return $current;
    }
}
