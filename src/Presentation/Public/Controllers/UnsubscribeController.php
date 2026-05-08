<?php
/**
 * Endpoint público para cancelamento de comunicações.
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

use Ibram\ParticipeIbram\Application\Consentimento\RevogarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Email\Templates\UnsubscribeTokenizer;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Throwable;

/**
 * Página `?pi_action=unsubscribe&token=...`.
 *
 *  - GET: mostra página de confirmação (sem revogar nada — R5 V-11: nunca
 *    desinscreve via GET direto pois pré-fetchers de mail clients fariam
 *    isso por engano).
 *  - POST com nonce: valida token, invoca {@see RevogarConsentimentoHandler}
 *    com a finalidade `comunicacao` e mostra mensagem de sucesso.
 *
 * O endpoint é registrado em `init` e responde a requisições com o param
 * `pi_action=unsubscribe` ANTES do tema renderizar.
 */
final class UnsubscribeController
{
    public const NONCE_ACTION = 'pi_unsubscribe_confirm';

    private UnsubscribeTokenizer $tokenizer;
    private RevogarConsentimentoHandler $revogar;
    private AgenteRepository $agentes;
    private AuditLogger $audit;
    private SecureLogger $logger;
    private string $templateFile;

    public function __construct(
        UnsubscribeTokenizer $tokenizer,
        RevogarConsentimentoHandler $revogar,
        AgenteRepository $agentes,
        AuditLogger $audit,
        SecureLogger $logger,
        string $templateFile
    ) {
        $this->tokenizer    = $tokenizer;
        $this->revogar      = $revogar;
        $this->agentes      = $agentes;
        $this->audit        = $audit;
        $this->logger       = $logger;
        $this->templateFile = $templateFile;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('init', [$this, 'maybeHandle'], 5);
    }

    public function maybeHandle(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['pi_action']) ? (string) wp_unslash($_GET['pi_action']) : '';
        if ($action !== 'unsubscribe') {
            return;
        }

        $token = (string) RequestHelper::request('token', 'sanitize_text_field', '');

        // Valida token (devolve userId, purpose, expiraEm).
        $userId   = 0;
        $purpose  = '';
        $expiraEm = null;
        $valid    = $this->tokenizer->verify($token, $userId, $purpose, $expiraEm);

        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

        if (!$valid) {
            $this->renderPage([
                'state'   => 'invalid',
                'message' => 'Link invalido ou expirado.',
            ]);
            exit;
        }

        if ($isPost) {
            $nonce = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
            if ($nonce === ''
                || !function_exists('wp_verify_nonce')
                || !\wp_verify_nonce($nonce, self::NONCE_ACTION)
            ) {
                $this->renderPage([
                    'state'   => 'invalid',
                    'message' => 'Nonce invalido. Volte ao link e tente novamente.',
                    'token'   => $token,
                ]);
                exit;
            }

            $this->processarRevogacao($userId, $purpose, $token);
            exit;
        }

        // GET — só mostra confirmação.
        $this->renderPage([
            'state'   => 'confirm',
            'token'   => $token,
            'purpose' => $purpose,
        ]);
        exit;
    }

    private function processarRevogacao(int $userId, string $purpose, string $token): void
    {
        try {
            $agente = $this->agentes->findById($userId);
            if ($agente === null) {
                $this->renderPage(['state' => 'invalid', 'message' => 'Cadastro nao localizado.']);
                return;
            }

            $finalidade = self::mapPurposeToFinalidade($purpose);
            if ($finalidade === null) {
                $this->renderPage(['state' => 'invalid', 'message' => 'Finalidade nao reconhecida.']);
                return;
            }

            // Finalidades obrigatórias (identificacao/comunicacao) NÃO podem ser
            // revogadas via unsubscribe — base legal é execução de política pública
            // (Art. 7º, III LGPD). O usuário deve usar o painel para gerenciar
            // preferências granulares ou solicitar exclusão completa via DPO.
            if ($finalidade->isObrigatoria()) {
                $this->audit->log(
                    'unsubscribe',
                    $userId,
                    'tentativa_revogacao_obrigatoria',
                    ['finalidade' => $finalidade->value()],
                    null
                );
                $this->renderPage([
                    'state'   => 'invalid',
                    'message' => 'Esta categoria de comunicacao nao pode ser desativada por este link, '
                        . 'pois esta vinculada a base legal de execucao de politica publica. '
                        . 'Acesse seu painel para gerenciar preferencias ou contate o DPO.',
                ]);
                return;
            }

            // ip_hash via do AuditLogger (não temos IpResolver aqui — passamos null;
            // a Wave 2 já dá fallback).
            $this->revogar->handle($userId, $finalidade, null, null);

            $this->audit->log(
                'unsubscribe',
                $userId,
                'confirmar',
                ['finalidade' => $finalidade->value()],
                ['status' => 'revogado']
            );

            $this->renderPage(['state' => 'sucesso', 'finalidade' => $finalidade->value()]);
        } catch (Throwable $e) {
            $this->logger->error('unsubscribe.exception', [
                'erro'    => $e->getMessage(),
                'agente_id' => $userId,
            ]);
            $this->renderPage([
                'state'   => 'erro',
                'message' => 'Nao foi possivel processar a solicitacao.',
                'token'   => $token,
            ]);
        }
    }

    private static function mapPurposeToFinalidade(string $purpose): ?Finalidade
    {
        // Wave 4 só usa 'comunicacao' (não-essencial). Outros mapeamentos
        // podem ser adicionados conforme finalidades opcionais surgirem.
        try {
            return Finalidade::fromString($purpose);
        } catch (\Throwable $ignored) {
            unset($ignored);
            return null;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderPage(array $data): void
    {
        if (function_exists('status_header')) {
            \status_header(200);
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        if (is_file($this->templateFile)) {
            $vars = $data;
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            include $this->templateFile;
            return;
        }
        $msg = isset($data['message']) ? (string) $data['message'] : 'Cancelamento de comunicacoes';
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
            . '</title></head><body><p>'
            . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
            . '</p></body></html>';
    }
}
