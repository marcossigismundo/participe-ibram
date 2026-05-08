<?php
/**
 * Disparo centralizado de eventos (hooks WP) do domínio Consentimento.
 *
 * @package Ibram\ParticipeIbram\Application\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Consentimento;

use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;

/**
 * Wrapper fino sobre `do_action()`.
 *
 * Hooks publicados:
 *   - `pi_consentimento_registrado(int $consentimentoId, Finalidade $finalidade)`
 *   - `pi_consentimento_revogado(int $consentimentoId, Finalidade $finalidade)`
 *     → consumidores devem PARAR todo tratamento baseado naquela finalidade.
 *   - `pi_solicitacao_titular_protocolada(int $solicitacaoId)`
 *     → alerta o DPO.
 *
 * Toda chamada é no-op se o WordPress não estiver carregado (testes unit).
 */
final class ConsentimentoEventos
{
    public const HOOK_REGISTRADO  = 'pi_consentimento_registrado';
    public const HOOK_REVOGADO    = 'pi_consentimento_revogado';
    public const HOOK_SOLICITACAO = 'pi_solicitacao_titular_protocolada';

    public static function dispararRegistrado(int $consentimentoId, Finalidade $finalidade): void
    {
        if (function_exists('do_action')) {
            \do_action(self::HOOK_REGISTRADO, $consentimentoId, $finalidade);
        }
    }

    public static function dispararRevogado(int $consentimentoId, Finalidade $finalidade): void
    {
        if (function_exists('do_action')) {
            \do_action(self::HOOK_REVOGADO, $consentimentoId, $finalidade);
        }
    }

    public static function dispararSolicitacaoProtocolada(int $solicitacaoId): void
    {
        if (function_exists('do_action')) {
            \do_action(self::HOOK_SOLICITACAO, $solicitacaoId);
        }
    }
}
