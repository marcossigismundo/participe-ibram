<?php
/**
 * Asset enqueuer for Participe Ibram (DSGov-aligned).
 *
 * Carrega CSS/JS apenas em contextos do plugin:
 *  - Frontend: páginas que contém os shortcodes do plugin.
 *  - Admin: hooks com prefixo `pi_*` ou `participe-ibram_*`.
 *
 * Wave 3 / W3-D — implementa enqueue condicional + scope wrapper.
 *
 * @package Ibram\ParticipeIbram\Presentation\Assets
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Assets;

use Ibram\ParticipeIbram\Core\Helpers\Json;

/**
 * Registra os hooks `wp_enqueue_scripts` e `admin_enqueue_scripts`.
 *
 * Wrapper obrigatório no HTML: `<div class="participe-ibram-scope">…</div>`.
 *
 * @see assets/dist/css/tokens.css
 * @see assets/dist/css/participe-ibram-public.css
 * @see assets/dist/css/participe-ibram-admin.css
 */
final class AssetEnqueuer
{
    /**
     * Versão dos assets — bumpar a cada deploy para invalidar cache.
     */
    public const VERSION = '1.0.0';

    /**
     * Shortcodes que disparam o enqueue público.
     *
     * @var list<string>
     */
    // W14.9: lista completa dos 8 shortcodes registrados pelos Public Controllers.
    // Antes faltavam pi_inscricao_edital, pi_editais_publicos, pi_edital_detalhes,
    // pi_edital_resultados e pi_votacao_transparencia — paginas com esses
    // shortcodes renderizavam SEM nenhum CSS porque isPluginPublicPage()
    // retornava false.
    private const PUBLIC_SHORTCODES = [
        'pi_cadastro',
        'pi_dashboard_publico',
        'pi_minha_conta',
        'pi_votacao',
        'pi_lgpd_meus_dados',
        'pi_editais_publicos',
        'pi_edital_detalhes',
        'pi_inscricao_edital',
        'pi_edital_resultados',
        'pi_votacao_transparencia',
    ];

    /**
     * Prefixos de hook admin que pertencem ao plugin.
     *
     * @var list<string>
     */
    private const ADMIN_HOOK_PREFIXES = [
        'pi_',
        'participe-ibram_',
        'toplevel_page_pi-',
        'toplevel_page_participe-ibram',
        // Paginas ocultas registradas com parent='options.php' geram hook
        // 'admin_page_<slug>' OU 'settings_page_<slug>' dependendo de como
        // o WP resolve get_admin_page_parent. Smoke test confirmou que e
        // 'admin_page_' (debug.log: hook NAO matched: admin_page_participe-ibram_edital).
        // Cobertura: Edital detalhes, Categoria, Detalhes do agente,
        // Apuracao, Audit detalhe.
        'admin_page_participe-ibram',
        'admin_page_pi-',
        'settings_page_participe-ibram',
        'settings_page_pi-',
    ];

    /**
     * Caminho absoluto da raiz do plugin (com trailing slash).
     */
    private string $pluginPath;

    /**
     * URL base dos assets (com trailing slash).
     */
    private string $pluginUrl;

    /**
     * @param string $pluginPath  __DIR__-style path da raiz do plugin.
     * @param string $pluginUrl   plugins_url() base do plugin.
     */
    public function __construct(string $pluginPath, string $pluginUrl)
    {
        $this->pluginPath = trailingslashit($pluginPath);
        $this->pluginUrl  = trailingslashit($pluginUrl);
    }

