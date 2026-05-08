<?php
/**
 * Stub admin controller para vocabulários (Wave 5 entrega UI completa).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioHandler;
use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioQuery;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use InvalidArgumentException;
use Throwable;

/**
 * Endpoints AJAX administrativos.
 *
 * Wave 2 entrega apenas LIST funcional. SAVE retorna HTTP 501 — UI completa
 * será implementada na Wave 5 (admin SPA). A capability necessária é
 * `pi_gerenciar_vocabularios`.
 */
final class VocabularioController
{
    private const NONCE_ACTION_LIST = 'pi_admin_listar_vocabulario';
    private const NONCE_ACTION_SAVE = 'pi_admin_salvar_item_vocabulario';

    /**
     * Capability admin para gerenciar vocabulários.
     */
    private const CAPABILITY = 'pi_gerenciar_vocabularios';

    private ListarVocabularioHandler $listHandler;

    public function __construct(ListarVocabularioHandler $listHandler)
    {
        $this->listHandler = $listHandler;
    }

    /**
     * Registra os hooks AJAX. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        add_action('wp_ajax_pi_admin_listar_vocabulario', [$this, 'ajaxListar']);
        add_action('wp_ajax_pi_admin_salvar_item_vocabulario', [$this, 'ajaxSalvar']);
    }

    /**
     * AJAX: lista itens de um tipo.
     *
     * Espera POST/GET:
     *  - `_wpnonce`     (action `pi_admin_listar_vocabulario`)
     *  - `tipo`         (string em TipoVocabulario::all())
     *  - `apenas_ativos` (1|0, default 1)
     */
    public function ajaxListar(): void
    {
        if (!$this->verifyNonce(self::NONCE_ACTION_LIST)) {
            $this->sendJsonError(__('Nonce inválido.', 'participe-ibram'), 403);
            return;
        }
        if (!$this->checkCapability()) {
            $this->sendJsonError(__('Permissão negada.', 'participe-ibram'), 403);
            return;
        }

        $tipo = (string) RequestHelper::request('tipo', 'sanitize_key', '');
        if ($tipo === '' || !TipoVocabulario::isValid($tipo)) {
            $this->sendJsonError(__('Tipo de vocabulário inválido.', 'participe-ibram'), 400);
            return;
        }
        $apenasAtivos = ((int) RequestHelper::request('apenas_ativos', 'absint', 1)) === 1;

        try {
            $items = $this->listHandler->handle(new ListarVocabularioQuery($tipo, $apenasAtivos));
        } catch (InvalidArgumentException $e) {
            $this->sendJsonError($e->getMessage(), 400);
            return;
        } catch (Throwable $e) {
            $this->sendJsonError(__('Erro ao listar vocabulário.', 'participe-ibram'), 500);
            return;
        }

        $this->sendJsonSuccess(['tipo' => $tipo, 'items' => $items]);
    }

    /**
     * AJAX: stub. Retorna 501 Not Implemented. UI completa virá na Wave 5.
     */
    public function ajaxSalvar(): void
    {
        if (!$this->verifyNonce(self::NONCE_ACTION_SAVE)) {
            $this->sendJsonError(__('Nonce inválido.', 'participe-ibram'), 403);
            return;
        }
        if (!$this->checkCapability()) {
            $this->sendJsonError(__('Permissão negada.', 'participe-ibram'), 403);
            return;
        }

        $this->sendJsonError(
            __('Salvar item de vocabulário ainda não está implementado (Wave 5).', 'participe-ibram'),
            501
        );
    }

    private function verifyNonce(string $action): bool
    {
        if (!function_exists('wp_verify_nonce')) {
            return false;
        }
        $nonce = (string) RequestHelper::request('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '') {
            return false;
        }

        return (bool) wp_verify_nonce($nonce, $action);
    }

    private function checkCapability(): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }

        return (bool) current_user_can(self::CAPABILITY);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function sendJsonSuccess(array $data): void
    {
        if (function_exists('wp_send_json_success')) {
            wp_send_json_success($data);
            return;
        }
        // Fallback (testes): emite e termina.
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function sendJsonError(string $message, int $status): void
    {
        $payload = ['message' => $message];
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error($payload, $status);
            return;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'data' => $payload]);
        exit;
    }
}
