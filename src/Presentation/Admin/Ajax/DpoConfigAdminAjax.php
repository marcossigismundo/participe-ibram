<?php
/**
 * AJAX handlers admin para configuração DPO.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailCommand;
use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailHandler;
use Ibram\ParticipeIbram\Application\Lgpd\Configuracao\DpoConfig;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use InvalidArgumentException;
use Throwable;

/**
 * Endpoints AJAX administrativos de configuração do DPO.
 *
 *  - `pi_admin_dpo_save_config` — salva email/nome/telefone do DPO (auditado).
 *  - `pi_admin_dpo_test_email`  — envia template `dpo_alerta_solicitacao_art18`
 *                                  de teste para o DPO configurado (dados fictícios).
 *
 * Pipeline: nonce + capability `pi_administrar_dpo` + audit.
 * NUNCA expõe dados sensíveis no response.
 */
final class DpoConfigAdminAjax
{
    public const CAP         = 'pi_administrar_dpo';
    public const NONCE_SAVE  = 'pi_admin_dpo_save_config';
    public const NONCE_TEST  = 'pi_admin_dpo_test_email';

    private DpoConfig $config;
    private EnfileirarEmailHandler $enfileirar;
    private AuditLogger $audit;
    private SecureLogger $logger;

    public function __construct(
        DpoConfig $config,
        EnfileirarEmailHandler $enfileirar,
        AuditLogger $audit,
        SecureLogger $logger
    ) {
        $this->config     = $config;
        $this->enfileirar = $enfileirar;
        $this->audit      = $audit;
        $this->logger     = $logger;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_' . self::NONCE_SAVE, [$this, 'saveConfig']);
        \add_action('wp_ajax_' . self::NONCE_TEST, [$this, 'testEmail']);
    }

    /**
     * Salva configuração do DPO.
     * POST: nonce, email, nome, telefone.
     */
    public function saveConfig(): void
    {
        try {
            $this->guard(self::NONCE_SAVE);

            $email    = \sanitize_email(\wp_unslash((string) ($_POST['email'] ?? '')));
            $nome     = \sanitize_text_field(\wp_unslash((string) ($_POST['nome'] ?? '')));
            $telefone = \sanitize_text_field(\wp_unslash((string) ($_POST['telefone'] ?? '')));

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                \wp_send_json_error([
                    'code'    => 'email_invalido',
                    'message' => __('E-mail inválido.', 'participe-ibram'),
                ], 422);
                return;
            }

            $atorId = \get_current_user_id();
            $this->config->setConfig(
                ['email' => $email, 'nome' => $nome, 'telefone' => $telefone],
                $atorId
            );

            $this->audit->log('dpo_config', null, 'atualizar_ajax', null, [
                'email_set' => $email !== '',
                'nome_set'  => $nome !== '',
            ], $atorId);

            \wp_send_json_success(['message' => __('Configuração DPO salva com sucesso.', 'participe-ibram')]);
        } catch (InvalidArgumentException $e) {
            \wp_send_json_error(['code' => 'validacao', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->logger->error('dpo_ajax.save_config.erro', ['erro' => $e->getMessage()]);
            \wp_send_json_error(['code' => 'erro_interno', 'message' => __('Erro interno.', 'participe-ibram')], 500);
        }
    }

    /**
     * Envia e-mail de teste para o DPO com dados fictícios.
     */
    public function testEmail(): void
    {
        try {
            $this->guard(self::NONCE_TEST);

            $dpoEmail = DpoConfig::getEmail();
            if ($dpoEmail === null) {
                \wp_send_json_error([
                    'code'    => 'sem_dpo_email',
                    'message' => __('E-mail do DPO não configurado.', 'participe-ibram'),
                ], 422);
                return;
            }

            // Vars fictícias — sem PII real
            $vars = [
                'solicitacao_id' => 0,
                'dias_restantes' => 3,
                'painel_url'     => function_exists('admin_url')
                    ? (string) \admin_url('admin.php?page=pi-lgpd')
                    : '/wp-admin/',
                'dpo_email'      => $dpoEmail,
                'unsubscribe_url' => '',
                '_teste'         => true,
            ];

            $atorId = \get_current_user_id();
            $this->enfileirar->handle(new EnfileirarEmailCommand(
                'dpo_alerta_solicitacao_art18',
                0,
                $dpoEmail,
                $vars
            ));

            $this->audit->log('dpo_config', null, 'teste_email_enviado', null, [
                'destinatario_masked' => substr($dpoEmail, 0, 3) . '***',
            ], $atorId);

            \wp_send_json_success(['message' => __('E-mail de teste enfileirado com sucesso.', 'participe-ibram')]);
        } catch (Throwable $e) {
            $this->logger->error('dpo_ajax.test_email.erro', ['erro' => $e->getMessage()]);
            \wp_send_json_error(['code' => 'erro_interno', 'message' => __('Erro ao enfileirar e-mail de teste.', 'participe-ibram')], 500);
        }
    }

    /* =====================================================================
     * Internal
     * ===================================================================== */

    /**
     * Verifica nonce + capability. Interrompe com wp_send_json_error se falhar.
     */
    private function guard(string $nonceAction): void
    {
        if (!function_exists('current_user_can')
            || !\current_user_can(self::CAP)
        ) {
            \wp_send_json_error(['code' => 'forbidden', 'message' => __('Acesso negado.', 'participe-ibram')], 403);
            exit;
        }

        $nonce = \wp_unslash((string) ($_POST['nonce'] ?? ($_POST['_wpnonce'] ?? '')));
        if (!\wp_verify_nonce($nonce, $nonceAction)) {
            \wp_send_json_error(['code' => 'nonce_invalido', 'message' => __('Token de segurança inválido.', 'participe-ibram')], 403);
            exit;
        }
    }
}
