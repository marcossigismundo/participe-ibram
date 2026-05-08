<?php
/**
 * VotacaoAuditoriaController — página de auditoria interna por votação.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoAuditQuery;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/**
 * Página de auditoria interna de uma votação:
 *  - Lista de eventos `voto_registrado` (na verdade, lê `pi_votos` direto,
 *    porque audit log é geral). Mostra apenas: ocorrido_em, categoria_id,
 *    eleitor_hash (mascarado primeiros 8 chars), candidato_inscricao_id,
 *    ip_hash (mascarado).
 *  - **NUNCA** revela `agente_id` (anti-rastreio).
 *  - Estatísticas: total de votos, por categoria, IPs únicos (não rastreáveis),
 *    distribuição temporal (gráfico simples renderizado por JS via dataset).
 *  - Botão "Verificar integridade": recalcula hash via AJAX W6-A
 *    `pi_admin_votacao_recalcular_hash` e compara com `hash_pre_apuracao`.
 *
 * Cap: pi_visualizar_audit_log (usada também pelo DPO).
 */
final class VotacaoAuditoriaController
{
    public const CAP_VIEW = VotacaoMenuRegistry::CAP_AUDIT_VIEW;

    private const PER_PAGE = 50;

    private VotacaoRepository $votacoesRepo;

    private VotoRepository $votosRepo;

    private VotacaoAuditQuery $auditQuery;

    public function __construct(
        VotacaoRepository $votacoesRepo,
        VotoRepository $votosRepo,
        VotacaoAuditQuery $auditQuery
    ) {
        $this->votacoesRepo = $votacoesRepo;
        $this->votosRepo    = $votosRepo;
        $this->auditQuery   = $auditQuery;
    }

    public function render(int $votacaoId): void
    {
        if (!self::userCan(self::CAP_VIEW)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        if ($votacaoId <= 0) {
            self::wpDie(self::tr('Votação não informada.'));
            return;
        }

        try {
            $votacao = $this->votacoesRepo->findById($votacaoId);
        } catch (VotacaoNotFound $e) {
            self::wpDie(self::tr('Votação não encontrada.'));
            return;
        }

        // Página corrente.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['paged']) ? max(1, (int) \absint(\wp_unslash($_GET['paged']))) : 1;

        $totalVotos = $this->votosRepo->contarTotalDaVotacao($votacaoId);
        $eventos    = $this->auditQuery->listarVotos($votacaoId, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $porCat     = $this->auditQuery->porCategoria($votacaoId);
        $porDia     = $this->auditQuery->distribuicaoTemporal($votacaoId);
        $ipsUnicos  = $this->auditQuery->ipsHashUnicos($votacaoId);

        // Aplica máscara para apresentação (8 chars).
        $eventosMascarados = array_map(
            static function (array $ev): array {
                return [
                    'ocorrido_em'             => (string) ($ev['ocorrido_em'] ?? ''),
                    'categoria_id'            => (int) ($ev['categoria_id'] ?? 0),
                    'eleitor_hash_mask'       => substr((string) ($ev['eleitor_hash'] ?? ''), 0, 8) . '…',
                    'candidato_inscricao_id'  => (int) ($ev['candidato_inscricao_id'] ?? 0),
                    'ip_hash_mask'            => $ev['ip_hash'] !== null
                        ? substr((string) $ev['ip_hash'], 0, 8) . '…'
                        : null,
                ];
            },
            $eventos
        );

        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        $nonces = [
            'recalcular' => self::nonce('pi_admin_votacao_recalcular_hash_' . $userId),
        ];

        $template = self::templatePath('votacoes/auditoria.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && \current_user_can($cap);
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

    private static function nonce(string $action): string
    {
        return function_exists('wp_create_nonce') ? (string) \wp_create_nonce($action) : '';
    }

    private static function templatePath(string $relative): ?string
    {
        $base      = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
