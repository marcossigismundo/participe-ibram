<?php
/**
 * Listeners de eventos LGPD — enfileira emails self-service do titular.
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailCommand;
use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailHandler;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Throwable;

/**
 * Hooks escutados:
 *  - `pi_lgpd_anonimizacao_solicitada(int $solicitacaoId, int $agenteId, string $token, string $expiraEm)`
 *      → email `lgpd_anonimizacao_link`
 *  - `pi_lgpd_anonimizado(int $solicitacaoId, int $agenteId)`
 *      → email `lgpd_anonimizacao_executada` (vai para o email anonimizado — best-effort,
 *        documentado como rastreio; admin recebe sinal via auditoria)
 *  - `pi_lgpd_export_gerado(int $agenteId, string $url, string $expiraEm)`
 *      → email `lgpd_export_pronto`
 *  - `pi_solicitacao_titular_protocolada(int $solicitacaoId, int $agenteId, string $tipo)`
 *      → email `lgpd_solicitacao_recebida` (confirmação ao titular)
 *  - `pi_solicitacao_titular_atendida(int $solicitacaoId, int $agenteId)`
 *      → email `lgpd_solicitacao_atendida` (resposta DPO)
 *
 * Os RESOLVERS recebem o id e devolvem `['nome' => string, 'email' => string]` —
 * isso isola Application de Infrastructure (Agente repo) por wave/teste.
 *
 * Templates de emails LGPD NUNCA carregam CPF/RG/etc. — somente nome e link.
 */
final class LgpdEventListeners
{
    private EnfileirarEmailHandler $enfileirar;
    private SecureLogger $logger;
    private string $homeUrl;
    private string $confirmAnonUrlBase;
    private string $painelMinhaContaUrl;

    /** @var callable(int):?array{nome:string,email:string} */
    private $agenteResolver;

    /** @var callable(int):?array{nome:string,email:string,tipo:string} */
    private $solicitacaoResolver;

    /**
     * @param callable(int):?array{nome:string,email:string}                    $agenteResolver
     * @param callable(int):?array{nome:string,email:string,tipo:string}        $solicitacaoResolver
     * @param string                                                              $confirmAnonUrlBase  URL pública da página de confirmação. Receberá `?pi_anonimizacao_token=...`
     * @param string                                                              $painelMinhaContaUrl URL do painel "Minha conta" (para link no rodapé).
     */
    public function __construct(
        EnfileirarEmailHandler $enfileirar,
        SecureLogger $logger,
        string $homeUrl,
        string $confirmAnonUrlBase,
        string $painelMinhaContaUrl,
        callable $agenteResolver,
        callable $solicitacaoResolver
    ) {
        $this->enfileirar          = $enfileirar;
        $this->logger              = $logger;
        $this->homeUrl             = rtrim($homeUrl, '/');
        $this->confirmAnonUrlBase  = $confirmAnonUrlBase;
        $this->painelMinhaContaUrl = $painelMinhaContaUrl;
        $this->agenteResolver      = $agenteResolver;
        $this->solicitacaoResolver = $solicitacaoResolver;
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('pi_lgpd_anonimizacao_solicitada', [$this, 'onAnonimizacaoSolicitada'], 10, 4);
        \add_action('pi_lgpd_anonimizado', [$this, 'onAnonimizado'], 10, 2);
        \add_action('pi_lgpd_export_gerado', [$this, 'onExportGerado'], 10, 3);
        \add_action('pi_solicitacao_titular_protocolada', [$this, 'onSolicitacaoProtocolada'], 10, 3);
        \add_action('pi_solicitacao_titular_atendida', [$this, 'onSolicitacaoAtendida'], 10, 2);
    }

    public function onAnonimizacaoSolicitada(int $solicitacaoId, int $agenteId, string $token, string $expiraEm): void
    {
        try {
            $info = ($this->agenteResolver)($agenteId);
            if ($info === null || empty($info['email'])) {
                $this->logger->warning('Email anonimização: agente sem email.', ['agente_id' => $agenteId]);
                return;
            }
            $url = $this->buildConfirmAnonUrl($token);
            $cmd = new EnfileirarEmailCommand(
                'lgpd_anonimizacao_link',
                $agenteId,
                (string) $info['email'],
                [
                    'nome'              => (string) $info['nome'],
                    'confirmacao_url'   => $url,
                    'expira_em'         => $expiraEm,
                    'solicitacao_id'    => $solicitacaoId,
                    'minha_conta_url'   => $this->painelMinhaContaUrl,
                    'dpo_email'         => self::dpoContact(),
                ]
            );
            $this->enfileirar->handle($cmd);
        } catch (Throwable $e) {
            $this->logger->error('Falha ao enfileirar lgpd_anonimizacao_link.', ['erro' => $e->getMessage()]);
        }
    }

