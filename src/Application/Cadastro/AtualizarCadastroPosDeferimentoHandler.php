<?php
/**
 * Handler: edição limitada do cadastro por estado (Área autenticada do agente).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use InvalidArgumentException;

/**
 * Aplica diff mínimo ao cadastro do próprio agente conforme a whitelist por status:
 *
 *  - RASCUNHO                              → fluxo é via SalvarRascunhoHandler (NÃO neste handler).
 *  - SUBMETIDO / EM_ANALISE                → BLOQUEADO (lança DomainException 'cadastro_em_analise').
 *  - DEFERIDO / DEFERIDO_EM_RETRATACAO /
 *    DEFERIDO_EM_RECURSO                   → permite: email_principal, telefone,
 *                                            cidade_residencia, estado_residencia,
 *                                            bairro_residencia, cidade_sede, estado_sede,
 *                                            bairro_sede, nome_social (apenas PF).
 *                                            BLOQUEADO: CPF, CNPJ, RG, passaporte,
 *                                            nome_completo, nome_organizacao,
 *                                            nome_orgao, tipo, numero_registro.
 *  - INDEFERIDO_AGUARDANDO_RECURSO /
 *    EM_RETRATACAO / EM_RECURSO_PRESIDENCIA→ BLOQUEADO ('cadastro_em_recurso').
 *  - INDEFERIDO_FINAL                      → BLOQUEADO ('cadastro_finalizado').
 *
 * Persistência:
 *  - Lê estado atual via {@see AgenteRepository::findById()}.
 *  - Carrega detalhes tipológicos + representantes via {@see AgenteDetalhesLoader}
 *    para reaproveitar `save()` (re-cifragem de campos sensíveis é responsabilidade
 *    do repositório através de {@see \Ibram\ParticipeIbram\Core\Encryption\SodiumCipher}).
 *  - Audita diff campo-a-campo: payload mascarado por {@see PiiMasker}.
 *  - Dispara `pi_minha_conta_dados_atualizados` com `[agenteId, camposAlterados]`.
 */
final class AtualizarCadastroPosDeferimentoHandler
{
    /**
     * Campos editáveis após deferimento (qualquer das três variações).
     *
     * Coberturas:
     *  - dados básicos: email, telefone
     *  - endereço/sede: cidade, estado, bairro
     *  - apresentação (markdown público)
     *  - nome_social (apenas PF)
     *
     * @var array<int,string>
     */
    public const CAMPOS_EDITAVEIS_POS_DEFERIMENTO = [
        'email_principal',
        'telefone',
        'nome_social',
        'cidade_residencia',
        'estado_residencia',
        'bairro_residencia',
        'cidade_sede',
        'estado_sede',
        'bairro_sede',
        'apresentacao_md',
        'recursos_acessibilidade',
    ];

