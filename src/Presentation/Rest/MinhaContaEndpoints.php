<?php
/**
 * Endpoints REST da área autenticada do agente ("Minha conta").
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use DomainException;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoCommand;
use Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoHandler;
use Ibram\ParticipeIbram\Application\Cadastro\PendenciasCalculator;
use Ibram\ParticipeIbram\Core\Audit\AccessTracker;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipDeniedException;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;
use InvalidArgumentException;
use Throwable;

/**
 * Endpoints "me" da área autenticada do agente:
 *
 *  - GET   /pi/v1/me/cadastro      — dados do próprio cadastro (mascarados; `?reveal=` p/ revelar)
 *  - PATCH /pi/v1/me/cadastro      — atualiza dados com whitelist por status
 *  - GET   /pi/v1/me/dashboard     — status, próximos passos, pendências
 *
 * Garantias:
 *  - Cada endpoint resolve o agente_id SEMPRE via {@see OwnershipResolver} —
 *    nunca confia em parâmetros enviados pelo cliente.
 *  - Whitelist defensiva no GET (não devolve entidade completa via JSON).
 *  - Reveal de PII é AUDITADO em cada visualização via {@see AccessTracker}.
 *  - PATCH só aceita campos editáveis no estado atual (retorna 423/422/200
 *    conforme {@see AtualizarCadastroPosDeferimentoHandler}).
 *  - Auditoria de violação de ownership ocorre dentro de {@see OwnershipResolver}.
 */
final class MinhaContaEndpoints
{
    use RestSupport;

    private OwnershipResolver $ownership;
    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhesLoader;
    private AtualizarCadastroPosDeferimentoHandler $atualizarHandler;
    private PendenciasCalculator $pendenciasCalculator;
    private AccessTracker $accessTracker;

