<?php
/**
 * Gerador / verificador de tokens de unsubscribe (R5 V-14).
 *
 * @package Ibram\ParticipeIbram\Application\Email\Templates
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email\Templates;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Tokens HMAC com expiração para link de "Cancelar comunicações" (LGPD R2 §3).
 *
 * Formato do token (URL-safe):
 *
 *     base64url( agente_id . "|" . purpose . "|" . expires_unix . "|" . hmac )
 *
 * onde `hmac = hash_hmac('sha256', agente_id|purpose|expires_unix, secret)`.
 *
 * O segredo é derivado de:
 *  - `wp_salt()` (salts WP — sempre presentes em produção)
 *  - constante `PI_UNSUBSCRIBE_SECRET` (DEVE ser definida em wp-config.php
 *    como base64 32 bytes — segredo SEPARADO do `PI_HMAC_KEY` para evitar
 *    reuso de chave entre primitivas distintas)
 *
 * Validação:
 *  - `hash_equals` (constant-time, R5 V-14)
 *  - expiração estrita (`expires_unix < now` -> rejeita)
 *  - tampering em `purpose` -> rejeita (parte do HMAC)
 *
 * O par {@see verify} usa parâmetros por referência para devolver os dados
 * decodificados em caso de sucesso.
 */
final class UnsubscribeTokenizer
{
    /**
     * Vida útil máxima permitida para um token (defesa em profundidade).
     */
    public const MAX_TTL_SECONDS = 90 * 86400; // 90 dias

    /**
     * Cria um token assinado.
     *
     * @param int                $userId   ID do agente (>= 1).
     * @param string             $purpose  Identificador de finalidade (ex.
     *                                     'comunicacao'). Apenas a-z 0-9 _.
     * @param DateTimeImmutable  $expiraEm Validade.
     *
     * @throws InvalidArgumentException Quando os parâmetros são inválidos.
     */
    public function tokenFor(int $userId, string $purpose, DateTimeImmutable $expiraEm): string
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('userId deve ser >= 1.');
        }
        if (!preg_match('/^[a-z0-9_]+$/', $purpose)) {
            throw new InvalidArgumentException('purpose deve conter apenas a-z 0-9 _.');
        }
        $expiresUnix = $expiraEm->getTimestamp();
        $now         = (new DateTimeImmutable('now'))->getTimestamp();
        if ($expiresUnix <= $now) {
            throw new InvalidArgumentException('expiraEm deve ser futuro.');
        }
        if (($expiresUnix - $now) > self::MAX_TTL_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'expiraEm excede TTL maximo (%d segundos).',
                self::MAX_TTL_SECONDS
            ));
        }

        $payload = $userId . '|' . $purpose . '|' . $expiresUnix;
        $hmac    = hash_hmac('sha256', $payload, self::secret());
        $token   = $payload . '|' . $hmac;

        return self::base64UrlEncode($token);
    }

    /**
     * Verifica e decodifica um token. Em sucesso, popula os refs e retorna true.
     *
     * @param string                  $token    Token recebido na URL.
     * @param int                     $userId   OUT — id decodificado.
     * @param string                  $purpose  OUT — purpose decodificada.
     * @param DateTimeImmutable|null  $expiraEm OUT — instante de expiração.
     */
    public function verify(string $token, int &$userId, string &$purpose, ?DateTimeImmutable &$expiraEm): bool
    {
        $userId   = 0;
        $purpose  = '';
        $expiraEm = null;

        $raw = self::base64UrlDecode($token);
        if ($raw === null) {
            return false;
        }
        // payload|hmac
        $parts = explode('|', $raw);
        if (count($parts) !== 4) {
            return false;
        }
        [$userIdStr, $purposeRaw, $expiresUnixStr, $hmacRcv] = $parts;

        if (!ctype_digit($userIdStr) || (int) $userIdStr < 1) {
            return false;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $purposeRaw)) {
            return false;
        }
        if (!ctype_digit($expiresUnixStr)) {
            return false;
        }
        $expiresUnix = (int) $expiresUnixStr;

        $payload = $userIdStr . '|' . $purposeRaw . '|' . $expiresUnixStr;
        $hmacExp = hash_hmac('sha256', $payload, self::secret());

        if (!hash_equals($hmacExp, $hmacRcv)) {
            return false;
        }
        $now = (new DateTimeImmutable('now'))->getTimestamp();
        if ($expiresUnix < $now) {
            return false;
        }

        $userId   = (int) $userIdStr;
        $purpose  = $purposeRaw;
        $expiraEm = (new DateTimeImmutable('@' . $expiresUnix))
            ->setTimezone((new DateTimeImmutable('now'))->getTimezone());

        return true;
    }

    /**
     * Concatena `wp_salt()` + `PI_UNSUBSCRIBE_SECRET` para chave HMAC.
     */
    private static function secret(): string
    {
        $salt = function_exists('wp_salt') ? (string) \wp_salt('auth') : '';
        $secret = '';
        if (\defined('PI_UNSUBSCRIBE_SECRET')) {
            $secret = (string) \PI_UNSUBSCRIBE_SECRET;
        }

        // Hash final mistura ambos com sha256 — ainda que um esteja vazio em
        // ambiente de teste, o outro contribui entropia.
        $combined = $salt . '|' . $secret;
        if ($combined === '|') {
            // Nenhum salt nem secret disponível — em testes geramos um salt
            // determinístico; em produção wp_salt() está sempre presente.
            $combined = 'pi-unsubscribe-test-fallback';
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
