<?php
/**
 * EditalFormController — criar / editar edital.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\EditalNotFound;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Throwable;

/**
 * Gerencia os formulários de criação e edição de editais.
 *
 * Capability gating (R5 V-06):
 *  - renderCreate(): pi_criar_edital
 *  - renderEdit():   pi_editar_edital
 *  - handlePostAction(): verifica separadamente por ação
 *
 * wp_unslash() aplicado em todos os superglobais via RequestHelper (R5 V-08).
 */
final class EditalFormController
{
    public const CAP_CRIAR  = 'pi_criar_edital';
    public const CAP_EDITAR = 'pi_editar_edital';

    private WpdbEditalRepository $editaisRepo;
    private AuditLogger $audit;

    public function __construct(
        WpdbEditalRepository $editaisRepo,
        AuditLogger $audit
    ) {
        $this->editaisRepo = $editaisRepo;
        $this->audit       = $audit;
    }

    /**
     * Render do form de criação.
     */
    public function renderCreate(): void
    {
        if (!self::userCan(self::CAP_CRIAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $edital = null;
        $errors = [];
        $flash  = $this->consumeFlash();
        $this->renderForm($edital, $errors, $flash);
    }

    /**
     * Render do form de edição.
     */
    public function renderEdit(int $editalId): void
    {
        if (!self::userCan(self::CAP_EDITAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $edital = $this->editaisRepo->findById($editalId);
        if ($edital === null) {
            self::wpDie(self::tr('Edital não encontrado.'));
            return;
        }

        $errors = [];
        $flash  = $this->consumeFlash();
        $this->renderForm($edital, $errors, $flash);
    }

    /**
     * Processa ações POST (criar / atualizar). Chamado via admin_init.
     */
    public function handlePostAction(): void
    {
        // R5 V-08: wp_unslash via RequestHelper.
        $action = (string) RequestHelper::post('pi_edital_action', 'sanitize_key', '');
        if ($action === '') {
            return;
        }

        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->setFlash('error', self::tr('Sessão expirada.'));
            return;
        }

        if ($action === 'criar_edital') {
            $this->handleCriar($userId);
        } elseif ($action === 'atualizar_edital') {
            $this->handleAtualizar($userId);
        }
    }

    /* ----------------------- Handlers ----------------------- */

    private function handleCriar(int $userId): void
    {
        if (!self::userCan(self::CAP_CRIAR)) {
            $this->setFlash('error', self::tr('Permissão negada.'));
            self::redirect(EditalMenuRegistry::urlEditaisList());
            return;
        }

        $nonceAction = 'pi_admin_criar_edital_' . $userId;
        $nonce       = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->setFlash('error', self::tr('Nonce inválido. Operação cancelada.'));
            self::redirect(EditalMenuRegistry::urlNovo());
            return;
        }

        $titulo     = (string) RequestHelper::post('titulo', 'sanitize_text_field', '');
        $descricao  = (string) RequestHelper::post('descricao_md', 'wp_kses_post', '');

        if (trim($titulo) === '') {
            $this->setFlash('error', self::tr('O título do edital é obrigatório.'));
            self::redirect(EditalMenuRegistry::urlNovo());
            return;
        }
        if (mb_strlen($titulo) > 255) {
            $this->setFlash('error', self::tr('O título não pode exceder 255 caracteres.'));
            self::redirect(EditalMenuRegistry::urlNovo());
            return;
        }

        try {
            $datas  = $this->parseDatas();
            $edital = Edital::novoRascunho($titulo, $userId, $descricao !== '' ? $descricao : null);
            if ($datas !== null) {
                $edital->programarDatas(...$datas);
            }
            $novoId = $this->editaisRepo->save($edital);
            $this->audit->log('edital', $novoId, 'criar', null, ['titulo' => $titulo], $userId);
            $this->setFlash('success', self::tr('Edital criado com sucesso.'));
            self::redirect(EditalMenuRegistry::urlEditalDetalhes($novoId));
        } catch (DomainException $e) {
            $this->setFlash('error', $this->friendlyDomainError($e));
            self::redirect(EditalMenuRegistry::urlNovo());
        } catch (Throwable $e) {
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->setFlash('error', $debug ? $e->getMessage() : self::tr('Falha ao criar edital.'));
            self::redirect(EditalMenuRegistry::urlNovo());
        }
    }

    private function handleAtualizar(int $userId): void
    {
        $editalId = (int) RequestHelper::post('edital_id', 'absint', 0);
        if ($editalId <= 0) {
            $this->setFlash('error', self::tr('Edital inválido.'));
            self::redirect(EditalMenuRegistry::urlEditaisList());
            return;
        }

        if (!self::userCan(self::CAP_EDITAR)) {
            $this->setFlash('error', self::tr('Permissão negada.'));
            self::redirect(EditalMenuRegistry::urlEditalDetalhes($editalId));
            return;
        }

        $nonceAction = 'pi_admin_atualizar_edital_' . $editalId . '_' . $userId;
        $nonce       = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->setFlash('error', self::tr('Nonce inválido.'));
            self::redirect(EditalMenuRegistry::urlEditalEdit($editalId));
            return;
        }

        $edital = $this->editaisRepo->findById($editalId);
        if ($edital === null) {
            $this->setFlash('error', self::tr('Edital não encontrado.'));
            self::redirect(EditalMenuRegistry::urlEditaisList());
            return;
        }

        // Só permite edição em rascunho.
        if ($edital->status()->value() !== StatusEdital::RASCUNHO) {
            $this->setFlash('error', self::tr('Apenas editais em rascunho podem ser editados.'));
            self::redirect(EditalMenuRegistry::urlEditalDetalhes($editalId));
            return;
        }

        $titulo    = (string) RequestHelper::post('titulo', 'sanitize_text_field', '');
        $descricao = (string) RequestHelper::post('descricao_md', 'wp_kses_post', '');

        if (trim($titulo) === '') {
            $this->setFlash('error', self::tr('O título do edital é obrigatório.'));
            self::redirect(EditalMenuRegistry::urlEditalEdit($editalId));
            return;
        }
        if (mb_strlen($titulo) > 255) {
            $this->setFlash('error', self::tr('O título não pode exceder 255 caracteres.'));
            self::redirect(EditalMenuRegistry::urlEditalEdit($editalId));
            return;
        }

        try {
            $before = ['titulo' => $edital->titulo(), 'status' => $edital->status()->value()];
            // Reconstrói com novos valores mantendo imutabilidade.
            $novo = new Edital(
                $edital->id(),
                $titulo,
                $descricao !== '' ? $descricao : null,
                $edital->status(),
                $edital->abertura(),
                $edital->encerramentoInscricoes(),
                $edital->publicacaoHabilitacao(),
                $edital->prazoRecursoInabilitacao(),
                $edital->aberturaVotacao(),
                $edital->encerramentoVotacao(),
                $edital->publicacaoResultado(),
                $edital->criadoPor(),
                $edital->createdAt(),
                new DateTimeImmutable('now')
            );
            $datas = $this->parseDatas();
            if ($datas !== null) {
                $novo->programarDatas(...$datas);
            }
            $this->editaisRepo->save($novo);
            $this->audit->log('edital', $editalId, 'atualizar', $before, ['titulo' => $titulo], $userId);
            $this->setFlash('success', self::tr('Edital atualizado com sucesso.'));
            self::redirect(EditalMenuRegistry::urlEditalDetalhes($editalId));
        } catch (DomainException $e) {
            $this->setFlash('error', $this->friendlyDomainError($e));
            self::redirect(EditalMenuRegistry::urlEditalEdit($editalId));
        } catch (Throwable $e) {
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->setFlash('error', $debug ? $e->getMessage() : self::tr('Falha ao atualizar edital.'));
            self::redirect(EditalMenuRegistry::urlEditalEdit($editalId));
        }
    }

    /* ----------------------- Date parsing ----------------------- */

    /**
     * Lê as 7 datas do POST e retorna o array na ordem esperada por
     * Edital::programarDatas() ou null se nenhuma foi enviada.
     *
     * A validação cronológica é delegada à entidade (TD-06 state machine guard).
     * Isso garante server-side revalidation mesmo quando o JS client falha.
     *
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable,2:DateTimeImmutable,3:DateTimeImmutable,4:DateTimeImmutable,5:DateTimeImmutable,6:DateTimeImmutable}|null
     */
    private function parseDatas(): ?array
    {
        $fields = [
            'abertura',
            'encerramento_inscricoes',
            'publicacao_habilitacao',
            'prazo_recurso_inabilitacao',
            'abertura_votacao',
            'encerramento_votacao',
            'publicacao_resultado',
        ];

        $dts = [];
        $allEmpty = true;

        foreach ($fields as $field) {
            $raw = (string) RequestHelper::post($field, 'sanitize_text_field', '');
            if ($raw !== '') {
                $allEmpty = false;
                try {
                    $dts[$field] = new DateTimeImmutable($raw);
                } catch (\Exception $e) {
                    throw new DomainException(sprintf(self::tr('Data inválida para o campo "%s".'), $field));
                }
            } else {
                $dts[$field] = null;
            }
        }

        if ($allEmpty) {
            return null;
        }

        // Todas as 7 datas devem ser fornecidas juntas.
        foreach ($fields as $field) {
            if ($dts[$field] === null) {
                throw new DomainException(
                    sprintf(self::tr('O campo "%s" é obrigatório quando qualquer data é informada.'), $field)
                );
            }
        }

        return [
            $dts['abertura'],
            $dts['encerramento_inscricoes'],
            $dts['publicacao_habilitacao'],
            $dts['prazo_recurso_inabilitacao'],
            $dts['abertura_votacao'],
            $dts['encerramento_votacao'],
            $dts['publicacao_resultado'],
        ];
    }

    /* ----------------------- Render ----------------------- */

    /**
     * @param array<string,string> $errors
     * @param array{type:string,message:string}|null $flash
     */
    private function renderForm(?Edital $edital, array $errors, ?array $flash): void
    {
        $userId      = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        $isNew       = $edital === null;
        $nonceAction = $isNew
            ? 'pi_admin_criar_edital_' . $userId
            : 'pi_admin_atualizar_edital_' . ($edital !== null ? (int) $edital->id() : 0) . '_' . $userId;
        $nonce = function_exists('wp_create_nonce') ? \wp_create_nonce($nonceAction) : '';

        $template = self::templatePath('editais/edital-form.php');
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
        \set_transient('pi_admin_edital_flash_' . $userId, ['type' => $type, 'message' => $message], 60);
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
        $key  = 'pi_admin_edital_flash_' . $userId;
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

    /**
     * Converte DomainException (mensagem técnica) em texto amigável (pt_BR).
     */
    private function friendlyDomainError(DomainException $e): string
    {
        $msg = $e->getMessage();
        if (strpos($msg, 'Cronologia invalida') !== false) {
            return self::tr('As datas informadas estão em ordem cronológica inválida. Verifique a sequência: abertura → encerramento inscrições → publicação habilitação → prazo recurso → abertura votação → encerramento votação → publicação resultado.');
        }
        if (strpos($msg, 'sem programacao completa') !== false) {
            return self::tr('Para publicar o edital todas as datas devem ser preenchidas.');
        }
        if (strpos($msg, 'sem ao menos uma categoria') !== false) {
            return self::tr('O edital deve ter pelo menos uma categoria antes de ser publicado.');
        }
        if (strpos($msg, 'Datas so podem ser programadas em rascunho') !== false) {
            return self::tr('As datas só podem ser alteradas enquanto o edital está em rascunho.');
        }
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        return $debug ? $msg : self::tr('Operação inválida. Verifique os dados informados.');
    }

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
