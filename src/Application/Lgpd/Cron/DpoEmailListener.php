<?php
/**
 * Listener que enfileira e-mails para os hooks disparados por DpoAlertsCron.
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd\Cron
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd\Cron;

use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailCommand;
use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailHandler;
use Ibram\ParticipeIbram\Application\Lgpd\Configuracao\DpoConfig;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Throwable;

/**
 * Registra os três hooks `pi_dpo_*` disparados pelo DpoAlertsCron e enfileira
 * e-mails via EnfileirarEmailHandler.
 *
 * Templates usados:
 *  - `dpo_alerta_solicitacao_art18`  — aviso de solicitação vencendo (destinatário: DPO)
 *  - `dpo_solicitacao_vencida_art18` — solicitação já vencida (destinatário: DPO, urgente)
 *  - `dpo_email_falhas`              — relatório de falhas (destinatário: sysadmin)
 *  - `dpo_alerta_recurso_prazo`      — alerta de recurso vencendo (destinatário: DPO)
 *
 * NUNCA inclui PII nos vars — apenas IDs, contagens e prazos.
 */
final class DpoEmailListener
{
    private EnfileirarEmailHandler $enfileirar;
    private SecureLogger $logger;

    public function __construct(
        EnfileirarEmailHandler $enfileirar,
        SecureLogger $logger
    ) {
        $this->enfileirar = $enfileirar;
        $this->logger     = $logger;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        \add_action('pi_dpo_alerta_solicitacao', [$this, 'onAlertaSolicitacao'], 10, 2);
        \add_action('pi_dpo_solicitacao_vencida', [$this, 'onSolicitacaoVencida'], 10, 2);
        \add_action('pi_dpo_alerta_email_falhas', [$this, 'onAlertaEmailFalhas'], 10, 1);
        \add_action('pi_dpo_alerta_recurso', [$this, 'onAlertaRecurso'], 10, 2);
    }

    /**
     * pi_dpo_alerta_solicitacao(int $solicitacaoId, int $diasRestantes)
     */
    public function onAlertaSolicitacao(int $solicitacaoId, int $diasRestantes): void
    {
        $this->dispatchDpo(
            'dpo_alerta_solicitacao_art18',
            [
                'solicitacao_id' => $solicitacaoId,
                'dias_restantes' => $diasRestantes,
                'painel_url'     => $this->painelUrl(),
                'dpo_email'      => DpoConfig::getEmail() ?? 'encarregado@museus.gov.br',
                'unsubscribe_url' => '',
            ],
            'pi_dpo_alerta_solicitacao'
        );
    }

    /**
     * pi_dpo_solicitacao_vencida(int $solicitacaoId, int $diasAtraso)
     */
    public function onSolicitacaoVencida(int $solicitacaoId, int $diasAtraso): void
    {
        $this->dispatchDpo(
            'dpo_solicitacao_vencida_art18',
            [
                'solicitacao_id' => $solicitacaoId,
                'dias_atraso'    => $diasAtraso,
                'painel_url'     => $this->painelUrl(),
                'dpo_email'      => DpoConfig::getEmail() ?? 'encarregado@museus.gov.br',
                'unsubscribe_url' => '',
            ],
            'pi_dpo_solicitacao_vencida'
        );
    }

    /**
     * pi_dpo_alerta_email_falhas(int $totalFalhas)
     * Destinatário: sysadmin (option `admin_email`).
     * NUNCA inclui PII ou conteúdo dos emails com falha — apenas contagem.
     */
    public function onAlertaEmailFalhas(int $totalFalhas): void
    {
        $sysadminEmail = function_exists('get_option')
            ? (string) \get_option('admin_email', '')
            : '';

        if ($sysadminEmail === '' || !filter_var($sysadminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('dpo_email_listener.sem_sysadmin_email');
            return;
        }

        try {
            $this->enfileirar->handle(new EnfileirarEmailCommand(
                'dpo_email_falhas',
                0,
                $sysadminEmail,
                [
                    'total_falhas'   => $totalFalhas,
                    'painel_url'     => $this->painelUrl(),
                    'dpo_email'      => DpoConfig::getEmail() ?? 'encarregado@museus.gov.br',
                    'unsubscribe_url' => '',
                ]
            ));
        } catch (Throwable $e) {
            $this->logger->error('dpo_email_listener.falha_enfileirar_email_falhas', [
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * pi_dpo_alerta_recurso(int $recursoId, int $diasRestantes)
     */
    public function onAlertaRecurso(int $recursoId, int $diasRestantes): void
    {
        $this->dispatchDpo(
            'dpo_alerta_recurso_prazo',
            [
                'recurso_id'     => $recursoId,
                'dias_restantes' => $diasRestantes,
                'painel_url'     => $this->painelUrl(),
                'dpo_email'      => DpoConfig::getEmail() ?? 'encarregado@museus.gov.br',
                'unsubscribe_url' => '',
            ],
            'pi_dpo_alerta_recurso'
        );
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    /**
     * Enfileira email para o DPO configurado.
     *
     * @param array<string,mixed> $vars
     */
    private function dispatchDpo(string $evento, array $vars, string $hookNome): void
    {
        $dpoEmail = DpoConfig::getEmail();
        if ($dpoEmail === null) {
            $this->logger->warning('dpo_email_listener.sem_dpo_email', ['hook' => $hookNome]);
            return;
        }

        try {
            $this->enfileirar->handle(new EnfileirarEmailCommand(
                $evento,
                0,
                $dpoEmail,
                $vars
            ));
        } catch (Throwable $e) {
            $this->logger->error('dpo_email_listener.falha_enfileirar', [
                'evento' => $evento,
                'erro'   => $e->getMessage(),
            ]);
        }
    }

    private function painelUrl(): string
    {
        if (!function_exists('admin_url')) {
            return '/wp-admin/';
        }

        return (string) \admin_url('admin.php?page=pi-lgpd');
    }
}
