<?php
/**
 * Handler de confirmação de anonimização (executa AnonimizarTitularHandler).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use RuntimeException;

/**
 * Verifica o token de email, invoca {@see AnonimizarTitularHandler::handle} e
 * encerra a solicitação como `atendida`. Esta operação é IRREVERSÍVEL.
 *
 * Audita TODOS os passos (forense — passo legalmente relevante).
 *
 * Pós-condições:
 *  - Dados pessoais do agente anonimizados.
 *  - Solicitação `tipo=anonimizacao` em status `atendida`.
 *  - Hook `pi_lgpd_anonimizado` disparado (listener envia email de confirmação
 *    para o endereço anonimizado — operação intencionalmente best-effort).
 *  - Login WP forçado a logout (callback opcional injetado pelo container).
 *
 * O callback `logoutCallback` é injetado para que esta camada não dependa de
 * `wp_logout()` diretamente (testabilidade — em integração o test passa um
 * callback que registra a chamada).
 */
final class ConfirmarAnonimizacaoHandler
{
    private SolicitacaoTitularRepository $solicitacoes;
    private AnonimizacaoTokenizer $tokenizer;
    private AnonimizarTitularHandler $anonimizador;
    private AuditLogger $audit;
    private SecureLogger $logger;

    /** @var callable(int):void */
    private $logoutCallback;

    /** @var callable(int):?int Resolve user_id a partir do agente_id. */
    private $userIdResolver;

    /**
     * @param callable(int):void  $logoutCallback   Recebe o user_id e força logout.
     * @param callable(int):?int  $userIdResolver   Recebe agente_id, devolve user_id ou null.
     */
    public function __construct(
        SolicitacaoTitularRepository $solicitacoes,
        AnonimizacaoTokenizer $tokenizer,
        AnonimizarTitularHandler $anonimizador,
        AuditLogger $audit,
        SecureLogger $logger,
        callable $logoutCallback,
        callable $userIdResolver
    ) {
        $this->solicitacoes   = $solicitacoes;
        $this->tokenizer      = $tokenizer;
        $this->anonimizador   = $anonimizador;
        $this->audit          = $audit;
        $this->logger         = $logger;
        $this->logoutCallback = $logoutCallback;
        $this->userIdResolver = $userIdResolver;
    }

