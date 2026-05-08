<?php
/**
 * Listeners de hooks WP que enfileiram e-mails (subscribers de domínio).
 *
 * @package Ibram\ParticipeIbram\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Email\Templates\UnsubscribeTokenizer;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Email\EventoEmail;
use Throwable;

/**
 * Liga os hooks `pi_*` (disparados pelos handlers de domínio) ao
 * {@see EnfileirarEmailHandler}.
 *
 * Cada handler recebe os parâmetros do hook, monta `vars` mínimas (SEM PII
 * sensível — apenas nome e número de registro quando aplicável) e enfileira.
 *
 * Os hooks são intencionalmente desacoplados — domínio dispara hook, o e-mail
 * é uma "consequência" registrada por este listener. Permite desligar e-mails
 * em testes apenas pelo container.
 */
final class EventListeners
{
    private EnfileirarEmailHandler $enfileirar;
    private AgenteRepository $agentes;
    private ?AgenteDetalhesLoader $detalhes;
    private UnsubscribeTokenizer $tokenizer;
    private SecureLogger $logger;

    /**
     * URL base do site (ex.: 'https://museus.gov.br'). Computada via home_url().
     */
    private string $homeUrl;

    public function __construct(
        EnfileirarEmailHandler $enfileirar,
        AgenteRepository $agentes,
        UnsubscribeTokenizer $tokenizer,
        SecureLogger $logger,
        ?AgenteDetalhesLoader $detalhes = null,
        string $homeUrl = ''
    ) {
        $this->enfileirar = $enfileirar;
        $this->agentes    = $agentes;
        $this->detalhes   = $detalhes;
        $this->tokenizer  = $tokenizer;
        $this->logger     = $logger;
        $this->homeUrl    = $homeUrl !== '' ? $homeUrl : (function_exists('home_url') ? (string) \home_url('/') : '/');
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        // Wave 3
        \add_action('pi_cadastro_submetido', [$this, 'onCadastroSubmetido'], 10, 2);
        \add_action('pi_recurso_protocolado', [$this, 'onRecursoProtocolado'], 10, 3);

        // Wave 4-A (admin cadastros)
        \add_action('pi_cadastro_deferido', [$this, 'onCadastroDeferido'], 10, 3);
        \add_action('pi_cadastro_indeferido', [$this, 'onCadastroIndeferido'], 10, 2);

        // Wave 4-B (admin recursos)
        \add_action('pi_recurso_decidido', [$this, 'onRecursoDecidido'], 10, 3);
        \add_action('pi_recurso_prazo_warning', [$this, 'onRecursoPrazoWarning'], 10, 2);

        // Wave 5/6
        \add_action('pi_edital_publicado', [$this, 'onEditalPublicado'], 10, 2);
        \add_action('pi_inscricoes_abertas', [$this, 'onInscricaoRecebida'], 10, 3);
        \add_action('pi_habilitacao_decidida', [$this, 'onHabilitacaoDecidida'], 10, 3);
        \add_action('pi_votacao_aberta', [$this, 'onVotacaoAberta'], 10, 2);
        \add_action('pi_resultado_publicado', [$this, 'onResultadoPublicado'], 10, 2);
    }

    /* =====================================================================
     * Handlers individuais (cadastros + recursos)
     * ===================================================================== */

    public function onCadastroSubmetido(int $agenteId, ?string $protocolo = null): void
    {
        $this->dispatchIndividual(
            EventoEmail::CADASTRO_SUBMETIDO,
            $agenteId,
            static function (string $nome): array {
                return [
                    'nome'           => $nome,
                    'data_submissao' => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
                ];
            }
        );
    }

    public function onCadastroDeferido(int $agenteId, string $numeroRegistro, int $analiseId): void
    {
        unset($analiseId);
        $this->dispatchIndividual(
            EventoEmail::CADASTRO_DEFERIDO,
            $agenteId,
            function (string $nome) use ($numeroRegistro): array {
                return [
                    'nome'             => $nome,
                    'numero_registro'  => $numeroRegistro,
                ];
            }
        );
    }

    public function onCadastroIndeferido(int $agenteId, ?array $payload = null): void
    {
        unset($payload);
        $this->dispatchIndividual(
            EventoEmail::CADASTRO_INDEFERIDO,
            $agenteId,
            static function (string $nome): array {
                return [
                    'nome'                => $nome,
                    'prazo_recurso'       => '10 dias corridos',
                    'data_limite_recurso' => (new DateTimeImmutable('+10 days'))->format('d/m/Y'),
                ];
            }
        );
    }

