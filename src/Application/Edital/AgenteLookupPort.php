<?php
/**
 * Port (forward-reference) para consulta cross-domain ao agregado Agente.
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

/**
 * Contrato mínimo que o domínio Edital precisa do domínio Agente sem
 * instanciar suas classes diretamente (Wave 2 paralela).
 *
 * Adapter de produção implementa contra
 * `\Ibram\ParticipeIbram\Domain\Agente\AgenteRepository` (D1).
 *
 * Devolve a tupla mínima:
 *  - exists: bool
 *  - deferido: bool (status_cadastro ∈ {deferido, deferido_em_retratacao, deferido_em_recurso})
 *  - tipo: string ("PF" | "OR" | "SM")
 */
interface AgenteLookupPort
{
    /**
     * Recupera os atributos relevantes do agente para validações de inscrição.
     *
     * @return array{exists:bool, deferido:bool, tipo:string}
     */
    public function lookup(int $agenteId): array;
}
