<?php
/**
 * Registers all admin menus, controllers, and AJAX handlers in the DI container.
 *
 * Called by Plugin::registerAdminServices() during plugin bootstrap (W9-B).
 * Depends on services registered by CoreRegistration (W9-A).
 *
 * Sections:
 *  1.0  Support Query objects (read-models; all take $wpdb)
 *  1.1  Infrastructure helpers (SequenceNumeroRegistroAllocator, EmailRenderer, etc.)
 *  1.2  Application handlers (use-case orchestrators)
 *  1.3  Admin Controllers
 *  1.4  Menu Registries  → registerHooks()
 *  1.5  AJAX handlers    → registerHooks()
 *
 * IMPORTANT: EmailController::registerHooks() is NOT called here — already
 * invoked by EmailRegistration::bootAdmin() in Plugin.php.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogHandler;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\AssumirAnaliseHandler;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRecursoPresidenciaHandler;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRetratacaoHandler;
use Ibram\ParticipeIbram\Application\Cadastro\DeferirCadastroHandler;
use Ibram\ParticipeIbram\Application\Cadastro\IndeferirCadastroHandler;
use Ibram\ParticipeIbram\Application\Edital\AbrirInscricoesHandler;
use Ibram\ParticipeIbram\Application\Edital\AvaliarHabilitacaoHandler;
use Ibram\ParticipeIbram\Application\Edital\DecidirRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Application\Edital\PublicarEditalHandler;
use Ibram\ParticipeIbram\Application\Email\AgenteBroadcastQuery;
use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailHandler;
use Ibram\ParticipeIbram\Application\Email\SmtpConfig;
use Ibram\ParticipeIbram\Application\Email\Templates\EmailRenderer;
use Ibram\ParticipeIbram\Application\Lgpd\Configuracao\DpoConfig;
use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioHandler;
use Ibram\ParticipeIbram\Application\Votacao\ApurarHandler;
use Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoHandler;
use Ibram\ParticipeIbram\Application\Votacao\PublicarResultadoHandler;
use Ibram\ParticipeIbram\Infrastructure\Repository\SequenceNumeroRegistroAllocator;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\AdminAjaxRouter;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\ApuracaoAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\AuditAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\CadastroAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\DashboardAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\DpoConfigAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\EditalAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\EmailAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\HabilitacaoAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\RecursoAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\VotacaoAuditAjax;
use Ibram\ParticipeIbram\Presentation\Admin\AjudaMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\AuditMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AgenteDetalhesController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AjudaController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\ApuracaoController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AuditDetalheController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AuditLogController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\CategoriaController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\DashboardController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\DpoConfigController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EditalDetalhesController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EditalFormController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EditalListController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\FilaAnaliseController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\HabilitacaoListController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\InscricaoDetalhesController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoInabilitacaoDetalhesController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoInabilitacaoListController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoPresidenciaController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoPrazosController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoRetratacaoController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\TodosAgentesController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\VocabularioController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\VotacaoAuditoriaController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\VotacaoListController;
use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;
use Ibram\ParticipeIbram\Presentation\Admin\Support\CadastroListQuery;
use Ibram\ParticipeIbram\Presentation\Admin\Support\DashboardMetricsQuery;
use Ibram\ParticipeIbram\Presentation\Admin\Support\EditalListQuery;
use Ibram\ParticipeIbram\Presentation\Admin\Support\RecursoListQuery;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoAuditQuery;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoListQuery;

/**
 * Wires all admin menus, controllers, and AJAX handlers into the DI container.
 *
 * All registrations use singleton() for lazy, cached resolution.
 * Each block is guarded by class_exists() for graceful degradation.
 */
