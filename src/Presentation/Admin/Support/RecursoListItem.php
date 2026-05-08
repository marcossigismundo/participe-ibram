<?php
/**
 * Item DTO para listagens administrativas de Recursos.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

use DateTimeImmutable;

/**
 * Linha read-model para as List Tables de Recurso.
 *
 * Carrega dados já agregados (recurso + análise + agente) em um struct imutável
 * — evita N+1 lookups na renderização e centraliza o mascaramento de PII de
 * acordo com R5 V-01 (qualquer texto sensível em listagens administrativas).
 */
final class RecursoListItem
{
    public int $recursoId;
    public int $analiseId;
    public int $agenteId;
    public string $fase;
    public ?string $decisao;
    public string $agenteTipo;
    public string $agenteNomeMascarado;
    public ?string $numeroRegistroOriginal;
    public DateTimeImmutable $dataProtocolo;
    public DateTimeImmutable $prazoFim;
    public ?string $decisorPotencialNome;
    public int $diasRestantes;

    public function __construct(
        int $recursoId,
        int $analiseId,
        int $agenteId,
        string $fase,
        ?string $decisao,
        string $agenteTipo,
        string $agenteNomeMascarado,
        ?string $numeroRegistroOriginal,
        DateTimeImmutable $dataProtocolo,
        DateTimeImmutable $prazoFim,
        ?string $decisorPotencialNome,
        int $diasRestantes
    ) {
        $this->recursoId              = $recursoId;
        $this->analiseId              = $analiseId;
        $this->agenteId               = $agenteId;
        $this->fase                   = $fase;
        $this->decisao                = $decisao;
        $this->agenteTipo             = $agenteTipo;
        $this->agenteNomeMascarado    = $agenteNomeMascarado;
        $this->numeroRegistroOriginal = $numeroRegistroOriginal;
        $this->dataProtocolo          = $dataProtocolo;
        $this->prazoFim               = $prazoFim;
        $this->decisorPotencialNome   = $decisorPotencialNome;
        $this->diasRestantes          = $diasRestantes;
    }

    /**
     * Severity label para uso em UIs de prazo.
     *
     * Retorna uma das strings: `vencido`, `urgente`, `atencao`, `ok`.
     */
    public function severidadePrazo(): string
    {
        if ($this->diasRestantes < 0) {
            return 'vencido';
        }
        if ($this->diasRestantes <= 2) {
            return 'urgente';
        }
        if ($this->diasRestantes <= 5) {
            return 'atencao';
        }

        return 'ok';
    }
}
