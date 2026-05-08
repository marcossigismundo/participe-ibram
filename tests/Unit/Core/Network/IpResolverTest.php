<?php
/**
 * Unit tests for IpResolver.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Network
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Network;

use Ibram\ParticipeIbram\Core\Network\IpResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Core\Network\IpResolver
 */
final class IpResolverTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!\defined('PI_IP_PEPPER')) {
            \define('PI_IP_PEPPER', base64_encode(random_bytes(16)));
        }
    }

    public function testReturnsRemoteAddrWhenNotBehindTrustedProxy(): void
    {
        $resolver = new IpResolver(
            ['10.0.0.1'],
            ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20']
        );

        // REMOTE_ADDR is not in trusted list -> XFF must be ignored.
        self::assertSame('203.0.113.10', $resolver->resolve());
    }

    public function testReturnsForwardedIpWhenBehindTrustedProxy(): void
    {
        $resolver = new IpResolver(
            ['10.0.0.1'],
            [
                'REMOTE_ADDR'          => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.20, 10.0.0.1',
            ]
        );

        self::assertSame('198.51.100.20', $resolver->resolve());
    }

    public function testTrustedProxyChainSkipsAllProxies(): void
    {
        $resolver = new IpResolver(
            ['10.0.0.0/8'],
            [
                'REMOTE_ADDR'          => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.20, 10.1.1.1, 10.0.0.1',
            ]
        );

        self::assertSame('198.51.100.20', $resolver->resolve());
    }

    public function testReturnsNullForInvalidRemoteAddr(): void
    {
        $resolver = new IpResolver([], ['REMOTE_ADDR' => 'not-an-ip']);

        self::assertNull($resolver->resolve());
    }

    public function testReturnsNullWhenRemoteAddrMissing(): void
    {
        $resolver = new IpResolver([], []);

        self::assertNull($resolver->resolve());
    }

    public function testIgnoresInvalidEntriesInForwardedHeader(): void
    {
        $resolver = new IpResolver(
            ['10.0.0.1'],
            [
                'REMOTE_ADDR'          => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => 'garbage, 198.51.100.20, 10.0.0.1',
            ]
        );

        self::assertSame('198.51.100.20', $resolver->resolve());
    }

    public function testHashIpProducesStableHmac(): void
    {
        $resolver = new IpResolver([], []);

        $a = $resolver->hashIp('203.0.113.42');
        $b = $resolver->hashIp('203.0.113.42');

        self::assertNotNull($a);
        self::assertSame($a, $b);
        self::assertSame(64, strlen((string) $a));
    }

    public function testHashIpReturnsNullForInvalidInput(): void
    {
        $resolver = new IpResolver([], []);

        self::assertNull($resolver->hashIp(null));
        self::assertNull($resolver->hashIp(''));
        self::assertNull($resolver->hashIp('not-an-ip'));
    }

    public function testCidrIPv6Match(): void
    {
        $resolver = new IpResolver(
            ['2001:db8::/64'],
            [
                'REMOTE_ADDR'          => '2001:db8::1',
                'HTTP_X_FORWARDED_FOR' => '2001:db9::beef, 2001:db8::1',
            ]
        );

        self::assertSame('2001:db9::beef', $resolver->resolve());
    }
}
