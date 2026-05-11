<?php
/**
 * Token HMAC para fluxo de anonimização (dupla confirmação por email).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Gera/valida tokens curtos para confirmação de anonimização (LGPD Art. 18, IV).
 *
 * Reuso do segredo `PI_UNSUBSCRIBE_SECRET` (decisão arquitetural: dois segredos
 * separados aumentariam fricção operacional sem ganho real — a *primitiva* aqui
 * (HMAC-SHA256) e o *purpose* (`anonimizacao_{solicitacaoId}`) são distintos do
 * unsubscribe, o que já garante separação de domínio criptográfico).
 *
 * Formato (base64-url):
 *
 *     base64url( solicitacaoId . "|" . agenteId . "|" . expires . "|" . hmac )
 *
 * Validação:
 *  - `hash_equals` (constant-time, R5 V-14)
 *  - expiração estrita (24h por padrão)
 *  - parâmetros tipados — qualquer não-numérico aborta antes do HMAC
 *
 * TTL fixo de 24 horas (LGPD recomenda janela curta para ações irreversíveis).
 */
final class AnonimizacaoTokenizer
{
    public const TTL_SECONDS = 86400; // 24h

    public function tokenFor(int $solicitacaoId, int $agenteId, DateTimeImmutable $expiraEm): string
    {
        if ($solicitacaoId < 1) {
            throw new InvalidArgumentException('solicitacaoId deve ser >= 1.');
        }
        if ($agenteId < 1) {
            throw new InvalidArgumentException('agenteId deve ser >= 1.');
        }
        $now     = (new DateTimeImmutable('now'))->getTimestamp();
        $expires = $expiraEm->getTimestamp();
        if ($expires <= $now) {
            throw new InvalidArgumentException('expiraEm deve ser futuro.');
        }
        if (($expires - $now) > self::TTL_SECONDS) {
            throw new InvalidArgumentException('expiraEm excede TTL maximo (24h).');
        }

        $payload = $solicitacaoId . '|' . $agenteId . '|' . $expires;
        $hmac    = hash_hmac('sha256', $payload, self::secret());

        return self::base64UrlEncode($payload . '|' . $hmac);
    }

    /**
     * @param int               $solicitacaoId OUT
     * @param int               $agenteId      OUT
     * @param DateTimeImmutable $expiraEm      OUT
     */
    public function verify(string $token, int &$solicitacaoId, int &$agenteId, ?DateTimeImmutable &$expiraEm): bool
    {
        $solicitacaoId = 0;
        $agenteId      = 0;
        $expiraEm      = null;

        $raw = self::base64UrlDecode($token);
        if ($raw === null) {
            return false;
        }
        $parts = explode('|', $raw);
        if (count($parts) !== 4) {
            return false;
        }
        [$sidStr, $aidStr, $expStr, $hmacRcv] = $parts;
        if (!ctype_digit($sidStr) || !ctype_digit($aidStr) || !ctype_digit($expStr)) {
            return false;
        }
        $sid = (int) $sidStr;
        $aid = (int) $aidStr;
        $exp = (int) $expStr;
        if ($sid < 1 || $aid < 1) {
            return false;
        }

        $payload = $sidStr . '|' . $aidStr . '|' . $expStr;
        $hmacExp = hash_hmac('sha256', $payload, self::secret());
        if (!hash_equals($hmacExp, $hmacRcv)) {
            return false;
        }
        if ($exp < (new DateTimeImmutable('now'))->getTimestamp()) {
            return false;
        }

        $solicitacaoId = $sid;
        $agenteId      = $aid;
        $expiraEm      = (new DateTimeImmutable('@' . $exp))
            ->setTimezone((new DateTimeImmutable('now'))->getTimezone());

        return true;
    }

    private static function secret(): string
    {
        $salt   = function_exists('wp_salt') ? (string) \wp_salt('auth') : '';
        $secret = '';
        if (\defined('PI_UNSUBSCRIBE_SECRET')) {
            $secret = (string) \PI_UNSUBSCRIBE_SECRET;
        }
        $combined = 'anon|' . $salt . '|' . $secret;
        if ($combined === 'anon||') {
            $combined = 'pi-anonimizacao-test-fallback';
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
