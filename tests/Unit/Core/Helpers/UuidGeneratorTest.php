<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Helpers\UuidGenerator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Helpers;

use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use PHPUnit\Framework\TestCase;

final class UuidGeneratorTest extends TestCase
{
    public function test_generate_returns_v4_uuid(): void
    {
        $uuid = UuidGenerator::generate();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function test_generate_returns_unique_values(): void
    {
        $a = UuidGenerator::generate();
        $b = UuidGenerator::generate();
        $this->assertNotSame($a, $b);
    }

    public function test_generate_short_returns_requested_length(): void
    {
        $this->assertSame(8,  strlen(UuidGenerator::generateShort(8)));
        $this->assertSame(16, strlen(UuidGenerator::generateShort(16)));
    }

    public function test_generate_short_uses_base62_alphabet(): void
    {
        $value = UuidGenerator::generateShort(32);
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]+$/', $value);
    }

    public function test_generate_short_clamps_zero_or_negative(): void
    {
        $this->assertSame(1, strlen(UuidGenerator::generateShort(0)));
        $this->assertSame(1, strlen(UuidGenerator::generateShort(-5)));
    }
}
