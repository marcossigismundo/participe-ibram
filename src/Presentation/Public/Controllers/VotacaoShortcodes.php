<?php
/**
 * Shortcodes públicos de votação (Participe Ibram — Wave 6).
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

/**
 * Registra os shortcodes públicos de votação:
 *  - [pi_votacao id="..."] → templates/public/votacao/votacao-app.php
 *
 * Verifica se a votação existe e está em status apropriado antes de
 * renderizar a app. Caso contrário, renderiza votacao-encerrada.php
 * com mensagem clara.
 *
 * Segurança:
 *  - id sanitizado via absint
 *  - usuário precisa estar logado para tentar votar (é a UI; o backend valida
 *    novamente)
 *  - nenhum dado privado de eleitor/candidato é vazado pelo shortcode
 */
final class VotacaoShortcodes
{
    /** Caminho absoluto para `templates/public/votacao/`. */
    private string $templatesDir;

    /** Função/closure que retorna metadados de uma votação por ID.
     *
     * Assinatura: `function (int $votacaoId): ?array` retornando, quando existe:
     *   [
     *     'id' => int,
     *     'status' => 'agendada'|'aberta'|'encerrada'|'cancelada',
     *     'titulo_edital' => string,
     *     'abertura_iso' => string,
     *     'encerramento_iso' => string,
     *     'resultados_url' => string,
     *   ]
     *
     * Aceitamos closure injetada para manter este controller livre do
     * acoplamento direto com o repositório (testabilidade).
     *
     * @var callable(int):?array<string,mixed>
     */
    private $resolverVotacao;

    /**
     * @param string                        $templatesDir   Caminho `templates/public/votacao/`.
     * @param callable(int):?array<string,mixed> $resolver  Closure de metadados.
     */
    public function __construct(string $templatesDir, callable $resolver)
    {
        $this->templatesDir    = rtrim($templatesDir, '/\\');
        $this->resolverVotacao = $resolver;
    }

    /** Registra os shortcodes (chamar em `init`). */
    public function register(): void
    {
        if (!function_exists('add_shortcode')) {
            return;
        }
        \add_shortcode('pi_votacao', [$this, 'renderVotacao']);
    }

