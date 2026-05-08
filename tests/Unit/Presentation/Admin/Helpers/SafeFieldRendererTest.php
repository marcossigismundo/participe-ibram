<?php
/**
 * Tests for SafeFieldRenderer.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Helpers;

use Ibram\ParticipeIbram\Presentation\Admin\Helpers\SafeFieldRenderer;
use PHPUnit\Framework\TestCase;

final class SafeFieldRendererTest extends TestCase
{
    public function test_cpf_masked_by_default(): void
    {
        $out = SafeFieldRenderer::cpf('12345678901', false);
        $this->assertStringContainsString('XXX', $out);
        $this->assertStringNotContainsString('123.456', $out);
        $this->assertStringContainsString('789', $out); // bloco preservado pelo PiiMasker
    }

    public function test_cpf_revealed_returns_formatted_value(): void
    {
        $out = SafeFieldRenderer::cpf('12345678901', true);
        $this->assertSame('123.456.789-01', $out);
    }

    public function test_cpf_returns_dash_when_empty(): void
    {
        $this->assertSame('—', SafeFieldRenderer::cpf(null, true));
        $this->assertSame('—', SafeFieldRenderer::cpf('', true));
        $this->assertSame('—', SafeFieldRenderer::cpf('   ', true));
    }

    public function test_cnpj_masked_by_default(): void
    {
        $out = SafeFieldRenderer::cnpj('12345678000199', false);
        $this->assertStringContainsString('XX', $out);
        $this->assertStringNotContainsString('12.345.678', $out);
    }

    public function test_cnpj_revealed_returns_formatted(): void
    {
        $out = SafeFieldRenderer::cnpj('12345678000199', true);
        $this->assertSame('12.345.678/0001-99', $out);
    }

    public function test_email_masked_by_default(): void
    {
        $out = SafeFieldRenderer::email('fulano@example.org', false);
        $this->assertStringContainsString('***', $out);
        $this->assertStringContainsString('@example.org', $out);
    }

    public function test_email_revealed(): void
    {
        $this->assertSame('fulano@example.org', SafeFieldRenderer::email('fulano@example.org', true));
    }

    public function test_identidade_masked_by_default(): void
    {
        $out = SafeFieldRenderer::identidade('1234567', false);
        $this->assertStringContainsString('***', $out);
    }

    public function test_phone_masked_by_default(): void
    {
        $out = SafeFieldRenderer::phone('+55 (61) 99999-1234', false);
        $this->assertStringContainsString('1234', $out);
        $this->assertStringContainsString('XX', $out);
    }

    public function test_default_arg_is_masking(): void
    {
        // Garante que o $reveal default = false (mascarado por segurança).
        $out = SafeFieldRenderer::cpf('12345678901');
        $this->assertStringContainsString('XXX', $out);
    }
}