    /**
     * Registra hooks WordPress. Chamar no Plugin/bootstrap.
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublic']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
        add_filter('script_loader_tag', [$this, 'asModule'], 10, 3);
    }

    /**
     * Enqueue público — apenas em páginas com shortcodes do plugin.
     */
    public function enqueuePublic(): void
    {
        if (!$this->isPluginPublicPage()) {
            return;
        }

        $cssBase = $this->pluginUrl . 'assets/dist/css/';
        $jsBase  = $this->pluginUrl . 'assets/dist/js/';

        // Cache-bust automatico via filemtime do CSS publico — qualquer
        // mudanca no dist invalida o cache do browser imediatamente
        // (paralelo ao enqueueAdmin).
        $cssPath    = $this->pluginPath . 'assets/dist/css/';
        $verPublic  = is_file($cssPath . 'participe-ibram-public.css')
            ? (string) filemtime($cssPath . 'participe-ibram-public.css')
            : self::VERSION;
        $verTokens  = is_file($cssPath . 'tokens.css')
            ? (string) filemtime($cssPath . 'tokens.css')
            : self::VERSION;

        // Tokens primeiro — outras folhas dependem dele.
        wp_enqueue_style(
            'pi-tokens',
            $cssBase . 'tokens.css',
            [],
            $verTokens
        );

        wp_enqueue_style(
            'pi-public',
            $cssBase . 'participe-ibram-public.css',
            ['pi-tokens'],
            $verPublic
        );

        // JS modular do W3-C
        wp_enqueue_script(
            'pi-wizard',
            $jsBase . 'wizard/index.js',
            [],
            self::VERSION,
            ['strategy' => 'defer', 'in_footer' => true]
        );

        wp_localize_script('pi-wizard', 'piConfig', $this->buildConfig());
        wp_localize_script('pi-wizard', 'piI18n', $this->buildI18n());
    }

    /**
     * Enqueue admin — apenas em telas do plugin.
     *
     * @param string $hook Suffix da tela (`get_current_screen()->id` like).
     */
    public function enqueueAdmin(string $hook): void
    {
        if (!$this->isPluginAdminScreen($hook)) {
            // Em DEBUG, registra hooks rejeitados para diagnose de cache/CSS.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[pi-assets] hook NAO matched: ' . $hook);
            }
            return;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[pi-assets] hook matched, enfileirando CSS para: ' . $hook);
        }

        $cssBase = $this->pluginUrl . 'assets/dist/css/';
        $jsBase  = $this->pluginUrl . 'assets/dist/js/';

        // Cache-bust automatico via filemtime do CSS principal — qualquer
        // mudanca no dist invalida o cache do browser imediatamente.
        $cssPath  = $this->pluginPath . 'assets/dist/css/';
        $verAdmin = is_file($cssPath . 'participe-ibram-admin.css')
            ? (string) filemtime($cssPath . 'participe-ibram-admin.css')
            : self::VERSION;
        $verTokens = is_file($cssPath . 'tokens.css')
            ? (string) filemtime($cssPath . 'tokens.css')
            : self::VERSION;

        wp_enqueue_style(
            'pi-tokens',
            $cssBase . 'tokens.css',
            [],
            $verTokens
        );

        wp_enqueue_style(
            'pi-admin',
            $cssBase . 'participe-ibram-admin.css',
            ['pi-tokens'],
            $verAdmin
        );

        // Wave 4-A admin JS: list table inline confirms + detalhes page tabs/modais.
        wp_enqueue_script(
            'pi-admin-list-table-actions',
            $jsBase . 'admin/list-table-actions.js',
            [],
            self::VERSION,
            ['strategy' => 'defer', 'in_footer' => true]
        );

        wp_enqueue_script(
            'pi-admin-agente-detalhes',
            $jsBase . 'admin/agente-detalhes.js',
            [],
            self::VERSION,
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }

    /**
     * Adiciona `type="module"` aos scripts modulares.
     *
     * @param string $tag    Tag HTML do script.
     * @param string $handle Handle do script.
     * @param string $src    URL do script.
     */
    public function asModule(string $tag, string $handle, string $src): string
    {
        $modules = ['pi-wizard'];
        if (!in_array($handle, $modules, true)) {
            return $tag;
        }
        // Substitui apenas a primeira ocorrência da abertura `<script` por
        // `<script type="module"`, preservando atributos como `defer`.
        $replaced = preg_replace(
            '/<script\b/',
            '<script type="module"',
            $tag,
            1
        );
        return $replaced ?? $tag;
    }

