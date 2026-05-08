<?php
/**
 * CategoriaController — CRUD de categorias dentro de um edital.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Edital\Categoria;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Throwable;

/**
 * Gerencia categorias de um edital (adicionar / editar / (soft-)remover).
 *
 * Regra de negócio: edição permitida apenas em status RASCUNHO ou PUBLICADO;
 * não permite alteração depois de INSCRICOES_ABERTAS (TD-06).
 *
 * Capability gating (R5 V-06): pi_editar_edital em todas as ações.
 * wp_unslash via RequestHelper (R5 V-08).
 */
final class CategoriaController
{
    public const CAP_LISTAR = 'pi_listar_cadastros';
    public const CAP_EDITAR = 'pi_editar_edital';

    /** Status onde edição de categorias ainda é permitida. */
    private const STATUS_EDITAVEIS = [StatusEdital::RASCUNHO, StatusEdital::PUBLICADO];

    private WpdbEditalRepository $editaisRepo;
    private WpdbCategoriaRepository $categoriasRepo;
    private AuditLogger $audit;

    public function __construct(
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        AuditLogger $audit
    ) {
        $this->editaisRepo    = $editaisRepo;
        $this->categoriasRepo = $categoriasRepo;
        $this->audit          = $audit;
    }

    /**
     * Render da página de criar/editar categoria.
     */
    public function render(): void
    {
        // R5 V-06.
        if (!self::userCan(self::CAP_LISTAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $editalId    = (int) RequestHelper::get('edital_id', 'absint', 0);
        $categoriaId = (int) RequestHelper::get('categoria_id', 'absint', 0);

        if ($editalId <= 0) {
            self::wpDie(self::tr('Edital não especificado.'));
            return;
        }

        $edital = $this->editaisRepo->findById($editalId);
        if ($edital === null) {
            self::wpDie(self::tr('Edital não encontrado.'));
            return;
        }

        $categoria    = $categoriaId > 0 ? $this->categoriasRepo->findById($categoriaId) : null;
        $podeEditar   = self::userCan(self::CAP_EDITAR) && in_array($edital->status()->value(), self::STATUS_EDITAVEIS, true);
        $userId       = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        $nonceAction  = 'pi_admin_salvar_categoria_' . $editalId . '_' . $userId;
        $nonce        = function_exists('wp_create_nonce') ? \wp_create_nonce($nonceAction) : '';
        $flash        = $this->consumeFlash();

        $template = self::templatePath('editais/categoria-form.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    /**
     * Processa POST (salvar / remover). Chamado via admin_init.
     */
    public function handlePostAction(): void
    {
        $action = (string) RequestHelper::post('pi_categoria_action', 'sanitize_key', '');
        if ($action === '') {
            return;
        }

        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->setFlash('error', self::tr('Sessão expirada.'));
            return;
        }

        if ($action === 'salvar_categoria') {
            $this->handleSalvar($userId);
        }
    }

    /* ----------------------- Handlers ----------------------- */

    private function handleSalvar(int $userId): void
    {
        if (!self::userCan(self::CAP_EDITAR)) {
            $this->setFlash('error', self::tr('Permissão negada.'));
            self::redirect(EditalMenuRegistry::urlEditaisList());
            return;
        }

        $editalId    = (int) RequestHelper::post('edital_id', 'absint', 0);
        $categoriaId = (int) RequestHelper::post('categoria_id', 'absint', 0);

        if ($editalId <= 0) {
            $this->setFlash('error', self::tr('Edital inválido.'));
            self::redirect(EditalMenuRegistry::urlEditaisList());
            return;
        }

        $nonceAction = 'pi_admin_salvar_categoria_' . $editalId . '_' . $userId;
        $nonce       = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '' || !function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->setFlash('error', self::tr('Nonce inválido.'));
            self::redirect(EditalMenuRegistry::urlCategoria($editalId, $categoriaId > 0 ? $categoriaId : null));
            return;
        }

        $edital = $this->editaisRepo->findById($editalId);
        if ($edital === null) {
            $this->setFlash('error', self::tr('Edital não encontrado.'));
            self::redirect(EditalMenuRegistry::urlEditaisList());
            return;
        }

        // Regra: edição de categoria após INSCRICOES_ABERTAS é proibida.
        if (!in_array($edital->status()->value(), self::STATUS_EDITAVEIS, true)) {
            $this->setFlash(
                'error',
                self::tr('Categorias não podem ser alteradas depois de as inscrições serem abertas.')
            );
            self::redirect(EditalMenuRegistry::urlEditalDetalhes($editalId));
            return;
        }

        $nome       = (string) RequestHelper::post('nome', 'sanitize_text_field', '');
        $descricao  = (string) RequestHelper::post('descricao_md', 'wp_kses_post', '');
        $numVagas   = (int) RequestHelper::post('num_vagas', 'absint', 0);
        $numSuplent = (int) RequestHelper::post('num_suplentes', 'absint', 0);
        $tiposRaw   = RequestHelper::postArray('tipos_agente_elegivel', 'sanitize_key');
        $docsRaw    = RequestHelper::postArray('documentos_exigidos', 'sanitize_text_field');
        $criterios  = (string) RequestHelper::post('criterios_md', 'wp_kses_post', '');
        $ordem      = (int) RequestHelper::post('ordem', 'absint', 0);

        $tiposStr = implode(',', array_filter(array_map('strtoupper', (array) $tiposRaw)));

        try {
            $before     = $categoriaId > 0 ? $this->categoriasRepo->findById($categoriaId) : null;
            $beforeArr  = $before !== null ? ['nome' => $before->nome()] : null;
            $categoria  = new Categoria(
                $categoriaId > 0 ? $categoriaId : null,
                $editalId,
                $nome,
                $descricao !== '' ? $descricao : null,
                $numVagas > 0 ? $numVagas : 1,
                max(0, $numSuplent),
                $tiposStr !== '' ? $tiposStr : 'PF',
                $criterios !== '' ? $criterios : null,
                array_values(array_filter((array) $docsRaw, static fn ($v) => $v !== '')),
                max(0, $ordem)
            );
            $savedId = $this->categoriasRepo->save($categoria);
            $acao    = $categoriaId > 0 ? 'atualizar_categoria' : 'criar_categoria';
            $this->audit->log('edital', $editalId, $acao, $beforeArr, ['nome' => $nome, 'categoria_id' => $savedId], $userId);
            $this->setFlash('success', self::tr('Categoria salva com sucesso.'));
            self::redirect(EditalMenuRegistry::urlEditalDetalhes($editalId));
        } catch (DomainException $e) {
            $this->setFlash('error', $e->getMessage());
            self::redirect(EditalMenuRegistry::urlCategoria($editalId, $categoriaId > 0 ? $categoriaId : null));
        } catch (Throwable $e) {
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->setFlash('error', $debug ? $e->getMessage() : self::tr('Falha ao salvar categoria.'));
            self::redirect(EditalMenuRegistry::urlCategoria($editalId, $categoriaId > 0 ? $categoriaId : null));
        }
    }

    /* ----------------------- Flash ----------------------- */

    public function setFlash(string $type, string $message): void
    {
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }
        $uid = (int) \get_current_user_id();
        if ($uid <= 0) {
            return;
        }
        \set_transient('pi_admin_edital_flash_' . $uid, ['type' => $type, 'message' => $message], 60);
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeFlash(): ?array
    {
        if (!function_exists('get_transient') || !function_exists('get_current_user_id')) {
            return null;
        }
        $uid  = (int) \get_current_user_id();
        $key  = 'pi_admin_edital_flash_' . $uid;
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
