<?php
/**
 * Registers all public controllers, shortcodes, and AJAX endpoints.
 *
 * Called by Plugin integrator after W9-A wires the Container.
 * Does NOT wire admin-only controllers (delegated to W9-B AdminRegistration).
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Application\Consentimento\RevogarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Edital\ProtocolarRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Application\Email\Templates\UnsubscribeTokenizer;
use Ibram\ParticipeIbram\Presentation\Assets\AssetEnqueuer;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\CadastroShortcode;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\DownloadDocumentoController;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\EditalShortcodes;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\MinhaContaShortcode;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\RecursoInabilitacaoPublicController;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\TransparenciaShortcodes;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\UnsubscribeController;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\VotacaoShortcodes;

/**
 * Wires all public-facing shortcodes and AJAX controllers.
 *
 * Shortcodes are hooked on `init` (priority 20) via each controller's
 * register() / registerHooks() method. AJAX handlers are hooked via
 * wp_ajax_* / wp_ajax_nopriv_* directly inside their register() calls.
 *
 * Templates directory convention (relative to plugin root):
 *  - Wizard / Cadastro : templates/public/wizard/
 *  - Editais           : templates/public/editais/
 *  - Votação           : templates/public/votacao/
 *  - Minha Conta       : templates/public/minha-conta/
 *  - Unsubscribe page  : templates/public/unsubscribe.php
 */
