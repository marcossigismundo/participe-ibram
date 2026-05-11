<?php
/**
 * Template parcial: aba "Histórico" da Minha Conta (W8-C).
 *
 * - Sub-tabs ARIA (Cadastro / Inscrições / Recursos / Votos / Eventos).
 * - Estados de carregamento e vazios acessíveis (live region).
 * - Voto secreto: aviso destacado na sub-tab "Votos".
 * - Botão "Regerar recibo" por voto (read-only seguro — sem confirmação).
 * - Eventos: descrição amigável em pt_BR (não expõe dados crus).
 *
 * Marcação esqueleto — preenchimento via HistoricoUI.js. Server-side renderiza
 * apenas estrutura segura (sem dados PII). Toda PII vem por REST autenticado.
 *
 * @package Ibram\ParticipeIbram
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<section
    class="participe-ibram-scope pi-historico"
    aria-labelledby="pi-historico-title"
    data-pi-historico-root="1"
>
    <header class="pi-historico__header">
        <h2 id="pi-historico-title" class="pi-historico__title" tabindex="-1">
            <?php esc_html_e('Histórico', 'participe-ibram'); ?>
        </h2>
        <p class="pi-historico__subtitle">
            <?php esc_html_e(
                'Consulte sua linha do tempo: status do cadastro, inscrições, recursos, votos e eventos.',
                'participe-ibram'
            ); ?>
        </p>
    </header>

    <div class="pi-historico__live" role="status" aria-live="polite" aria-atomic="true">
        <span class="pi-sr-only" data-pi-historico-live></span>
    </div>

    <div class="pi-historico__tabs" role="tablist" aria-label="<?php
        echo esc_attr__('Sub-abas do histórico', 'participe-ibram'); ?>">
        <button
            type="button"
            role="tab"
            id="pi-tab-cadastro"
            class="pi-historico__tab"
            aria-controls="pi-panel-cadastro"
            aria-selected="true"
            tabindex="0"
            data-pi-tab="cadastro"
        >
            <?php esc_html_e('Cadastro', 'participe-ibram'); ?>
        </button>
        <button
            type="button"
            role="tab"
            id="pi-tab-inscricoes"
            class="pi-historico__tab"
            aria-controls="pi-panel-inscricoes"
            aria-selected="false"
            tabindex="-1"
            data-pi-tab="inscricoes"
        >
            <?php esc_html_e('Inscrições', 'participe-ibram'); ?>
        </button>
        <button
            type="button"
            role="tab"
            id="pi-tab-recursos"
            class="pi-historico__tab"
            aria-controls="pi-panel-recursos"
            aria-selected="false"
            tabindex="-1"
            data-pi-tab="recursos"
        >
            <?php esc_html_e('Recursos', 'participe-ibram'); ?>
        </button>
        <button
            type="button"
            role="tab"
            id="pi-tab-votos"
            class="pi-historico__tab"
            aria-controls="pi-panel-votos"
            aria-selected="false"
            tabindex="-1"
            data-pi-tab="votos"
        >
            <?php esc_html_e('Votos', 'participe-ibram'); ?>
        </button>
        <button
            type="button"
            role="tab"
            id="pi-tab-auditoria"
            class="pi-historico__tab"
            aria-controls="pi-panel-auditoria"
            aria-selected="false"
            tabindex="-1"
            data-pi-tab="auditoria"
        >
            <?php esc_html_e('Eventos', 'participe-ibram'); ?>
        </button>
    </div>

    <!-- Cadastro -->
    <section
        id="pi-panel-cadastro"
        role="tabpanel"
        aria-labelledby="pi-tab-cadastro"
        class="pi-historico__panel"
        tabindex="0"
    >
        <h3 class="pi-historico__panel-title">
            <?php esc_html_e('Status do seu cadastro', 'participe-ibram'); ?>
        </h3>
        <ol class="pi-historico__timeline" data-pi-list="cadastro" aria-live="off">
            <li class="pi-historico__loading" data-pi-loading><?php
                esc_html_e('Carregando…', 'participe-ibram'); ?></li>
        </ol>
        <p class="pi-historico__empty" hidden data-pi-empty="cadastro">
            <?php esc_html_e('Você ainda não tem transições de status registradas.', 'participe-ibram'); ?>
        </p>
    </section>

    <!-- Inscrições -->
    <section
        id="pi-panel-inscricoes"
        role="tabpanel"
        aria-labelledby="pi-tab-inscricoes"
        class="pi-historico__panel"
        tabindex="0"
        hidden
    >
        <h3 class="pi-historico__panel-title">
            <?php esc_html_e('Minhas inscrições em editais', 'participe-ibram'); ?>
        </h3>
        <ol class="pi-historico__cards" data-pi-list="inscricoes" aria-live="off">
            <li class="pi-historico__loading" data-pi-loading><?php
                esc_html_e('Carregando…', 'participe-ibram'); ?></li>
        </ol>
        <nav class="pi-historico__paginacao" data-pi-paginacao="inscricoes" hidden
             aria-label="<?php echo esc_attr__('Paginação de inscrições', 'participe-ibram'); ?>">
            <button type="button" class="pi-btn pi-btn--terciario" data-pi-page-prev disabled>
                <?php esc_html_e('Anterior', 'participe-ibram'); ?>
            </button>
            <span class="pi-historico__pagina" data-pi-page-label></span>
            <button type="button" class="pi-btn pi-btn--terciario" data-pi-page-next disabled>
                <?php esc_html_e('Próxima', 'participe-ibram'); ?>
            </button>
        </nav>
        <p class="pi-historico__empty" hidden data-pi-empty="inscricoes">
            <?php esc_html_e('Você ainda não se inscreveu em nenhum edital.', 'participe-ibram'); ?>
        </p>
    </section>

    <!-- Recursos -->
    <section
        id="pi-panel-recursos"
        role="tabpanel"
        aria-labelledby="pi-tab-recursos"
        class="pi-historico__panel"
        tabindex="0"
        hidden
    >
        <h3 class="pi-historico__panel-title">
            <?php esc_html_e('Meus recursos', 'participe-ibram'); ?>
        </h3>
        <ol class="pi-historico__cards" data-pi-list="recursos" aria-live="off">
            <li class="pi-historico__loading" data-pi-loading><?php
                esc_html_e('Carregando…', 'participe-ibram'); ?></li>
        </ol>
        <p class="pi-historico__empty" hidden data-pi-empty="recursos">
            <?php esc_html_e('Você ainda não protocolou recursos.', 'participe-ibram'); ?>
        </p>
    </section>

    <!-- Votos (VOTO SECRETO) -->
    <section
        id="pi-panel-votos"
        role="tabpanel"
        aria-labelledby="pi-tab-votos"
        class="pi-historico__panel"
        tabindex="0"
        hidden
    >
        <h3 class="pi-historico__panel-title">
            <?php esc_html_e('Meus votos', 'participe-ibram'); ?>
        </h3>
        <div class="pi-historico__aviso-secreto" role="note">
            <strong><?php esc_html_e('Voto secreto.', 'participe-ibram'); ?></strong>
            <?php esc_html_e(
                'O sistema mostra apenas que você votou — nunca em quem. Esta é uma proteção legal contra coerção.',
                'participe-ibram'
            ); ?>
        </div>
        <ol class="pi-historico__cards pi-historico__cards--votos"
            data-pi-list="votos" aria-live="off">
            <li class="pi-historico__loading" data-pi-loading><?php
                esc_html_e('Carregando…', 'participe-ibram'); ?></li>
        </ol>
        <p class="pi-historico__empty" hidden data-pi-empty="votos">
            <?php esc_html_e('Você ainda não registrou votos.', 'participe-ibram'); ?>
        </p>
    </section>

    <!-- Eventos / Auditoria -->
    <section
        id="pi-panel-auditoria"
        role="tabpanel"
        aria-labelledby="pi-tab-auditoria"
        class="pi-historico__panel"
        tabindex="0"
        hidden
    >
        <h3 class="pi-historico__panel-title">
            <?php esc_html_e('Eventos da minha conta', 'participe-ibram'); ?>
        </h3>
        <p class="pi-historico__hint">
            <?php esc_html_e(
                'Ações que você realizou ou que afetaram seu cadastro. Os textos abaixo descrevem o evento — detalhes técnicos não são exibidos por segurança.',
                'participe-ibram'
            ); ?>
        </p>
        <ol class="pi-historico__timeline" data-pi-list="auditoria" aria-live="off">
            <li class="pi-historico__loading" data-pi-loading><?php
                esc_html_e('Carregando…', 'participe-ibram'); ?></li>
        </ol>
        <nav class="pi-historico__paginacao" data-pi-paginacao="auditoria" hidden
             aria-label="<?php echo esc_attr__('Paginação de eventos', 'participe-ibram'); ?>">
            <button type="button" class="pi-btn pi-btn--terciario" data-pi-page-prev disabled>
                <?php esc_html_e('Anterior', 'participe-ibram'); ?>
            </button>
            <span class="pi-historico__pagina" data-pi-page-label></span>
            <button type="button" class="pi-btn pi-btn--terciario" data-pi-page-next disabled>
                <?php esc_html_e('Próxima', 'participe-ibram'); ?>
            </button>
        </nav>
        <p class="pi-historico__empty" hidden data-pi-empty="auditoria">
            <?php esc_html_e('Nenhum evento registrado.', 'participe-ibram'); ?>
        </p>
    </section>

    <!-- Modal de recibo (preenchido pelo HistoricoUI.js) -->
    <div
        class="pi-modal pi-modal--recibo"
        id="pi-historico-recibo-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pi-historico-recibo-title"
        aria-describedby="pi-historico-recibo-desc"
        hidden
        data-pi-recibo-modal
    >
        <div class="pi-modal__backdrop" data-pi-modal-close></div>
        <div class="pi-modal__dialog" role="document">
            <header class="pi-modal__header">
                <h2 id="pi-historico-recibo-title" class="pi-modal__title" tabindex="-1">
                    <?php esc_html_e('Recibo do voto', 'participe-ibram'); ?>
                </h2>
                <button
                    type="button"
                    class="pi-modal__close"
                    data-pi-modal-close
                    aria-label="<?php echo esc_attr__('Fechar', 'participe-ibram'); ?>"
                >&times;</button>
            </header>
            <div class="pi-modal__body">
                <p id="pi-historico-recibo-desc" class="pi-historico__recibo-aviso">
                    <?php esc_html_e(
                        'Este é o código de integridade do seu voto. Ele NÃO revela em quem você votou — apenas comprova que o voto foi gravado naquele instante.',
                        'participe-ibram'
                    ); ?>
                </p>
                <dl class="pi-historico__recibo-dados">
                    <dt><?php esc_html_e('Votação:', 'participe-ibram'); ?></dt>
                    <dd data-pi-recibo-votacao>—</dd>
                    <dt><?php esc_html_e('Data e hora:', 'participe-ibram'); ?></dt>
                    <dd data-pi-recibo-votado-em>—</dd>
                </dl>
                <label class="pi-historico__recibo-label" for="pi-historico-recibo-hash">
                    <?php esc_html_e('Hash do recibo:', 'participe-ibram'); ?>
                </label>
                <input
                    type="text"
                    id="pi-historico-recibo-hash"
                    class="pi-historico__recibo-hash"
                    readonly
                    aria-readonly="true"
                    spellcheck="false"
                    data-pi-recibo-hash
                    value=""
                />
                <div class="pi-historico__recibo-acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-pi-recibo-copiar>
                        <?php esc_html_e('Copiar', 'participe-ibram'); ?>
                    </button>
                </div>
                <p class="pi-historico__recibo-status pi-sr-only"
                   role="status" aria-live="polite" data-pi-recibo-status></p>
            </div>
        </div>
    </div>
</section>
