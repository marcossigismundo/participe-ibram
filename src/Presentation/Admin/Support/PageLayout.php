<?php
/**
 * PageLayout — helper estático para chrome de páginas admin.
 *
 * Uso mínimo:
 *   PageLayout::open('Título');
 *   // … conteúdo da tela …
 *   PageLayout::close();
 *
 * Com breadcrumbs e ação primária:
 *   PageLayout::open(
 *       'Editais',
 *       [['label' => 'Início', 'url' => admin_url()], ['label' => 'Editais']],
 *       ['label' => 'Novo edital', 'url' => admin_url('admin.php?page=pi_edital_novo')]
 *   );
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Gera o chrome HTML compartilhado por todas as telas admin do plugin.
 * Produz as classes .participe-ibram-scope + .pi-admin-page + .wrap do WP.
 *
 * Wave 12: layout de 2 colunas com sidebar lateral (.pi-admin-page-shell).
 * O <aside class="pi-sidebar"> é renderizado por SidebarRenderer::render()
 * antes de <main class="pi-content">. O header, breadcrumb, ações e
 * content-card ficam dentro de <main>.
 *
 * Toda saída passa por esc_html / esc_url / esc_attr.
 * Text-domain: participe-ibram.
 *
 * WCAG 2.1 AA:
 *  - Breadcrumb: aria-label="Trilha de navegação", item atual aria-current="page"
 *  - Botões de ação: texto visível (não apenas ícone)
 *  - Sidebar: navegação semântica (aside + nav + aria-label), aria-current
 */
final class PageLayout
{
    /**
     * Abre o wrapper da página admin e renderiza sidebar + header + ações.
     *
     * Estrutura emitida (Wave 12):
     *   <div class="participe-ibram-scope">
     *     <div class="wrap pi-admin-page">
     *       <div class="pi-admin-page-shell">
     *         <aside class="pi-sidebar" …>…</aside>   ← SidebarRenderer
     *         <main class="pi-content">
     *           <header class="pi-admin-page__header">…</header>
     *           <div class="pi-admin-page__content-card">  ← fechado em close()
     *
     * @param string                                                          $title           Título principal da página (h1).
     * @param array<int,array{label:string,url?:string}>                      $breadcrumbs     Itens da trilha. O último é o atual.
     * @param array{label:string,url:string,class?:string}|null               $primaryAction   Botão/link principal no header.
     * @param array<int,array{label:string,url:string}>                       $secondaryActions Links secundários.
     */
    public static function open(
        string $title,
        array $breadcrumbs = [],
        ?array $primaryAction = null,
        array $secondaryActions = []
    ): void {
        // Abertura do wrapper raiz: 2 elementos aninhados.
        // .participe-ibram-scope é o ANCESTRAL que escopa todos os seletores
        // do design system (`.participe-ibram-scope .pi-X`); .pi-admin-page é o
        // descendente real que recebe o chrome da página.
        echo '<div class="participe-ibram-scope">' . "\n";
        echo '<div class="wrap pi-admin-page">' . "\n";

        // ── Shell de 2 colunas: sidebar + main ────────────────────────────────
        echo '<div class="pi-admin-page-shell">' . "\n";

        // Sidebar lateral (Wave 12) — renderiza <aside class="pi-sidebar">
        SidebarRenderer::render();

        // Abertura da coluna de conteúdo principal
        echo '<main class="pi-content">' . "\n";

        // ── Header ───────────────────────────────────────────────────────────
        echo '<header class="pi-admin-page__header">' . "\n";

        // Breadcrumbs (renderiza apenas se houver items)
        if (!empty($breadcrumbs)) {
            self::breadcrumbs($breadcrumbs);
        }

        // Linha título + ações
        echo '<div class="pi-admin-page__header-row">' . "\n";
        echo '<h1 class="pi-admin-page__title">' . esc_html($title) . '</h1>' . "\n";

        // Ações (primária + secundárias)
        $hasActions = $primaryAction !== null || !empty($secondaryActions);
        if ($hasActions) {
            echo '<div class="pi-admin-page__actions">' . "\n";

            // Ações secundárias primeiro (ficam à esquerda do primário)
            foreach ($secondaryActions as $sec) {
                $secLabel = isset($sec['label']) ? (string) $sec['label'] : '';
                $secUrl   = isset($sec['url'])   ? (string) $sec['url']   : '#';
                if ($secLabel === '') {
                    continue;
                }
                echo '<a class="pi-button pi-button--secondary pi-button--sm" href="'
                    . esc_url($secUrl) . '">'
                    . esc_html($secLabel)
                    . '</a>' . "\n";
            }

            // Ação primária
            if ($primaryAction !== null) {
                $priLabel = isset($primaryAction['label']) ? (string) $primaryAction['label'] : '';
                $priUrl   = isset($primaryAction['url'])   ? (string) $primaryAction['url']   : '#';
                $priClass = isset($primaryAction['class']) ? ' ' . (string) $primaryAction['class'] : '';

                if ($priLabel !== '') {
                    echo '<a class="pi-button pi-button--primary' . esc_attr($priClass) . '" href="'
                        . esc_url($priUrl) . '">'
                        . esc_html($priLabel)
                        . '</a>' . "\n";
                }
            }

            echo '</div>' . "\n"; // .pi-admin-page__actions
        }

        echo '</div>' . "\n"; // .pi-admin-page__header-row
        echo '</header>' . "\n"; // header.pi-admin-page__header

        // Abertura do content-card (o chamador coloca o conteúdo; close() fecha)
        echo '<div class="pi-admin-page__content-card">' . "\n";
    }