    /**
     * [pi_votacao id="..."]
     *
     * @param array<string,string>|string $atts
     */
    public function renderVotacao($atts): string
    {
        $atts = function_exists('shortcode_atts')
            ? \shortcode_atts(['id' => ''], is_array($atts) ? $atts : [])
            : (is_array($atts) ? $atts + ['id' => ''] : ['id' => '']);

        $votacao_id = function_exists('absint')
            ? (int) \absint($atts['id'])
            : (int) abs((int) $atts['id']);

        if ($votacao_id <= 0) {
            return $this->mensagem(
                $this->t('ID de votação não informado.', 'pi-votacao-id-vazio'),
                'erro'
            );
        }

        // Verificar autenticação — UI requer login. Backend valida novamente.
        if (!$this->isUserLoggedIn()) {
            $login_url = $this->loginUrl();

            return $this->mensagem(
                sprintf(
                    /* translators: %s: link de login */
                    $this->t('Você precisa estar autenticado para votar. %s', 'pi-votacao-login'),
                    '<a href="' . \esc_url($login_url) . '">' . \esc_html($this->t('Entrar', 'pi-votacao-entrar')) . '</a>'
                ),
                'aviso'
            );
        }

        // Resolver metadados da votação.
        $meta = null;
        try {
            $meta = ($this->resolverVotacao)($votacao_id);
        } catch (\Throwable $e) {
            return $this->mensagem(
                $this->t('Não foi possível carregar os dados da votação. Tente novamente em instantes.', 'pi-votacao-erro-meta'),
                'erro'
            );
        }

        if (!is_array($meta) || empty($meta)) {
            return $this->renderTemplate('votacao-encerrada.php', [
                'votacao_id'       => $votacao_id,
                'titulo_edital'    => '',
                'status'           => 'inexistente',
                'abertura_iso'     => '',
                'encerramento_iso' => '',
                'resultados_url'   => '',
            ]);
        }

        $status = isset($meta['status']) ? (string) $meta['status'] : 'inexistente';
        $titulo_edital   = isset($meta['titulo_edital']) ? (string) $meta['titulo_edital'] : '';
        $abertura_iso    = isset($meta['abertura_iso']) ? (string) $meta['abertura_iso'] : '';
        $encerramento_iso = isset($meta['encerramento_iso']) ? (string) $meta['encerramento_iso'] : '';
        $resultados_url  = isset($meta['resultados_url']) ? (string) $meta['resultados_url'] : '';

        // Status apropriado para renderizar a UI ativa: 'aberta'.
        if ($status !== 'aberta') {
            return $this->renderTemplate('votacao-encerrada.php', [
                'votacao_id'       => $votacao_id,
                'titulo_edital'    => $titulo_edital,
                'status'           => in_array($status, ['agendada', 'encerrada', 'cancelada'], true)
                    ? $status
                    : 'encerrada',
                'abertura_iso'     => $abertura_iso,
                'encerramento_iso' => $encerramento_iso,
                'resultados_url'   => $resultados_url,
            ]);
        }

        // Render UI ativa.
        return $this->renderTemplate('votacao-app.php', [
            'votacao_id'         => $votacao_id,
            'titulo_edital'      => $titulo_edital,
            'abertura_iso'       => $abertura_iso,
            'encerramento_iso'   => $encerramento_iso,
            'api_url'            => $this->apiUrl(),
            'rest_nonce'         => $this->restNonce(),
            'login_url'          => $this->loginUrl(),
            'auditoria_url_base' => $this->auditoriaUrlBase($votacao_id),
        ]);
    }

    // --- Helpers ------------------------------------------------------------

    /**
     * @param array<string,mixed> $vars
     */
    private function renderTemplate(string $template, array $vars): string
    {
        $path = $this->templatesDir . DIRECTORY_SEPARATOR . $template;
        if (!is_file($path)) {
            return $this->mensagem(
                $this->t('Template indisponível.', 'pi-votacao-template-falta'),
                'erro'
            );
        }
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($vars, EXTR_SKIP);
        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    private function mensagem(string $html, string $tipo = 'aviso'): string
    {
        $cls = $tipo === 'erro' ? 'pi-aviso pi-aviso--erro' : 'pi-aviso';

        return '<div class="participe-ibram-scope"><p class="' . \esc_attr($cls) . '" role="alert">'
            . \wp_kses_post($html)
            . '</p></div>';
    }

    private function apiUrl(): string
    {
        if (function_exists('get_rest_url')) {
            return (string) \get_rest_url(null, 'pi/v1');
        }
        if (function_exists('home_url')) {
            return (string) \home_url('/wp-json/pi/v1');
        }

        return '/wp-json/pi/v1';
    }

    private function restNonce(): string
    {
        return function_exists('wp_create_nonce') ? (string) \wp_create_nonce('wp_rest') : '';
    }

    private function loginUrl(): string
    {
        if (function_exists('wp_login_url') && function_exists('get_permalink')) {
            return (string) \wp_login_url((string) \get_permalink());
        }

        return '/wp-login.php';
    }

    private function isUserLoggedIn(): bool
    {
        return function_exists('is_user_logged_in') && (bool) \is_user_logged_in();
    }

    private function auditoriaUrlBase(int $votacaoId): string
    {
        if (function_exists('get_rest_url')) {
            return (string) \get_rest_url(null, 'pi/v1/publico/votacao/' . $votacaoId . '/auditoria');
        }

        return '/wp-json/pi/v1/publico/votacao/' . $votacaoId . '/auditoria';
    }

    /**
     * Tradução defensiva — usa __() quando disponível.
     */
    private function t(string $text, string $_context = ''): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
