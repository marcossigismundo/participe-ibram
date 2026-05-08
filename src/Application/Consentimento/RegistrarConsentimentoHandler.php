<?php
/**
 * Handler do RegistrarConsentimentoCommand.
 *
 * @package Ibram\ParticipeIbram\Application\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Consentimento;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\Consentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\StatusConsentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;

/**
 * Registra consentimentos de um agente em batch.
 *
 *  - Valida que o termo referenciado existe.
 *  - Valida que TODAS as finalidades obrigatórias foram aceitas (caso
 *    contrário lança {@see DomainException}).
 *  - Cria um registro {@see Consentimento} ACEITO ou NEGADO por finalidade.
 *  - Audita cada decisão via {@see AuditLogger} e dispara hooks WP em
 *    {@see ConsentimentoEventos}.
 */
final class RegistrarConsentimentoHandler
{
    private ConsentimentoRepository $consentimentos;
    private TermoRepository $termos;
    private AuditLogger $audit;

    public function __construct(
        ConsentimentoRepository $consentimentos,
        TermoRepository $termos,
        AuditLogger $audit
    ) {
        $this->consentimentos = $consentimentos;
        $this->termos         = $termos;
        $this->audit          = $audit;
    }

    /**
     * @return array<string,int> Mapa finalidade => id do consentimento gravado.
     *
     * @throws DomainException Quando o termo é inválido ou faltam obrigatórias.
     */
    public function handle(RegistrarConsentimentoCommand $command): array
    {
        $termo = $this->termos->findById($command->termoId());
        if ($termo === null) {
            throw new DomainException(sprintf(
                'Termo id=%d não encontrado.',
                $command->termoId()
            ));
        }
        if (!$termo->isAtivo()) {
            throw new DomainException(sprintf(
                'Termo "%s" não está ativo.',
                $termo->versao()
            ));
        }

        // Garante que todas as finalidades obrigatórias foram aceitas.
        $aceitasNorm = $command->finalidadesAceitas();
        foreach (Finalidade::all() as $f) {
            if ($f->isObrigatoria() && !in_array($f->value(), $aceitasNorm, true)) {
                throw new DomainException(sprintf(
                    'Finalidade obrigatória "%s" deve ser aceita.',
                    $f->value()
                ));
            }
        }

        $resultado = [];

        foreach ($aceitasNorm as $valor) {
            $finalidade = Finalidade::fromString($valor);
            $id         = $this->registrarUm($command, $termo->id() ?? $command->termoId(), $finalidade, StatusConsentimento::aceito());
            $resultado[$finalidade->value()] = $id;

            $this->audit->log(
                'consentimento',
                $id,
                'consentimento_aceito',
                null,
                [
                    'agente_id'  => $command->agenteId(),
                    'termo_id'   => $termo->id(),
                    'finalidade' => $finalidade->value(),
                ]
            );

            ConsentimentoEventos::dispararRegistrado($id, $finalidade);
        }

        foreach ($command->finalidadesNegadas() as $valor) {
            $finalidade = Finalidade::fromString($valor);
            if ($finalidade->isObrigatoria()) {
                // Já barrado acima — defesa em profundidade.
                throw new DomainException(sprintf(
                    'Finalidade obrigatória "%s" não pode ser negada.',
                    $finalidade->value()
                ));
            }
            $id = $this->registrarUm($command, $termo->id() ?? $command->termoId(), $finalidade, StatusConsentimento::negado());
            $resultado[$finalidade->value()] = $id;

            $this->audit->log(
                'consentimento',
                $id,
                'consentimento_negado',
                null,
                [
                    'agente_id'  => $command->agenteId(),
                    'termo_id'   => $termo->id(),
                    'finalidade' => $finalidade->value(),
                ]
            );
        }

        return $resultado;
    }

    private function registrarUm(
        RegistrarConsentimentoCommand $command,
        int $termoId,
        Finalidade $finalidade,
        StatusConsentimento $status
    ): int {
        $consentimento = Consentimento::registrar(
            $command->agenteId(),
            $termoId,
            $finalidade,
            $status,
            $command->ipHash(),
            $command->userAgent()
        );

        return $this->consentimentos->save($consentimento);
    }
}
