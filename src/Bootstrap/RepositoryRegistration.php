<?php
/**
 * Registers all repository and ownership services in the DI container.
 *
 * Depends on CoreRegistration having already been called so that
 * core:wpdb, core:cipher, core:audit_logger, core:access_tracker,
 * and core:sequence_gen are available.
 *
 * Consumed by: W9-B AdminRegistration, W9-C REST+shortcodes,
 *              W9-D events+crons, W9-E LAI+portabilidade.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Infrastructure\Repository\AgenteHydrator;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteBroadcastQuery;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAnaliseRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbConsentimentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbDocumentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEmailQueueRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbHistoricoVotosRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbRecursoInabilitacaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbRecursoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbResultadoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbSolicitacaoTitularRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbStatusHistoricoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbTermoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbTipoDocumentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbVocabularioRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbVotacaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbVotoRepository;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;

/**
 * Registers all repo:* and ownership:* services.
 *
 * All registrations use singleton() for lazy, cached resolution.
 * Each block is guarded by class_exists() for graceful degradation.
 */
final class RepositoryRegistration
{
    public static function register(Container $container): void
    {
        // ------------------------------------------------------------------
        // repo:agente_hydrator — depends on core:cipher + core:access_tracker
        // Must be registered before repo:agente (AgenteRepository depends on it)
        // ------------------------------------------------------------------
        if (!class_exists(AgenteHydrator::class)) {
            return;
        }
        $container->singleton('repo:agente_hydrator', static function (Container $c): AgenteHydrator {
            return new AgenteHydrator(
                $c->get('core:cipher'),
                $c->get('core:access_tracker')
            );
        });

        // ------------------------------------------------------------------
        // repo:agente — WpdbAgenteRepository
        // Constructor: wpdb, SodiumCipher, AuditLogger, SequenceGenerator, AgenteHydrator
        // Consumed by: most application handlers, ownership:resolver, adapters
        // ------------------------------------------------------------------
        if (!class_exists(WpdbAgenteRepository::class)) {
            return;
        }
        $container->singleton('repo:agente', static function (Container $c): WpdbAgenteRepository {
            return new WpdbAgenteRepository(
                $c->get('core:wpdb'),
                $c->get('core:cipher'),
                $c->get('core:audit_logger'),
                $c->get('core:sequence_gen'),
                $c->get('repo:agente_hydrator')
            );
        });

        // ------------------------------------------------------------------
        // repo:documento — depends on core:wpdb + core:audit_logger
        // Consumed by: documento upload/download handlers (W9-C, W9-E)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbDocumentoRepository::class)) {
            return;
        }
        $container->singleton('repo:documento', static function (Container $c): WpdbDocumentoRepository {
            return new WpdbDocumentoRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:tipo_documento — depends on core:wpdb only
        // Consumed by: inscrição handlers, admin document config (W9-B)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbTipoDocumentoRepository::class)) {
            return;
        }
        $container->singleton('repo:tipo_documento', static function (Container $c): WpdbTipoDocumentoRepository {
            return new WpdbTipoDocumentoRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:vocabulario — depends on core:wpdb + core:audit_logger
        // Consumed by: form validation, shortcodes (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbVocabularioRepository::class)) {
            return;
        }
        $container->singleton('repo:vocabulario', static function (Container $c): WpdbVocabularioRepository {
            return new WpdbVocabularioRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:termo — depends on core:wpdb only
        // Consumed by: consentimento handlers (W9-E)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbTermoRepository::class)) {
            return;
        }
        $container->singleton('repo:termo', static function (Container $c): WpdbTermoRepository {
            return new WpdbTermoRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:consentimento — depends on core:wpdb only
        // Consumed by: LAI / portabilidade handlers (W9-E)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbConsentimentoRepository::class)) {
            return;
        }
        $container->singleton('repo:consentimento', static function (Container $c): WpdbConsentimentoRepository {
            return new WpdbConsentimentoRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:solicitacao_titular — depends on core:wpdb only
        // Consumed by: LAI / titular request handlers (W9-E)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbSolicitacaoTitularRepository::class)) {
            return;
        }
        $container->singleton('repo:solicitacao_titular', static function (Container $c): WpdbSolicitacaoTitularRepository {
            return new WpdbSolicitacaoTitularRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:edital — depends on core:wpdb + core:audit_logger
        // Consumed by: edital admin (W9-B), inscrição REST (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbEditalRepository::class)) {
            return;
        }
        $container->singleton('repo:edital', static function (Container $c): WpdbEditalRepository {
            return new WpdbEditalRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:categoria — depends on core:wpdb only
        // Consumed by: edital admin (W9-B), adapter:categoria_consulta
        // ------------------------------------------------------------------
        if (!class_exists(WpdbCategoriaRepository::class)) {
            return;
        }
        $container->singleton('repo:categoria', static function (Container $c): WpdbCategoriaRepository {
            return new WpdbCategoriaRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:inscricao — depends on core:wpdb + core:audit_logger
        // Consumed by: inscrição handlers (W9-C), adapter:inscricao_consulta
        // ------------------------------------------------------------------
        if (!class_exists(WpdbInscricaoRepository::class)) {
            return;
        }
        $container->singleton('repo:inscricao', static function (Container $c): WpdbInscricaoRepository {
            return new WpdbInscricaoRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:recurso_inabilitacao — depends on core:wpdb + core:audit_logger
        // Consumed by: recurso inabilitação handlers (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbRecursoInabilitacaoRepository::class)) {
            return;
        }
        $container->singleton('repo:recurso_inabilitacao', static function (Container $c): WpdbRecursoInabilitacaoRepository {
            return new WpdbRecursoInabilitacaoRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:analise — depends on core:wpdb + core:audit_logger
        // Consumed by: análise admin handlers (W9-B)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbAnaliseRepository::class)) {
            return;
        }
        $container->singleton('repo:analise', static function (Container $c): WpdbAnaliseRepository {
            return new WpdbAnaliseRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:recurso — depends on core:wpdb + core:audit_logger
        // Constructor: wpdb, AuditLogger, ?tableName, ?tableAnalises (use defaults)
        // Consumed by: recurso cadastro handlers (W9-C), cron (W9-D)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbRecursoRepository::class)) {
            return;
        }
        $container->singleton('repo:recurso', static function (Container $c): WpdbRecursoRepository {
            return new WpdbRecursoRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:status_historico — depends on core:wpdb only
        // Consumed by: status transition handlers (W9-D)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbStatusHistoricoRepository::class)) {
            return;
        }
        $container->singleton('repo:status_historico', static function (Container $c): WpdbStatusHistoricoRepository {
            return new WpdbStatusHistoricoRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:votacao — depends on core:wpdb + core:audit_logger
        // Consumed by: votação admin (W9-B), votação REST (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbVotacaoRepository::class)) {
            return;
        }
        $container->singleton('repo:votacao', static function (Container $c): WpdbVotacaoRepository {
            return new WpdbVotacaoRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:voto — depends on core:wpdb only (no AuditLogger per privacy design)
        // Consumed by: RegistrarVotoHandler (W9-D)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbVotoRepository::class)) {
            return;
        }
        $container->singleton('repo:voto', static function (Container $c): WpdbVotoRepository {
            return new WpdbVotoRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:resultado — depends on core:wpdb + core:audit_logger
        // Consumed by: apuração cron (W9-D), resultado REST (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbResultadoRepository::class)) {
            return;
        }
        $container->singleton('repo:resultado', static function (Container $c): WpdbResultadoRepository {
            return new WpdbResultadoRepository(
                $c->get('core:wpdb'),
                $c->get('core:audit_logger')
            );
        });

        // ------------------------------------------------------------------
        // repo:historico_votos — depends on core:wpdb only (secret ballot design)
        // Consumed by: minha conta historico endpoint (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbHistoricoVotosRepository::class)) {
            return;
        }
        $container->singleton('repo:historico_votos', static function (Container $c): WpdbHistoricoVotosRepository {
            return new WpdbHistoricoVotosRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:email_queue — depends on core:wpdb only
        // Consumed by: email dispatch cron (W9-D)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbEmailQueueRepository::class)) {
            return;
        }
        $container->singleton('repo:email_queue', static function (Container $c): WpdbEmailQueueRepository {
            return new WpdbEmailQueueRepository($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // repo:agente_broadcast — depends on core:wpdb only
        // Consumed by: broadcast email cron (W9-D)
        // ------------------------------------------------------------------
        if (!class_exists(WpdbAgenteBroadcastQuery::class)) {
            return;
        }
        $container->singleton('repo:agente_broadcast', static function (Container $c): WpdbAgenteBroadcastQuery {
            return new WpdbAgenteBroadcastQuery($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // ownership:resolver — depends on repo:agente + core:audit_logger
        // Consumed by: all minha-conta REST endpoints (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(OwnershipResolver::class)) {
            return;
        }
        $container->singleton('ownership:resolver', static function (Container $c): OwnershipResolver {
            return new OwnershipResolver(
                $c->get('repo:agente'),
                $c->get('core:audit_logger')
            );
        });
    }
}
