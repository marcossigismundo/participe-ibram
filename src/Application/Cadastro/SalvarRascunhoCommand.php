<?php
/**
 * Command DTO para salvamento de rascunho do wizard (TD-04).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * DTO imutável para o caso de uso `SalvarRascunhoHandler`.
 *
 * Um único command serve para criar um rascunho novo (`agenteId = null`) e
 * para atualizar um existente (`agenteId > 0`). O wizard salva a cada passo
 * (`debounce 2s`) — o handler cuida da UPSERT atômica.
 *
 * @psalm-immutable
 */
final class SalvarRascunhoCommand
{
    public const TIPO_PF = 'PF';
    public const TIPO_OR = 'OR';
    public const TIPO_SM = 'SM';

    /** @var array<int,string> */
    private const TIPOS_VALIDOS = [self::TIPO_PF, self::TIPO_OR, self::TIPO_SM];

    private ?int $agenteId;
    private string $tipoAgente;

    /** @var array<string,mixed> */
    private array $dadosBasicos;

    /** @var array<string,mixed> */
    private array $dadosTipologia;

    /** @var array<string,array<int,string>> Mapa tipo_vocabulario => list<valor>. */
    private array $vocabulariosMultiSelect;

    /** @var array<int,array<string,mixed>> Lista de representantes (OR/SM). */
    private array $representantes;

    private int $userId;

    /**
     * @param array<string,mixed>             $dadosBasicos
     * @param array<string,mixed>             $dadosTipologia
     * @param array<string,array<int,string>> $vocabulariosMultiSelect
     * @param array<int,array<string,mixed>>  $representantes
     */
    public function __construct(
        ?int $agenteId,
        string $tipoAgente,
        array $dadosBasicos,
        array $dadosTipologia,
        array $vocabulariosMultiSelect,
        array $representantes,
        int $userId
    ) {
        if ($agenteId !== null && $agenteId <= 0) {
            throw new InvalidArgumentException('SalvarRascunhoCommand: agenteId deve ser positivo ou null.');
        }
        $tipoNorm = strtoupper(trim($tipoAgente));
        if (!in_array($tipoNorm, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf(
                'SalvarRascunhoCommand: tipoAgente invalido "%s".',
                $tipoAgente
            ));
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('SalvarRascunhoCommand: userId deve ser positivo.');
        }

        $this->agenteId                = $agenteId;
        $this->tipoAgente              = $tipoNorm;
        $this->dadosBasicos            = $dadosBasicos;
        $this->dadosTipologia          = $dadosTipologia;
        $this->vocabulariosMultiSelect = $vocabulariosMultiSelect;
        $this->representantes          = array_values($representantes);
        $this->userId                  = $userId;
    }

    public function agenteId(): ?int
    {
        return $this->agenteId;
    }

    public function tipoAgente(): string
    {
        return $this->tipoAgente;
    }

    /** @return array<string,mixed> */
    public function dadosBasicos(): array
    {
        return $this->dadosBasicos;
    }

    /** @return array<string,mixed> */
    public function dadosTipologia(): array
    {
        return $this->dadosTipologia;
    }

    /** @return array<string,array<int,string>> */
    public function vocabulariosMultiSelect(): array
    {
        return $this->vocabulariosMultiSelect;
    }

    /** @return array<int,array<string,mixed>> */
    public function representantes(): array
    {
        return $this->representantes;
    }

    public function userId(): int
    {
        return $this->userId;
    }
}