    /**
     * Fecha o content-card, opcional nota de rodapé e o wrapper raiz.
     *
     * @param string|null $contextualHelpUrl URL para "Saiba mais" no rodapé.
     */
    public static function close(?string $contextualHelpUrl = null): void
    {
        echo '</div>' . "\n"; // .pi-admin-page__content-card

        if ($contextualHelpUrl !== null && $contextualHelpUrl !== '') {
            echo '<p class="pi-admin-page__footer-hint">'
                . esc_html__('Precisa de ajuda?', 'participe-ibram')
                . ' <a href="' . esc_url($contextualHelpUrl) . '">'
                . esc_html__('Consulte a documentação', 'participe-ibram')
                . '</a>.</p>' . "\n";
        }

        echo '</main>' . "\n";         // main.pi-content
        echo '</div>' . "\n";          // .pi-admin-page-shell
        echo '</div>' . "\n";          // .wrap.pi-admin-page
        echo '</div>' . "\n";          // .participe-ibram-scope
    }

    /**
     * Renderiza a trilha de navegação (breadcrumb) de forma autônoma.
     *
     * O último item é sempre tratado como página atual (aria-current="page").
     * Se o último item tiver 'url', ela é ignorada (item atual não é link).
     *
     * @param array<int,array{label:string,url?:string}> $items Itens da trilha (mínimo 1).
     */
    public static function breadcrumbs(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $lastIndex = count($items) - 1;

        echo '<nav class="pi-breadcrumb" aria-label="'
            . esc_attr__('Trilha de navegação', 'participe-ibram')
            . '">' . "\n";
        echo '<ol class="pi-breadcrumb__list">' . "\n";

        foreach ($items as $index => $item) {
            $label     = isset($item['label']) ? (string) $item['label'] : '';
            $url       = isset($item['url'])   ? (string) $item['url']   : '';
            $isCurrent = ($index === $lastIndex);

            if ($label === '') {
                continue;
            }

            if ($isCurrent) {
                // Item atual: sem link, com aria-current
                echo '<li class="pi-breadcrumb__item" aria-current="page">'
                    . esc_html($label)
                    . '</li>' . "\n";
            } else {
                // Item intermediário: com link
                echo '<li class="pi-breadcrumb__item"><a href="'
                    . esc_url($url !== '' ? $url : '#')
                    . '">' . esc_html($label) . '</a></li>' . "\n";
            }
        }

        echo '</ol>' . "\n";
        echo '</nav>' . "\n";
    }
}
