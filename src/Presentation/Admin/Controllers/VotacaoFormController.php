<?php
/**
 * VotacaoFormController — criar / editar votação (apenas status agendada).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Ibram\ParticipeIbram\Application\Votacao\CriarVotacaoCommand;
use Ibram\ParticipeIbram\Application\Votacao\CriarVotacaoHandler;
use Ibram\ParticipeIbram\Application\Votacao\EditarVotacaoCommand;
use Ibram\ParticipeIbram\Application\Votacao\EditarVotacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;
use Throwable;

/**
 * Gerencia os formulários de criação e edição de votações.
 *
 * Capability gating (R5 V-06, defense-in-depth):
 *  - renderCreate() / renderEdit() / handlePostAction(): pi_apurar_votacao
 *
 * wp_unslash() aplicado em todos os superglobais via RequestHelper (R5 V-08).
 */
final class VotacaoFormController
{
    public const CAP = VotacaoMenuRegistry::CAP_APURAR;

    private CriarVotacaoHandler $criarHandler;

    private EditarVotacaoHandler $editarHandler;

    private VotacaoRepository $votacaoRepo;

    private WpdbEditalRepository $editalRepo;

    private AuditLogger $audit;

    public function __construct(
        CriarVotacaoHandler $criarHandler,
        EditarVotacaoHandler $editarHandler,
        VotacaoRepository $votacaoRepo,
        WpdbEditalRepository $editalRepo,
        AuditLogger $audit
    ) {
        $this->criarHandler  = $criarHandler;
        $this->editarHandler = $editarHandler;
        $this->votacaoRepo   = $votacaoRepo;
        $this->editalRepo    = $editalRepo;
        $this->audit         = $audit;
    }

    /**
     * Render do formulário de criação.
     */
    public function renderCreate(): void
    {
        if (!self::userCan(self::CAP)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $flash    = $this->consumeFlash();
        $editais  = $this->loadEditaisParaVotacao(null);
        $modos    = ModoVotacao::all();
        $votacao  = null;
        $isNew    = true;

        $this->renderForm($votacao, $editais, $modos, $isNew, $flash);
    }

    /**
     * Render do formulário de edição (apenas votações agendadas).
     */
    public function renderEdit(int $votacaoId): void
    {
        if (!self::userCan(self::CAP)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        try {
            $votacao = $this->votacaoRepo->findById($votacaoId);
        } catch (VotacaoNotFound $e) {
            self::wpDie(self::tr('Votação não encontrada.'));
            return;
        }

        if (!$votacao->status()->isAgendada()) {
            self::wpDie(self::tr('Apenas votações agendadas podem ser editadas.'));
            return;
        }

        $flash   = $this->consumeFlash();
        $editais = $this->loadEditaisParaVotacao((int) $votacao->editalId());
        $modos   = ModoVotacao::all();
        $isNew   = false;

        $this->renderForm($votacao, $editais, $modos, $isNew, $flash);
    }

    /**
     * Processa ações POST (criar / atualizar). Chamado via admin_init.
     */
    public function handlePostAction(): void
    {
        $action = (string) RequestHelper::post('pi_votacao_action', 'sanitize_key', '');
        if ($action === '') {
            return;
        }

        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->setFlash('error', self::tr('Sessão expirada.'));
            return;
        }

        if ($action === 'criar_votacao') {
            $this->handleCriar($userId);
        } elseif ($action === 'atualizar_votacao') {
            $this->handleAtualizar($userId);
        }
    }

    /* ----------------------- Handlers ----------------------- */

    private function handleCriar(int $userId): void
    {
        if (!self::userCan(self::CAP)) {
            $this->setFlash('error', self::tr('Permissão negada.'));
            self::redirect(VotacaoMenuRegistry::urlVotacoesList());
            return;
        }

        $nonceAction = 'pi_admin_criar_votacao_' . $userId;
        $nonce       = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->setFlash('error', self::tr('Nonce inválido. Operação cancelada.'));
            self::redirect(VotacaoMenuRegistry::urlNovaVotacao());
            return;
        }

        $editalId = (int) RequestHelper::post('edital_id', 'absint', 0);
        if ($editalId <= 0) {
            $this->setFlash('error', self::tr('Selecione um edital.'));
            self::redirect(VotacaoMenuRegistry::urlNovaVotacao());
            return;
        }

        try {
            [$abertura, $encerramento] = $this->parseDatas();
            $modo = (string) RequestHelper::post('modo', 'sanitize_key', ModoVotacao::POR_CATEGORIA);

            $command = new CriarVotacaoCommand($editalId, $abertura, $encerramento, $modo, $userId);
            $votacao = $this->criarHandler->handle($command);

            $this->setFlash('success', self::tr('Votação criada com sucesso.'));
            self::redirect(VotacaoMenuRegistry::urlVotacoesList());
        } catch (DomainException | InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
            self::redirect(VotacaoMenuRegistry::urlNovaVotacao());
        } catch (Throwable $e) {
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->setFlash('error', $debug ? $e->getMessage() : self::tr('Falha ao criar votação.'));
            self::redirect(VotacaoMenuRegistry::urlNovaVotacao());
        }
    }