    public function __construct(
        OwnershipResolver $ownership,
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhesLoader,
        AtualizarCadastroPosDeferimentoHandler $atualizarHandler,
        PendenciasCalculator $pendenciasCalculator,
        AccessTracker $accessTracker
    ) {
        $this->ownership            = $ownership;
        $this->agentes              = $agentes;
        $this->detalhesLoader       = $detalhesLoader;
        $this->atualizarHandler     = $atualizarHandler;
        $this->pendenciasCalculator = $pendenciasCalculator;
        $this->accessTracker        = $accessTracker;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/me/cadastro', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getCadastro'],
                'permission_callback' => $this->permissionLoggedIn(),
                'args'                => [
                    'reveal' => [
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'patchCadastro'],
                'permission_callback' => $this->permissionLoggedIn(),
            ],
        ]);

        \register_rest_route($namespace, '/me/dashboard', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getDashboard'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);
    }

    /**
     * GET /me/cadastro
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function getCadastro(object $request)
    {
        try {
            $this->enforceRateLimit('me_cadastro_get', 60, 60);

            $userId   = $this->currentUserId();
            $agenteId = $this->ownership->currentUserAgenteId();
            if ($agenteId === null) {
                // Não revela existência de outros cadastros — apenas "não tem cadastro".
                return $this->ok(['has_cadastro' => false]);
            }

            $agente = $this->agentes->findById($agenteId);
            if ($agente === null) {
                return $this->ok(['has_cadastro' => false]);
            }

            // Defesa-em-profundidade: revalida ownership mesmo após resolver.
            $this->ownership->assertOwnership($userId, $agenteId);

            $revealRaw = $this->paramFromRequest($request, 'reveal', '');
            $revealList = self::parseRevealList((string) $revealRaw);

            $detalhes = $this->detalhesLoader->loadDetalhes($agenteId, $agente->getTipo()->value());

            $payload = $this->montarPayloadCadastro($agente, $detalhes, $revealList, $userId);

            return $this->ok($payload);
        } catch (OwnershipDeniedException $e) {
            return RestException::forbidden()->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * PATCH /me/cadastro
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function patchCadastro(object $request)
    {
        try {
            $this->enforceRateLimit('me_cadastro_patch', 20, 60);

            $userId   = $this->currentUserId();
            $agenteId = $this->ownership->currentUserAgenteId();
            if ($agenteId === null) {
                throw RestException::notFound();
            }
            // Bypass-attempts: cliente pode tentar enviar agente_id no body — ignoramos sempre.
            $this->ownership->assertOwnership($userId, $agenteId);

            $body = $this->readJsonBody($request);
            if (!is_array($body)) {
                throw RestException::validation('Corpo inválido.');
            }
            // Remove campos perigosos que o cliente nunca deveria enviar.
            unset($body['agente_id'], $body['user_id'], $body['status_cadastro'], $body['numero_registro'], $body['tipo']);

            // Sanitização superficial: trim + sanitize_text_field para strings curtas;
            // campos longos (apresentacao_md) só passam por wp_kses_post (no template, antes de exibir).
            $sanitized = self::sanitizeShallow($body);

            $command = new AtualizarCadastroPosDeferimentoCommand($agenteId, $userId, $sanitized);

            try {
                $result = $this->atualizarHandler->handle($command);
            } catch (DomainException $e) {
                $msg = $e->getMessage();
                if (
                    $msg === 'cadastro_em_analise'
                    || $msg === 'cadastro_em_recurso'
                    || $msg === 'cadastro_finalizado'
                    || $msg === 'cadastro_em_rascunho'
                ) {
                    // Estado bloqueado: 423 Locked.
                    return (new RestException(
                        self::msgEstadoBloqueado($msg),
                        'pi_locked',
                        423,
                        ['estado' => $msg]
                    ))->toResponse();
                }
                throw RestException::validation($msg);
            } catch (InvalidArgumentException $e) {
                // Campo bloqueado ou inválido para o estado atual.
                throw RestException::validation($e->getMessage());
            }

            return $this->ok($result);
        } catch (OwnershipDeniedException $e) {
            return RestException::forbidden()->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /me/dashboard
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function getDashboard(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_dashboard', 60, 60);

            $userId   = $this->currentUserId();
            $agenteId = $this->ownership->currentUserAgenteId();
            if ($agenteId === null) {
                return $this->ok([
                    'has_cadastro' => false,
                    'cta'          => [
                        'titulo' => function_exists('__')
                            ? \__('Você ainda não tem cadastro.', 'participe-ibram')
                            : 'Sem cadastro.',
                    ],
                ]);
            }
            $this->ownership->assertOwnership($userId, $agenteId);

            $agente = $this->agentes->findById($agenteId);
            if ($agente === null) {
                throw RestException::notFound();
            }

            $resumo = $this->pendenciasCalculator->paraAgente($agente);

            $numeroRegistro = null;
            $nr = $agente->getNumeroRegistro();
            if ($nr !== null) {
                $numeroRegistro = (string) $nr;
            }

            return $this->ok([
                'has_cadastro'    => true,
                'status_cadastro' => $agente->getStatusCadastro()->value(),
                'tipo'            => $agente->getTipo()->value(),
                'numero_registro' => $numeroRegistro,
                'submetido_em'    => self::dt($agente->getSubmetidoEm()),
                'deferido_em'     => self::dt($agente->getDeferidoEm()),
                'publicado_em'    => self::dt($agente->getPublicadoEm()),
                'proximos_passos' => $resumo['proximos_passos'],
                'pendencias'      => $resumo['pendencias'],
                'prazo_atual'     => self::dt($resumo['prazo_atual']),
            ]);
        } catch (OwnershipDeniedException $e) {
            return RestException::forbidden()->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /* ----------------------------------------------------------------- */

    /**
     * Whitelist defensiva por tipo. Mascara campos sensíveis a menos que estejam em $reveal.
     *
     * @param AgentePF|AgenteOR|AgenteSM $detalhes
     * @param array<int,string>          $reveal
     * @return array<string,mixed>
     */
    private function montarPayloadCadastro(
        Agente $agente,
        object $detalhes,
        array $reveal,
        int $atorId
    ): array {
        $base = [
            'has_cadastro'    => true,
            'agente_id'       => $agente->getId(),
            'tipo'            => $agente->getTipo()->value(),
            'status_cadastro' => $agente->getStatusCadastro()->value(),
            'numero_registro' => $agente->getNumeroRegistro() !== null
                ? (string) $agente->getNumeroRegistro()
                : null,
            'email_principal' => $agente->getEmailPrincipal(),
            'telefone'        => $agente->getTelefone(),
            'submetido_em'    => self::dt($agente->getSubmetidoEm()),
            'deferido_em'     => self::dt($agente->getDeferidoEm()),
            'publicado_em'    => self::dt($agente->getPublicadoEm()),
        ];

        $tipo = $agente->getTipo()->value();
        $agenteId = (int) $agente->getId();

        if ($tipo === TipoAgente::PF && $detalhes instanceof AgentePF) {
            $base['nome_completo']           = $detalhes->getNomeCompleto();
            $base['nome_social']             = $detalhes->getNomeSocial();
            $base['nacionalidade']           = $detalhes->getNacionalidade();
            $base['faixa_etaria']            = $detalhes->getFaixaEtaria();
            $base['identidade_genero']       = $detalhes->getIdentidadeGenero();
            $base['orientacao_sexual']       = $detalhes->getOrientacaoSexual();
            $base['raca_cor']                = $detalhes->getRacaCor();
            $base['pessoa_deficiencia']      = $detalhes->getPessoaDeficiencia();
            $base['deficiencia_descricao']   = $detalhes->getDeficienciaDescricao();
            $base['recursos_acessibilidade'] = $detalhes->getRecursosAcessibilidade();
            $base['grau_instrucao']          = $detalhes->getGrauInstrucao();
            $base['ocupacao']                = $detalhes->getOcupacao();
            $base['cidade_residencia']       = $detalhes->getCidadeResidencia();
            $base['estado_residencia']       = $detalhes->getEstadoResidencia();
            $base['bairro_residencia']       = $detalhes->getBairroResidencia();
            $base['apresentacao_md']         = $detalhes->getApresentacaoMd();
            $base['cpf']         = $this->maskOrReveal('cpf',        $detalhes->getCpfPlain(),        $reveal, $agenteId, $atorId);
            $base['rg']          = $this->maskOrReveal('rg',         $detalhes->getRgPlain(),         $reveal, $agenteId, $atorId);
            $base['passaporte']  = $this->maskOrReveal('passaporte', $detalhes->getPassaportePlain(), $reveal, $agenteId, $atorId);
        }

        if ($tipo === TipoAgente::OR && $detalhes instanceof AgenteOR) {
            $base['nome_organizacao']        = $detalhes->getNomeOrganizacao();
            $base['tem_cnpj']                = $detalhes->getTemCnpj();
            $base['tipo_coletivo']           = $detalhes->getTipoColetivo();
            $base['abrangencia']             = $detalhes->getAbrangencia();
            $base['cidade_sede']             = $detalhes->getCidadeSede();
            $base['estado_sede']             = $detalhes->getEstadoSede();
            $base['bairro_sede']             = $detalhes->getBairroSede();
            $base['apresentacao_md']         = $detalhes->getApresentacaoMd();
            $base['estrutura_governanca_md'] = $detalhes->getEstruturaGovernancaMd();
            $base['data_fundacao'] = $detalhes->getDataFundacao() !== null
                ? $detalhes->getDataFundacao()->format('Y-m-d')
                : null;
            $base['cnpj'] = $this->maskOrReveal('cnpj', $detalhes->getCnpjPlain(), $reveal, $agenteId, $atorId);
        }

        if ($tipo === TipoAgente::SM && $detalhes instanceof AgenteSM) {
            $base['nome_orgao']                = $detalhes->getNomeOrgao();
            $base['esfera']                    = $detalhes->getEsfera();
            $base['tipo_orgao']                = $detalhes->getTipoOrgao();
            $base['uf']                        = $detalhes->getUf();
            $base['municipio']                 = $detalhes->getMunicipio();
            $base['lei_instituicao']           = $detalhes->getLeiInstituicao();
            $base['ano_lei']                   = $detalhes->getAnoLei();
            $base['representante_legal_nome']  = $detalhes->getRepresentanteLegalNome();
            $base['representante_legal_cargo'] = $detalhes->getRepresentanteLegalCargo();
            $base['representante_cpf'] = $this->maskOrReveal(
                'representante_cpf',
                $detalhes->getRepresentanteCpfPlain(),
                $reveal,
                $agenteId,
                $atorId
            );
        }

        return $base;
    }

    /**
     * @param array<int,string> $reveal
     * @return array{value:?string,masked:bool}
     */
    private function maskOrReveal(string $campo, ?string $plain, array $reveal, int $agenteId, int $atorId): array
    {
        if ($plain === null || $plain === '') {
            return ['value' => null, 'masked' => false];
        }
        if (!in_array($campo, $reveal, true)) {
            return ['value' => self::maskValue($campo, $plain), 'masked' => true];
        }
        // Auditar cada reveal: titular pediu para ver dado próprio, mas registramos.
        if ($agenteId > 0) {
            $this->accessTracker->trackDecryption(
                self::entidadeParaCampo($campo),
                $agenteId,
                $campo,
                $atorId
            );
        }

        return ['value' => $plain, 'masked' => false];
    }

    private static function maskValue(string $campo, string $plain): string
    {
        switch ($campo) {
            case 'cpf':
            case 'representante_cpf':
                return PiiMasker::maskCpf($plain);
            case 'cnpj':
                return PiiMasker::maskCnpj($plain);
            case 'rg':
            case 'passaporte':
            default:
                return PiiMasker::maskGeneric($plain, 2, 2);
        }
    }

    private static function entidadeParaCampo(string $campo): string
    {
        if ($campo === 'cnpj') {
            return 'agente_or';
        }
        if ($campo === 'representante_cpf') {
            return 'agente_sm';
        }

        return 'agente_pf';
    }

    /**
     * @return array<int,string>
     */
    private static function parseRevealList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $allowed = ['cpf', 'rg', 'passaporte', 'cnpj', 'representante_cpf'];
        $parts   = array_map('strtolower', array_map('trim', explode(',', $raw)));

        return array_values(array_unique(array_filter(
            $parts,
            static fn (string $c): bool => in_array($c, $allowed, true)
        )));
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private static function sanitizeShallow(array $body): array
    {
        $out = [];
        foreach ($body as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (is_string($v)) {
                if (function_exists('wp_unslash')) {
                    $v = (string) \wp_unslash($v);
                }
                if (function_exists('sanitize_text_field')) {
                    $v = (string) \sanitize_text_field($v);
                } else {
                    $v = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $v) ?? '');
                }
            }
            $out[strtolower($k)] = $v;
        }

        return $out;
    }

    private static function dt(?\DateTimeImmutable $dt): ?string
    {
        return $dt !== null ? $dt->format(\DateTimeInterface::ATOM) : null;
    }

    private static function msgEstadoBloqueado(string $code): string
    {
        $map = [
            'cadastro_em_analise'   => 'Seu cadastro está em análise e não pode ser editado neste momento.',
            'cadastro_em_recurso'   => 'Há um recurso em andamento. Aguarde o desfecho para alterar dados.',
            'cadastro_finalizado'   => 'Cadastro indeferido em definitivo — não é possível editar.',
            'cadastro_em_rascunho'  => 'Use o wizard de cadastro para editar rascunhos.',
        ];

        $msg = $map[$code] ?? 'Edição bloqueada no estado atual.';

        return function_exists('__') ? \__($msg, 'participe-ibram') : $msg;
    }

    private function currentUserId(): int
    {
        return function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function paramFromRequest(object $request, string $key, $default)
    {
        if (method_exists($request, 'get_param')) {
            $v = $request->get_param($key);
            if ($v !== null) {
                return $v;
            }
        }
        if (method_exists($request, 'get_params')) {
            $params = $request->get_params();
            if (is_array($params) && isset($params[$key])) {
                return $params[$key];
            }
        }

        return $default;
    }
}
