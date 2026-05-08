<?php
/**
 * Command DTO: submeter cadastro à análise (TD-05 rascunho -> submetido).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class SubmeterCadastroCommand
{
    private int $agenteId;
    private int $userId;

    /** @var array<int,string> */
    private array $finalidadesAceitas;

    /** @var array<int,string> */
    private array $finalidadesNegadas;

    private string $ipAddress;
    private ?string $userAgent;

    /**
     * @param array{finalidades_aceitas?: array<int,string>, finalidades_negadas?: array<int,string>} $consentimentos
     */
    public function __construct(
        int $agenteId,
        int $userId,
        array $consentimentos,
        string $ipAddress,
        ?string $userAgent
    ) {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('SubmeterCadastroCommand: agenteId deve ser positivo.');
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('SubmeterCadastroCommand: userId deve ser positivo.');
        }

        $aceitas = isset($consentimentos['finalidades_aceitas']) && is_array($consentimentos['finalidades_aceitas'])
            ? self::normalize($consentimentos['finalidades_aceitas'])
            : [];
        $negadas = isset($consentimentos['finalidades_negadas']) && is_array($consentimentos['finalidades_negadas'])
            ? self::normalize($consentimentos['finalidades_negadas'])
            : [];
        $intersect = array_intersect($aceitas, $negadas);
        if ($intersect !== []) {
            throw new InvalidArgumentException(sprintf(
                'SubmeterCadastroCommand: finalidades aparecem em ambos os lados: %s.',
                implode(', ', $intersect)
            ));
        }

        $this->agenteId           = $agenteId;
        $this->userId             = $userId;
        $this->finalidadesAceitas = $aceitas;
        $this->finalidadesNegadas = $negadas;
        $this->ipAddress          = trim($ipAddress);
        $this->userAgent          = $userAgent !== null ? trim($userAgent) : null;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    /** @return array<int,string> */
    public function finalidadesAceitas(): array
    {
        return $this->finalidadesAceitas;
    }

    /** @return array<int,string> */
    public function finalidadesNegadas(): array
    {
        return $this->finalidadesNegadas;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
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
    private static function normalize(array $list): array
    {
        $out = [];
        foreach ($list as $v) {
            if (!is_string($v)) {
                continue;
            }
            $val = strtolower(trim($v));
            if ($val === '') {
                continue;
            }
            $out[] = $val;
        }

        return array_values(array_unique($out));
    }
}
