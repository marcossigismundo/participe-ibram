<?php
/**
 * Handler de solicitação de anonimização (passo 1 — gera token e protocola).
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
 * Cria solicitação `tipo=anonimizacao`, gera token de confirmação por email e
 * dispara hook `pi_lgpd_anonimizacao_solicitada` para que o listener envie o
 * email com o link (mantém Application desacoplada de Infrastructure de email).
 *
 * Idempotência defensiva: se já existe uma solicitação `anonimizacao` aberta
 * para o agente, REJEITA com DomainException — o usuário deve aguardar o token
 * anterior expirar OU executar a confirmação pendente. Isso evita "spam" de
 * tokens válidos circulando por email.
 *
 * Audita em DOIS passos:
 *  1. `anonimizacao_solicitada` (forense — antes do INSERT)
 *  2. `anonimizacao_token_emitido` (após o INSERT, com solicitacao_id)
 */
final class SolicitarAnonimizacaoHandler
{
    private SolicitacaoTitularRepository $solicitacoes;
    private AnonimizacaoTokenizer $tokenizer;
    private AuditLogger $audit;
    private SecureLogger $logger;

    public function __construct(
        SolicitacaoTitularRepository $solicitacoes,
        AnonimizacaoTokenizer $tokenizer,
        AuditLogger $audit,
        SecureLogger $logger
    ) {
        $this->solicitacoes = $solicitacoes;
        $this->tokenizer    = $tokenizer;
        $this->audit        = $audit;
        $this->logger       = $logger;
    }

    /**
     * @return array{solicitacao_id:int,token:string,expira_em:string}
     *
     * @throws DomainException Quando há anonimização em andamento.
     */
    public function handle(SolicitarAnonimizacaoCommand $command): array
    {
        // 1. Verifica que NÃO há anonimização em andamento (idempotência defensiva).
        $abertas = $this->solicitacoes->findAbertasPorAgente($command->agenteId());
        foreach ($abertas as $aberta) {
            if ($aberta->tipo() === SolicitacaoTitular::TIPO_ANONIMIZACAO) {
                throw new DomainException(
                    'Já existe uma solicitação de anonimização em andamento. '
                    . 'Verifique seu email ou aguarde 24h para refazer.'
                );
            }
        }

        // 2. Auditoria de intent (antes do INSERT) — preserva trilha mesmo se DB falhar depois.
        $this->audit->log(
            'lgpd_solicitacao_titular',
            null,
            'anonimizacao_solicitada',
            null,
            [
                'agente_id' => $command->agenteId(),
                'user_id'   => $command->userId(),
                'motivo'    => $command->motivo() !== null ? '[redacted-length-' . mb_strlen($command->motivo()) . ']' : null,
                'ip_hash'   => $command->ipHash(),
            ],
            $command->userId()
        );

        // 3. Protocola solicitação (status=aberta).
        $detalhes = $this->buildDetalhes($command->motivo());
        $solicitacao = SolicitacaoTitular::protocolar(
            $command->agenteId(),
            SolicitacaoTitular::TIPO_ANONIMIZACAO,
            $detalhes
        );
        $solicitacaoId = $this->solicitacoes->save($solicitacao);
        if ($solicitacaoId < 1) {
            throw new RuntimeException('Falha ao persistir solicitação de anonimização.');
        }

        // 4. Gera token HMAC 24h.
        $expiraEm = (new DateTimeImmutable('now'))->modify('+' . AnonimizacaoTokenizer::TTL_SECONDS . ' seconds');
        $token    = $this->tokenizer->tokenFor($solicitacaoId, $command->agenteId(), $expiraEm);

        // 5. Auditoria do token emitido (forense).
        $this->audit->log(
            'lgpd_solicitacao_titular',
            $solicitacaoId,
            'anonimizacao_token_emitido',
            null,
            [
                'agente_id'    => $command->agenteId(),
                'user_id'      => $command->userId(),
                'expira_em'    => $expiraEm->format(\DateTimeInterface::ATOM),
                'token_prefix' => substr($token, 0, 8) . '…', // só prefixo, NUNCA token inteiro em log
            ],
            $command->userId()
        );

        // 6. Dispara hook para listener de email.
        if (function_exists('do_action')) {
            \do_action(
                'pi_lgpd_anonimizacao_solicitada',
                $solicitacaoId,
                $command->agenteId(),
                $token,
                $expiraEm->format(\DateTimeInterface::ATOM)
            );
        }

        $this->logger->info('LGPD anonimização solicitada.', [
            'solicitacao_id' => $solicitacaoId,
            'agente_id'      => $command->agenteId(),
        ]);

        return [
            'solicitacao_id' => $solicitacaoId,
            'token'          => $token,
            'expira_em'      => $expiraEm->format(\DateTimeInterface::ATOM),
        ];
    }

    private function buildDetalhes(?string $motivo): string
    {
        $linhas = ['## Solicitação de anonimização (LGPD Art. 18, IV)'];
        if ($motivo !== null && $motivo !== '') {
            // O motivo é texto puro (foi sanitizado no Command); aqui apenas embutimos.
            $linhas[] = '';
            $linhas[] = '### Motivo informado pelo titular';
            $linhas[] = $motivo;
        } else {
            $linhas[] = '';
            $linhas[] = '_Motivo não informado pelo titular._';
        }
        $linhas[] = '';
        $linhas[] = '> Aguardando confirmação do titular via link enviado por email (TTL 24h).';

        return implode("\n", $linhas);
    }
}
