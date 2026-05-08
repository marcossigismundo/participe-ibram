<?php
/**
 * Detalhes específicos de Sistema de Museu / Secretaria (`wp_pi_agentes_sm`).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use InvalidArgumentException;

/**
 * Sub-entidade ligada 1:1 com {@see Agente} quando `tipo = SM`.
 *
 * Aplica-se à alínea d da Portaria 3230/2024 (Sistemas de Museus / Secretarias
 * de Cultura ou Turismo etc.).
 *
 * O CPF do representante legal (`representanteCpfPlain`) só vive antes da
 * persistência ou após decifragem.
 */
final class AgenteSM
{
    public const ESFERA_FEDERAL    = 'federal';
    public const ESFERA_ESTADUAL   = 'estadual';
    public const ESFERA_DISTRITAL  = 'distrital';
    public const ESFERA_MUNICIPAL  = 'municipal';
    public const ESFERA_REGIONAL   = 'regional';

    public const TIPO_ORGAO_SISTEMA_MUSEUS       = 'sistema_museus';
    public const TIPO_ORGAO_SECRETARIA_CULTURA   = 'secretaria_cultura';
    public const TIPO_ORGAO_SECRETARIA_TURISMO   = 'secretaria_turismo';
    public const TIPO_ORGAO_OUTRO                = 'outro';

    /** @var array<int,string> */
    private const ESFERA_VALUES = [
        self::ESFERA_FEDERAL,
        self::ESFERA_ESTADUAL,
        self::ESFERA_DISTRITAL,
        self::ESFERA_MUNICIPAL,
        self::ESFERA_REGIONAL,
    ];

    /** @var array<int,string> */
    private const TIPO_ORGAO_VALUES = [
        self::TIPO_ORGAO_SISTEMA_MUSEUS,
        self::TIPO_ORGAO_SECRETARIA_CULTURA,
        self::TIPO_ORGAO_SECRETARIA_TURISMO,
        self::TIPO_ORGAO_OUTRO,
    ];

    private int $agenteId;
    private string $nomeOrgao;
    private string $esfera;
    private string $tipoOrgao;
    private ?string $uf;
    private ?string $municipio;
    private ?string $leiInstituicao;
    private ?int $anoLei;
    private string $representanteLegalNome;
    private ?string $representanteLegalCargo;
    private ?string $representanteCpfPlain;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $agenteId,
        string $nomeOrgao,
        string $esfera,
        string $tipoOrgao,
        string $representanteLegalNome,
        ?string $uf = null,
        ?string $municipio = null,
        ?string $leiInstituicao = null,
        ?int $anoLei = null,
        ?string $representanteLegalCargo = null,
        ?string $representanteCpfPlain = null
    ) {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AgenteSM: agenteId deve ser positivo.');
        }
        $nomeOrgao = trim($nomeOrgao);
        if ($nomeOrgao === '') {
            throw new InvalidArgumentException('AgenteSM: nomeOrgao nao pode ser vazio.');
        }
        $representanteLegalNome = trim($representanteLegalNome);
        if ($representanteLegalNome === '') {
            throw new InvalidArgumentException(
                'AgenteSM: representanteLegalNome nao pode ser vazio.'
            );
        }
        if (!in_array($esfera, self::ESFERA_VALUES, true)) {
            throw new InvalidArgumentException(sprintf(
                'AgenteSM: esfera invalida "%s".',
                $esfera
            ));
        }
        if (!in_array($tipoOrgao, self::TIPO_ORGAO_VALUES, true)) {
            throw new InvalidArgumentException(sprintf(
                'AgenteSM: tipoOrgao invalido "%s".',
                $tipoOrgao
            ));
        }
        if ($uf !== null && strlen($uf) !== 2) {
            throw new InvalidArgumentException('AgenteSM: uf deve ter exatamente 2 caracteres.');
        }
        if ($anoLei !== null && ($anoLei < 1500 || $anoLei > 2200)) {
            throw new InvalidArgumentException('AgenteSM: anoLei fora de intervalo razoavel.');
        }

        $this->agenteId                = $agenteId;
        $this->nomeOrgao               = $nomeOrgao;
        $this->esfera                  = $esfera;
        $this->tipoOrgao               = $tipoOrgao;
        $this->uf                      = $uf;
        $this->municipio               = $municipio;
        $this->leiInstituicao          = $leiInstituicao;
        $this->anoLei                  = $anoLei;
        $this->representanteLegalNome  = $representanteLegalNome;
        $this->representanteLegalCargo = $representanteLegalCargo;
        $this->representanteCpfPlain   = $representanteCpfPlain;
    }

    public function getAgenteId(): int
    {
        return $this->agenteId;
    }

    public function getNomeOrgao(): string
    {
        return $this->nomeOrgao;
    }

    public function getEsfera(): string
    {
        return $this->esfera;
    }

    public function getTipoOrgao(): string
    {
        return $this->tipoOrgao;
    }

    public function getUf(): ?string
    {
        return $this->uf;
    }

    public function getMunicipio(): ?string
    {
        return $this->municipio;
    }

    public function getLeiInstituicao(): ?string
    {
        return $this->leiInstituicao;
    }

    public function getAnoLei(): ?int
    {
        return $this->anoLei;
    }

    public function getRepresentanteLegalNome(): string
    {
        return $this->representanteLegalNome;
    }

    public function getRepresentanteLegalCargo(): ?string
    {
        return $this->representanteLegalCargo;
    }

    public function getRepresentanteCpfPlain(): ?string
    {
        return $this->representanteCpfPlain;
    }

    public function withoutPlainSecrets(): self
    {
        $copy = clone $this;
        $copy->representanteCpfPlain = null;

        return $copy;
    }

    public function withAgenteId(int $agenteId): self
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AgenteSM: agenteId deve ser positivo.');
        }
        $copy = clone $this;
        $copy->agenteId = $agenteId;

        return $copy;
    }
}
