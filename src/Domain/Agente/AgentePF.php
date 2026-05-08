<?php
/**
 * Detalhes específicos de Pessoa Física (`wp_pi_agentes_pf`).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use InvalidArgumentException;

/**
 * Sub-entidade ligada 1:1 com {@see Agente} quando `tipo = PF`.
 *
 * Imutável: alterações geram nova instância via `with*()`. Os campos `*Plain`
 * (CPF, RG, Passaporte) são apenas pré-persistência — após `save` no
 * repositório, o repositório descarta os valores em claro e persiste o
 * cifrado em `*_enc`. Reconstruir via decifragem usa o mesmo construtor.
 *
 * Os enumerados controlados (nacionalidade, faixa_etaria, identidade_genero,
 * orientacao_sexual, raca_cor, grau_instrucao, ocupacao) são strings simples
 * neste nível e validados externamente contra `wp_pi_vocabularios` (Domain
 * Vocabulario, owner D4).
 */
final class AgentePF
{
    public const PESSOA_DEFICIENCIA_SIM                  = 'sim';
    public const PESSOA_DEFICIENCIA_NAO                  = 'nao';
    public const PESSOA_DEFICIENCIA_PREFIRO_NAO_INFORMAR = 'prefiro_nao_informar';

    /** @var array<int,string> */
    private const PESSOA_DEFICIENCIA_VALUES = [
        self::PESSOA_DEFICIENCIA_SIM,
        self::PESSOA_DEFICIENCIA_NAO,
        self::PESSOA_DEFICIENCIA_PREFIRO_NAO_INFORMAR,
    ];

    private int $agenteId;
    private string $nomeCompleto;
    private ?string $nomeSocial;

    /**
     * CPF em claro — usado APENAS antes de persistir / após decifrar.
     * O repositório nunca reflete este campo de volta após save (deve-se
     * descartar a referência).
     */
    private ?string $cpfPlain;
    private ?string $rgPlain;
    private ?string $passaportePlain;

    private ?string $nacionalidade;
    private ?string $faixaEtaria;
    private ?string $identidadeGenero;
    private ?string $orientacaoSexual;
    private ?string $racaCor;
    private string $pessoaDeficiencia;
    private ?string $deficienciaDescricao;
    private ?string $recursosAcessibilidade;
    private ?string $grauInstrucao;
    private ?string $ocupacao;
    private ?string $cidadeResidencia;
    private ?string $estadoResidencia;
    private ?string $bairroResidencia;
    private ?int $organizacaoVinculadaId;
    private ?string $apresentacaoMd;

    /**
     * @throws InvalidArgumentException Quando `agenteId <= 0`, `nomeCompleto`
     *                                  vazio, `pessoaDeficiencia` fora do enum
     *                                  ou `estadoResidencia` com tamanho != 2.
     */
    public function __construct(
        int $agenteId,
        string $nomeCompleto,
        ?string $nomeSocial = null,
        ?string $cpfPlain = null,
        ?string $rgPlain = null,
        ?string $passaportePlain = null,
        ?string $nacionalidade = null,
        ?string $faixaEtaria = null,
        ?string $identidadeGenero = null,
        ?string $orientacaoSexual = null,
        ?string $racaCor = null,
        string $pessoaDeficiencia = self::PESSOA_DEFICIENCIA_PREFIRO_NAO_INFORMAR,
        ?string $deficienciaDescricao = null,
        ?string $recursosAcessibilidade = null,
        ?string $grauInstrucao = null,
        ?string $ocupacao = null,
        ?string $cidadeResidencia = null,
        ?string $estadoResidencia = null,
        ?string $bairroResidencia = null,
        ?int $organizacaoVinculadaId = null,
        ?string $apresentacaoMd = null
    ) {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AgentePF: agenteId deve ser positivo.');
        }
        $nomeCompleto = trim($nomeCompleto);
        if ($nomeCompleto === '') {
            throw new InvalidArgumentException('AgentePF: nomeCompleto nao pode ser vazio.');
        }
        if (!in_array($pessoaDeficiencia, self::PESSOA_DEFICIENCIA_VALUES, true)) {
            throw new InvalidArgumentException(sprintf(
                'AgentePF: pessoaDeficiencia invalida "%s".',
                $pessoaDeficiencia
            ));
        }
        if ($estadoResidencia !== null && strlen($estadoResidencia) !== 2) {
            throw new InvalidArgumentException(
                'AgentePF: estadoResidencia deve ter exatamente 2 caracteres (UF).'
            );
        }