    private function handleAtualizar(int $userId): void
    {
        $votacaoId = (int) RequestHelper::post('votacao_id', 'absint', 0);
        if ($votacaoId <= 0) {
            $this->setFlash('error', self::tr('Votação inválida.'));
            self::redirect(VotacaoMenuRegistry::urlVotacoesList());
            return;
        }

        if (!self::userCan(self::CAP)) {
            $this->setFlash('error', self::tr('Permissão negada.'));
            self::redirect(VotacaoMenuRegistry::urlVotacoesList());
            return;
        }

        $nonceAction = 'pi_admin_atualizar_votacao_' . $votacaoId . '_' . $userId;
        $nonce       = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->setFlash('error', self::tr('Nonce inválido.'));
            self::redirect(VotacaoMenuRegistry::urlEditarVotacao($votacaoId));
            return;
        }

        try {
            [$abertura, $encerramento] = $this->parseDatas();
            $modo = (string) RequestHelper::post('modo', 'sanitize_key', ModoVotacao::POR_CATEGORIA);

            $command = new EditarVotacaoCommand($votacaoId, $abertura, $encerramento, $modo, $userId);
            $this->editarHandler->handle($command);

            $this->setFlash('success', self::tr('Votação atualizada com sucesso.'));
            self::redirect(VotacaoMenuRegistry::urlVotacoesList());
        } catch (DomainException | InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
            self::redirect(VotacaoMenuRegistry::urlEditarVotacao($votacaoId));
        } catch (Throwable $e) {
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->setFlash('error', $debug ? $e->getMessage() : self::tr('Falha ao atualizar votação.'));
            self::redirect(VotacaoMenuRegistry::urlEditarVotacao($votacaoId));
        }
    }

    /* ----------------------- Date parsing ----------------------- */

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     * @throws DomainException Quando algum campo de data é inválido ou ausente.
     */
    private function parseDatas(): array
    {
        $rawAbertura     = (string) RequestHelper::post('abertura', 'sanitize_text_field', '');
        $rawEncerramento = (string) RequestHelper::post('encerramento', 'sanitize_text_field', '');

        if ($rawAbertura === '') {
            throw new DomainException(self::tr('A data de abertura é obrigatória.'));
        }
        if ($rawEncerramento === '') {
            throw new DomainException(self::tr('A data de encerramento é obrigatória.'));
        }

        try {
            $abertura = new DateTimeImmutable($rawAbertura);
        } catch (\Exception $e) {
            throw new DomainException(self::tr('Data de abertura inválida.'));
        }

        try {
            $encerramento = new DateTimeImmutable($rawEncerramento);
        } catch (\Exception $e) {
            throw new DomainException(self::tr('Data de encerramento inválida.'));
        }

        if ($encerramento <= $abertura) {
            throw new DomainException(self::tr('O encerramento deve ser posterior à abertura.'));
        }

        return [$abertura, $encerramento];
    }

    /* ----------------------- Render ----------------------- */

    /**
     * Carrega editais disponíveis para seleção.
     * Mostra editais publicados (ou superiores) SEM votação ativa —
     * exceto no modo edição, onde o edital atual é sempre incluído.
     *
     * @return array<int,array{id:int,titulo:string}>
     */
    private function loadEditaisParaVotacao(?int $editalAtualId): array
    {
        // Fallback seguro — se o container não puder resolver a query, retorna vazio.
        if (!function_exists('global')) {
            // não é possível fazer query sem $wpdb — retorna vazio.
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return [];
        }

        $prefix        = is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $tableEditais  = $prefix . 'pi_editais';
        $tableVotacoes = $prefix . 'pi_votacoes';

        // Editais com status publicado ou superior (não rascunho) que não possuem
        // votação ativa (agendada/aberta). Estados terminais (apurada/cancelada)
        // não bloqueiam nova votação.
        $statusBloqueantes = "'agendada','aberta'";

        $sql = $wpdb->prepare(
            "SELECT e.id, e.titulo
             FROM {$tableEditais} e
             WHERE e.status != %s
               AND NOT EXISTS (
                   SELECT 1 FROM {$tableVotacoes} v
                   WHERE v.edital_id = e.id
                     AND v.status IN ({$statusBloqueantes}) -- phpcs:ignore
               )
             ORDER BY e.titulo ASC
             LIMIT 200",
            'rascunho'
        );

        $rows = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $out  = [];

        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $out[] = ['id' => (int) $row['id'], 'titulo' => (string) $row['titulo']];
        }

        // No modo edição, garante que o edital atual aparece mesmo se já tiver
        // votação existente (pois estamos editando essa mesma votação).
        if ($editalAtualId !== null) {
            $encontrado = false;
            foreach ($out as $item) {
                if ($item['id'] === $editalAtualId) {
                    $encontrado = true;
                    break;
                }
            }
            if (!$encontrado) {
                $edital = $this->editalRepo->findById($editalAtualId);
                if ($edital !== null) {
                    array_unshift($out, ['id' => $editalAtualId, 'titulo' => $edital->titulo()]);
                }
            }
        }

        return $out;
    }

    /**
     * @param \Ibram\ParticipeIbram\Domain\Votacao\Votacao|null $votacao
     * @param array<int,array{id:int,titulo:string}>             $editais
     * @param array<int,string>                                  $modos
     * @param array{type:string,message:string}|null             $flash
     */
    private function renderForm(
        $votacao,
        array $editais,
        array $modos,
        bool $isNew,
        ?array $flash
    ): void {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        $votacaoId = ($votacao !== null && $votacao->id() !== null) ? (int) $votacao->id() : 0;

        $nonceAction = $isNew
            ? 'pi_admin_criar_votacao_' . $userId
            : 'pi_admin_atualizar_votacao_' . $votacaoId . '_' . $userId;
        $nonce = function_exists('wp_create_nonce') ? \wp_create_nonce($nonceAction) : '';

        $template = self::templatePath('votacoes/votacao-form.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    /* ----------------------- Flash ----------------------- */

    public function setFlash(string $type, string $message): void
    {
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        \set_transient('pi_admin_votacao_flash_' . $userId, ['type' => $type, 'message' => $message], 60);
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeFlash(): ?array
    {
        if (!function_exists('get_transient') || !function_exists('get_current_user_id')) {
            return null;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return null;
        }
        $key  = 'pi_admin_votacao_flash_' . $userId;
        $data = \get_transient($key);
        if (!is_array($data) || !isset($data['type'], $data['message'])) {
            return null;
        }
        if (function_exists('delete_transient')) {
            \delete_transient($key);
        }
        return ['type' => (string) $data['type'], 'message' => (string) $data['message']];
    }

    /* ----------------------- Helpers ----------------------- */

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && \current_user_can($cap);
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function wpDie(string $message): void
    {
        if (function_exists('wp_die')) {
            \wp_die(self::escHtml($message));
        } else {
            echo $message;
            exit;
        }
    }

    private static function redirect(string $url): void
    {
        if (function_exists('wp_safe_redirect')) {
            \wp_safe_redirect($url);
            exit;
        }
    }

    private static function templatePath(string $relative): ?string
    {
        $base      = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