final class AdminRegistration
{
    public static function register(Container $container): void
    {
        $templatesAdmin = self::templatesAdminDir();

        // ==================================================================
        // 1.0  SUPPORT QUERY OBJECTS
        // Read-models used by controllers and AJAX handlers.
        // All accept $wpdb from core:wpdb.
        // ==================================================================

        // admin:query:cadastro_list
        if (class_exists(CadastroListQuery::class)) {
            $container->singleton('admin:query:cadastro_list', static function (Container $c): CadastroListQuery {
                return new CadastroListQuery($c->get('core:wpdb'));
            });
        }

        // admin:query:audit_log
        if (class_exists(AuditLogQuery::class)) {
            $container->singleton('admin:query:audit_log', static function (Container $c): AuditLogQuery {
                return new AuditLogQuery($c->get('core:wpdb'));
            });
        }

        // admin:query:edital_list
        if (class_exists(EditalListQuery::class)) {
            $container->singleton('admin:query:edital_list', static function (Container $c): EditalListQuery {
                return new EditalListQuery($c->get('core:wpdb'));
            });
        }

        // admin:query:votacao_list
        if (class_exists(VotacaoListQuery::class)) {
            $container->singleton('admin:query:votacao_list', static function (Container $c): VotacaoListQuery {
                return new VotacaoListQuery($c->get('core:wpdb'));
            });
        }

        // admin:query:recurso_list
        if (class_exists(RecursoListQuery::class)) {
            $container->singleton('admin:query:recurso_list', static function (Container $c): RecursoListQuery {
                return new RecursoListQuery($c->get('core:wpdb'));
            });
        }

        // admin:query:votacao_audit
        if (class_exists(VotacaoAuditQuery::class)) {
            $container->singleton('admin:query:votacao_audit', static function (Container $c): VotacaoAuditQuery {
                return new VotacaoAuditQuery($c->get('core:wpdb'));
            });
        }

        // admin:query:dashboard_metrics
        if (class_exists(DashboardMetricsQuery::class)) {
            $container->singleton('admin:query:dashboard_metrics', static function (Container $c): DashboardMetricsQuery {
                return new DashboardMetricsQuery($c->get('core:wpdb'));
            });
        }

        // ==================================================================
        // 1.1  INFRASTRUCTURE HELPERS
        // ==================================================================

        // infra:numero_registro_allocator
        // SequenceNumeroRegistroAllocator wraps core:sequence_gen (SequenceGenerator)
        // and implements the NumeroRegistroAllocator interface.
        if (class_exists(SequenceNumeroRegistroAllocator::class)) {
            $container->singleton('infra:numero_registro_allocator', static function (Container $c): SequenceNumeroRegistroAllocator {
                return new SequenceNumeroRegistroAllocator($c->get('core:sequence_gen'));
            });
        }

        // app:email_renderer
        if (class_exists(EmailRenderer::class)) {
            $container->singleton('app:email_renderer', static function (): EmailRenderer {
                $dir = \defined('PI_PLUGIN_DIR')
                    ? rtrim((string) \PI_PLUGIN_DIR, '/\\') . '/templates/emails'
                    : '';
                return new EmailRenderer($dir);
            });
        }

        // app:smtp_config — SodiumCipher + SecureLogger
        if (class_exists(SmtpConfig::class)) {
            $container->singleton('app:smtp_config', static function (Container $c): SmtpConfig {
                return new SmtpConfig(
                    $c->get('core:cipher'),
                    $c->get('core:secure_logger')
                );
            });
        }

        // app:dpo_config — depends on core:audit_logger
        if (class_exists(DpoConfig::class)) {
            $container->singleton('app:dpo_config', static function (Container $c): DpoConfig {
                return new DpoConfig($c->get('core:audit_logger'));
            });
        }

        // ==================================================================
        // 1.2  APPLICATION HANDLERS
        // ==================================================================

        // app:handler:enfileirar_email
        if (class_exists(EnfileirarEmailHandler::class)) {
            $container->singleton('app:handler:enfileirar_email', static function (Container $c): EnfileirarEmailHandler {
                $broadcastQuery = $c->has('repo:agente_broadcast')
                    ? $c->get('repo:agente_broadcast')
                    : null;
                return new EnfileirarEmailHandler(
                    $c->get('repo:email_queue'),
                    $c->get('app:email_renderer'),
                    $c->get('core:secure_logger'),
                    $broadcastQuery instanceof AgenteBroadcastQuery ? $broadcastQuery : null
                );
            });
        }

        // app:handler:assumir_analise
        if (class_exists(AssumirAnaliseHandler::class)) {
            $container->singleton('app:handler:assumir_analise', static function (Container $c): AssumirAnaliseHandler {
                return new AssumirAnaliseHandler(
                    $c->get('repo:agente'),
                    $c->get('repo:agente_hydrator'),
                    $c->get('repo:status_historico'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:deferir_cadastro
        if (class_exists(DeferirCadastroHandler::class)) {
            $container->singleton('app:handler:deferir_cadastro', static function (Container $c): DeferirCadastroHandler {
                return new DeferirCadastroHandler(
                    $c->get('repo:agente'),
                    $c->get('repo:agente_hydrator'),
                    $c->get('repo:analise'),
                    $c->get('repo:status_historico'),
                    $c->get('infra:numero_registro_allocator'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:indeferir_cadastro
        if (class_exists(IndeferirCadastroHandler::class)) {
            $container->singleton('app:handler:indeferir_cadastro', static function (Container $c): IndeferirCadastroHandler {
                return new IndeferirCadastroHandler(
                    $c->get('repo:agente'),
                    $c->get('repo:agente_hydrator'),
                    $c->get('repo:analise'),
                    $c->get('repo:status_historico'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:decidir_retratacao
        if (class_exists(DecidirRetratacaoHandler::class)) {
            $container->singleton('app:handler:decidir_retratacao', static function (Container $c): DecidirRetratacaoHandler {
                return new DecidirRetratacaoHandler(
                    $c->get('repo:agente'),
                    $c->get('repo:agente_hydrator'),
                    $c->get('repo:analise'),
                    $c->get('repo:recurso'),
                    $c->get('repo:status_historico'),
                    $c->get('infra:numero_registro_allocator'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:decidir_recurso_presidencia
        if (class_exists(DecidirRecursoPresidenciaHandler::class)) {
            $container->singleton('app:handler:decidir_recurso_presidencia', static function (Container $c): DecidirRecursoPresidenciaHandler {
                return new DecidirRecursoPresidenciaHandler(
                    $c->get('repo:agente'),
                    $c->get('repo:agente_hydrator'),
                    $c->get('repo:analise'),
                    $c->get('repo:recurso'),
                    $c->get('repo:status_historico'),
                    $c->get('infra:numero_registro_allocator'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:publicar_edital
        if (class_exists(PublicarEditalHandler::class)) {
            $container->singleton('app:handler:publicar_edital', static function (Container $c): PublicarEditalHandler {
                return new PublicarEditalHandler(
                    $c->get('repo:edital'),
                    $c->get('repo:categoria'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:abrir_inscricoes
        if (class_exists(AbrirInscricoesHandler::class)) {
            $container->singleton('app:handler:abrir_inscricoes', static function (Container $c): AbrirInscricoesHandler {
                return new AbrirInscricoesHandler(
                    $c->get('repo:edital'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:avaliar_habilitacao
        if (class_exists(AvaliarHabilitacaoHandler::class)) {
            $container->singleton('app:handler:avaliar_habilitacao', static function (Container $c): AvaliarHabilitacaoHandler {
                return new AvaliarHabilitacaoHandler(
                    $c->get('repo:inscricao'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:decidir_recurso_inabilitacao
        if (class_exists(DecidirRecursoInabilitacaoHandler::class)) {
            $container->singleton('app:handler:decidir_recurso_inabilitacao', static function (Container $c): DecidirRecursoInabilitacaoHandler {
                return new DecidirRecursoInabilitacaoHandler(
                    $c->get('repo:recurso_inabilitacao'),
                    $c->get('repo:inscricao'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:apurar
        if (class_exists(ApurarHandler::class)) {
            $container->singleton('app:handler:apurar', static function (Container $c): ApurarHandler {
                return new ApurarHandler(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('repo:resultado'),
                    $c->get('adapter:categoria_consulta'),
                    $c->get('adapter:inscricao_consulta'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:publicar_resultado
        if (class_exists(PublicarResultadoHandler::class)) {
            $container->singleton('app:handler:publicar_resultado', static function (Container $c): PublicarResultadoHandler {
                return new PublicarResultadoHandler(
                    $c->get('repo:votacao'),
                    $c->get('repo:resultado'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // app:handler:exportar_relatorio_apuracao
        if (class_exists(ExportarRelatorioApuracaoHandler::class)) {
            $container->singleton('app:handler:exportar_relatorio_apuracao', static function (Container $c): ExportarRelatorioApuracaoHandler {
                $inscricaoLookup = $c->get('adapter:inscricao_consulta');
                return new ExportarRelatorioApuracaoHandler(
                    $c->get('repo:votacao'),
                    $c->get('repo:resultado'),
                    $c->get('repo:voto'),
                    $c->get('core:audit_logger'),
                    is_callable($inscricaoLookup) ? $inscricaoLookup : [$inscricaoLookup, 'consultar']
                );
            });
        }

        // app:handler:exportar_audit_log
        // ExportarAuditLogHandler takes UuidGenerator and Json instances (not FQCN strings)
        if (class_exists(ExportarAuditLogHandler::class)) {
            $container->singleton('app:handler:exportar_audit_log', static function (Container $c): ExportarAuditLogHandler {
                // core:uuid and core:json are registered as FQCN strings.
                // Instantiate directly to get the real instances needed.
                $uuidFqcn = $c->get('core:uuid'); // returns UuidGenerator::class string
                $jsonFqcn = $c->get('core:json'); // returns Json::class string
                return new ExportarAuditLogHandler(
                    $c->get('admin:query:audit_log'),
                    $c->get('core:audit_logger'),
                    new $uuidFqcn(),
                    new $jsonFqcn()
                );
            });
        }

        // app:handler:listar_vocabulario
        if (class_exists(ListarVocabularioHandler::class)) {
            $container->singleton('app:handler:listar_vocabulario', static function (Container $c): ListarVocabularioHandler {
                return new ListarVocabularioHandler($c->get('repo:vocabulario'));
            });
        }

        // ==================================================================
        // 1.3  ADMIN CONTROLLERS
        // ==================================================================

        // admin:controller:fila_analise
        if (class_exists(FilaAnaliseController::class)) {
            $container->singleton('admin:controller:fila_analise', static function (Container $c) use ($templatesAdmin): FilaAnaliseController {
                return new FilaAnaliseController(
                    $c->get('admin:query:cadastro_list'),
                    $c->get('app:handler:assumir_analise'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // admin:controller:todos_agentes
        if (class_exists(TodosAgentesController::class)) {
            $container->singleton('admin:controller:todos_agentes', static function (Container $c): TodosAgentesController {
                return new TodosAgentesController($c->get('admin:query:cadastro_list'));
            });
        }

        // admin:controller:agente_detalhes
        if (class_exists(AgenteDetalhesController::class)) {
            $container->singleton('admin:controller:agente_detalhes', static function (Container $c): AgenteDetalhesController {
                return new AgenteDetalhesController(
                    $c->get('repo:agente'),
                    $c->get('repo:agente_hydrator'),
                    $c->get('repo:analise'),
                    $c->get('repo:recurso'),
                    $c->get('repo:status_historico'),
                    $c->get('repo:consentimento'),
                    $c->get('repo:documento'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // admin:controller:recurso_retratacao
        if (class_exists(RecursoRetratacaoController::class)) {
            $container->singleton('admin:controller:recurso_retratacao', static function (Container $c) use ($templatesAdmin): RecursoRetratacaoController {
                return new RecursoRetratacaoController(
                    $c->get('app:handler:decidir_retratacao'),
                    $c->get('repo:recurso'),
                    $c->get('repo:analise'),
                    $c->get('repo:agente'),
                    $c->get('admin:query:recurso_list'),
                    $c->get('core:audit_logger'),
                    $templatesAdmin . '/recursos'
                );
            });
        }

        // admin:controller:recurso_presidencia
        if (class_exists(RecursoPresidenciaController::class)) {
            $container->singleton('admin:controller:recurso_presidencia', static function (Container $c) use ($templatesAdmin): RecursoPresidenciaController {
                return new RecursoPresidenciaController(
                    $c->get('app:handler:decidir_recurso_presidencia'),
                    $c->get('repo:recurso'),
                    $c->get('repo:analise'),
                    $c->get('repo:agente'),
                    $c->get('admin:query:recurso_list'),
                    $c->get('core:audit_logger'),
                    $templatesAdmin . '/recursos'
                );
            });
        }

        // admin:controller:recurso_prazos
        if (class_exists(RecursoPrazosController::class)) {
            $container->singleton('admin:controller:recurso_prazos', static function (Container $c) use ($templatesAdmin): RecursoPrazosController {
                return new RecursoPrazosController(
                    $c->get('admin:query:recurso_list'),
                    $templatesAdmin . '/recursos'
                );
            });
        }

        // admin:controller:edital_list
        if (class_exists(EditalListController::class)) {
            $container->singleton('admin:controller:edital_list', static function (Container $c): EditalListController {
                return new EditalListController($c->get('admin:query:edital_list'));
            });
        }

        // admin:controller:edital_form
        if (class_exists(EditalFormController::class)) {
            $container->singleton('admin:controller:edital_form', static function (Container $c): EditalFormController {
                return new EditalFormController(
                    $c->get('repo:edital'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // admin:controller:edital_detalhes
        if (class_exists(EditalDetalhesController::class)) {
            $container->singleton('admin:controller:edital_detalhes', static function (Container $c): EditalDetalhesController {
                return new EditalDetalhesController(
                    $c->get('repo:edital'),
                    $c->get('repo:categoria'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // admin:controller:categoria
        if (class_exists(CategoriaController::class)) {
            $container->singleton('admin:controller:categoria', static function (Container $c): CategoriaController {
                return new CategoriaController(
                    $c->get('repo:edital'),
                    $c->get('repo:categoria'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // admin:controller:inscricao_detalhes
        if (class_exists(InscricaoDetalhesController::class)) {
            $container->singleton('admin:controller:inscricao_detalhes', static function (Container $c) use ($templatesAdmin): InscricaoDetalhesController {
                return new InscricaoDetalhesController(
                    $c->get('repo:inscricao'),
                    $c->get('core:audit_logger'),
                    $templatesAdmin
                );
            });
        }

        // admin:controller:recurso_inabilitacao_detalhes
        if (class_exists(RecursoInabilitacaoDetalhesController::class)) {
            $container->singleton('admin:controller:recurso_inabilitacao_detalhes', static function (Container $c) use ($templatesAdmin): RecursoInabilitacaoDetalhesController {
                return new RecursoInabilitacaoDetalhesController(
                    $c->get('app:handler:decidir_recurso_inabilitacao'),
                    $c->get('repo:recurso_inabilitacao'),
                    $c->get('repo:inscricao'),
                    $c->get('core:audit_logger'),
                    $templatesAdmin
                );
            });
        }

        // admin:controller:habilitacao_list
        if (class_exists(HabilitacaoListController::class)) {
            $container->singleton('admin:controller:habilitacao_list', static function (Container $c) use ($templatesAdmin): HabilitacaoListController {
                return new HabilitacaoListController(
                    $c->get('admin:controller:inscricao_detalhes'),
                    $c->get('repo:inscricao'),
                    $c->get('core:wpdb'),
                    $templatesAdmin
                );
            });
        }

        // admin:controller:recurso_inabilitacao_list
        if (class_exists(RecursoInabilitacaoListController::class)) {
            $container->singleton('admin:controller:recurso_inabilitacao_list', static function (Container $c) use ($templatesAdmin): RecursoInabilitacaoListController {
                return new RecursoInabilitacaoListController(
                    $c->get('admin:controller:recurso_inabilitacao_detalhes'),
                    $c->get('core:wpdb'),
                    $templatesAdmin
                );
            });
        }

        // admin:controller:votacao_list
        if (class_exists(VotacaoListController::class)) {
            $container->singleton('admin:controller:votacao_list', static function (Container $c): VotacaoListController {
                return new VotacaoListController($c->get('admin:query:votacao_list'));
            });
        }

        // admin:controller:apuracao
        if (class_exists(ApuracaoController::class)) {
            $container->singleton('admin:controller:apuracao', static function (Container $c): ApuracaoController {
                $inscricaoLookup = $c->get('adapter:inscricao_consulta');
                return new ApuracaoController(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('repo:resultado'),
                    $c->get('repo:edital'),
                    $c->get('repo:categoria'),
                    is_callable($inscricaoLookup) ? $inscricaoLookup : [$inscricaoLookup, 'consultar']
                );
            });
        }

        // admin:controller:votacao_auditoria
        if (class_exists(VotacaoAuditoriaController::class)) {
            $container->singleton('admin:controller:votacao_auditoria', static function (Container $c): VotacaoAuditoriaController {
                return new VotacaoAuditoriaController(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('admin:query:votacao_audit')
                );
            });
        }

        // admin:controller:audit_log
        if (class_exists(AuditLogController::class)) {
            $container->singleton('admin:controller:audit_log', static function (Container $c): AuditLogController {
                return new AuditLogController(
                    $c->get('admin:query:audit_log'),
                    $c->get('app:handler:exportar_audit_log')
                );
            });
        }

        // admin:controller:audit_detalhe
        if (class_exists(AuditDetalheController::class)) {
            $container->singleton('admin:controller:audit_detalhe', static function (Container $c): AuditDetalheController {
                return new AuditDetalheController(
                    $c->get('admin:query:audit_log'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // admin:controller:ajuda
        if (class_exists(AjudaController::class)) {
            $container->singleton('admin:controller:ajuda', static function (): AjudaController {
                return new AjudaController();
            });
        }

        // admin:controller:dashboard
        if (class_exists(DashboardController::class)) {
            $container->singleton('admin:controller:dashboard', static function (Container $c): DashboardController {
                return new DashboardController($c->get('admin:query:dashboard_metrics'));
            });
        }

        // admin:controller:dpo_config
        // DpoConfigController::registerHooks() adds submenu under pi-participe-ibram.
        // Called below in section 1.4.
        if (class_exists(DpoConfigController::class)) {
            $container->singleton('admin:controller:dpo_config', static function (Container $c) use ($templatesAdmin): DpoConfigController {
                return new DpoConfigController(
                    $c->get('app:handler:enfileirar_email'),
                    $templatesAdmin
                );
            });
        }

        // admin:controller:vocabulario
        // VocabularioController::registerHooks() adds AJAX hooks for vocabulário.
        // Called below in section 1.5.
        if (class_exists(VocabularioController::class)) {
            $container->singleton('admin:controller:vocabulario', static function (Container $c): VocabularioController {
                return new VocabularioController($c->get('app:handler:listar_vocabulario'));
            });
        }

        // ==================================================================
        // 1.4  MENU REGISTRIES — instantiate and call registerHooks()
        //
        // All MenuRegistry classes hook onto 'admin_menu' (and sometimes
        // 'admin_init'). registerHooks() must be called during plugin boot,
        // before 'admin_menu' fires.
        //
        // NOTE: EmailController::registerHooks() is intentionally NOT called
        // here — it is already invoked by EmailRegistration::bootAdmin() in
        // Plugin.php and would create duplicate menu entries.
        // ==================================================================

        // Cadastros menu (FilaAnalise, TodosAgentes, AgenteDetalhes)
        if (class_exists(MenuRegistry::class)
            && class_exists(FilaAnaliseController::class)
            && class_exists(TodosAgentesController::class)
            && class_exists(AgenteDetalhesController::class)
        ) {
            $menuRegistry = new MenuRegistry(
                $container->get('admin:controller:fila_analise'),
                $container->get('admin:controller:todos_agentes'),
                $container->get('admin:controller:agente_detalhes')
            );
            $menuRegistry->registerHooks();
        }

        // Editais menu (EditalList, EditalForm, EditalDetalhes, Categoria)
        if (class_exists(EditalMenuRegistry::class)
            && class_exists(EditalListController::class)
            && class_exists(EditalFormController::class)
            && class_exists(EditalDetalhesController::class)
            && class_exists(CategoriaController::class)
        ) {
            $editalMenuRegistry = new EditalMenuRegistry(
                $container->get('admin:controller:edital_list'),
                $container->get('admin:controller:edital_form'),
                $container->get('admin:controller:edital_detalhes'),
                $container->get('admin:controller:categoria')
            );
            $editalMenuRegistry->registerHooks();
        }

        // Recursos menu (Retratação, Presidência, Prazos)
        if (class_exists(RecursoMenuRegistry::class)
            && class_exists(RecursoRetratacaoController::class)
            && class_exists(RecursoPresidenciaController::class)
            && class_exists(RecursoPrazosController::class)
        ) {
            $recursoMenuRegistry = new RecursoMenuRegistry(
                $container->get('admin:controller:recurso_retratacao'),
                $container->get('admin:controller:recurso_presidencia'),
                $container->get('admin:controller:recurso_prazos')
            );
            $recursoMenuRegistry->registerHooks();
        }

        // Habilitações menu (HabilitacaoList, RecursoInabilitacaoList)
        if (class_exists(HabilitacaoMenuRegistry::class)
            && class_exists(HabilitacaoListController::class)
            && class_exists(RecursoInabilitacaoListController::class)
        ) {
            $habilitacaoMenuRegistry = new HabilitacaoMenuRegistry(
                $container->get('admin:controller:habilitacao_list'),
                $container->get('admin:controller:recurso_inabilitacao_list')
            );
            $habilitacaoMenuRegistry->registerHooks();
        }

        // Votações menu (VotacaoList, Apuracao, VotacaoAuditoria)
        if (class_exists(VotacaoMenuRegistry::class)
            && class_exists(VotacaoListController::class)
            && class_exists(ApuracaoController::class)
            && class_exists(VotacaoAuditoriaController::class)
        ) {
            $votacaoMenuRegistry = new VotacaoMenuRegistry(
                $container->get('admin:controller:votacao_list'),
                $container->get('admin:controller:apuracao'),
                $container->get('admin:controller:votacao_auditoria')
            );
            $votacaoMenuRegistry->registerHooks();
        }

        // Auditoria menu (AuditLog, AuditDetalhe)
        if (class_exists(AuditMenuRegistry::class)
            && class_exists(AuditLogController::class)
            && class_exists(AuditDetalheController::class)
        ) {
            $auditMenuRegistry = new AuditMenuRegistry(
                $container->get('admin:controller:audit_log'),
                $container->get('admin:controller:audit_detalhe')
            );
            $auditMenuRegistry->registerHooks();
        }

        // Ajuda menu
        if (class_exists(AjudaMenuRegistry::class) && class_exists(AjudaController::class)) {
            $ajudaMenuRegistry = new AjudaMenuRegistry(
                $container->get('admin:controller:ajuda')
            );
            $ajudaMenuRegistry->registerHooks();
        }

        // DpoConfigController submenu (under pi-participe-ibram, separate from main menu)
        if (class_exists(DpoConfigController::class)) {
            $container->get('admin:controller:dpo_config')->registerHooks();
        }

        // ==================================================================
        // 1.5  AJAX HANDLERS — instantiate and call registerHooks()
        //
        // All AJAX classes hook onto 'wp_ajax_*' actions (never nopriv).
        // registerHooks() must be called before 'wp_ajax_*' fires.
        // ==================================================================

        // AdminAjaxRouter — thin router, optional assumirAnalise factory
        if (class_exists(AdminAjaxRouter::class)) {
            $router = new AdminAjaxRouter(
                class_exists(AssumirAnaliseHandler::class)
                    ? static function () use ($container): AssumirAnaliseHandler {
                        return $container->get('app:handler:assumir_analise');
                    }
                    : null
            );
            $router->registerHooks();
        }

        // CadastroAdminAjax
        if (class_exists(CadastroAdminAjax::class)) {
            $cadastroAjax = new CadastroAdminAjax(
                $container->get('app:handler:assumir_analise'),
                $container->get('app:handler:deferir_cadastro'),
                $container->get('app:handler:indeferir_cadastro'),
                $container->get('repo:agente'),
                $container->get('repo:agente_hydrator'),
                $container->get('core:cipher'),
                $container->get('core:access_tracker'),
                $container->get('core:audit_logger')
            );
            $cadastroAjax->registerHooks();
        }

        // RecursoAdminAjax
        if (class_exists(RecursoAdminAjax::class)) {
            $recursoAjax = new RecursoAdminAjax(
                $container->get('app:handler:decidir_retratacao'),
                $container->get('app:handler:decidir_recurso_presidencia'),
                $container->get('core:audit_logger')
            );
            $recursoAjax->registerHooks();
        }

        // EditalAdminAjax
        if (class_exists(EditalAdminAjax::class)) {
            $editalAjax = new EditalAdminAjax(
                $container->get('app:handler:publicar_edital'),
                $container->get('app:handler:abrir_inscricoes'),
                $container->get('repo:edital'),
                $container->get('repo:categoria'),
                $container->get('core:audit_logger')
            );
            $editalAjax->registerHooks();
        }

        // HabilitacaoAdminAjax
        if (class_exists(HabilitacaoAdminAjax::class)) {
            $habilitacaoAjax = new HabilitacaoAdminAjax(
                $container->get('app:handler:avaliar_habilitacao'),
                $container->get('app:handler:decidir_recurso_inabilitacao'),
                $container->get('core:audit_logger')
            );
            $habilitacaoAjax->registerHooks();
        }

        // ApuracaoAdminAjax
        if (class_exists(ApuracaoAdminAjax::class)) {
            $apuracaoAjax = new ApuracaoAdminAjax(
                $container->get('app:handler:apurar'),
                $container->get('app:handler:publicar_resultado'),
                $container->get('app:handler:exportar_relatorio_apuracao'),
                $container->get('core:audit_logger')
            );
            $apuracaoAjax->registerHooks();
        }

        // AuditAdminAjax
        if (class_exists(AuditAdminAjax::class)) {
            $auditAjax = new AuditAdminAjax(
                $container->get('admin:query:audit_log'),
                $container->get('admin:controller:audit_detalhe'),
                $container->get('app:handler:exportar_audit_log'),
                $container->get('core:audit_logger')
            );
            $auditAjax->registerHooks();
        }

        // DashboardAdminAjax
        if (class_exists(DashboardAdminAjax::class)) {
            $dashboardAjax = new DashboardAdminAjax(
                $container->get('admin:query:dashboard_metrics')
            );
            $dashboardAjax->registerHooks();
        }

        // DpoConfigAdminAjax
        if (class_exists(DpoConfigAdminAjax::class)) {
            $dpoAjax = new DpoConfigAdminAjax(
                $container->get('app:dpo_config'),
                $container->get('app:handler:enfileirar_email'),
                $container->get('core:audit_logger'),
                $container->get('core:secure_logger')
            );
            $dpoAjax->registerHooks();
        }

        // EmailAdminAjax
        if (class_exists(EmailAdminAjax::class)) {
            $emailAjax = new EmailAdminAjax(
                $container->get('app:smtp_config'),
                $container->get('repo:email_queue'),
                $container->get('core:audit_logger'),
                $container->get('core:secure_logger')
            );
            $emailAjax->registerHooks();
        }

        // VotacaoAuditAjax
        if (class_exists(VotacaoAuditAjax::class)) {
            $votacaoAuditAjax = new VotacaoAuditAjax(
                $container->get('repo:votacao'),
                $container->get('repo:voto'),
                $container->get('core:audit_logger'),
                $container->get('core:wpdb')
            );
            $votacaoAuditAjax->registerHooks();
        }

        // VocabularioController (registers its own AJAX hooks)
        if (class_exists(VocabularioController::class)) {
            $container->get('admin:controller:vocabulario')->registerHooks();
        }
    }

    // ==================================================================
    // PRIVATE HELPERS
    // ==================================================================

    /**
     * Returns the absolute path to templates/admin, without trailing slash.
     * Falls back to empty string when PI_PLUGIN_DIR is not yet defined
     * (unit-test environment).
     */
    private static function templatesAdminDir(): string
    {
        if (\defined('PI_PLUGIN_DIR')) {
            return rtrim((string) \PI_PLUGIN_DIR, '/\\') . '/templates/admin';
        }
        return '';
    }
}
