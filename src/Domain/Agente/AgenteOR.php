<?php
/**
 * Detalhes específicos de Organização (`wp_pi_agentes_or`).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Sub-entidade ligada 1:1 com {@see Agente} quando `tipo = OR`.
 * Engloba PJ formal (com CNPJ) e Coletivo sem CNPJ (Portaria 3230 alíneas a/c).
 *
 * O CNPJ em claro (`cnpjPlain`) só vive antes da persistência ou após
 * decifragem; após save no repositório o campo deve ser descartado via
 * {@see withoutPlainSecrets()}.
 */
final class AgenteOR
{
    public const TEM_CNPJ_SIM = 'sim';
    public const TEM_CNPJ_NAO = 'nao';

    /** @var array<int,string> */
    private const TEM_CNPJ_VALUES = [self::TEM_CNPJ_SIM, self::TEM_CNPJ_NAO];

    private int $agenteId;
    private string $nomeOrganizacao;
    private string $temCnpj;
    private ?string $cnpjPlain;
    private ?string $tipoColetivo;
    private ?string $abrangencia;
    private ?string $cidadeSede;
    private ?string $estadoSede;
    private ?string $bairroSede;
    private ?string $apresentacaoMd;
    private ?string $estruturaGovernancaMd;
    private ?DateTimeImmutable $dataFundacao;

    /**
     * @throws InvalidArgumentException Quando invariantes básicos falham.
     */
    public function __construct(
        int $agenteId,
        string $nomeOrganizacao,
        string $temCnpj,
        ?string $cnpjPlain = null,
        ?string $tipoColetivo = null,
        ?string $abrangencia = null,
        ?string $cidadeSede = null,
        ?string $estadoSede = null,
        ?string $bairroSede = null,
        ?string $apresentacaoMd = null,
        ?string $estruturaGovernancaMd = null,
        ?DateTimeImmutable $dataFundacao = null
    ) {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AgenteOR: agenteId deve ser positivo.');
        }
        $nomeOrganizacao = trim($nomeOrganizacao);
        if ($nomeOrganizacao === '') {
            throw new InvalidArgumentException('AgenteOR: nomeOrganizacao nao pode ser vazio.');
        }
        if (!in_array($temCnpj, self::TEM_CNPJ_VALUES, true)) {
            throw new InvalidArgumentException(sprintf(
                'AgenteOR: temCnpj invalido "%s".',
                $temCnpj
            ));
        }
        if ($temCnpj === self::TEM_CNPJ_NAO && $cnpjPlain !== null && $cnpjPlain !== '') {
            // Inconsistência lógica: marcou "não tem CNPJ" mas mandou CNPJ.
            throw new InvalidArgumentException(
                'AgenteOR: cnpj fornecido com temCnpj="nao".'
            );
        }
        if ($estadoSede !== null && strlen($estadoSede) !== 2) {
            throw new InvalidArgumentException(
                'AgenteOR: estadoSede deve ter exatamente 2 caracteres (UF).'
            );
        }
        if ($apresentacaoMd !== null && strlen($apresentacaoMd) > 3000) {
            throw new InvalidArgumentException('AgenteOR: apresentacaoMd excede 3000 caracteres.');
        }
        if ($estruturaGovernancaMd !== null && strlen($estruturaGovernancaMd) > 3000) {
            throw new InvalidArgumentException(
                'AgenteOR: estruturaGovernancaMd excede 3000 caracteres.'
            );
        }

        $this->agenteId               = $agenteId;
        $this->nomeOrganizacao        = $nomeOrganizacao;
        $this->temCnpj                = $temCnpj;
        $this->cnpjPlain              = $cnpjPlain;
        $this->tipoColetivo           = $tipoColetivo;
        $this->abrangencia            = $abrangencia;
        $this->cidadeSede             = $cidadeSede;
        $this->estadoSede             = $estadoSede;
        $this->bairroSede             = $bairroSede;
        $this->apresentacaoMd         = $apresentacaoMd;
        $this->estruturaGovernancaMd  = $estruturaGovernancaMd;
        $this->dataFundacao           = $dataFundacao;
    }

    public function getAgenteId(): int
    {
        return $this->agenteId;
    }

    public function getNomeOrganizacao(): string
    {
        return $this->nomeOrganizacao;
    }

    public function getTemCnpj(): string
    {
        return $this->temCnpj;
    }

    public function getCnpjPlain(): ?string
    {
        return $this->cnpjPlain;
    }

    public function getTipoColetivo(): ?string
    {
        return $this->tipoColetivo;
    }

    public function getAbrangencia(): ?string
    {
        return $this->abrangencia;
    }

    public function getCidadeSede(): ?string
    {
        return $this->cidadeSede;
    }

    public function getEstadoSede(): ?string
    {
        return $this->estadoSede;
    }

    public function getBairroSede(): ?string
    {
        return $this->bairroSede;
    }

    public function getApresentacaoMd(): ?string
    {
        return $this->apresentacaoMd;
    }

    public function getEstruturaGovernancaMd(): ?string
    {
        return $this->estruturaGovernancaMd;
    }

    public function getDataFundacao(): ?DateTimeImmutable
    {
        return $this->dataFundacao;
    }

    /**
     * Cópia sem segredo em claro.
     */
    public function withoutPlainSecrets(): self
    {
        $copy = clone $this;
        $copy->cnpjPlain = null;

        return $copy;
    }

    public function withAgenteId(int $agenteId): self
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AgenteOR: agenteId deve ser positivo.');
        }
        $copy = clone $this;
        $copy->agenteId = $agenteId;

        return $copy;
    }
}
