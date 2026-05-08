<?php
/**
 * Entidade StatusHistorico — append-only de transições do agente (TD-05).
 *
 * @package Ibram\ParticipeIbram\Domain\Analise
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Analise;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Linha de histórico em `wp_pi_status_historico`. Imutável; persistência é
 * estritamente APPEND-ONLY (mesma garantia de `wp_pi_audit_log`, com escopo
 * narrow para transições de status do cadastro do agente).
 */
final class StatusHistorico
{
    private ?int $id;
    private int $agenteId;
    private string $statusAnterior;
    private string $statusNovo;
    private ?int $atorId;
    private ?string $observacao;
    private DateTimeImmutable $ocorridoEm;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?int $id,
        int $agenteId,
        string $statusAnterior,
        string $statusNovo,
        ?int $atorId,
        ?string $observacao,
        DateTimeImmutable $ocorridoEm
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('StatusHistorico.id deve ser positivo quando informado.');
        }
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('StatusHistorico.agenteId deve ser positivo.');
        }
        $anterior = trim($statusAnterior);
        $novo     = trim($statusNovo);
        if ($anterior === '' || $novo === '') {
            throw new InvalidArgumentException(
                'StatusHistorico: statusAnterior e statusNovo nao podem ser vazios.'
            );
        }
        if ($atorId !== null && $atorId <= 0) {
            throw new InvalidArgumentException('StatusHistorico.atorId deve ser positivo quando informado.');
        }

        $this->id             = $id;
        $this->agenteId       = $agenteId;
        $this->statusAnterior = $anterior;
        $this->statusNovo     = $novo;
        $this->atorId         = $atorId;
        $this->observacao     = $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null;
        $this->ocorridoEm     = $ocorridoEm;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function statusAnterior(): string
    {
        return $this->statusAnterior;
    }

    public function statusNovo(): string
    {
        return $this->statusNovo;
    }

    public function atorId(): ?int
    {
        return $this->atorId;
    }

    public function observacao(): ?string
    {
        return $this->observacao;
    }

    public function ocorridoEm(): DateTimeImmutable
    {
        return $this->ocorridoEm;
    }
}