final class PublicRegistration
{
    public static function register(Container $container): void
    {
        // Resolve the plugin root dir from the PI_PLUGIN_DIR constant
        // (defined in participe-ibram.php on plugin activation).
        $pluginDir = defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : '';
        $pluginUrl = defined('PI_PLUGIN_URL') ? (string) \PI_PLUGIN_URL : '';

        $tplBase = rtrim($pluginDir, '/\\') . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'public';

        // ------------------------------------------------------------------
        // [pi_cadastro tipo="PF|OR|SM"] — Wave 3 (W9-C creates controller)
        // Template: templates/public/wizard/form-{pf,or,sm}.php
        // ------------------------------------------------------------------
        if (class_exists(CadastroShortcode::class)) {
            $container->singleton('public:cadastro_shortcode', static function () use ($tplBase): CadastroShortcode {
                return new CadastroShortcode(
                    $tplBase . DIRECTORY_SEPARATOR . 'wizard'
                );
            });

            \add_action('init', static function () use ($container): void {
                $container->get('public:cadastro_shortcode')->register();
            }, 20);
        }

        // ------------------------------------------------------------------
        // [pi_editais_publicos], [pi_edital_detalhes], [pi_inscricao_edital],
        // [pi_edital_resultados] — Wave 5-B
        // Templates: templates/public/editais/*.php
        // ------------------------------------------------------------------
        if (class_exists(EditalShortcodes::class)) {
            $container->singleton('public:edital_shortcodes', static function () use ($tplBase): EditalShortcodes {
                return new EditalShortcodes(
                    $tplBase . DIRECTORY_SEPARATOR . 'editais'
                );
            });

            \add_action('init', static function () use ($container): void {
                $container->get('public:edital_shortcodes')->register();
            }, 20);
        }

        // ------------------------------------------------------------------
        // [pi_minha_conta] — Wave 8-A
        // Template: templates/public/minha-conta/index.php
        // ------------------------------------------------------------------
        if (class_exists(MinhaContaShortcode::class)) {
            $container->singleton('public:minha_conta_shortcode', static function () use ($tplBase): MinhaContaShortcode {
                return new MinhaContaShortcode(
                    $tplBase . DIRECTORY_SEPARATOR . 'minha-conta'
                );
            });

            \add_action('init', static function () use ($container): void {
                $container->get('public:minha_conta_shortcode')->register();
            }, 20);
        }

        // ------------------------------------------------------------------
        // [pi_votacao id="..."] — Wave 6-B
        // Template: templates/public/votacao/votacao-app.php
        // ------------------------------------------------------------------
        if (class_exists(VotacaoShortcodes::class)) {
            $container->singleton('public:votacao_shortcodes', static function (Container $c) use ($tplBase): VotacaoShortcodes {
                // Resolver: returns basic votacao metadata from the repository.
                $resolver = static function (int $votacaoId) use ($c): ?array {
                    $v = $c->get('repo:votacao')->findById($votacaoId);
                    if ($v === null) {
                        return null;
                    }
                    return [
                        'id'                 => $v->id(),
                        'status'             => $v->status()->value(),
                        'titulo_edital'      => '',
                        'abertura_iso'       => $v->abertura() !== null
                            ? $v->abertura()->format(\DateTimeInterface::ATOM) : '',
                        'encerramento_iso'   => $v->encerramento() !== null
                            ? $v->encerramento()->format(\DateTimeInterface::ATOM) : '',
                        'resultados_url'     => '',
                    ];
                };
                return new VotacaoShortcodes(
                    $tplBase . DIRECTORY_SEPARATOR . 'votacao',
                    $resolver
                );
            });

            \add_action('init', static function () use ($container): void {
                $container->get('public:votacao_shortcodes')->register();
            }, 20);
        }

        // ------------------------------------------------------------------
        // [pi_votacao_transparencia id="..."] — Wave 6-C
        // Template: templates/public/votacao/transparencia.php
        // ------------------------------------------------------------------
        if (class_exists(TransparenciaShortcodes::class)) {
            $container->singleton('public:transparencia_shortcodes', static function () use ($tplBase): TransparenciaShortcodes {
                return new TransparenciaShortcodes(
                    $tplBase . DIRECTORY_SEPARATOR . 'votacao'
                );
            });

            \add_action('init', static function () use ($container): void {
                $container->get('public:transparencia_shortcodes')->register();
            }, 20);
        }

        // ------------------------------------------------------------------
        // Unsubscribe page — Wave 4-C
        // Responds to ?pi_action=unsubscribe&token=... on `init` priority 5.
        // Template: templates/public/unsubscribe.php
        // ------------------------------------------------------------------
        if (class_exists(UnsubscribeController::class)) {
            $container->singleton('public:unsubscribe', static function (Container $c) use ($tplBase): UnsubscribeController {
                $revogarHandler = new RevogarConsentimentoHandler(
                    $c->get('repo:consentimento'),
                    $c->get('core:audit_logger')
                );
                return new UnsubscribeController(
                    new UnsubscribeTokenizer(),
                    $revogarHandler,
                    $c->get('repo:agente'),
                    $c->get('core:audit_logger'),
                    $c->get('core:secure_logger'),
                    $tplBase . DIRECTORY_SEPARATOR . 'unsubscribe.php'
                );
            });

            // registerHooks() adds `add_action('init', ..., 5)` internally.
            $container->get('public:unsubscribe')->registerHooks();
        }

        // ------------------------------------------------------------------
        // Download Documento — AJAX `pi_download_document` — Wave 3
        // wp_ajax_pi_download_document (auth only; 401 for nopriv)
        // ------------------------------------------------------------------
        if (class_exists(DownloadDocumentoController::class)) {
            $container->singleton('public:download_documento', static function (Container $c): DownloadDocumentoController {
                return new DownloadDocumentoController(
                    $c->get('repo:documento'),
                    $c->get('storage:private_files'),
                    $c->get('core:access_tracker')
                );
            });

            // register() adds wp_ajax_pi_download_document + nopriv variant internally.
            $container->get('public:download_documento')->register();
        }

        // ------------------------------------------------------------------
        // Recurso Inabilitação — AJAX `pi_recurso_inabilitacao_protocolar` — W5-C
        // wp_ajax_pi_recurso_inabilitacao_protocolar (auth only)
        // ------------------------------------------------------------------
        if (class_exists(RecursoInabilitacaoPublicController::class)
            && class_exists(ProtocolarRecursoInabilitacaoHandler::class)
        ) {
            $container->singleton('public:recurso_inabilitacao', static function (Container $c): RecursoInabilitacaoPublicController {
                $handler = new ProtocolarRecursoInabilitacaoHandler(
                    $c->get('repo:inscricao'),
                    $c->get('repo:edital'),
                    $c->get('repo:recurso_inabilitacao'),
                    $c->get('core:audit_logger')
                );
                return new RecursoInabilitacaoPublicController(
                    $handler,
                    $c->get('repo:inscricao'),
                    $c->get('core:audit_logger')
                );
            });

            // registerHooks() adds wp_ajax_pi_recurso_inabilitacao_protocolar internally.
            $container->get('public:recurso_inabilitacao')->registerHooks();
        }

        // ------------------------------------------------------------------
        // Asset Enqueuer — wave 3 (W3-D). Wire CSS/JS for frontend + admin.
        // Kept here so it runs alongside other public init registrations.
        // ------------------------------------------------------------------
        if (class_exists(AssetEnqueuer::class) && $pluginDir !== '' && $pluginUrl !== '') {
            $container->singleton('public:asset_enqueuer', static function () use ($pluginDir, $pluginUrl): AssetEnqueuer {
                return new AssetEnqueuer(
                    rtrim($pluginDir, '/\\'),
                    rtrim($pluginUrl, '/\\') . '/'
                );
            });

            $container->get('public:asset_enqueuer')->register();
        }
    }
}