    public function onRecursoProtocolado(int $agenteId, ?int $recursoId = null, ?string $protocolo = null): void
    {
        $this->dispatchIndividual(
            EventoEmail::RECURSO_PROTOCOLADO,
            $agenteId,
            static function (string $nome) use ($protocolo): array {
                return [
                    'nome'             => $nome,
                    'numero_protocolo' => $protocolo ?? '',
                    'data_protocolo'   => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
                ];
            }
        );
    }

    public function onRecursoDecidido(int $agenteId, string $decisao, ?array $payload = null): void
    {
        $instancia = '';
        $numero    = '';
        if (is_array($payload)) {
            $instancia = isset($payload['instancia']) ? (string) $payload['instancia'] : '';
            $numero    = isset($payload['numero_registro']) ? (string) $payload['numero_registro'] : '';
        }
        $this->dispatchIndividual(
            EventoEmail::RECURSO_DECIDIDO,
            $agenteId,
            static function (string $nome) use ($decisao, $instancia, $numero): array {
                return [
                    'nome'            => $nome,
                    'decisao'         => $decisao,
                    'instancia'       => $instancia,
                    'numero_registro' => $numero,
                ];
            }
        );
    }

    public function onRecursoPrazoWarning(int $agenteId, ?int $diasRestantes = null): void
    {
        $this->dispatchIndividual(
            EventoEmail::RECURSO_PRAZO_WARNING,
            $agenteId,
            static function (string $nome) use ($diasRestantes): array {
                $dias = $diasRestantes !== null ? max(0, $diasRestantes) : 2;
                return [
                    'nome'           => $nome,
                    'dias_restantes' => (string) $dias,
                    'data_limite'    => (new DateTimeImmutable('+' . $dias . ' days'))->format('d/m/Y'),
                ];
            }
        );
    }

    public function onInscricaoRecebida(int $agenteId, int $editalId, ?array $payload = null): void
    {
        unset($editalId);
        $titulo = is_array($payload) && isset($payload['edital_titulo']) ? (string) $payload['edital_titulo'] : '';
        $vaga   = is_array($payload) && isset($payload['vaga']) ? (string) $payload['vaga'] : '';
        $this->dispatchIndividual(
            EventoEmail::INSCRICAO_RECEBIDA,
            $agenteId,
            static function (string $nome) use ($titulo, $vaga): array {
                return [
                    'nome'          => $nome,
                    'edital_titulo' => $titulo,
                    'vaga'          => $vaga,
                ];
            }
        );
    }

    public function onHabilitacaoDecidida(int $agenteId, string $decisao, ?array $payload = null): void
    {
        $titulo = is_array($payload) && isset($payload['edital_titulo']) ? (string) $payload['edital_titulo'] : '';
        $this->dispatchIndividual(
            EventoEmail::HABILITACAO_DECIDIDA,
            $agenteId,
            static function (string $nome) use ($titulo, $decisao): array {
                return [
                    'nome'          => $nome,
                    'decisao'       => $decisao,
                    'edital_titulo' => $titulo,
                ];
            }
        );
    }

    /* =====================================================================
     * Handlers broadcast
     * ===================================================================== */

    public function onEditalPublicado(int $editalId, ?array $payload = null): void
    {
        unset($editalId);
        $vars = [
            'edital_titulo'      => is_array($payload) && isset($payload['titulo']) ? (string) $payload['titulo'] : '',
            'edital_resumo'      => is_array($payload) && isset($payload['resumo']) ? (string) $payload['resumo'] : '',
            'periodo_inscricao'  => is_array($payload) && isset($payload['periodo']) ? (string) $payload['periodo'] : '',
            'edital_url'         => is_array($payload) && isset($payload['url']) ? (string) $payload['url'] : $this->homeUrl,
            'unsubscribe_url'    => '',
            'dpo_email'          => 'encarregado@museus.gov.br',
        ];
        $this->dispatchBroadcast(EventoEmail::EDITAL_PUBLICADO, $vars);
    }

    public function onVotacaoAberta(int $votacaoId, ?array $payload = null): void
    {
        unset($votacaoId);
        $vars = [
            'edital_titulo'    => is_array($payload) && isset($payload['titulo']) ? (string) $payload['titulo'] : '',
            'periodo_votacao'  => is_array($payload) && isset($payload['periodo']) ? (string) $payload['periodo'] : '',
            'votar_url'        => is_array($payload) && isset($payload['url']) ? (string) $payload['url'] : $this->homeUrl,
            'unsubscribe_url'  => '',
            'dpo_email'        => 'encarregado@museus.gov.br',
        ];
        $this->dispatchBroadcast(EventoEmail::VOTACAO_ABERTA, $vars);
    }

