<?php
/**
 * Registers all REST endpoints with the WordPress REST API.
 *
 * Called by Plugin integrator (W9-A wires Container; this file consumes it).
 * Hook: rest_api_init — each endpoint class calls register_rest_route inside.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

// REST endpoint classes
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoHandler;
use Ibram\ParticipeIbram\Application\Cadastro\PendenciasCalculator;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Consentimento\RevogarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Documento\UploadDocumentoHandler;
use Ibram\ParticipeIbram\Application\Edital\AgenteLookupPort;
use Ibram\ParticipeIbram\Application\Edital\InscreverAgenteHandler;
use Ibram\ParticipeIbram\Application\Edital\ProtocolarRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Application\Edital\SalvarRascunhoInscricaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\AnonimizacaoTokenizer;
use Ibram\ParticipeIbram\Application\Lgpd\AnonimizarTitularHandler;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\ExportUrlSigner;
use Ibram\ParticipeIbram\Application\Lgpd\ExportarDadosTitularHandler;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarExportDadosHandler;
use Ibram\ParticipeIbram\Application\MinhaConta\AuditTrailPessoalQuery;
use Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosHandler;
use Ibram\ParticipeIbram\Application\MinhaConta\RegerarReciboVotoHandler;
use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioHandler;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoHandler;
use Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeHandler;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteDetalhesLoader;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbDocumentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoAuditQuery;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;
use Ibram\ParticipeIbram\Presentation\Rest\EditalPublicEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\InscricaoEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\LgpdMeEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\MinhaContaEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\MinhaContaHistoricoEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\MinhaContaLgpdEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\PublicEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\RecursoEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\VotacaoEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\VotacaoPublicEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\VotacaoTransparenciaPublicEndpoints;
use Ibram\ParticipeIbram\Presentation\Rest\WizardEndpoints;

/**
 * Wires all REST endpoint classes into the WordPress REST API.
 *
 * Each endpoint is stored as a singleton in the container so that multiple
 * calls to register() are idempotent. The actual route registration happens
 * inside each class's register(string $namespace) method, called from the
 * rest_api_init hook added here.
 */
final class RestRegistration
{
    /** REST namespace shared by all endpoints. */
    public const NAMESPACE = 'pi/v1';

