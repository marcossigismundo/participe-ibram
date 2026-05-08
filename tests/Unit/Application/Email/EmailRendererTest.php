<?php
/**
 * Testes unitários do {@see EmailRenderer}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Email;

use Ibram\ParticipeIbram\Application\Email\Templates\EmailRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Ibram\ParticipeIbram\Application\Email\Templates\EmailRenderer
 */
final class EmailRendererTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Aponta para a pasta de templates real do plugin.
        $this->baseDir = realpath(__DIR__ . '/../../../../templates/emails')
            ?: dirname(__DIR__, 4) . '/templates/emails';
    }

    public function test_render_substitui_vars_e_envelopa_html(): void
    {
        $renderer = new EmailRenderer($this->baseDir);
        $out = $renderer->render('cadastro_deferido', [
            'nome'             => 'Joana da Silva',
            'numero_registro'  => 'PI-PF-2025-000001',
            'painel_url'       => 'https://museus.gov.br/painel/',
            'unsubscribe_url'  => 'https://museus.gov.br/?pi_action=unsubscribe&token=AAA',
            'dpo_email'        => 'encarregado@museus.gov.br',
        ]);

        $this->assertArrayHasKey('assunto', $out);
        $this->assertArrayHasKey('html', $out);
        $this->assertArrayHasKey('text', $out);

        // Subject inclui número de registro.
        $this->assertStringContainsString('PI-PF-2025-000001', $out['assunto']);
        // Subject <= 78 chars.
        $this->assertLessThanOrEqual(78, mb_strlen($out['assunto'], 'UTF-8'));

        // HTML envelopado com lang pt-BR e charset UTF-8.
        $this->assertStringContainsString('<html lang="pt-BR">', $out['html']);
        $this->assertStringContainsString('charset="UTF-8"', $out['html']);
        // Nome aparece escapado.
        $this->assertStringContainsString('Joana da Silva', $out['html']);
        // Footer com unsubscribe e DPO.
        $this->assertStringContainsString('?pi_action=unsubscribe', $out['html']);
        $this->assertStringContainsString('encarregado@museus.gov.br', $out['html']);

        // Versão texto presente e contém o número.
        $this->assertNotSame('', trim($out['text']));
        $this->assertStringContainsString('PI-PF-2025-000001', $out['text']);
    }

    public function test_render_escapa_html_em_var_simples(): void
    {
        $renderer = new EmailRenderer($this->baseDir);
        $out = $renderer->render('cadastro_submetido', [
            'nome'           => '<script>alert(1)</script>',
            'data_submissao' => '01/01/2025',
            'painel_url'     => 'https://museus.gov.br/painel/',
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $out['html']);
        $this->assertStringContainsString('&lt;script&gt;', $out['html']);
    }

    public function test_render_falha_quando_template_nao_existe(): void
    {
        $renderer = new EmailRenderer($this->baseDir);
        $this->expectException(RuntimeException::class);
        $renderer->render('evento_inexistente', []);
    }

    public function test_subject_long_truncated_para_78(): void
    {
        $renderer = new EmailRenderer($this->baseDir);
        $longNumero = str_repeat('A', 200);
        $out = $renderer->render('cadastro_deferido', [
            'nome'             => 'X',
            'numero_registro'  => $longNumero,
            'painel_url'       => 'https://museus.gov.br/painel/',
        ]);
        $this->assertLessThanOrEqual(78, mb_strlen($out['assunto'], 'UTF-8'));
    }

    public function test_render_broadcast_sem_pii(): void
    {
        $renderer = new EmailRenderer($this->baseDir);
        $out = $renderer->render('edital_publicado', [
            'edital_titulo'      => 'Edital 2025',
            'edital_resumo'      => 'Texto do resumo.',
            'periodo_inscricao'  => '01/01 a 31/01/2025',
            'edital_url'         => 'https://museus.gov.br/edital/2025-001',
            'unsubscribe_url'    => '',
            'dpo_email'          => 'encarregado@museus.gov.br',
        ]);
        // Broadcast: NÃO tem nome individual.
        $this->assertStringNotContainsString('{nome}', $out['html']);
    }

    public function test_template_invalido_lanca(): void
    {
        $renderer = new EmailRenderer($this->baseDir);
        $this->expectException(\InvalidArgumentException::class);
        $renderer->render('../etc/passwd', []);
    }
}
