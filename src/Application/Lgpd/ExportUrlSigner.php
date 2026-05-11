<?php
/**
 * Assina URLs de download de export LGPD (HMAC + TTL).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Gera/valida URLs assinadas para download de ZIP de export (LGPD Art. 18 V).
 *
 * Componentes:
 *   sig = base64url( agenteId|fileId|expires|hmac )
 *
 * onde `hmac = HMAC-SHA256(agenteId|fileId|expires, secret)`.
 *
 * TTL padrão 24h. Validação por `hash_equals` (constant-time).
 */
final class ExportUrlSigner
{
    public const TTL_SECONDS = 86400; // 24h

    public function sign(int $agenteId, string $fileId, DateTimeImmutable $expiraEm): string
    {
        if ($agenteId < 1) {
            throw new InvalidArgumentException('agenteId deve ser >= 1.');
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fileId)) {
            throw new InvalidArgumentException('fileId contém caracteres não permitidos.');
        }
        $expires = $expiraEm->getTimestamp();
        $now     = (new DateTimeImmutable('now'))->getTimestamp();
        if ($expires <= $now) {
            throw new InvalidArgumentException('expiraEm deve ser futuro.');
        }
        if (($expires - $now) > self::TTL_SECONDS) {
            throw new InvalidArgumentException('expiraEm excede TTL máximo (24h).');
        }

        $payload = $agenteId . '|' . $fileId . '|' . $expires;
        $hmac    = hash_hmac('sha256', $payload, self::secret());

        return self::base64UrlEncode($payload . '|' . $hmac);
    }

    public function verify(string $sig, int &$agenteId, string &$fileId, ?DateTimeImmutable &$expiraEm): bool
    {
        $agenteId = 0;
        $fileId   = '';
        $expiraEm = null;

        $raw = self::base64UrlDecode($sig);
        if ($raw === null) {
            return false;
        }
        $parts = explode('|', $raw);
        if (count($parts) !== 4) {
            return false;
        }
        [$aidStr, $fidRaw, $expStr, $hmacRcv] = $parts;
        if (!ctype_digit($aidStr) || !ctype_digit($expStr) || $fidRaw === '') {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fidRaw)) {
            return false;
        }

        $payload = $aidStr . '|' . $fidRaw . '|' . $expStr;
        $hmacExp = hash_hmac('sha256', $payload, self::secret());
        if (!hash_equals($hmacExp, $hmacRcv)) {
            return false;
        }
        if ((int) $expStr < (new DateTimeImmutable('now'))->getTimestamp()) {
            return false;
        }

        $agenteId = (int) $aidStr;
        $fileId   = $fidRaw;
        $expiraEm = new DateTimeImmutable('@' . (int) $expStr);

        return true;
    }

    private static function secret(): string
    {
        $salt   = function_exists('wp_salt') ? (string) \wp_salt('auth') : '';
        $secret = '';
        if (\defined('PI_UNSUBSCRIBE_SECRET')) {
            $secret = (string) \PI_UNSUBSCRIBE_SECRET;
        }
        $combined = 'lgpd_export|' . $salt . '|' . $secret;
        if ($combined === 'lgpd_export||') {
            $combined = 'pi-lgpd-export-test-fallback';
        }

        return hash('sha256', $combined, true);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
