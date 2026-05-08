<?php
/**
 * AgenteSummary — extracts display strings from an Agente + tipo-specific details.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Helpers;

use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;

/**
 * Pure helpers (no I/O) used by list tables and the detalhes controller to
 * render agent identity blocks. Keeps tipo-aware logic in a single place so
 * templates remain dumb.
 */
final class AgenteSummary
{
    /**
     * Returns the human-facing name of the agent:
     *  - PF: nome_social if present, else nome_completo.
     *  - OR: nome_organizacao.
     *  - SM: nome_orgao.
     *
     * @param Agente                     $agente
     * @param AgentePF|AgenteOR|AgenteSM $detalhes
     */
    public static function nomeAgente(Agente $agente, object $detalhes): string
    {
        $tipo = $agente->getTipo()->value();

        if ($tipo === TipoAgente::PF && $detalhes instanceof AgentePF) {
            $social = (string) ($detalhes->getNomeSocial() ?? '');
            if ($social !== '') {
                return $social;
            }
            return $detalhes->getNomeCompleto();
        }
        if ($tipo === TipoAgente::OR && $detalhes instanceof AgenteOR) {
            return $detalhes->getNomeOrganizacao();
        }
        if ($tipo === TipoAgente::SM && $detalhes instanceof AgenteSM) {
            return $detalhes->getNomeOrgao();
        }

        return '—';
    }

    /**
     * Maps the canonical PF/OR/SM code to a translated label for the UI.
     */
    public static function tipoLabel(string $tipo): string
    {
        switch (strtoupper(trim($tipo))) {
            case TipoAgente::PF:
                return self::translate('Pessoa Física');
            case TipoAgente::OR:
                return self::translate('Organização');
            case TipoAgente::SM:
                return self::translate('Sistema/Secretaria');
            default:
                return $tipo;
        }
    }

    /**
     * Status badge tuple: ['label' => string, 'variant' => string].
     *
     * Variants map to CSS modifiers:
     *  - draft (rascunho)
     *  - info (submetido / em_analise)
     *  - success (deferido*)
     *  - warning (indeferido_aguardando_recurso, em_retratacao, em_recurso_presidencia)
     *  - danger (indeferido_final)
     *
     * @return array{label:string, variant:string, code:string}
     */
    public static function statusBadge(StatusCadastro $status): array
    {
        $code   = $status->value();
        $labels = self::statusLabels();
        $label  = $labels[$code] ?? $code;

        $variant = 'default';
        switch ($code) {
            case StatusCadastro::RASCUNHO:
                $variant = 'draft';
                break;
            case StatusCadastro::SUBMETIDO:
            case StatusCadastro::EM_ANALISE:
                $variant = 'info';
                break;
            case StatusCadastro::DEFERIDO:
            case StatusCadastro::DEFERIDO_EM_RETRATACAO:
            case StatusCadastro::DEFERIDO_EM_RECURSO:
                $variant = 'success';
                break;
            case StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO:
            case StatusCadastro::EM_RETRATACAO:
            case StatusCadastro::EM_RECURSO_PRESIDENCIA:
                $variant = 'warning';
                break;
            case StatusCadastro::INDEFERIDO_FINAL:
                $variant = 'danger';
                break;
        }

        return [
            'label'   => $label,
            'variant' => $variant,
            'code'    => $code,
        ];
    }

    /**
     * Map every status code to its translated label.
     *
     * @return array<string,string>
     */
    public static function statusLabels(): array
    {
        return [
            StatusCadastro::RASCUNHO                      => self::translate('Rascunho'),
            StatusCadastro::SUBMETIDO                     => self::translate('Submetido'),
            StatusCadastro::EM_ANALISE                    => self::translate('Em análise'),
            StatusCadastro::DEFERIDO                      => self::translate('Deferido'),
            StatusCadastro::DEFERIDO_EM_RETRATACAO        => self::translate('Deferido (retratação)'),
            StatusCadastro::DEFERIDO_EM_RECURSO           => self::translate('Deferido (recurso)'),
            StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO => self::translate('Indeferido — aguardando recurso'),
            StatusCadastro::EM_RETRATACAO                 => self::translate('Em retratação'),
            StatusCadastro::EM_RECURSO_PRESIDENCIA        => self::translate('Em recurso (presidência)'),
            StatusCadastro::INDEFERIDO_FINAL              => self::translate('Indeferido (final)'),
        ];
    }

    /**
     * Computes elapsed days under analysis (status submetido ou em_analise).
     * Returns null when status is not in those states or submetidoEm is null.
     */
    public static function tempoEmAnaliseDias(Agente $agente, ?\DateTimeImmutable $now = null): ?int
    {
        $code = $agente->getStatusCadastro()->value();
        if ($code !== StatusCadastro::SUBMETIDO && $code !== StatusCadastro::EM_ANALISE) {
            return null;
        }
        $sub = $agente->getSubmetidoEm();
        if ($sub === null) {
            return null;
        }
        $now      = $now ?? new \DateTimeImmutable('now');
        $interval = $now->diff($sub);

        return (int) $interval->days;
    }

    /**
     * Format the numero_registro for display (returns em-dash if null).
     */
    public static function numeroRegistroOrDash(Agente $agente): string
    {
        $numero = $agente->getNumeroRegistro();
        return $numero !== null ? $numero->value() : '—';
    }

    private static function translate(string $text): string
    {
        if (function_exists('__')) {
            return (string) \__($text, 'participe-ibram');
        }
        return $text;
    }
}