        $this->agenteId               = $agenteId;
        $this->nomeCompleto           = $nomeCompleto;
        $this->nomeSocial             = $nomeSocial !== null ? trim($nomeSocial) : null;
        $this->cpfPlain               = $cpfPlain;
        $this->rgPlain                = $rgPlain;
        $this->passaportePlain        = $passaportePlain;
        $this->nacionalidade          = $nacionalidade;
        $this->faixaEtaria            = $faixaEtaria;
        $this->identidadeGenero       = $identidadeGenero;
        $this->orientacaoSexual       = $orientacaoSexual;
        $this->racaCor                = $racaCor;
        $this->pessoaDeficiencia      = $pessoaDeficiencia;
        $this->deficienciaDescricao   = $deficienciaDescricao;
        $this->recursosAcessibilidade = $recursosAcessibilidade;
        $this->grauInstrucao          = $grauInstrucao;
        $this->ocupacao               = $ocupacao;
        $this->cidadeResidencia       = $cidadeResidencia;
        $this->estadoResidencia       = $estadoResidencia;
        $this->bairroResidencia       = $bairroResidencia;
        $this->organizacaoVinculadaId = $organizacaoVinculadaId;
        $this->apresentacaoMd         = $apresentacaoMd;
    }

    public function getAgenteId(): int
    {
        return $this->agenteId;
    }

    public function getNomeCompleto(): string
    {
        return $this->nomeCompleto;
    }

    public function getNomeSocial(): ?string
    {
        return $this->nomeSocial;
    }

    public function getCpfPlain(): ?string
    {
        return $this->cpfPlain;
    }

    public function getRgPlain(): ?string
    {
        return $this->rgPlain;
    }

    public function getPassaportePlain(): ?string
    {
        return $this->passaportePlain;
    }

    public function getNacionalidade(): ?string
    {
        return $this->nacionalidade;
    }

    public function getFaixaEtaria(): ?string
    {
        return $this->faixaEtaria;
    }

    public function getIdentidadeGenero(): ?string
    {
        return $this->identidadeGenero;
    }

    public function getOrientacaoSexual(): ?string
    {
        return $this->orientacaoSexual;
    }

    public function getRacaCor(): ?string
    {
        return $this->racaCor;
    }

    public function getPessoaDeficiencia(): string
    {
        return $this->pessoaDeficiencia;
    }

    public function getDeficienciaDescricao(): ?string
    {
        return $this->deficienciaDescricao;
    }

    public function getRecursosAcessibilidade(): ?string
    {
        return $this->recursosAcessibilidade;
    }

    public function getGrauInstrucao(): ?string
    {
        return $this->grauInstrucao;
    }

    public function getOcupacao(): ?string
    {
        return $this->ocupacao;
    }

    public function getCidadeResidencia(): ?string
    {
        return $this->cidadeResidencia;
    }

    public function getEstadoResidencia(): ?string
    {
        return $this->estadoResidencia;
    }

    public function getBairroResidencia(): ?string
    {
        return $this->bairroResidencia;
    }

    public function getOrganizacaoVinculadaId(): ?int
    {
        return $this->organizacaoVinculadaId;
    }

    public function getApresentacaoMd(): ?string
    {
        return $this->apresentacaoMd;
    }

    /**
     * Devolve uma cópia sem segredos em claro (CPF/RG/Passaporte zerados em
     * memória). Útil para evitar reaproveitamento acidental após persistência.
     *
     * @return self Nova instância sem `*Plain` populados.
     */
    public function withoutPlainSecrets(): self
    {
        $copy = clone $this;
        // Apaga referências; sodium_memzero exige passar por referência e o
        // string é imutável aqui — mas nullificar evita persistência indevida.
        $copy->cpfPlain        = null;
        $copy->rgPlain         = null;
        $copy->passaportePlain = null;

        return $copy;
    }

    /**
     * Substitui o agenteId (após primeira persistência do `Agente`).
     */
    public function withAgenteId(int $agenteId): self
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AgentePF: agenteId deve ser positivo.');
        }
        $copy = clone $this;
        $copy->agenteId = $agenteId;

        return $copy;
    }
}
