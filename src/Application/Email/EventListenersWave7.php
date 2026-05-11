<?php
/**
 * Listeners complementares Wave 7 — hooks não cobertos por EventListeners (Wave 4-C).
 *
 * Adiciona:
 *  1. `pi_inscricao_recebida(int $inscricaoId)`        — email individual ao inscrito
 *  2. `pi_recurso_inabilitacao_protocolado(int $recursoId)` — email individual ao agente
 *  3. `pi_recurso_inabilitacao_decidido(int $recursoId, string $decisao)` — email individual
 *  4. `pi_votacao_encerrada(int $votacaoId)`           — email ao gestor do edital
 *  5. `pi_inscricoes_abertas(int $editalId)` (broadcast elegíveis) — NÃO sobrescreve
 *     o listener da Wave 4-C; dispara broadcast paginado usando WpdbAgenteBroadcastQuery
 *     via hook de prioridade 20 (depois do listener 4-C no prioridade 10).
 *
 * NÃO remove nenhum hook registrado por EventListeners.php (Wave 4-C).
 *
 * @package Ibram\ParticipeIbram\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteBroadcastQuery;
use Throwable;

/**
 * Complementa os listeners de e-mail da Wave 4-C com hooks adicionais.
 *
 * Esta classe é autônoma e não herda de EventListeners para manter
 * compatibilidade e clareza de responsabilidade por wave.
 *
 * Comunicação obrigatória vs. opcional:
 *  - `pi_inscricao_recebida`, `pi_recurso_inabilitacao_*`, `pi_votacao_encerrada`:
 *    comunicação institucional obrigatória (Despacho 98/2025) — não filtram
 *    revogação de `comunicacao`.
 *  - `pi_inscricoes_abertas` broadcast: comunicação institucional obrigatória
 *    (aviso de edital aberto para elegíveis) — usa `iterar()` geral, não filtra
 *    revogação (base legal Art. 7º, III LGPD — política pública).
 */
final class EventListenersWave7
{
    private EnfileirarEmailHandler $enfileirar;
    private SecureLogger $logger;
    private ?WpdbAgenteBroadcastQuery $broadcastQuery;
    private string $homeUrl;

    /** @var callable(int):array<string,mixed>|null */
    private $inscricaoResolver;

    /** @var callable(int):array<string,mixed>|null */
    private $recursoInabilitacaoResolver;

    /** @var callable(int):array<string,mixed>|null */
    private $votacaoResolver;

    /**
     * @param callable(int):array<string,mixed>|null $inscricaoResolver
     *   Recebe inscricaoId, retorna ['agente_id'=>int, 'edital_titulo'=>string, 'vaga'=>string, 'email'=>string, 'nome'=>string]
     * @param callable(int):array<string,mixed>|null $recursoInabilitacaoResolver
     *   Recebe recursoId, retorna ['agente_id'=>int, 'email'=>string, 'nome'=>string, 'edital_titulo'=>string]
     * @param callable(int):array<string,mixed>|null $votacaoResolver
     *   Recebe votacaoId, retorna ['gestor_id'=>int, 'gestor_email'=>string, 'gestor_nome'=>string, 'edital_titulo'=>string, 'edital_id'=>int]
     */
    public function __construct(
        EnfileirarEmailHandler $enfileirar,
        SecureLogger $logger,
        ?WpdbAgenteBroadcastQuery $broadcastQuery = null,
        ?callable $inscricaoResolver = null,
        ?callable $recursoInabilitacaoResolver = null,
        ?callable $votacaoResolver = null,
        string $homeUrl = ''
    ) {
        $this->enfileirar                  = $enfileirar;
        $this->logger                      = $logger;
        $this->broadcastQuery              = $broadcastQuery;
        $this->inscricaoResolver           = $inscricaoResolver;
        $this->recursoInabilitacaoResolver = $recursoInabilitacaoResolver;
        $this->votacaoResolver             = $votacaoResolver;
        $this->homeUrl                     = $homeUrl !== ''
            ? $homeUrl
            : (function_exists('home_url') ? (string) \home_url('/') : '/');
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        // Hook 1: inscrição recebida (individual para o inscrito)
        \add_action('pi_inscricao_recebida', [$this, 'onInscricaoRecebida'], 10, 1);

        // Hook 2/3: recurso de inabilitação (individual para o agente)
        \add_action('pi_recurso_inabilitacao_protocolado', [$this, 'onRecursoInabilitacaoProtocolado'], 10, 1);
        \add_action('pi_recurso_inabilitacao_decidido', [$this, 'onRecursoInabilitacaoDecidido'], 10, 2);

        // Hook 4: votação encerrada — email ao gestor do edital
        \add_action('pi_votacao_encerrada', [$this, 'onVotacaoEncerrada'], 10, 1);

        // Hook 5: inscricoes_abertas — broadcast paginado aos elegíveis (prio 20 — após W4-C prio 10)
        // W4-C envia email individual ao inscrito já cadastrado; este envia broadcast de aviso de abertura
        \add_action('pi_inscricoes_abertas', [$this, 'onInscricoesAbertasBroadcast'], 20, 2);
    }

