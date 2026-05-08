<?php
/**
 * Resolves the client IP behind proxies/CDNs and computes a privacy-preserving hash.
 *
 * @package Ibram\ParticipeIbram\Core\Network
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Network;

/**
 * IP resolver with explicit trusted-proxy whitelist (R5 B-16).
 *
 * Honours `X-Forwarded-For` ONLY when the immediate `REMOTE_ADDR` is in the
 * configured trusted-proxy list (single IPs or CIDR ranges, IPv4 / IPv6).
 *
 * The hash output is HMAC-SHA256 over the IP using `PI_IP_PEPPER` as the key.
 * The pepper MUST be different from the encryption keys (LGPD §5).
 */
final class IpResolver
{
    /**
     * Server superglobal snapshot (passed in for testability).
     *
     * @var array<string,mixed>
     */
    private array $server;

    /**
     * List of trusted proxies. Each entry is an IP or CIDR (`10.0.0.0/8`).
     *
     * @var list<string>
     */
    private array $trustedProxies;

    /**
     * @param list<string>             $trustedProxies List of IPs/CIDR ranges.
     * @param array<string,mixed>|null $server         Overrides `$_SERVER` for tests.
     */
    public function __construct(array $trustedProxies = [], ?array $server = null)
    {
        $this->trustedProxies = array_values(array_filter(
            $trustedProxies,
            static fn ($p): bool => is_string($p) && $p !== ''
        ));
        $this->server = $server ?? $_SERVER ?? [];
    }

    /**
     * Build an IpResolver from `wp-config` constant `PI_TRUSTED_PROXIES`
     * and/or the WordPress option `pi_trusted_proxies` (if available).
     *
     * @param array<string,mixed>|null $server Optional server override.
     */
    public static function fromConfig(?array $server = null): self
    {
        $list = [];

        if (\defined('PI_TRUSTED_PROXIES')) {
            $raw = (string) \PI_TRUSTED_PROXIES;
            foreach (preg_split('/[\s,]+/', $raw) ?: [] as $entry) {
                $entry = trim($entry);
                if ($entry !== '') {
                    $list[] = $entry;
                }
            }
        }

        if (function_exists('get_option')) {
            $stored = get_option('pi_trusted_proxies', []);
            if (is_array($stored)) {
                foreach ($stored as $entry) {
                    if (is_string($entry) && $entry !== '') {
                        $list[] = trim($entry);
                    }
                }
            } elseif (is_string($stored) && $stored !== '') {
                foreach (preg_split('/[\s,]+/', $stored) ?: [] as $entry) {
                    $entry = trim($entry);
                    if ($entry !== '') {
                        $list[] = $entry;
                    }
                }
            }
        }

        return new self(array_values(array_unique($list)), $server);
    }

    /**
     * Resolve the best-effort client IP.
     *
     * @return string|null Validated IP (v4 or v6) or null when nothing usable.
     */
    public function resolve(): ?string
    {
        $remote = isset($this->server['REMOTE_ADDR']) ? (string) $this->server['REMOTE_ADDR'] : '';
        if (function_exists('wp_unslash')) {
            $remote = (string) wp_unslash($remote);
        }
        $remote = trim($remote);

        if ($remote === '' || filter_var($remote, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        // If the connecting peer is not a trusted proxy, never trust XFF.
        if (!$this->isTrustedProxy($remote)) {
            return $remote;
        }

        $xffRaw = isset($this->server['HTTP_X_FORWARDED_FOR'])
            ? (string) $this->server['HTTP_X_FORWARDED_FOR']
            : '';
        if (function_exists('wp_unslash')) {
            $xffRaw = (string) wp_unslash($xffRaw);
        }
        if ($xffRaw === '') {
            return $remote;
        }

        // RFC 7239: client is the leftmost; intermediate proxies append themselves.
        // Walk from RIGHT to LEFT and return the first IP that is NOT a trusted proxy.
        $chain = array_map('trim', explode(',', $xffRaw));
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $candidate = $chain[$i];
            if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            if (!$this->isTrustedProxy($candidate)) {
                return $candidate;
            }
        }

        // All hops were trusted proxies; fall back to the leftmost valid IP.
        foreach ($chain as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return $remote;
    }

    /**
     * Compute HMAC-SHA256(`PI_IP_PEPPER`, ip). Returns null when input is null
     * or `PI_IP_PEPPER` is not configured.
     *
     * @param string|null $ip
     */
    public function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        if (!\defined('PI_IP_PEPPER')) {
            return null;
        }
        $pepper = (string) \PI_IP_PEPPER;
        if ($pepper === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, $pepper);
    }

    /**
     * Convenience: resolve + hash in a single call.
     */
    public function resolveHash(): ?string
    {
        return $this->hashIp($this->resolve());
    }

    /**
     * Check whether an IP belongs to the configured trusted-proxy set.
     */
    private function isTrustedProxy(string $ip): bool
    {
        foreach ($this->trustedProxies as $entry) {
            if (self::ipMatches($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match an IP against a single IP or a CIDR range (IPv4/IPv6).
     */
    private static function ipMatches(string $ip, string $rangeOrIp): bool
    {
        if (strpos($rangeOrIp, '/') === false) {
            return $ip === $rangeOrIp;
        }

        [$subnet, $maskStr] = explode('/', $rangeOrIp, 2);
        $mask = (int) $maskStr;
        if ($mask < 0) {
            return false;
        }

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false; // mixed v4/v6 — no match.
        }

        $bits  = strlen($ipBin) * 8;
        if ($mask > $bits) {
            return false;
        }
        $bytes = intdiv($mask, 8);
        $rem   = $mask % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $maskByte = chr((0xFF << (8 - $rem)) & 0xFF);

        return (ord($ipBin[$bytes]) & ord($maskByte)) === (ord($subnetBin[$bytes]) & ord($maskByte));
    }
}
