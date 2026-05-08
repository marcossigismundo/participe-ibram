<?php
/**
 * Exception thrown by the encryption subsystem.
 *
 * @package Ibram\ParticipeIbram\Core\Encryption
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Encryption;

/**
 * Encryption-specific runtime failure.
 *
 * Thrown for missing/invalid keys, corrupted ciphertext, MAC failures
 * and any other condition where decryption or encryption cannot proceed.
 * Messages MUST NEVER include plaintext, key material or PII.
 */
final class EncryptionException extends \RuntimeException
{
}