    public function onAnonimizado(int $solicitacaoId, int $agenteId): void
    {
        try {
            $info = ($this->agenteResolver)($agenteId);
            // Após anonimização o email é `anon-{id}@participe-ibram.local` — não envia
            // se for o endereço .local (rastreável apenas via auditoria).
            if ($info === null || empty($info['email']) || substr((string) $info['email'], -27) === '@participe-ibram.local') {
                return;
            }
            $cmd = new EnfileirarEmailCommand(
                'lgpd_anonimizacao_executada',
                $agenteId,
                (string) $info['email'],
                [
                    'nome'              => '',                  // nome já anonimizado — não usa
                    'solicitacao_id'    => $solicitacaoId,
                    'dpo_email'         => self::dpoContact(),
                ]
            );
            $this->enfileirar->handle($cmd);
        } catch (Throwable $e) {
            $this->logger->error('Falha ao enfileirar lgpd_anonimizacao_executada.', ['erro' => $e->getMessage()]);
        }
    }

    public function onExportGerado(int $agenteId, string $url, string $expiraEm): void
    {
        try {
            $info = ($this->agenteResolver)($agenteId);
            if ($info === null || empty($info['email'])) {
                return;
            }
            $cmd = new EnfileirarEmailCommand(
                'lgpd_export_pronto',
                $agenteId,
                (string) $info['email'],
                [
                    'nome'         => (string) $info['nome'],
                    'download_url' => $url,
                    'expira_em'    => $expiraEm,
                    'dpo_email'    => self::dpoContact(),
                ]
            );
            $this->enfileirar->handle($cmd);
        } catch (Throwable $e) {
            $this->logger->error('Falha ao enfileirar lgpd_export_pronto.', ['erro' => $e->getMessage()]);
        }
    }

    public function onSolicitacaoProtocolada(int $solicitacaoId, int $agenteId, string $tipo): void
    {
        try {
            $info = ($this->agenteResolver)($agenteId);
            if ($info === null || empty($info['email'])) {
                return;
            }
            $cmd = new EnfileirarEmailCommand(
                'lgpd_solicitacao_recebida',
                $agenteId,
                (string) $info['email'],
                [
                    'nome'           => (string) $info['nome'],
                    'solicitacao_id' => $solicitacaoId,
                    'tipo'           => $tipo,
                    'minha_conta_url' => $this->painelMinhaContaUrl,
                    'dpo_email'      => self::dpoContact(),
                ]
            );
            $this->enfileirar->handle($cmd);
        } catch (Throwable $e) {
            $this->logger->error('Falha ao enfileirar lgpd_solicitacao_recebida.', ['erro' => $e->getMessage()]);
        }
    }

    public function onSolicitacaoAtendida(int $solicitacaoId, int $agenteId): void
    {
        try {
            $info = ($this->solicitacaoResolver)($solicitacaoId);
            if ($info === null || empty($info['email'])) {
                return;
            }
            $cmd = new EnfileirarEmailCommand(
                'lgpd_solicitacao_atendida',
                $agenteId,
                (string) $info['email'],
                [
                    'nome'            => (string) $info['nome'],
                    'solicitacao_id'  => $solicitacaoId,
                    'tipo'            => (string) ($info['tipo'] ?? ''),
                    'minha_conta_url' => $this->painelMinhaContaUrl,
                    'dpo_email'       => self::dpoContact(),
                ]
            );
            $this->enfileirar->handle($cmd);
        } catch (Throwable $e) {
            $this->logger->error('Falha ao enfileirar lgpd_solicitacao_atendida.', ['erro' => $e->getMessage()]);
        }
    }

    private function buildConfirmAnonUrl(string $token): string
    {
        $sep = strpos($this->confirmAnonUrlBase, '?') === false ? '?' : '&';
        return $this->confirmAnonUrlBase . $sep . 'pi_anonimizacao_token=' . rawurlencode($token);
    }

    private static function dpoContact(): string
    {
        if (function_exists('get_option')) {
            $email = (string) \get_option('pi_dpo_email', '');
            if ($email !== '') {
                return $email;
            }
        }
        return 'encarregado@museus.gov.br';
    }
}
