<?php
/**
 * SidebarRenderer — emite o HTML do sidebar de navegação do plugin.
 *
 * Renderiza o <aside class="pi-sidebar"> com grupos expansíveis, ícones
 * Dashicons, estado activo, filtragem de capability e acessibilidade WCAG 2.1 AA.
 *
 * Comportamento responsivo (≤900px): botão hamburger toggle gerado aqui;
 * JS inline mínimo ao final do método render().
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Renderizador do sidebar lateral de navegação admin do Participe Ibram.
 *
 * Toda saída é escapada via esc_html / esc_url / esc_attr.
 * Text-domain: participe-ibram.
 * WCAG 2.1 AA: nav/aside semânticos, aria-current, aria-labelledby, focus rings.
 */
final class SidebarRenderer
{
    /**
     * Emite o HTML completo do sidebar.
     *
     * Deve ser chamado DENTRO de `.pi-admin-page-shell`, antes de `<main>`.
     */
    public static function render(): void
    {
        $groups = SidebarNavigation::getGroups();

        // Construir a URL base de admin.php uma única vez.
        $adminBase = function_exists('admin_url')
            ? \admin_url('admin.php')
            : 'admin.php';

        echo '<aside class="pi-sidebar" id="pi-sidebar-nav" role="navigation" aria-label="'
            . \esc_attr__('Navegação do Participe Ibram', 'participe-ibram')
            . '">' . "\n";

        // ── Botão hamburger (visível apenas em ≤900px via CSS) ────────────────
        echo '<button class="pi-sidebar__toggle" '
            . 'aria-controls="pi-sidebar-nav-list" '
            . 'aria-expanded="false" '
            . 'type="button">'
            . '<span class="dashicons dashicons-menu" aria-hidden="true"></span>'
            . ' ' . \esc_html__('Menu', 'participe-ibram')
            . '</button>' . "\n";

        // ── Lista de grupos ───────────────────────────────────────────────────
        echo '<div class="pi-sidebar__nav-list" id="pi-sidebar-nav-list">' . "\n";

        foreach ($groups as $groupIndex => $group) {
            $groupTitle = $group['title'];
            $items      = $group['items'];

            if (empty($items)) {
                continue;
            }

            echo '<div class="pi-sidebar__group">' . "\n";

            // Cabeçalho do grupo (Grupo 1 — Visão Geral tem title null)
            if ($groupTitle !== null && $groupTitle !== '') {
                $headingId = 'pi-sidebar-group-' . $groupIndex;
                echo '<h2 class="pi-sidebar__group-title" id="'
                    . \esc_attr($headingId)
                    . '">'
                    . \esc_html($groupTitle)
                    . '</h2>' . "\n";
                echo '<ul class="pi-sidebar__group-items" role="list" aria-labelledby="'
                    . \esc_attr($headingId)
                    . '">' . "\n";
            } else {
                echo '<ul class="pi-sidebar__group-items" role="list">' . "\n";
            }

            foreach ($items as $item) {
                $slug     = (string) $item['slug'];
                $label    = (string) $item['label'];
                $icon     = (string) $item['icon'];
                $isActive = (bool)   $item['is_active'];

                $itemUrl = \esc_url($adminBase . '?page=' . $slug);

                $liClasses = 'pi-sidebar__item';
                if ($isActive) {
                    $liClasses .= ' is-active';
                }

                $ariaCurrent = $isActive
                    ? ' aria-current="page"'
                    : '';

                echo '<li class="' . \esc_attr($liClasses) . '">' . "\n";
                echo '<a class="pi-sidebar__link" href="' . $itemUrl . '"'
                    . $ariaCurrent
                    . '>' . "\n";
                echo '<span class="dashicons ' . \esc_attr($icon) . '" aria-hidden="true"></span>' . "\n";
                echo '<span class="pi-sidebar__label">' . \esc_html($label) . '</span>' . "\n";
                echo '</a>' . "\n";
                echo '</li>' . "\n";
            }

            echo '</ul>' . "\n";
            echo '</div>' . "\n"; // .pi-sidebar__group
        }

        echo '</div>' . "\n"; // .pi-sidebar__nav-list

        echo '</aside>' . "\n";

        // ── JS inline mínimo para o toggle responsivo ─────────────────────────
        // Apenas wiring do botão hamburger — sem ficheiro externo.
        echo '<script>' . "\n";
        echo '(function(){' . "\n";
        echo '  var btn = document.getElementById("pi-sidebar-nav") ? ' . "\n";
        echo '    document.querySelector("#pi-sidebar-nav .pi-sidebar__toggle") : null;' . "\n";
        echo '  if (!btn) return;' . "\n";
        echo '  var navList = document.getElementById("pi-sidebar-nav-list");' . "\n";
        echo '  if (!navList) return;' . "\n";
        echo '  btn.addEventListener("click", function() {' . "\n";
        echo '    var expanded = btn.getAttribute("aria-expanded") === "true";' . "\n";
        echo '    btn.setAttribute("aria-expanded", expanded ? "false" : "true");' . "\n";
        echo '    navList.classList.toggle("pi-sidebar__nav-list--open");' . "\n";
        echo '  });' . "\n";
        echo '})();' . "\n";
        echo '</script>' . "\n";
    }
}