    /**
     * Detecta se o post atual contém algum dos shortcodes do plugin.
     */
    private function isPluginPublicPage(): bool
    {
        if (is_admin()) {
            return false;
        }

        global $post;
        if (!$post || !isset($post->post_content)) {
            return false;
        }

        foreach (self::PUBLIC_SHORTCODES as $shortcode) {
            if (has_shortcode((string) $post->post_content, $shortcode)) {
                return true;
            }
        }

        // Permite override via filter (ex.: rotas de votação sem shortcode).
        return (bool) apply_filters('pi_assets_is_public_page', false, $post);
    }

    /**
     * Detecta se o hook admin pertence ao plugin.
     *
     * @param string $hook Hook suffix recebido por `admin_enqueue_scripts`.
     */
    private function isPluginAdminScreen(string $hook): bool
    {
        foreach (self::ADMIN_HOOK_PREFIXES as $prefix) {
            if (strpos($hook, $prefix) === 0) {
                return true;
            }
        }

        // Override via filter para casos especiais.
        return (bool) apply_filters('pi_assets_is_admin_screen', false, $hook);
    }

    /**
     * Configuração injetada como `window.piConfig`.
     *
     * @return array<string, mixed>
     */
    private function buildConfig(): array
    {
        $config = [
            'apiUrl'         => esc_url_raw(rest_url('pi/v1/')),
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('wp_rest'),
            'wizardNonce'    => wp_create_nonce('pi_wizard'),
            'uploadMaxBytes' => (int) apply_filters('pi_upload_max_bytes', 10 * 1024 * 1024),
            'mimePermitidos' => [
                'application/pdf',
                'image/jpeg',
                'image/png',
            ],
            'vocabularios'   => [
                'racaCor' => ['branca', 'preta', 'parda', 'amarela', 'indigena', 'prefiro_nao_informar'],
                'genero'  => ['mulher_cis', 'homem_cis', 'mulher_trans', 'homem_trans', 'nao_binario', 'outro', 'prefiro_nao_informar'],
            ],
        ];

        /** @var array<string, mixed> */
        return apply_filters('pi_assets_public_config', $config);
    }

    /**
     * Dicionário i18n pt_BR.
     *
     * @return array<string, string>
     */
    private function buildI18n(): array
    {
        $strings = [
            'wizard.next'            => __('Avançar', 'participe-ibram'),
            'wizard.prev'            => __('Voltar', 'participe-ibram'),
            'wizard.save'            => __('Salvar rascunho', 'participe-ibram'),
            'wizard.submit'          => __('Enviar cadastro', 'participe-ibram'),
            'wizard.savingDraft'     => __('Salvando rascunho…', 'participe-ibram'),
            'wizard.draftSavedAt'    => __('Rascunho salvo em %s.', 'participe-ibram'),
            'wizard.draftSaveError'  => __('Não foi possível salvar. Tente novamente.', 'participe-ibram'),
            'wizard.stepStatus'      => __('Passo %1$d de %2$d: %3$s.', 'participe-ibram'),
            'wizard.errorsFound'     => __('%d erro(s) encontrado(s) neste passo.', 'participe-ibram'),
            'wizard.requiredField'   => __('Este campo é obrigatório.', 'participe-ibram'),
            'wizard.invalidFormat'   => __('Formato inválido.', 'participe-ibram'),
            'upload.tooLarge'        => __('Arquivo excede o tamanho máximo permitido.', 'participe-ibram'),
            'upload.invalidType'     => __('Tipo de arquivo não permitido.', 'participe-ibram'),
            'modal.close'            => __('Fechar', 'participe-ibram'),
            'lgpd.requiredConsent'   => __('Você precisa aceitar os termos obrigatórios para prosseguir.', 'participe-ibram'),
        ];

        /** @var array<string, string> */
        return apply_filters('pi_assets_public_i18n', $strings);
    }

    /**
     * Helper para gerar `<script type="application/json">` inline com payload
     * seguro para script-context.
     *
     * @param string               $id   ID do `<script>`.
     * @param array<string, mixed> $data Payload.
     */
    public static function renderInlineJson(string $id, array $data): string
    {
        return sprintf(
            '<script type="application/json" id="%s">%s</script>',
            esc_attr($id),
            Json::encodeForScript($data)
        );
    }
}