    public static function register(Container $container): void
    {
        // ------------------------------------------------------------------ //
        // rest:wizard — W3-B                                                   //
        // WizardEndpoints(UploadDocumentoHandler, DocumentoRepository,         //
        //   AgenteRepository, ListarVocabularioHandler, PrivateFileStorage,    //
        //   IpResolver, AuditLogger, ?callable, ?callable)                     //
        // ------------------------------------------------------------------ //
        if (class_exists(WizardEndpoints::class)) {
            $container->singleton('rest:wizard', static function (Container $c): WizardEndpoints {
                $uploadHandler = new UploadDocumentoHandler(
                    $c->get('repo:tipo_documento'),
                    $c->get('repo:documento'),
                    $c->get('storage:private_files'),
                    $c->get('core:audit_logger')
                );
                $vocabHandler = new ListarVocabularioHandler(
                    $c->get('repo:vocabulario')
                );
                return new WizardEndpoints(
                    $uploadHandler,
                    $c->get('repo:documento'),
                    $c->get('repo:agente'),
                    $vocabHandler,
                    $c->get('storage:private_files'),
                    $c->get('core:ip_resolver'),
                    $c->get('core:audit_logger')
                    // salvarRascunhoFactory: null (stub 503 until W3-A integrates)
                    // submeterFactory: null (stub 503 until W3-A integrates)
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:wizard')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:public — W3-B                                                   //
        // PublicEndpoints(TermoRepository, ?callable, ?callable)               //
        // ------------------------------------------------------------------ //
        if (class_exists(PublicEndpoints::class)) {
            $container->singleton('rest:public', static function (Container $c): PublicEndpoints {
                return new PublicEndpoints(
                    $c->get('repo:termo')
                    // deferidosProvider: null (stub — returns empty list)
                    // editaisProvider: null (provided by EditalPublicEndpoints)
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:public')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:edital_public — W5-B                                            //
        // EditalPublicEndpoints(WpdbEditalRepository, WpdbCategoriaRepository, //
        //   WpdbInscricaoRepository, ?callable)                                //
        // ------------------------------------------------------------------ //
        if (class_exists(EditalPublicEndpoints::class)) {
            $container->singleton('rest:edital_public', static function (Container $c): EditalPublicEndpoints {
                return new EditalPublicEndpoints(
                    $c->get('repo:edital'),
                    $c->get('repo:categoria'),
                    $c->get('repo:inscricao')
                    // inscritosHabilitadosProvider: null (cross-domain; wired by W9-D if needed)
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:edital_public')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:inscricao — W5-B                                                //
        // InscricaoEndpoints(SalvarRascunhoInscricaoHandler,                   //
        //   InscreverAgenteHandler, UploadDocumentoHandler,                    //
        //   WpdbInscricaoRepository, WpdbDocumentoRepository,                  //
        //   AgenteLookupPort, callable $agenteIdByUserProvider)                //
        // ------------------------------------------------------------------ //
        if (class_exists(InscricaoEndpoints::class)
            && class_exists(SalvarRascunhoInscricaoHandler::class)
            && class_exists(InscreverAgenteHandler::class)
        ) {
            $container->singleton('rest:inscricao', static function (Container $c): InscricaoEndpoints {
                $rascunhoHandler = new SalvarRascunhoInscricaoHandler(
                    $c->get('repo:edital'),
                    $c->get('repo:categoria'),
                    $c->get('repo:inscricao'),
                    $c->get('adapter:agente_lookup'),
                    $c->get('core:audit_logger')
                );
                $inscreverHandler = new InscreverAgenteHandler(
                    $c->get('repo:edital'),
                    $c->get('repo:categoria'),
                    $c->get('repo:inscricao'),
                    $c->get('adapter:agente_lookup'),
                    $c->get('core:audit_logger')
                );
                $uploadHandler = new UploadDocumentoHandler(
                    $c->get('repo:tipo_documento'),
                    $c->get('repo:documento'),
                    $c->get('storage:private_files'),
                    $c->get('core:audit_logger')
                );
                // Cross-domain resolver: WP user_id → agente_id
                $agenteIdByUser = static function (int $userId) use ($c): ?int {
                    $agente = $c->get('repo:agente')->findByUserId($userId);
                    return $agente !== null ? (int) $agente->getId() : null;
                };
                return new InscricaoEndpoints(
                    $rascunhoHandler,
                    $inscreverHandler,
                    $uploadHandler,
                    $c->get('repo:inscricao'),
                    $c->get('repo:documento'),
                    $c->get('adapter:agente_lookup'),
                    $agenteIdByUser
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:inscricao')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:recurso — W3-B                                                  //
        // RecursoEndpoints(AgenteRepository,                                   //
        //   ProtocolarRecursoInabilitacaoHandler, ?callable)                   //
        // ------------------------------------------------------------------ //
        if (class_exists(RecursoEndpoints::class)
            && class_exists(ProtocolarRecursoInabilitacaoHandler::class)
        ) {
            $container->singleton('rest:recurso', static function (Container $c): RecursoEndpoints {
                $recursoInabHandler = new ProtocolarRecursoInabilitacaoHandler(
                    $c->get('repo:inscricao'),
                    $c->get('repo:edital'),
                    $c->get('repo:recurso_inabilitacao'),
                    $c->get('core:audit_logger')
                );
                return new RecursoEndpoints(
                    $c->get('repo:agente'),
                    $recursoInabHandler
                    // recursoCadastroFactory: null (stub 503 until W3-A integrates)
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:recurso')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:lgpd_me — W3-B stubs (expanded by MinhaContaLgpdEndpoints W8-B) //
        // LgpdMeEndpoints(AgenteRepository, ConsentimentoRepository,            //
        //   RevogarConsentimentoHandler, IpResolver)                            //
        // ------------------------------------------------------------------ //
        if (class_exists(LgpdMeEndpoints::class)) {
            $container->singleton('rest:lgpd_me', static function (Container $c): LgpdMeEndpoints {
                $revogarHandler = new RevogarConsentimentoHandler(
                    $c->get('repo:consentimento'),
                    $c->get('core:audit_logger')
                );
                return new LgpdMeEndpoints(
                    $c->get('repo:agente'),
                    $c->get('repo:consentimento'),
                    $revogarHandler,
                    $c->get('core:ip_resolver')
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:lgpd_me')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:minha_conta — W8-A                                              //
        // MinhaContaEndpoints(OwnershipResolver, AgenteRepository,             //
        //   AgenteDetalhesLoader, AtualizarCadastroPosDeferimentoHandler,      //
        //   PendenciasCalculator, AccessTracker)                               //
        // ------------------------------------------------------------------ //
        if (class_exists(MinhaContaEndpoints::class)
            && class_exists(WpdbAgenteDetalhesLoader::class)
            && class_exists(AtualizarCadastroPosDeferimentoHandler::class)
            && class_exists(PendenciasCalculator::class)
        ) {
            $container->singleton('rest:minha_conta', static function (Container $c): MinhaContaEndpoints {
                $detalhesLoader = new WpdbAgenteDetalhesLoader(
                    $c->get('core:wpdb'),
                    $c->get('repo:agente_hydrator')
                );
                $atualizarHandler = new AtualizarCadastroPosDeferimentoHandler(
                    $c->get('repo:agente'),
                    $detalhesLoader,
                    $c->get('core:audit_logger')
                );
                $pendencias = new PendenciasCalculator(
                    $c->get('repo:documento'),
                    $c->get('repo:tipo_documento')
                );
                return new MinhaContaEndpoints(
                    $c->get('ownership:resolver'),
                    $c->get('repo:agente'),
                    $detalhesLoader,
                    $atualizarHandler,
                    $pendencias,
                    $c->get('core:access_tracker')
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:minha_conta')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:minha_conta_lgpd — W8-B                                        //
        // MinhaContaLgpdEndpoints(OwnershipResolver,                           //
        //   ConsentimentoRepository, TermoRepository,                          //
        //   SolicitacaoTitularRepository, RegistrarConsentimentoHandler,       //
        //   RevogarConsentimentoHandler, SolicitarAnonimizacaoHandler,         //
        //   ConfirmarAnonimizacaoHandler, SolicitarExportDadosHandler,         //
        //   AuditLogger, IpResolver, callable $passwordChecker,                //
        //   callable $userLookup)                                              //
        // ------------------------------------------------------------------ //
        if (class_exists(MinhaContaLgpdEndpoints::class)
            && class_exists(SolicitarAnonimizacaoHandler::class)
            && class_exists(ConfirmarAnonimizacaoHandler::class)
            && class_exists(SolicitarExportDadosHandler::class)
        ) {
            $container->singleton('rest:minha_conta_lgpd', static function (Container $c): MinhaContaLgpdEndpoints {
                $revogarHandler = new RevogarConsentimentoHandler(
                    $c->get('repo:consentimento'),
                    $c->get('core:audit_logger')
                );
                $registrarHandler = new RegistrarConsentimentoHandler(
                    $c->get('repo:consentimento'),
                    $c->get('repo:termo'),
                    $c->get('core:audit_logger')
                );
                $solicitarAnonHandler = new SolicitarAnonimizacaoHandler(
                    $c->get('repo:solicitacao_titular'),
                    new AnonimizacaoTokenizer(),
                    $c->get('core:audit_logger'),
                    $c->get('core:secure_logger')
                );
                // AnonimizarTitularHandler needs wpdb, audit, logger, privateUploadsDir
                $privateUploadsDir = defined('PI_PLUGIN_DIR')
                    ? rtrim(\PI_PLUGIN_DIR, '/\\') . '/private-uploads'
                    : '';
                $anonimizarHandler = new AnonimizarTitularHandler(
                    $c->get('core:wpdb'),
                    $c->get('core:audit_logger'),
                    $c->get('core:secure_logger'),
                    $privateUploadsDir
                );
                $confirmarAnonHandler = new ConfirmarAnonimizacaoHandler(
                    $c->get('repo:solicitacao_titular'),
                    new AnonimizacaoTokenizer(),
                    $anonimizarHandler,
                    $c->get('core:audit_logger'),
                    $c->get('core:secure_logger'),
                    // logoutCallback: força wp_logout do user
                    static function (int $userId): void {
                        if (function_exists('wp_destroy_all_sessions')) {
                            \wp_destroy_all_sessions();
                        }
                    },
                    // userIdResolver: agente_id → WP user_id via AgenteRepository
                    static function (int $agenteId) use ($c): ?int {
                        $agente = $c->get('repo:agente')->findById($agenteId);
                        return $agente !== null ? $agente->getUserId() : null;
                    }
                );
                // ExportarDadosTitularHandler needs dataSubjectResolver, consentimentos, termos, audit, logger, dir
                $exportarHandler = new ExportarDadosTitularHandler(
                    static function (int $agenteId) use ($c): array {
                        $agente = $c->get('repo:agente')->findById($agenteId);
                        return $agente !== null ? ['agente_id' => $agente->getId()] : [];
                    },
                    $c->get('repo:consentimento'),
                    $c->get('repo:termo'),
                    $c->get('core:audit_logger'),
                    $c->get('core:secure_logger'),
                    $privateUploadsDir
                );
                $solicitarExportHandler = new SolicitarExportDadosHandler(
                    $exportarHandler,
                    $c->get('repo:solicitacao_titular'),
                    new ExportUrlSigner(),
                    $c->get('core:audit_logger'),
                    $c->get('core:secure_logger'),
                    // urlBuilder: builds absolute download URL
                    static function (string $sig): string {
                        return function_exists('home_url')
                            ? \home_url('/wp-json/pi/v1/me/exportar-dados/download?sig=' . rawurlencode($sig))
                            : '/wp-json/pi/v1/me/exportar-dados/download?sig=' . rawurlencode($sig);
                    }
                );
                return new MinhaContaLgpdEndpoints(
                    $c->get('ownership:resolver'),
                    $c->get('repo:consentimento'),
                    $c->get('repo:termo'),
                    $c->get('repo:solicitacao_titular'),
                    $registrarHandler,
                    $revogarHandler,
                    $solicitarAnonHandler,
                    $confirmarAnonHandler,
                    $solicitarExportHandler,
                    $c->get('core:audit_logger'),
                    $c->get('core:ip_resolver'),
                    // passwordChecker: delegates to wp_check_password
                    static function (string $pass, string $hash, int $userId): bool {
                        return function_exists('wp_check_password')
                            && (bool) \wp_check_password($pass, $hash, $userId);
                    },
                    // userLookup: returns user data array or null
                    static function (int $userId): ?array {
                        if (!function_exists('get_userdata')) {
                            return null;
                        }
                        $user = \get_userdata($userId);
                        if (!$user) {
                            return null;
                        }
                        return [
                            'ID'         => $user->ID,
                            'user_login' => $user->user_login,
                            'user_pass'  => $user->user_pass,
                        ];
                    }
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:minha_conta_lgpd')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:minha_conta_historico — W8-C                                   //
        // MinhaContaHistoricoEndpoints(AgenteRepository, wpdb,                 //
        //   ListarHistoricoVotosHandler, RegerarReciboVotoHandler,             //
        //   AuditTrailPessoalQuery)                                            //
        // ------------------------------------------------------------------ //
        if (class_exists(MinhaContaHistoricoEndpoints::class)
            && class_exists(ListarHistoricoVotosHandler::class)
            && class_exists(RegerarReciboVotoHandler::class)
        ) {
            $container->singleton('rest:minha_conta_historico', static function (Container $c): MinhaContaHistoricoEndpoints {
                // HistoricoVotosPort: WpdbHistoricoVotosRepository
                $historicoPort = $c->get('repo:historico_votos');
                $listarVotosHandler = new ListarHistoricoVotosHandler(
                    $c->get('hasher:eleitor'),
                    $historicoPort,
                    // votacoesElegiveisResolver: agente_id → list<int votacaoIds>
                    static function (int $agenteId) use ($c): array {
                        // Stub: returns all votacao IDs from repo
                        return [];
                    },
                    // contextoVotacaoResolver: votacaoId → context array or null
                    static function (int $votacaoId) use ($c): ?array {
                        $v = $c->get('repo:votacao')->findById($votacaoId);
                        if ($v === null) {
                            return null;
                        }
                        return [
                            'edital_titulo' => '',
                            'categorias'    => [],
                        ];
                    }
                );
                $regerarReciboHandler = new RegerarReciboVotoHandler(
                    $c->get('hasher:eleitor'),
                    $historicoPort,
                    $c->get('core:audit_logger')
                );
                $auditTrailQuery = new AuditTrailPessoalQuery(
                    $c->get('core:wpdb')
                );
                return new MinhaContaHistoricoEndpoints(
                    $c->get('repo:agente'),
                    $c->get('core:wpdb'),
                    $listarVotosHandler,
                    $regerarReciboHandler,
                    $auditTrailQuery
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:minha_conta_historico')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:votacao — W6-A                                                  //
        // VotacaoEndpoints(RegistrarVotoHandler, VerificarElegibilidadeHandler, //
        //   VotacaoRepository, VotoRepository, callable, ?callable)            //
        // ------------------------------------------------------------------ //
        if (class_exists(VotacaoEndpoints::class)
            && class_exists(RegistrarVotoHandler::class)
            && class_exists(VerificarElegibilidadeHandler::class)
        ) {
            $container->singleton('rest:votacao', static function (Container $c): VotacaoEndpoints {
                $registrarHandler = new RegistrarVotoHandler(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('hasher:eleitor'),
                    $c->get('adapter:agente_votante'),
                    $c->get('adapter:categoria_consulta'),
                    $c->get('adapter:inscricao_consulta'),
                    $c->get('core:audit_logger'),
                    $c->get('core:ip_resolver')
                );
                // agenteIdByUserResolver: WP user_id → agente_id
                $agenteIdByUser = static function (int $userId) use ($c): ?int {
                    $agente = $c->get('repo:agente')->findByUserId($userId);
                    return $agente !== null ? (int) $agente->getId() : null;
                };
                $elegibilidadeHandler = new VerificarElegibilidadeHandler(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('adapter:agente_votante'),
                    $c->get('adapter:categoria_consulta'),
                    $c->get('hasher:eleitor'),
                    $agenteIdByUser
                );
                return new VotacaoEndpoints(
                    $registrarHandler,
                    $elegibilidadeHandler,
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $agenteIdByUser
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:votacao')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:votacao_public — W6-A                                           //
        // VotacaoPublicEndpoints(VotacaoRepository, VotoRepository,            //
        //   ResultadoRepository, ?callable, ?callable)                         //
        // ------------------------------------------------------------------ //
        if (class_exists(VotacaoPublicEndpoints::class)) {
            $container->singleton('rest:votacao_public', static function (Container $c): VotacaoPublicEndpoints {
                return new VotacaoPublicEndpoints(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('repo:resultado')
                    // candidatoPublicoProvider: null (cross-domain; wirable via filter)
                    // categoriaNomeProvider: null
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:votacao_public')->register(self::NAMESPACE);
            });
        }

        // ------------------------------------------------------------------ //
        // rest:votacao_transparencia_public — W6-C                             //
        // VotacaoTransparenciaPublicEndpoints(VotacaoRepository,               //
        //   VotoRepository, WpdbEditalRepository, VotacaoAuditQuery)           //
        // ------------------------------------------------------------------ //
        if (class_exists(VotacaoTransparenciaPublicEndpoints::class)
            && class_exists(VotacaoAuditQuery::class)
        ) {
            $container->singleton('rest:votacao_transparencia_public', static function (Container $c): VotacaoTransparenciaPublicEndpoints {
                $auditQuery = new VotacaoAuditQuery($c->get('core:wpdb'));
                return new VotacaoTransparenciaPublicEndpoints(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('repo:edital'),
                    $auditQuery
                );
            });

            \add_action('rest_api_init', static function () use ($container): void {
                $container->get('rest:votacao_transparencia_public')->register(self::NAMESPACE);
            });
        }
    }
}
