<?php
/**
 * Command DTO para registrar consentimentos do agente em batch.
 *
 * @package Ibram\ParticipeIbram\Application\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Consentimento;

use InvalidArgumentException;

/**
 * DTO imutável.
 *
 * @psalm-immutable
 */
final class RegistrarConsentimentoCommand
{
    private int $agenteId;
    private int $termoId;

    /** @var array<int,string> */
    private array $finalidades;

    /** @var array<int,string> */
    private array $negadas;

    private ?string $ipHash;
    private ?string $userAgent;

    /**
     * @param array<int,string> $finalidades Lista de strings de Finalidade aceitas.
     * @param array<int,string> $negadas     Lista de strings de Finalidade negadas.
     */
    public function __construct(
        int $agenteId,
        int $termoId,
        array $finalidades,
        array $negadas,
        ?string $ipHash,
        ?string $userAgent
    ) {
        if ($agenteId < 1) {
            throw new InvalidArgumentException('agenteId deve ser positivo.');
        }
        if ($termoId < 1) {
            throw new InvalidArgumentException('termoId deve ser positivo.');
        }

        $finalidades = self::normalizeStringList($finalidades);
        $negadas     = self::normalizeStringList($negadas);

        $intersect = array_intersect($finalidades, $negadas);
        if ($intersect !== []) {
            throw new InvalidArgumentException(sprintf(
                'Finalidades não podem aparecer em aceitas e negadas: %s',
                implode(', ', $intersect)
            ));
        }

        $this->agenteId    = $agenteId;
        $this->termoId     = $termoId;
        $this->finalidades = $finalidades;
        $this->negadas     = $negadas;
        $this->ipHash      = $ipHash;
        $this->userAgent   = $userAgent;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function termoId(): int
    {
        return $this->termoId;
    }

    /** @return array<int,string> */
    public function finalidadesAceitas(): array
    {
        return $this->finalidades;
    }

    /** @return array<int,string> */
    public function finalidadesNegadas(): array
    {
        return $this->negadas;
    }

    public function ipHash(): ?string
    {
        return $this->ipHash;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @param array<mixed,mixed> $list
     *
     * @return array<int,string>
     */
    private static function normalizeStringList(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            if (!is_string($item)) {
                continue;
            }
            $val = strtolower(trim($item));
            if ($val === '') {
                continue;
            }
            $out[] = $val;
        }

        return array_values(array_unique($out));
    }
}
