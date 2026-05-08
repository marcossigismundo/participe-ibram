<?php
/**
 * Comando para salvar rascunho de inscrição em edital.
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use InvalidArgumentException;

/**
 * DTO imutável com os parâmetros de um rascunho de inscrição.
 */
final class SalvarRascunhoInscricaoCommand
{
    private int $editalId;
    private int $categoriaId;
    private int $agenteId;
    private ?string $portfolioMd;
    private ?int $inscricaoId;
    private string $etapaAtual;

    /**
     * @param int         $editalId    ID do edital.
     * @param int         $categoriaId ID da categoria.
     * @param int         $agenteId    ID do agente inscrito.
     * @param string|null $portfolioMd Texto do portfólio em Markdown (opcional).
     * @param int|null    $inscricaoId ID da inscrição existente (null = nova).
     * @param string      $etapaAtual  Identificador do passo atual do wizard.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $editalId,
        int $categoriaId,
        int $agenteId,
        ?string $portfolioMd = null,
        ?int $inscricaoId = null,
        string $etapaAtual = 'categoria'
    ) {
        if ($editalId <= 0) {
            throw new InvalidArgumentException('SalvarRascunhoInscricaoCommand.editalId deve ser positivo.');
        }
        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('SalvarRascunhoInscricaoCommand.categoriaId deve ser positivo.');
        }
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('SalvarRascunhoInscricaoCommand.agenteId deve ser positivo.');
        }
        if ($inscricaoId !== null && $inscricaoId <= 0) {
            throw new InvalidArgumentException('SalvarRascunhoInscricaoCommand.inscricaoId deve ser positivo quando informado.');
        }

        $this->editalId    = $editalId;
        $this->categoriaId = $categoriaId;
        $this->agenteId    = $agenteId;
        $this->portfolioMd = $portfolioMd;
        $this->inscricaoId = $inscricaoId;
        $this->etapaAtual  = $etapaAtual !== '' ? $etapaAtual : 'categoria';
    }

    public function editalId(): int
    {
        return $this->editalId;
    }

    public function categoriaId(): int
    {
        return $this->categoriaId;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function portfolioMd(): ?string
    {
        return $this->portfolioMd;
    }

    public function inscricaoId(): ?int
    {
        return $this->inscricaoId;
    }

    public function etapaAtual(): string
    {
        return $this->etapaAtual;
    }
}
