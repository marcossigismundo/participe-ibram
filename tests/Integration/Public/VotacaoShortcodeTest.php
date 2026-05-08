<?php
/**
 * Integration tests — VotacaoShortcodes (W6-B).
 *
 * Cobre:
 *  - Shortcode com id inválido → mensagem clara (sem template carregado)
 *  - Votação encerrada → renderiza votacao-encerrada.php
 *  - Votação ativa (status=aberta) → renderiza votacao-app.php com data-pi-votacao
 *  - Usuário não logado → mensagem com link para login
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Public
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Public;

use Ibram\ParticipeIbram\Presentation\Public\Controllers\VotacaoShortcodes;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bootstrap.php';

// Stubs WordPress específicos do shortcode -----------------------------------

if (!function_exists('Ibram\\ParticipeIbram\\Tests\\Integration\\Public\\__shortcode_stubs_loaded')) {
    function __shortcode_stubs_loaded(): bool { return true; }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $cb): void
    {
        $GLOBALS['__pi_test_shortcodes'][$tag] = $cb;
    }
}
if (!function_exists('shortcode_atts')) {
    function shortcode_atts(array $defaults, $atts): array
    {
        return array_merge($defaults, is_array($atts) ? $atts : []);
    }
}
if (!function_exists('esc_html')) {
    function esc_html(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url(string $v): string { return htmlspecialchars(filter_var($v, FILTER_SANITIZE_URL) ?: '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
}
if (!function_exists('esc_html__')) {
    function esc_html__(string $t, string $d = 'default'): string { return $t; }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__(string $t, string $d = 'default'): string { return $t; }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e(string $t, string $d = 'default'): void { echo $t; }
}
if (!function_exists('esc_attr_e')) {
    function esc_attr_e(string $t, string $d = 'default'): void { echo $t; }
}
if (!function_exists('wp_login_url')) {
    function wp_login_url(string $redirect = ''): string
    {
        return '/wp-login.php' . ($redirect !== '' ? '?redirect_to=' . urlencode($redirect) : '');
    }
}
if (!function_exists('get_permalink')) {
    function get_permalink(): string { return 'http://localhost/votar/'; }
}
if (!function_exists('get_rest_url')) {
    function get_rest_url($x = null, string $path = ''): string
    {
        return 'http://localhost/wp-json/' . ltrim($path, '/');
    }
}
if (!function_exists('home_url')) {
    function home_url(string $path = ''): string { return 'http://localhost' . $path; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string { return 'NONCE-' . md5($action); }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $opts = 0, int $depth = 512): string|false
    {
        return json_encode($data, $opts | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth);
    }
}
if (!function_exists('date_i18n')) {
    function date_i18n(string $format): string { return date($format); }
}

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Public\Controllers\VotacaoShortcodes
 */
