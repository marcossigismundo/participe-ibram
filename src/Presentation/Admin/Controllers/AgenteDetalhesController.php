<?php
/**
 * AgenteDetalhesController — admin page com visão completa de um cadastro.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Helpers\AgenteSummary;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;

/**
 * Renders the read-only agente detail page with tabs (identification, dados,
 * documentos, consentimentos, histórico, análises).
 *
 * Sensitive fields (CPF, RG, Passaporte, CNPJ) are masked by default. Reveal
 * is gated by capability `pi_visualizar_dados_sensiveis` and audited via
 * AccessTracker (handled by the AJAX endpoint, not here — this controller
 * never decrypts).
 *
 * Capability gating:
 *  - read       -> pi_listar_cadastros
 *  - reveal     -> pi_visualizar_dados_sensiveis (checked in AJAX endpoint)
 *  - actions    -> pi_analisar_cadastro / pi_deferir / pi_indeferir (POST handlers)
 */
final class AgenteDetalhesController
{
    public const CAP_LISTAR             = 'pi_listar_cadastros';
    public const CAP_REVELAR_SENSIVEIS  = 'pi_visualizar_dados_sensiveis';
    public const CAP_ANALISAR           = 'pi_analisar_cadastro';
    public const CAP_DEFERIR            = 'pi_deferir';
    public const CAP_INDEFERIR          = 'pi_indeferir';

    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhesLoader;
    private AnaliseRepository $analises;
    private RecursoRepository $recursos;
    private StatusHistoricoRepository $statusHistorico;
    private ConsentimentoRepository $consentimentos;
    private DocumentoRepository $documentos;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhesLoader,
        AnaliseRepository $analises,
        RecursoRepository $recursos,
        StatusHistoricoRepository $statusHistorico,
        ConsentimentoRepository $consentimentos,
        DocumentoRepository $documentos,
        AuditLogger $audit
    ) {
        $this->agentes         = $agentes;
        $this->detalhesLoader  = $detalhesLoader;
        $this->analises        = $analises;
        $this->recursos        = $recursos;
        $this->statusHistorico = $statusHistorico;
        $this->consentimentos  = $consentimentos;
        $this->documentos      = $documentos;
        $this->audit           = $audit;
    }

    /**
     * Render the detail page for the agente given by ?id=.
     */
    public function render(): void
    {
        if (!self::userCan(self::CAP_LISTAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $agenteId = (int) RequestHelper::get('id', 'absint', 0);
        if ($agenteId <= 0) {
            self::wpDie(self::tr('Cadastro inválido.'));
            return;
        }

        $agente = $this->agentes->findById($agenteId);
        if ($agente === null) {
            self::wpDie(self::tr('Cadastro não encontrado.'));
            return;
        }

        $tipo     = $agente->getTipo()->value();
        $detalhes = $this->detalhesLoader->loadDetalhes($agenteId, $tipo);
        $reps     = $this->detalhesLoader->loadRepresentantes($agenteId);

        $documentos    = $this->documentos->findByAgente($agenteId);
        $consentimentos = $this->consentimentos->findTodosPorAgente($agenteId);
        $analises      = $this->analises->findByAgente($agenteId);
        $historico     = $this->statusHistorico->findByAgente($agenteId);

        // Recursos (uma por fase, podem ser null)
        $recursoRetratacao = $this->recursos->findPorAgenteEFase($agenteId, 'retratacao');
        $recursoPresid     = $this->recursos->findPorAgenteEFase($agenteId, 'presidencia');

        $podeRevelar  = self::userCan(self::CAP_REVELAR_SENSIVEIS);
        $podeAssumir  = $agente->getStatusCadastro()->value() === StatusCadastro::SUBMETIDO
            && self::userCan(self::CAP_ANALISAR);
        $podeDeferir  = $agente->getStatusCadastro()->value() === StatusCadastro::EM_ANALISE
            && self::userCan(self::CAP_DEFERIR);
        $podeIndeferir = $agente->getStatusCadastro()->value() === StatusCadastro::EM_ANALISE
            && self::userCan(self::CAP_INDEFERIR);

        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;

        // Audit page view (read access).
        $this->audit->log('agente', $agenteId, 'visualizar_cadastro_admin', null, ['tipo' => $tipo], $userId > 0 ? $userId : null);

        $nonces = [
            'assumir'   => self::createNonce('pi_admin_assumir_analise_' . $userId),
            'iniciar'   => self::createNonce('pi_admin_iniciar_analise_' . $userId),
            'deferir'   => self::createNonce('pi_admin_deferir_cadastro_' . $userId),
            'indeferir' => self::createNonce('pi_admin_indeferir_cadastro_' . $userId),
            'revelar'   => self::createNonce('pi_admin_revelar_sensivel_' . $userId),
        ];

        $template = self::templatePath('cadastros/agente-detalhes.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }

        // Nome/title + status badge para o header
        $nomeAgente   = AgenteSummary::nomeAgente($agente, $detalhes);
        $tipoLabel    = AgenteSummary::tipoLabel($tipo);
        $statusBadge  = AgenteSummary::statusBadge($agente->getStatusCadastro());
        $numeroReg    = AgenteSummary::numeroRegistroOrDash($agente);

        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && \current_user_can($cap);
    }

    private static function createNonce(string $action): string
    {
        return function_exists('wp_create_nonce') ? (string) \wp_create_nonce($action) : '';
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function wpDie(string $message): void
    {
        if (function_exists('wp_die')) {
            \wp_die(self::escHtml($message));
        } else {
            echo $message;
            exit;
        }
    }

    private static function templatePath(string $relative): ?string
    {
        $base      = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