    /* =====================================================================
     * Handlers
     * ===================================================================== */

    /**
     * pi_inscricao_recebida(int $inscricaoId)
     * Email individual ao agente que se inscreveu (comunicação obrigatória).
     */
    public function onInscricaoRecebida(int $inscricaoId): void
    {
        try {
            if ($this->inscricaoResolver === null) {
                $this->logger->warning('email.w7.inscricao_recebida.sem_resolver', [
                    'inscricao_id' => $inscricaoId,
                ]);
                return;
            }
            $data = ($this->inscricaoResolver)($inscricaoId);
            if (!is_array($data) || empty($data['email']) || empty($data['agente_id'])) {
                return;
            }
            $email    = (string) $data['email'];
            $agenteId = (int) $data['agente_id'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('email.w7.inscricao_recebida.email_invalido', [
                    'inscricao_id' => $inscricaoId,
                    'email_masked' => PiiMasker::maskEmail($email),
                ]);
                return;
            }
            $vars = [
                'nome'          => (string) ($data['nome'] ?? 'Participante'),
                'edital_titulo' => (string) ($data['edital_titulo'] ?? ''),
                'vaga'          => (string) ($data['vaga'] ?? ''),
                'painel_url'    => rtrim($this->homeUrl, '/') . '/painel/',
                'dpo_email'     => $this->dpoEmail(),
                'unsubscribe_url' => '',
            ];
            $this->enfileirar->handle(new EnfileirarEmailCommand(
                'inscricao_recebida',
                $agenteId,
                $email,
                $vars
            ));
        } catch (Throwable $e) {
            $this->logger->error('email.w7.inscricao_recebida.exception', [
                'inscricao_id' => $inscricaoId,
                'erro'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * pi_recurso_inabilitacao_protocolado(int $recursoId)
     * Email individual ao agente confirmando protocolo (comunicação obrigatória).
     */
    public function onRecursoInabilitacaoProtocolado(int $recursoId): void
    {
        $this->dispatchRecursoInabilitacao($recursoId, 'recurso_inabilitacao_protocolado', null);
    }

    /**
     * pi_recurso_inabilitacao_decidido(int $recursoId, string $decisao)
     * Email individual ao agente com a decisão (comunicação obrigatória).
     */
    public function onRecursoInabilitacaoDecidido(int $recursoId, string $decisao): void
    {
        $this->dispatchRecursoInabilitacao($recursoId, 'recurso_inabilitacao_decidido', $decisao);
    }

    /**
     * pi_votacao_encerrada(int $votacaoId)
     * Email ao gestor do edital para iniciar apuração (comunicação obrigatória interna).
     */
    public function onVotacaoEncerrada(int $votacaoId): void
    {
        try {
            if ($this->votacaoResolver === null) {
                $this->logger->warning('email.w7.votacao_encerrada.sem_resolver', [
                    'votacao_id' => $votacaoId,
                ]);
                return;
            }
            $data = ($this->votacaoResolver)($votacaoId);
            if (!is_array($data) || empty($data['gestor_email'])) {
                return;
            }
            $email    = (string) $data['gestor_email'];
            $gestorId = (int) ($data['gestor_id'] ?? 0);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $vars = [
                'nome'           => (string) ($data['gestor_nome'] ?? 'Gestor'),
                'edital_titulo'  => (string) ($data['edital_titulo'] ?? ''),
                'votacao_id'     => $votacaoId,
                'apuracao_url'   => rtrim($this->homeUrl, '/') . '/wp-admin/admin.php?page=pi-apuracao&votacao_id=' . $votacaoId,
                'painel_url'     => rtrim($this->homeUrl, '/') . '/wp-admin/',
                'dpo_email'      => $this->dpoEmail(),
                'unsubscribe_url' => '',
            ];
            $this->enfileirar->handle(new EnfileirarEmailCommand(
                'votacao_encerrada',
                $gestorId,
                $email,
                $vars
            ));
        } catch (Throwable $e) {
            $this->logger->error('email.w7.votacao_encerrada.exception', [
                'votacao_id' => $votacaoId,
                'erro'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * pi_inscricoes_abertas(int $editalId, ?array $payload) — broadcast aos elegíveis.
     *
     * Comunicação obrigatória institucional (Despacho 98/2025 item 7). Usa
     * `iterar()` geral (todos deferidos) pois elegibilidade por categoria exige
     * resolução de tipo; o broadcast genérico avisa TODOS os deferidos que o
     * edital está aberto a inscrições, e eles verificam sua elegibilidade.
     *
     * Para broadcast granular por categoria, use `listarDeferidosElegiveisCategoria`
     * diretamente no handler de edital antes de disparar este hook.
     */
    public function onInscricoesAbertasBroadcast(int $editalId, ?array $payload = null): void
    {
        try {
            $vars = [
                'edital_titulo'  => is_array($payload) && isset($payload['titulo'])
                    ? (string) $payload['titulo'] : '',
                'periodo'        => is_array($payload) && isset($payload['periodo'])
                    ? (string) $payload['periodo'] : '',
                'inscricao_url'  => is_array($payload) && isset($payload['url'])
                    ? (string) $payload['url'] : $this->homeUrl,
                'dpo_email'      => $this->dpoEmail(),
                'unsubscribe_url' => '',
            ];
            $this->enfileirar->broadcast('inscricoes_abertas', $vars, new DateTimeImmutable('now'));
        } catch (Throwable $e) {
            $this->logger->error('email.w7.inscricoes_abertas_broadcast.exception', [
                'edital_id' => $editalId,
                'erro'      => $e->getMessage(),
            ]);
        }
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    private function dispatchRecursoInabilitacao(int $recursoId, string $evento, ?string $decisao): void
    {
        try {
            if ($this->recursoInabilitacaoResolver === null) {
                $this->logger->warning('email.w7.recurso_inabilitacao.sem_resolver', [
                    'evento'     => $evento,
                    'recurso_id' => $recursoId,
                ]);
                return;
            }
            $data = ($this->recursoInabilitacaoResolver)($recursoId);
            if (!is_array($data) || empty($data['email']) || empty($data['agente_id'])) {
                return;
            }
            $email    = (string) $data['email'];
            $agenteId = (int) $data['agente_id'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('email.w7.recurso_inabilitacao.email_invalido', [
                    'evento'       => $evento,
                    'recurso_id'   => $recursoId,
                    'email_masked' => PiiMasker::maskEmail($email),
                ]);
                return;
            }
            $vars = [
                'nome'           => (string) ($data['nome'] ?? 'Participante'),
                'edital_titulo'  => (string) ($data['edital_titulo'] ?? ''),
                'recurso_id'     => $recursoId,
                'painel_url'     => rtrim($this->homeUrl, '/') . '/painel/',
                'dpo_email'      => $this->dpoEmail(),
                'unsubscribe_url' => '',
            ];
            if ($decisao !== null) {
                $vars['decisao'] = $decisao;
            }
            $this->enfileirar->handle(new EnfileirarEmailCommand(
                $evento,
                $agenteId,
                $email,
                $vars
            ));
        } catch (Throwable $e) {
            $this->logger->error('email.w7.recurso_inabilitacao.exception', [
                'evento'     => $evento,
                'recurso_id' => $recursoId,
                'erro'       => $e->getMessage(),
            ]);
        }
    }

    private function dpoEmail(): string
    {
        if (function_exists('get_option')) {
            $email = (string) \get_option('pi_dpo_email', '');
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return 'encarregado@museus.gov.br';
    }
}