final class VotacaoShortcodeTest extends TestCase
{
    private string $templatesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templatesDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR
            . 'templates' . DIRECTORY_SEPARATOR
            . 'public' . DIRECTORY_SEPARATOR
            . 'votacao';
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $GLOBALS['__pi_test_shortcodes']      = [];
    }

    private function makeShortcode(callable $resolver): VotacaoShortcodes
    {
        return new VotacaoShortcodes($this->templatesDir, $resolver);
    }

    public function test_register_adiciona_shortcode_pi_votacao(): void
    {
        $sc = $this->makeShortcode(static fn (int $id) => null);
        $sc->register();
        $this->assertArrayHasKey('pi_votacao', $GLOBALS['__pi_test_shortcodes']);
    }

    public function test_id_invalido_retorna_mensagem_clara_sem_template(): void
    {
        $sc = $this->makeShortcode(static fn (int $id) => null);
        $html = $sc->renderVotacao(['id' => '0']);

        $this->assertStringContainsString('pi-aviso', $html, 'Deve renderizar uma mensagem');
        $this->assertStringContainsString('ID de votação não informado', $html);
        // Não deve renderizar a app
        $this->assertStringNotContainsString('data-pi-votacao=', $html);
    }

    public function test_id_negativo_ou_textual_retorna_mensagem_clara(): void
    {
        $sc = $this->makeShortcode(static fn (int $id) => null);
        $html = $sc->renderVotacao(['id' => 'abc']);
        $this->assertStringContainsString('ID de votação não informado', $html);
    }

    public function test_usuario_nao_logado_recebe_link_de_login(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 0; // não logado
        $sc = $this->makeShortcode(static fn (int $id) => [
            'id'               => $id,
            'status'           => 'aberta',
            'titulo_edital'    => 'Edital X',
            'abertura_iso'     => '2026-01-01T00:00:00-03:00',
            'encerramento_iso' => '2026-12-31T23:59:59-03:00',
            'resultados_url'   => '',
        ]);
        $html = $sc->renderVotacao(['id' => '42']);
        $this->assertStringContainsString('autenticado', $html);
        $this->assertStringContainsString('wp-login.php', $html);
    }

    public function test_votacao_encerrada_renderiza_template_encerrada(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $sc = $this->makeShortcode(static fn (int $id) => [
            'id'               => $id,
            'status'           => 'encerrada',
            'titulo_edital'    => 'Edital Y',
            'abertura_iso'     => '2025-01-01T00:00:00-03:00',
            'encerramento_iso' => '2025-12-31T23:59:59-03:00',
            'resultados_url'   => '/resultados/123',
        ]);
        $html = $sc->renderVotacao(['id' => '99']);

        $this->assertStringContainsString('pi-votacao-app--encerrada', $html);
        $this->assertStringContainsString('Edital Y', $html);
        // Não deve carregar a app interativa
        $this->assertStringNotContainsString('data-pi-votacao="99"', $html);
    }

    public function test_votacao_agendada_renderiza_template_encerrada_com_status_agendada(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $sc = $this->makeShortcode(static fn (int $id) => [
            'id'               => $id,
            'status'           => 'agendada',
            'titulo_edital'    => 'Edital Z',
            'abertura_iso'     => '2027-01-01T00:00:00-03:00',
            'encerramento_iso' => '2027-12-31T23:59:59-03:00',
            'resultados_url'   => '',
        ]);
        $html = $sc->renderVotacao(['id' => '13']);

        $this->assertStringContainsString('Votação agendada', $html);
        $this->assertStringContainsString('Edital Z', $html);
    }

    public function test_votacao_ativa_renderiza_app_com_atributos_de_dados(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 11;
        $sc = $this->makeShortcode(static fn (int $id) => [
            'id'               => $id,
            'status'           => 'aberta',
            'titulo_edital'    => 'Edital Ativo',
            'abertura_iso'     => '2026-04-01T00:00:00-03:00',
            'encerramento_iso' => '2026-05-31T23:59:59-03:00',
            'resultados_url'   => '',
        ]);
        $html = $sc->renderVotacao(['id' => '77']);

        $this->assertStringContainsString('data-pi-votacao="77"', $html);
        $this->assertStringContainsString('Edital Ativo', $html);
        $this->assertStringContainsString('participe-ibram-scope', $html);
        // Modal de confirmação presente no DOM
        $this->assertStringContainsString('pi-confirmacao-modal--warning', $html);
        $this->assertStringContainsString('IRREVERSÍVEL', $html);
        // Botão Confirmar inicialmente desabilitado (aria-disabled)
        $this->assertStringContainsString('data-pi-confirm-ok', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        // Live region presente
        $this->assertStringContainsString('data-pi-votacao-live', $html);
    }

    public function test_resolver_lanca_excecao_renderiza_mensagem_de_erro(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 11;
        $sc = $this->makeShortcode(static function (int $id) {
            throw new \RuntimeException('db down');
        });
        $html = $sc->renderVotacao(['id' => '77']);
        $this->assertStringContainsString('pi-aviso--erro', $html);
        $this->assertStringContainsString('Não foi possível carregar', $html);
    }

    public function test_resolver_retorna_null_renderiza_inexistente(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 11;
        $sc = $this->makeShortcode(static fn (int $id) => null);
        $html = $sc->renderVotacao(['id' => '77']);
        $this->assertStringContainsString('Votação não encontrada', $html);
    }
}