    /**
     * @return array{solicitacao_id:int,agente_id:int,short_id:string,campos_limpos:array<int,string>}
     *
     * @throws DomainException
     */
    public function handle(ConfirmarAnonimizacaoCommand $command): array
    {
        // 1. Verificar token (HMAC + expiração) — falhas auditadas mas sem detalhar.
        $solicitacaoId = 0;
        $agenteId      = 0;
        $expiraEm      = null;
        $valido = $this->tokenizer->verify($command->token(), $solicitacaoId, $agenteId, $expiraEm);

        if (!$valido) {
            $this->audit->log(
                'lgpd_solicitacao_titular',
                null,
                'anonimizacao_token_invalido',
                null,
                [
                    'ator_user_id' => $command->atorUserId(),
                    'ip_hash'      => $command->ipHash(),
                    'motivo'       => 'token_invalido_ou_expirado',
                ],
                $command->atorUserId()
            );
            throw new DomainException('Token de confirmação inválido ou expirado.');
        }

        // 2. Carregar solicitação e validar consistência.
        $solicitacao = $this->solicitacoes->findById($solicitacaoId);
        if ($solicitacao === null) {
            $this->audit->log(
                'lgpd_solicitacao_titular',
                $solicitacaoId,
                'anonimizacao_token_invalido',
                null,
                ['motivo' => 'solicitacao_nao_encontrada'],
                $command->atorUserId()
            );
            throw new DomainException('Solicitação não encontrada.');
        }
        if ($solicitacao->agenteId() !== $agenteId) {
            $this->audit->log(
                'lgpd_solicitacao_titular',
                $solicitacaoId,
                'anonimizacao_token_invalido',
                null,
                ['motivo' => 'agente_id_divergente'],
                $command->atorUserId()
            );
            throw new DomainException('Token incompatível com a solicitação.');
        }
        if ($solicitacao->tipo() !== SolicitacaoTitular::TIPO_ANONIMIZACAO) {
            throw new DomainException('Tipo de solicitação inesperado.');
        }
        if ($solicitacao->status() !== SolicitacaoTitular::STATUS_ABERTA
            && $solicitacao->status() !== SolicitacaoTitular::STATUS_EM_ATENDIMENTO) {
            $this->audit->log(
                'lgpd_solicitacao_titular',
                $solicitacaoId,
                'anonimizacao_token_invalido',
                null,
                ['motivo' => 'solicitacao_ja_encerrada', 'status_atual' => $solicitacao->status()],
                $command->atorUserId()
            );
            throw new DomainException('Solicitação já encerrada.');
        }

        $this->audit->log(
            'lgpd_solicitacao_titular',
            $solicitacaoId,
            'anonimizacao_confirmacao_recebida',
            null,
            [
                'agente_id'    => $agenteId,
                'ator_user_id' => $command->atorUserId(),
                'ip_hash'      => $command->ipHash(),
            ],
            $command->atorUserId()
        );

        // 3. EXECUTAR (irreversível).
        try {
            $resumo = $this->anonimizador->handle($agenteId, $command->atorUserId());
        } catch (\Throwable $e) {
            $this->audit->log(
                'lgpd_solicitacao_titular',
                $solicitacaoId,
                'anonimizacao_falhou',
                null,
                ['agente_id' => $agenteId, 'erro' => $e->getMessage()],
                $command->atorUserId()
            );
            throw new RuntimeException('Falha ao executar anonimização: ' . $e->getMessage(), 0, $e);
        }

        // 4. Encerrar solicitação como atendida.
        $atorParaFechar = $command->atorUserId() ?? 0;
        if ($atorParaFechar < 1) {
            // Fallback: usa user_id do agente como ator (auto-atendimento).
            $resolvido = ($this->userIdResolver)($agenteId);
            $atorParaFechar = $resolvido !== null && $resolvido > 0 ? $resolvido : 0;
        }
        if ($atorParaFechar > 0) {
            $solicitacao->responder(
                'Anonimização executada irreversivelmente pelo titular. Dados removidos conforme LGPD Art. 18, IV. Audit log preservado por obrigação legal (Art. 16, II).',
                $atorParaFechar,
                true
            );
            $this->solicitacoes->save($solicitacao);
        }

        // 5. Forçar logout (a conta WP foi anonimizada — sessão atual não pode persistir).
        $userIdAlvo = ($this->userIdResolver)($agenteId);
        if ($userIdAlvo !== null && $userIdAlvo > 0) {
            try {
                ($this->logoutCallback)($userIdAlvo);
            } catch (\Throwable $e) {
                // Logout best-effort — não falhar a confirmação por isso.
                $this->logger->warning('Falha no logout pós-anonimização.', [
                    'user_id' => $userIdAlvo,
                    'erro'    => $e->getMessage(),
                ]);
            }
        }

        // 6. Auditoria final + hook.
        $this->audit->log(
            'lgpd_solicitacao_titular',
            $solicitacaoId,
            'anonimizacao_executada_via_token',
            null,
            [
                'agente_id'     => $agenteId,
                'short_id'      => $resumo['short_id'] ?? null,
                'ator_user_id'  => $command->atorUserId(),
                'campos_limpos' => $resumo['campos_limpos'] ?? [],
            ],
            $command->atorUserId()
        );

        if (function_exists('do_action')) {
            \do_action('pi_lgpd_anonimizado', $solicitacaoId, $agenteId);
        }

        $this->logger->info('LGPD anonimização confirmada e executada.', [
            'solicitacao_id' => $solicitacaoId,
            'agente_id'      => $agenteId,
        ]);

        return [
            'solicitacao_id' => $solicitacaoId,
            'agente_id'      => $agenteId,
            'short_id'       => (string) ($resumo['short_id'] ?? ''),
            'campos_limpos'  => (array) ($resumo['campos_limpos'] ?? []),
        ];
    }
}
