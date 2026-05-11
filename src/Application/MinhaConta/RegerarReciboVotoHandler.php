<?php
/**
 * Handler: regerar o recibo (`hash_voto`) de um voto do próprio agente.
 *
 * @package Ibram\ParticipeIbram\Application\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\MinhaConta;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use RuntimeException;

/**
 * Caso de uso: o agente pediu para receber novamente o recibo de um voto que
 * ele já registrou (ex.: perdeu o impresso original).
 *
 * Segurança / privacidade:
 *  - Recibo combina **dados públicos** já presentes no banco
 *    (`votacao_id|categoria_id|candidato_inscricao_id|votado_em`) — mesmo
 *    algoritmo do W6-A. Reproduzir não revela nada novo.
 *  - O `candidato_inscricao_id` entra no cálculo do hash mas **NÃO retorna**
 *    para o caller — voto secreto. O retorno tem apenas `hash_voto` e
 *    `votado_em`.
 *  - O re-acesso é **auditado** ({@see AuditLogger}) em entidade `recibo_voto`,
 *    ação `regerar`, com `entidade_id = votacao_id` e SEM dados sensíveis
 *    (sem candidato, sem eleitor_hash). Permite detecção forense de pedidos
 *    repetidos suspeitos (e.g. tentativa de phishing usando o sistema).
 *  - Voto não encontrado retorna `null` (caller traduz para 404 genérico —
 *    NUNCA revela "este voto não é seu" vs "este voto não existe").
 */
final class RegerarReciboVotoHandler
{
    private EleitorHasher $eleitorHasher;

    private HistoricoVotosPort $historicoPort;

    private AuditLogger $audit;

    public function __construct(
        EleitorHasher $eleitorHasher,
        HistoricoVotosPort $historicoPort,
        AuditLogger $audit
    ) {
        $this->eleitorHasher = $eleitorHasher;
        $this->historicoPort = $historicoPort;
        $this->audit         = $audit;
    }

    /**
     * @return array{hash_voto:string, votado_em:string}|null `null` quando o
     *                                                        voto não existe
     *                                                        para esse agente.
     *
     * @throws RuntimeException Em falha do hash (extremamente raro).
     */
    public function handle(RegerarReciboVotoCommand $command): ?array
    {
        $agenteId  = $command->agenteId();
        $votacaoId = $command->votacaoId();

        // 1. Calcula eleitor_hash deste agente nesta votação.
        $eleitorHash = $this->eleitorHasher->hash($agenteId, $votacaoId);

        // 2. Recupera dados internos para regenerar o recibo. O `candidato`
        //    sai do port apenas para entrar no `hash_voto`; não atravessa
        //    esta camada de volta.
        $dados = $this->historicoPort->obterDadosParaRecibo($votacaoId, $eleitorHash);
        if ($dados === null) {
            // Audita tentativa de regerar recibo inexistente (forense — pode indicar
            // probing / phishing). Sem dados sensíveis, apenas votacao_id e ator_id.
            $this->audit->log(
                'recibo_voto',
                $votacaoId,
                'regerar_inexistente',
                null,
                [
                    'votacao_id' => $votacaoId,
                ],
                null
            );

            return null;
        }

        // 3. Hash sha256 sobre dados públicos — mesmo algoritmo do
        //    VotacaoEndpoints (W6-A): `votacaoId|categoriaId|candidatoId|votadoEm`.
        $hash = hash(
            'sha256',
            sprintf(
                '%d|%d|%d|%s',
                (int) $dados['votacao_id'],
                (int) $dados['categoria_id'],
                (int) $dados['candidato_inscricao_id'],
                (string) $dados['votado_em']
            )
        );

        // 4. Auditoria de re-acesso — SEM candidato, SEM eleitor_hash.
        $this->audit->log(
            'recibo_voto',
            $votacaoId,
            'regerar',
            null,
            [
                'votacao_id' => $votacaoId,
                'votado_em'  => (string) $dados['votado_em'],
            ],
            null
        );

        // 5. Retorna apenas o hash e o timestamp — `candidato_inscricao_id`
        //    nunca atravessa a fronteira.
        return [
            'hash_voto' => $hash,
            'votado_em' => (string) $dados['votado_em'],
        ];
    }
}