    public function onResultadoPublicado(int $editalId, ?array $payload = null): void
    {
        unset($editalId);
        $vars = [
            'edital_titulo'   => is_array($payload) && isset($payload['titulo']) ? (string) $payload['titulo'] : '',
            'resultado_url'   => is_array($payload) && isset($payload['url']) ? (string) $payload['url'] : $this->homeUrl,
            'unsubscribe_url' => '',
            'dpo_email'       => 'encarregado@museus.gov.br',
        ];
        $this->dispatchBroadcast(EventoEmail::RESULTADO_PUBLICADO, $vars);
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    /**
     * Carrega agente, monta vars com URL de unsubscribe e enfileira.
     *
     * @param callable(string):array<string,mixed> $varsBuilder Recebe nome -> vars de domínio.
     */
    private function dispatchIndividual(string $evento, int $agenteId, callable $varsBuilder): void
    {
        try {
            $agente = $this->agentes->findById($agenteId);
            if ($agente === null) {
                $this->logger->warning('email.listener.agente_nao_encontrado', [
                    'evento'    => $evento,
                    'agente_id' => $agenteId,
                ]);
                return;
            }
            $email = (string) $agente->getEmailPrincipal();
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('email.listener.email_invalido', [
                    'evento'    => $evento,
                    'agente_id' => $agenteId,
                    'destinatario' => PiiMasker::maskEmail($email),
                ]);
                return;
            }
            $nome = $this->resolveNome($agente);
            $vars = $varsBuilder($nome);

            // Footer comum: unsubscribe + DPO + painel.
            $vars['painel_url']      = rtrim($this->homeUrl, '/') . '/painel/';
            $vars['dpo_email']       = 'encarregado@museus.gov.br';
            $vars['unsubscribe_url'] = $this->buildUnsubscribeUrl($agenteId);

            $this->enfileirar->handle(new EnfileirarEmailCommand(
                $evento,
                $agenteId,
                $email,
                $vars
            ));
        } catch (Throwable $e) {
            $this->logger->error('email.listener.exception', [
                'evento' => $evento,
                'erro'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string,mixed> $vars
     */
    private function dispatchBroadcast(string $evento, array $vars): void
    {
        try {
            $this->enfileirar->broadcast($evento, $vars, new DateTimeImmutable('now'));
        } catch (Throwable $e) {
            $this->logger->error('email.listener.broadcast_exception', [
                'evento' => $evento,
                'erro'   => $e->getMessage(),
            ]);
        }
    }

    private function buildUnsubscribeUrl(int $agenteId): string
    {
        try {
            $token = $this->tokenizer->tokenFor(
                $agenteId,
                'comunicacao',
                (new DateTimeImmutable('now'))->modify('+30 days')
            );
        } catch (Throwable $e) {
            return '';
        }

        $base = rtrim($this->homeUrl, '/');
        $sep  = strpos($base, '?') === false ? '?' : '&';

        return $base . '/' . $sep . 'pi_action=unsubscribe&token=' . rawurlencode($token);
    }

    /**
     * Resolve "nome de exibição" do agente sem expor PII desnecessária.
     *
     *  - PF: nome social (se houver) OU primeiro nome de getNomeCompleto().
     *  - OR: nome da organização.
     *  - SM: nome do órgão.
     *
     * Em qualquer caso, retorna SOMENTE nome — nunca CPF/CNPJ.
     *
     * @param object $agente Agregado Agente do domínio.
     */
    private function resolveNome($agente): string
    {
        try {
            if ($this->detalhes === null) {
                return 'Cidadao(a)';
            }
            $tipo = method_exists($agente, 'getTipo') ? (string) $agente->getTipo() : '';
            $id   = method_exists($agente, 'getId') ? (int) $agente->getId() : 0;
            if ($id < 1 || $tipo === '') {
                return 'Cidadao(a)';
            }
            $det = $this->detalhes->loadDetalhes($id, $tipo);
            if ($det instanceof AgentePF) {
                $social = $det->getNomeSocial();
                if (is_string($social) && $social !== '') {
                    return $social;
                }
                $completo = $det->getNomeCompleto();
                $primeiro = explode(' ', trim($completo))[0] ?? '';
                return $primeiro !== '' ? $primeiro : $completo;
            }
            if ($det instanceof AgenteOR) {
                return $det->getNomeOrganizacao();
            }
            if ($det instanceof AgenteSM) {
                return $det->getNomeOrgao();
            }
        } catch (Throwable $e) {
            // ignore — fallback abaixo.
        }

        return 'Cidadao(a)';
    }
}
