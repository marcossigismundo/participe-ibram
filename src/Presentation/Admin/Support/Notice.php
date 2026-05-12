<?php
/**
 * Notice — helper estático para avisos admin (.pi-notice).
 *
 * Uso:
 *   Notice::success(__('Edital publicado com sucesso!', 'participe-ibram'));
 *   Notice::warning(__('Prazo se encerra em 2 dias.', 'participe-ibram'), true);
 *   Notice::danger(__('Falha ao salvar. Tente novamente.', 'participe-ibram'));
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Renderiza avisos visuais .pi-notice em quatro variantes semânticas.
 *
 * NÃO interfere com o .notice do WordPress core — classes completamente
 * distintas para evitar conflito de estilo.
 *
 * Toda saída passa por esc_html / esc_attr.
 * Text-domain: participe-ibram.
 *
 * WCAG 2.1 AA:
 *  - Notices informativos/sucesso: role="status" (polido, não interrompe)
 *  - Notices de alerta/erro:       role="alert"  (assertivo, anunciado imediatamente)
 *  - Ícone decorativo via CSS pseudo-element; conteúdo de texto sempre presente.
 *  - Botão fechar: aria-label localizado.
 */
final class Notice
{
    // ── API pública ──────────────────────────────────────────────────────────

    /**
     * Renderiza um notice informativo (azul).
     *
     * @param string $message     Mensagem HTML-escaped (plain text, sem tags).
     * @param bool   $dismissible Se true, exibe botão de fechar.
     */
    public static function info(string $message, bool $dismissible = false): void
    {
        self::render('info', $message, $dismissible);
    }

    /**
     * Renderiza um notice de sucesso (verde).
     *
     * @param string $message     Mensagem HTML-escaped (plain text, sem tags).
     * @param bool   $dismissible Se true, exibe botão de fechar.
     */
    public static function success(string $message, bool $dismissible = false): void
    {
        self::render('success', $message, $dismissible);
    }

    /**
     * Renderiza um notice de aviso (amarelo-laranja).
     *
     * @param string $message     Mensagem HTML-escaped (plain text, sem tags).
     * @param bool   $dismissible Se true, exibe botão de fechar.
     */
    public static function warning(string $message, bool $dismissible = false): void
    {
        self::render('warning', $message, $dismissible);
    }

    /**
     * Renderiza um notice de erro/perigo (vermelho).
     *
     * @param string $message     Mensagem HTML-escaped (plain text, sem tags).
     * @param bool   $dismissible Se true, exibe botão de fechar.
     */
    public static function danger(string $message, bool $dismissible = false): void
    {
        self::render('danger', $message, $dismissible);
    }

    // ── Implementação interna ────────────────────────────────────────────────

    /**
     * Renderiza o HTML do componente .pi-notice.
     *
     * @param string $variant     'info' | 'success' | 'warning' | 'danger'
     * @param string $message     Mensagem (plain text; será esc_html'd).
     * @param bool   $dismissible Exibe botão fechar.
     */
    private static function render(
        string $variant,
        string $message,
        bool $dismissible
    ): void {
        // Notices assertivos (interrompem leitor) para warning/danger.
        // Notices polidos (não interrompem) para info/success.
        $role = in_array($variant, ['warning', 'danger'], true) ? 'alert' : 'status';

        $cssClasses = 'pi-notice pi-notice--' . esc_attr($variant);
        if ($dismissible) {
            $cssClasses .= ' pi-notice--dismissible';
        }

        echo '<div class="' . $cssClasses . '" role="' . esc_attr($role) . '">' . "\n";

        // Ícone decorativo (visual via CSS ::before; aria-hidden)
        echo '<span class="pi-notice__icon" aria-hidden="true"></span>' . "\n";

        // Corpo
        echo '<div class="pi-notice__body">' . "\n";
        echo '<p class="pi-notice__message">' . esc_html($message) . '</p>' . "\n";
        echo '</div>' . "\n"; // .pi-notice__body

        // Botão fechar
        if ($dismissible) {
            echo '<button type="button" class="pi-notice__close" aria-label="'
                . esc_attr__('Fechar aviso', 'participe-ibram')
                . '">'
                . '<span aria-hidden="true">&times;</span>'
                . '</button>' . "\n";
        }

        echo '</div>' . "\n"; // .pi-notice
    }
}