    /**
     * Campos sensíveis sob `wp_pi_agentes_pf` / `_or` que NUNCA são editáveis
     * pós-deferimento. Lista usada para validação defensiva mesmo se chegar
     * por engano.
     *
     * @var array<int,string>
     */
    public const CAMPOS_SENSIVEIS_BLOQUEADOS = [
        'cpf',
        'rg',
        'passaporte',
        'cnpj',
        'representante_cpf',
        'nome_completo',
        'nome_organizacao',
        'nome_orgao',
        'tipo',
        'numero_registro',
        'status_cadastro',
        'user_id',
    ];

    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhesLoader;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhesLoader,
        AuditLogger $audit
    ) {
        $this->agentes        = $agentes;
        $this->detalhesLoader = $detalhesLoader;
        $this->audit          = $audit;
    }

    /**
     * @return array<string,mixed> Diff aplicado (camposAlterados, antes mascarado, depois mascarado).
     *
     * @throws DomainException        Quando o status atual não permite edição.
     * @throws InvalidArgumentException Quando um campo enviado é bloqueado pelo estado.
     */
    public function handle(AtualizarCadastroPosDeferimentoCommand $command): array
    {
        $agente = $this->agentes->findById($command->agenteId());
        if ($agente === null) {
            throw new DomainException('Agente não encontrado.');
        }

        $status = $agente->getStatusCadastro();
        self::guardEstadoEditavel($status);

        $editaveis  = self::camposEditaveisPara($status, $agente->getTipo());
        $dadosLimp  = self::normalizarDados($command->dados(), $editaveis);

        // Carrega detalhes atuais para preservar tudo o que NÃO está sendo editado.
        $detalhes       = $this->detalhesLoader->loadDetalhes($command->agenteId(), $agente->getTipo()->value());
        $representantes = $this->detalhesLoader->loadRepresentantes($command->agenteId());

        // Aplica diff mínimo no agregado raiz (email/telefone).
        $emailAntes    = $agente->getEmailPrincipal();
        $telefoneAntes = $agente->getTelefone();
        $emailDepois   = array_key_exists('email_principal', $dadosLimp)
            ? (string) $dadosLimp['email_principal']
            : $emailAntes;
        $telefoneDepois = array_key_exists('telefone', $dadosLimp)
            ? ($dadosLimp['telefone'] === null ? null : (string) $dadosLimp['telefone'])
            : $telefoneAntes;

        if ($emailDepois === '') {
            throw new InvalidArgumentException('email_principal não pode ser vazio.');
        }

        $novoAgente = new Agente(
            $agente->getId(),
            $agente->getTipo(),
            $agente->getNumeroRegistro(),
            $agente->getStatusCadastro(),
            $agente->getUserId(),
            $emailDepois,
            $telefoneDepois,
            $agente->getSubmetidoEm(),
            $agente->getDeferidoEm(),
            $agente->getPublicadoEm(),
            $agente->getCreatedAt(),
            new \DateTimeImmutable('now'),
            $agente->getDeletedAt()
        );

        // Aplica diff nos detalhes tipológicos.
        [$novoDetalhes, $detalhesAntes, $detalhesDepois] =
            self::aplicarDiffDetalhes($detalhes, $dadosLimp, $agente->getTipo(), $editaveis);

        $this->agentes->save($novoAgente, $novoDetalhes, $representantes);

        // Monta diff campo-a-campo (apenas alterados).
        $diffAntes  = array_filter([
            'email_principal' => $emailAntes !== $emailDepois ? PiiMasker::maskEmail($emailAntes) : null,
            'telefone'        => $telefoneAntes !== $telefoneDepois && $telefoneAntes !== null
                ? PiiMasker::maskPhone($telefoneAntes)
                : null,
        ], static fn ($v) => $v !== null);
        $diffDepois = array_filter([
            'email_principal' => $emailAntes !== $emailDepois ? PiiMasker::maskEmail($emailDepois) : null,
            'telefone'        => $telefoneAntes !== $telefoneDepois && $telefoneDepois !== null
                ? PiiMasker::maskPhone($telefoneDepois)
                : null,
        ], static fn ($v) => $v !== null);

        foreach ($detalhesAntes as $campo => $valor) {
            $diffAntes[$campo] = $valor;
        }
        foreach ($detalhesDepois as $campo => $valor) {
            $diffDepois[$campo] = $valor;
        }

        $camposAlterados = array_values(array_unique(array_merge(
            array_keys($diffAntes),
            array_keys($diffDepois)
        )));

        if ($camposAlterados !== []) {
            $this->audit->log(
                'agente',
                $command->agenteId(),
                'minha_conta_atualizar',
                $diffAntes,
                $diffDepois,
                $command->userId()
            );

            if (function_exists('do_action')) {
                \do_action('pi_minha_conta_dados_atualizados', $command->agenteId(), $camposAlterados);
            }
        }

        return [
            'agente_id'        => $command->agenteId(),
            'campos_alterados' => $camposAlterados,
            'status'           => $status->value(),
        ];
    }

    /**
     * @throws DomainException
     */
    private static function guardEstadoEditavel(StatusCadastro $status): void
    {
        $valor = $status->value();

        if ($valor === StatusCadastro::RASCUNHO) {
            // Cliente deve usar SalvarRascunhoHandler — aqui é fluxo errado.
            throw new DomainException('cadastro_em_rascunho');
        }
        if ($valor === StatusCadastro::SUBMETIDO || $valor === StatusCadastro::EM_ANALISE) {
            throw new DomainException('cadastro_em_analise');
        }
        if (
            $valor === StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO
            || $valor === StatusCadastro::EM_RETRATACAO
            || $valor === StatusCadastro::EM_RECURSO_PRESIDENCIA
        ) {
            throw new DomainException('cadastro_em_recurso');
        }
        if ($valor === StatusCadastro::INDEFERIDO_FINAL) {
            throw new DomainException('cadastro_finalizado');
        }
        // Estados DEFERIDO* seguem.
    }

    /**
     * @return array<int,string>
     */
    private static function camposEditaveisPara(StatusCadastro $status, TipoAgente $tipo): array
    {
        if (!$status->isDeferido()) {
            return [];
        }
        $base = self::CAMPOS_EDITAVEIS_POS_DEFERIMENTO;
        if ($tipo->value() !== TipoAgente::PF) {
            // nome_social existe somente em PF.
            $base = array_values(array_filter($base, static fn (string $c): bool => $c !== 'nome_social'));
        }
        if ($tipo->value() === TipoAgente::PF) {
            // residencia, não sede, em PF.
            $base = array_values(array_filter($base, static fn (string $c): bool => !in_array($c, [
                'cidade_sede', 'estado_sede', 'bairro_sede',
            ], true)));
        } else {
            // sede, não residencia.
            $base = array_values(array_filter($base, static fn (string $c): bool => !in_array($c, [
                'cidade_residencia', 'estado_residencia', 'bairro_residencia',
            ], true)));
        }
        if ($tipo->value() === TipoAgente::SM) {
            // SM não tem apresentacao_md livre via Minha Conta.
            $base = array_values(array_filter($base, static fn (string $c): bool => $c !== 'apresentacao_md'));
            // Residência tampouco; mas já filtramos acima.
        }

        return $base;
    }

    /**
     * @param array<string,mixed> $dados
     * @param array<int,string>   $editaveis
     *
     * @return array<string,mixed>
     */
    private static function normalizarDados(array $dados, array $editaveis): array
    {
        $out = [];
        foreach ($dados as $campo => $valor) {
            if (!is_string($campo)) {
                continue;
            }
            $campoLower = strtolower($campo);
            if (in_array($campoLower, self::CAMPOS_SENSIVEIS_BLOQUEADOS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Campo "%s" não pode ser alterado no estado atual.',
                    $campoLower
                ));
            }
            if (!in_array($campoLower, $editaveis, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Campo "%s" não é editável no estado atual.',
                    $campoLower
                ));
            }
            $out[$campoLower] = is_string($valor) ? trim($valor) : $valor;
        }

        return $out;
    }

    /**
     * @param AgentePF|AgenteOR|AgenteSM $detalhes
     * @param array<string,mixed>        $dados
     * @param array<int,string>          $editaveis
     *
     * @return array{0:AgentePF|AgenteOR|AgenteSM,1:array<string,string>,2:array<string,string>}
     */
    private static function aplicarDiffDetalhes(
        object $detalhes,
        array $dados,
        TipoAgente $tipo,
        array $editaveis
    ): array {
        $antes  = [];
        $depois = [];
        if ($detalhes instanceof AgentePF) {
            $novo = self::pfComDiff($detalhes, $dados, $editaveis, $antes, $depois);

            return [$novo, $antes, $depois];
        }
        if ($detalhes instanceof AgenteOR) {
            $novo = self::orComDiff($detalhes, $dados, $editaveis, $antes, $depois);

            return [$novo, $antes, $depois];
        }
        if ($detalhes instanceof AgenteSM) {
            // SM: nada editável em detalhes via Minha Conta (apenas email/telefone do agregado).
            return [$detalhes, $antes, $depois];
        }
        throw new InvalidArgumentException('Tipo de agente desconhecido.');
    }

    /**
     * @param array<string,mixed> $dados
     * @param array<int,string>   $editaveis
     * @param array<string,string> $antes
     * @param array<string,string> $depois
     */
    private static function pfComDiff(
        AgentePF $detalhes,
        array $dados,
        array $editaveis,
        array &$antes,
        array &$depois
    ): AgentePF {
        $nomeSocialNovo = self::pegarOuManter('nome_social', $dados, $detalhes->getNomeSocial(), $editaveis);
        $cidadeNovo     = self::pegarOuManter('cidade_residencia', $dados, $detalhes->getCidadeResidencia(), $editaveis);
        $estadoNovo     = self::pegarOuManter('estado_residencia', $dados, $detalhes->getEstadoResidencia(), $editaveis);
        if (is_string($estadoNovo)) {
            $estadoNovo = strtoupper($estadoNovo);
        }
        $bairroNovo     = self::pegarOuManter('bairro_residencia', $dados, $detalhes->getBairroResidencia(), $editaveis);
        $apresentacaoNovo = self::pegarOuManter('apresentacao_md', $dados, $detalhes->getApresentacaoMd(), $editaveis);
        $recursosNovo   = self::pegarOuManter(
            'recursos_acessibilidade',
            $dados,
            $detalhes->getRecursosAcessibilidade(),
            $editaveis
        );

        self::registrarDiff('nome_social', $detalhes->getNomeSocial(), $nomeSocialNovo, $antes, $depois);
        self::registrarDiff('cidade_residencia', $detalhes->getCidadeResidencia(), $cidadeNovo, $antes, $depois);
        self::registrarDiff('estado_residencia', $detalhes->getEstadoResidencia(), $estadoNovo, $antes, $depois);
        self::registrarDiff('bairro_residencia', $detalhes->getBairroResidencia(), $bairroNovo, $antes, $depois);
        self::registrarDiff('apresentacao_md', $detalhes->getApresentacaoMd(), $apresentacaoNovo, $antes, $depois, true);
        self::registrarDiff(
            'recursos_acessibilidade',
            $detalhes->getRecursosAcessibilidade(),
            $recursosNovo,
            $antes,
            $depois,
            true
        );

        return new AgentePF(
            $detalhes->getAgenteId(),
            $detalhes->getNomeCompleto(),
            self::nullableString($nomeSocialNovo),
            $detalhes->getCpfPlain(),
            $detalhes->getRgPlain(),
            $detalhes->getPassaportePlain(),
            $detalhes->getNacionalidade(),
            $detalhes->getFaixaEtaria(),
            $detalhes->getIdentidadeGenero(),
            $detalhes->getOrientacaoSexual(),
            $detalhes->getRacaCor(),
            $detalhes->getPessoaDeficiencia(),
            $detalhes->getDeficienciaDescricao(),
            self::nullableString($recursosNovo),
            $detalhes->getGrauInstrucao(),
            $detalhes->getOcupacao(),
            self::nullableString($cidadeNovo),
            self::nullableString($estadoNovo),
            self::nullableString($bairroNovo),
            $detalhes->getOrganizacaoVinculadaId(),
            self::nullableString($apresentacaoNovo)
        );
    }

    /**
     * @param array<string,mixed> $dados
     * @param array<int,string>   $editaveis
     * @param array<string,string> $antes
     * @param array<string,string> $depois
     */
    private static function orComDiff(
        AgenteOR $detalhes,
        array $dados,
        array $editaveis,
        array &$antes,
        array &$depois
    ): AgenteOR {
        $cidadeNovo     = self::pegarOuManter('cidade_sede', $dados, $detalhes->getCidadeSede(), $editaveis);
        $estadoNovo     = self::pegarOuManter('estado_sede', $dados, $detalhes->getEstadoSede(), $editaveis);
        if (is_string($estadoNovo)) {
            $estadoNovo = strtoupper($estadoNovo);
        }
        $bairroNovo     = self::pegarOuManter('bairro_sede', $dados, $detalhes->getBairroSede(), $editaveis);
        $apresentacaoNovo = self::pegarOuManter('apresentacao_md', $dados, $detalhes->getApresentacaoMd(), $editaveis);

        self::registrarDiff('cidade_sede', $detalhes->getCidadeSede(), $cidadeNovo, $antes, $depois);
        self::registrarDiff('estado_sede', $detalhes->getEstadoSede(), $estadoNovo, $antes, $depois);
        self::registrarDiff('bairro_sede', $detalhes->getBairroSede(), $bairroNovo, $antes, $depois);
        self::registrarDiff('apresentacao_md', $detalhes->getApresentacaoMd(), $apresentacaoNovo, $antes, $depois, true);

        return new AgenteOR(
            $detalhes->getAgenteId(),
            $detalhes->getNomeOrganizacao(),
            $detalhes->getTemCnpj(),
            $detalhes->getCnpjPlain(),
            $detalhes->getTipoColetivo(),
            $detalhes->getAbrangencia(),
            self::nullableString($cidadeNovo),
            self::nullableString($estadoNovo),
            self::nullableString($bairroNovo),
            self::nullableString($apresentacaoNovo),
            $detalhes->getEstruturaGovernancaMd(),
            $detalhes->getDataFundacao()
        );
    }

    /**
     * @param array<string,mixed> $dados
     * @param array<int,string>   $editaveis
     * @return mixed
     */
    private static function pegarOuManter(string $campo, array $dados, $atual, array $editaveis)
    {
        if (!in_array($campo, $editaveis, true)) {
            return $atual;
        }
        if (!array_key_exists($campo, $dados)) {
            return $atual;
        }
        $v = $dados[$campo];

        return $v === '' ? null : $v;
    }

    /**
     * @param mixed                $antes
     * @param mixed                $depois
     * @param array<string,string> $antesArr
     * @param array<string,string> $depoisArr
     * @param bool                 $longText Se true, registra apenas hash/tamanho ao invés do valor.
     */
    private static function registrarDiff(
        string $campo,
        $antes,
        $depois,
        array &$antesArr,
        array &$depoisArr,
        bool $longText = false
    ): void {
        if ($antes === $depois) {
            return;
        }
        if ($longText) {
            $antesArr[$campo]  = '[len:' . (is_string($antes) ? strlen($antes) : 0) . ']';
            $depoisArr[$campo] = '[len:' . (is_string($depois) ? strlen($depois) : 0) . ']';

            return;
        }
        $antesArr[$campo]  = $antes === null ? '[null]' : (string) $antes;
        $depoisArr[$campo] = $depois === null ? '[null]' : (string) $depois;
    }

    /**
     * @param mixed $v
     */
    private static function nullableString($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
