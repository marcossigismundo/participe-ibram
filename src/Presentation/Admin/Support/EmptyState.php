<?php
/**
 * EmptyState — helper para renderizar estado vazio em telas admin.
 *
 * Uso típico:
 *   EmptyState::render(
 *       __('Nenhum cadastro encontrado', 'participe-ibram'),
 *       __('Ainda não há cadastros submetidos para análise.', 'participe-ibram'),
 *       ['label' => __('Ver documentação', 'participe-ibram'), 'url' => 'https://…'],
 *       'dashicons-id'
 *   );
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Renderiza o componente .pi-empty-state.
 *
 * Toda saída passa por esc_html / esc_url / esc_attr.
 * Text-domain: participe-ibram.
 *
 * WCAG 2.1 AA:
 *  - Ícone decorativo aria-hidden="true"
 *  - CTA é link descritivo (não apenas "clique aqui")
 */
final class EmptyState
{
    /**
     * Renderiza o componente de estado vazio.
     *
     * @param string                              $title   Título (h3) do estado vazio.
     * @param string                              $message Mensagem descritiva para o usuário.
     * @param array{label:string,url:string}|null $cta     Ação principal (link/botão). Null = sem CTA.
     * @param string|null                         $icon    Classe CSS do ícone (ex.: "dashicons-id").
     *                                                     Null = círculo padrão com ícone genérico.
     */
    public static function render(
        string $title,
        string $message,
        ?array $cta = null,
        ?string $icon = null
    ): void {
        echo '<div class="pi-empty-state">' . "\n";

        // ── Ilustração / ícone ────────────────────────────────────────────
        echo '<div class="pi-empty-state__illustration" aria-hidden="true">' . "\n";

        if ($icon !== null && $icon !== '') {
            // Ícone fornecido (dashicons ou outra fonte de ícones)
            echo '<span class="dashicons ' . esc_attr($icon) . '"></span>' . "\n";
        } else {
            // Ícone genérico de "lista vazia" via dashicons
            echo '<span class="dashicons dashicons-database-view"></span>' . "\n";
        }

        echo '</div>' . "\n"; // .pi-empty-state__illustration

        // ── Título ────────────────────────────────────────────────────────
        echo '<h3 class="pi-empty-state__title">' . esc_html($title) . '</h3>' . "\n";

        // ── Mensagem ──────────────────────────────────────────────────────
        echo '<p class="pi-empty-state__message">' . esc_html($message) . '</p>' . "\n";

        // ── CTA ───────────────────────────────────────────────────────────
        if ($cta !== null) {
            $ctaLabel = isset($cta['label']) ? (string) $cta['label'] : '';
            $ctaUrl   = isset($cta['url'])   ? (string) $cta['url']   : '#';

            if ($ctaLabel !== '') {
                echo '<div class="pi-empty-state__cta">' . "\n";
                echo '<a class="pi-button pi-button--primary" href="'
                    . esc_url($ctaUrl) . '">'
                    . esc_html($ctaLabel)
                    . '</a>' . "\n";
                echo '</div>' . "\n"; // .pi-empty-state__cta
            }
        }

        echo '</div>' . "\n"; // .pi-empty-state
    }
}
