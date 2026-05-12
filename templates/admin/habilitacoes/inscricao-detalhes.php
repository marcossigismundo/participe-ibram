<?php
/**
 * Template — detalhe de inscrição com tabs ARIA e modais de decisão (W5-C).
 *
 * Vars injetadas por InscricaoDetalhesController::render():
 *  - Inscricao $inscricao
 *  - array{level:string,message:string}|null $flash
 *  - string $nonce
 *  - string $listaUrl
 *  - string $agenteDetalhesUrl
 *  - int $podeDecidirId (userId — cap é verificada no controller/ajax)
 *
 * NOTA PII: CPF, e-mail e telefone do agente NÃO são renderizados aqui.
 * Use o link "Ver dados do agente" → agente-detalhes.php (W4-A) gateado por
 * cap `pi_visualizar_dados_sensiveis`.
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Habilitacoes
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\InscricaoDetalhesController;
use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Domain\Edital\Inscricao $inscricao */
/** @var array{level:string,message:string}|null $flash */
/** @var string $nonce */
/** @var string $listaUrl */
/** @var string $agenteDetalhesUrl */
/** @var int $podeDecidirId */

$status        = $inscricao->status()->value();
$podeDecidirHab = current_user_can(HabilitacaoMenuRegistry::CAP_HABILITACAO);

$statusVariants = [
    StatusInscricao::INSCRITO       => 'info',
    StatusInscricao::EM_HABILITACAO => 'warning',
    StatusInscricao::HABILITADO     => 'success',
    StatusInscricao::INABILITADO    => 'danger',
    StatusInscricao::EM_RECURSO     => 'warning',
    StatusInscricao::FINAL_HABILITADO   => 'success',
    StatusInscricao::FINAL_INABILITADO  => 'danger',
];
$statusLabels = [
    StatusInscricao::INSCRITO           => __('Inscrito', 'participe-ibram'),
    StatusInscricao::EM_HABILITACAO     => __('Em habilitação', 'participe-ibram'),
    StatusInscricao::HABILITADO         => __('Habilitado', 'participe-ibram'),
    StatusInscricao::INABILITADO        => __('Inabilitado', 'participe-ibram'),
    StatusInscricao::EM_RECURSO         => __('Em recurso', 'participe-ibram'),
    StatusInscricao::FINAL_HABILITADO   => __('Final — Habilitado', 'participe-ibram'),
    StatusInscricao::FINAL_INABILITADO  => __('Final — Inabilitado', 'participe-ibram'),
];
$statusVariant = $statusVariants[$status] ?? 'default';
$statusLabel   = $statusLabels[$status] ?? $status;

$userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
$nonceHabilitar  = function_exists('wp_create_nonce') ? wp_create_nonce(\Ibram\ParticipeIbram\Presentation\Admin\Ajax\HabilitacaoAdminAjax::nonceAction(\Ibram\ParticipeIbram\Presentation\Admin\Ajax\HabilitacaoAdminAjax::ACTION_HABILITAR, $userId)) : '';
$nonceInabilitar = function_exists('wp_create_nonce') ? wp_create_nonce(\Ibram\ParticipeIbram\Presentation\Admin\Ajax\HabilitacaoAdminAjax::nonceAction(\Ibram\ParticipeIbram\Presentation\Admin\Ajax\HabilitacaoAdminAjax::ACTION_INABILITAR, $userId)) : '';

$podeHabilitar  = $podeDecidirHab && in_array($status, [StatusInscricao::INSCRITO, StatusInscricao::EM_HABILITACAO], true);
$podeInabilitar = $podeDecidirHab && in_array($status, [StatusInscricao::INSCRITO, StatusInscricao::EM_HABILITACAO], true);

$inscricaoLabel = sprintf(__('Inscrição #%d', 'participe-ibram'), (int) $inscricao->id());

PageLayout::open(
    $inscricaoLabel,
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Editais & habilitações', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . HabilitacaoMenuRegistry::SLUG_HABILITACOES)],
        ['label' => __('Habilitações', 'participe-ibram'), 'url' => $listaUrl],
        ['label' => $inscricaoLabel],
    ]
);
?>
<a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<?php if ($flash !== null) : ?>
    <?php
    if ($flash['level'] === 'success') {
        Notice::success($flash['message'], true);
    } else {
        Notice::danger($flash['message'], true);
    }
    ?>
<?php endif; ?>

<header role="banner" class="pi-inscricao-detalhes__header">
    <div class="pi-inscricao-detalhes__header-meta">
        <span class="pi-badge pi-badge--status pi-badge--status-<?php echo esc_attr($statusVariant); ?>">
            <?php echo esc_html($statusLabel); ?>
        </span>
        <dl class="pi-inscricao-detalhes__meta pi-meta-list">
            <div class="pi-meta-list__row">
                <dt><?php esc_html_e('Edital', 'participe-ibram'); ?></dt>
                <dd>#<?php echo esc_html((string) $inscricao->editalId()); ?></dd>
            </div>
            <div class="pi-meta-list__row">
                <dt><?php esc_html_e('Categoria', 'participe-ibram'); ?></dt>
                <dd>#<?php echo esc_html((string) $inscricao->categoriaId()); ?></dd>
            </div>
            <div class="pi-meta-list__row">
                <dt><?php esc_html_e('Agente', 'participe-ibram'); ?></dt>
                <dd>
                    #<?php echo esc_html((string) $inscricao->agenteId()); ?>
                    <?php if ($agenteDetalhesUrl !== '') : ?>
                        —
                        <a href="<?php echo esc_url($agenteDetalhesUrl); ?>">
                            <?php esc_html_e('Ver dados do agente', 'participe-ibram'); ?>
                        </a>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="pi-meta-list__row">
                <dt><?php esc_html_e('Inscrito em', 'participe-ibram'); ?></dt>
                <dd><?php echo esc_html($inscricao->inscritoEm() !== null ? $inscricao->inscritoEm()->format('d/m/Y H:i') : '—'); ?></dd>
            </div>
        </dl>
    </div>

    <?php if ($podeHabilitar || $podeInabilitar) : ?>
    <div class="pi-inscricao-detalhes__actions" role="group"
         aria-label="<?php esc_attr_e('Ações de habilitação', 'participe-ibram'); ?>">
        <?php if ($podeHabilitar) : ?>
            <button type="button" class="pi-button pi-button--primary pi-btn--habilitar"
                    data-pi-action="abrir-habilitar"
                    aria-controls="pi-modal-habilitar">
                <?php esc_html_e('Habilitar inscrição', 'participe-ibram'); ?>
            </button>
        <?php endif; ?>
        <?php if ($podeInabilitar) : ?>
            <button type="button" class="pi-button pi-button--secondary pi-btn--inabilitar"
                    data-pi-action="abrir-inabilitar"
                    aria-controls="pi-modal-inabilitar">
                <?php esc_html_e('Inabilitar inscrição', 'participe-ibram'); ?>
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</header>

<div role="status" aria-live="polite" id="pi-inscricao-live" class="screen-reader-text"></div>

<main id="pi-admin-main" tabindex="-1" data-pi-inscricao-detalhes>
    <div class="pi-tabs" data-pi-tabs>
        <div role="tablist" aria-label="<?php esc_attr_e('Seções da inscrição', 'participe-ibram'); ?>" class="pi-tabs__list">
            <button role="tab" id="pi-tab-resumo" aria-controls="pi-panel-resumo"
                    aria-selected="true" tabindex="0" class="pi-tabs__tab">
                <?php esc_html_e('Resumo', 'participe-ibram'); ?>
            </button>
            <button role="tab" id="pi-tab-portfolio" aria-controls="pi-panel-portfolio"
                    aria-selected="false" tabindex="-1" class="pi-tabs__tab">
                <?php esc_html_e('Portfolio', 'participe-ibram'); ?>
            </button>
            <button role="tab" id="pi-tab-documentos" aria-controls="pi-panel-documentos"
                    aria-selected="false" tabindex="-1" class="pi-tabs__tab">
                <?php esc_html_e('Documentos Anexados', 'participe-ibram'); ?>
            </button>
            <button role="tab" id="pi-tab-historico" aria-controls="pi-panel-historico"
                    aria-selected="false" tabindex="-1" class="pi-tabs__tab">
                <?php esc_html_e('Histórico', 'participe-ibram'); ?>
            </button>
        </div>

        <!-- Resumo -->
        <section role="tabpanel" id="pi-panel-resumo" aria-labelledby="pi-tab-resumo" class="pi-tabs__panel">
            <h2 class="screen-reader-text"><?php esc_html_e('Resumo', 'participe-ibram'); ?></h2>
            <dl class="pi-defs">
                <div class="pi-defs__row">
                    <dt><?php esc_html_e('Status', 'participe-ibram'); ?></dt>
                    <dd>
                        <span class="pi-badge pi-badge--status-<?php echo esc_attr($statusVariant); ?>">
                            <?php echo esc_html($statusLabel); ?>
                        </span>
                    </dd>
                </div>
                <div class="pi-defs__row">
                    <dt><?php esc_html_e('Habilitado em', 'participe-ibram'); ?></dt>
                    <dd><?php echo esc_html($inscricao->habilitadoEm() !== null ? $inscricao->habilitadoEm()->format('d/m/Y H:i') : '—'); ?></dd>
                </div>
                <div class="pi-defs__row">
                    <dt><?php esc_html_e('Inabilitado em', 'participe-ibram'); ?></dt>
                    <dd><?php echo esc_html($inscricao->inabilitadoEm() !== null ? $inscricao->inabilitadoEm()->format('d/m/Y H:i') : '—'); ?></dd>
                </div>
                <?php if ($inscricao->motivoInabilitacaoMd() !== null) : ?>
                <div class="pi-defs__row pi-defs__row--block">
                    <dt><?php esc_html_e('Motivo da inabilitação', 'participe-ibram'); ?></dt>
                    <dd class="pi-prose">
                        <?php echo wp_kses_post(wpautop($inscricao->motivoInabilitacaoMd())); ?>
                    </dd>
                </div>
                <?php endif; ?>
            </dl>
        </section>

        <!-- Portfolio -->
        <section role="tabpanel" id="pi-panel-portfolio" aria-labelledby="pi-tab-portfolio"
                 class="pi-tabs__panel" hidden>
            <h2 class="screen-reader-text"><?php esc_html_e('Portfolio', 'participe-ibram'); ?></h2>
            <?php if ($inscricao->portfolioMd() !== null && trim($inscricao->portfolioMd()) !== '') : ?>
                <div class="pi-prose pi-md-content">
                    <?php echo wp_kses_post(wpautop($inscricao->portfolioMd())); ?>
                </div>
            <?php else : ?>
                <p><?php esc_html_e('Nenhum portfolio enviado.', 'participe-ibram'); ?></p>
            <?php endif; ?>
        </section>

        <!-- Documentos -->
        <section role="tabpanel" id="pi-panel-documentos" aria-labelledby="pi-tab-documentos"
                 class="pi-tabs__panel" hidden>
            <h2 class="screen-reader-text"><?php esc_html_e('Documentos Anexados', 'participe-ibram'); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %s link para detalhes do agente */
                    esc_html__('Documentos são listados na tela do agente. %s.', 'participe-ibram'),
                    '<a href="' . esc_url($agenteDetalhesUrl) . '">' . esc_html__('Acessar dados do agente', 'participe-ibram') . '</a>'
                );
                ?>
            </p>
        </section>

        <!-- Histórico -->
        <section role="tabpanel" id="pi-panel-historico" aria-labelledby="pi-tab-historico"
                 class="pi-tabs__panel" hidden>
            <h2 class="screen-reader-text"><?php esc_html_e('Histórico', 'participe-ibram'); ?></h2>
            <p><?php esc_html_e('Histórico de transições disponível no log de auditoria.', 'participe-ibram'); ?></p>
        </section>
    </div>
</main>

<?php /* ===== Modais ARIA (R4 §6) ===== */ ?>

<?php if ($podeHabilitar) : ?>
<div class="pi-modal" id="pi-modal-habilitar" role="dialog" aria-modal="true"
     aria-labelledby="pi-modal-habilitar-title" aria-describedby="pi-modal-habilitar-desc" hidden>
    <div class="pi-modal__dialog">
        <header class="pi-modal__header">
            <h2 id="pi-modal-habilitar-title"><?php esc_html_e('Confirmar habilitação', 'participe-ibram'); ?></h2>
            <button type="button" class="pi-modal__close" data-pi-modal-close
                    aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">×</button>
        </header>
        <div class="pi-modal__body">
            <p id="pi-modal-habilitar-desc">
                <?php echo esc_html(sprintf(__('Confirma a habilitação da inscrição #%d? Esta ação atualizará o status para "Habilitado".', 'participe-ibram'), (int) $inscricao->id())); ?>
            </p>
        </div>
        <footer class="pi-modal__footer">
            <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
                <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
            </button>
            <button type="button" class="pi-button pi-button--primary" data-pi-confirm="habilitar"
                    data-inscricao-id="<?php echo esc_attr((string) $inscricao->id()); ?>"
                    data-nonce="<?php echo esc_attr($nonceHabilitar); ?>">
                <?php esc_html_e('Habilitar', 'participe-ibram'); ?>
            </button>
        </footer>
    </div>
</div>
<?php endif; ?>

<?php if ($podeInabilitar) : ?>
<div class="pi-modal" id="pi-modal-inabilitar" role="dialog" aria-modal="true"
     aria-labelledby="pi-modal-inabilitar-title" hidden>
    <div class="pi-modal__dialog">
        <header class="pi-modal__header">
            <h2 id="pi-modal-inabilitar-title"><?php esc_html_e('Inabilitar inscrição', 'participe-ibram'); ?></h2>
            <button type="button" class="pi-modal__close" data-pi-modal-close
                    aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">×</button>
        </header>
        <div class="pi-modal__body">
            <p><?php esc_html_e('Informe o motivo da inabilitação (mín. 50 caracteres). Markdown permitido.', 'participe-ibram'); ?></p>
            <label for="pi-motivo-inabilitacao" class="pi-label">
                <strong><?php esc_html_e('Motivo da inabilitação', 'participe-ibram'); ?></strong>
                <span aria-hidden="true">*</span>
            </label>
            <textarea id="pi-motivo-inabilitacao" name="motivo_inabilitacao_md"
                      rows="6" minlength="50" required
                      class="pi-input pi-input--full" aria-required="true"
                      aria-describedby="pi-motivo-help"></textarea>
            <p id="pi-motivo-help" class="description">
                <?php esc_html_e('Não inclua dados pessoais sensíveis (CPF, RG, e-mail).', 'participe-ibram'); ?>
            </p>
        </div>
        <footer class="pi-modal__footer">
            <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
                <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
            </button>
            <button type="button" class="pi-button pi-button--primary" data-pi-confirm="inabilitar"
                    data-inscricao-id="<?php echo esc_attr((string) $inscricao->id()); ?>"
                    data-nonce="<?php echo esc_attr($nonceInabilitar); ?>">
                <?php esc_html_e('Confirmar inabilitação', 'participe-ibram'); ?>
            </button>
        </footer>
    </div>
</div>
<?php endif; ?>

<script type="application/json" id="pi-inscricao-detalhes-data">
    <?php
    if (function_exists('wp_json_encode')) {
        echo wp_json_encode([
            'ajaxUrl'     => function_exists('admin_url') ? admin_url('admin-ajax.php') : '',
            'inscricaoId' => (int) $inscricao->id(),
            'i18n'        => [
                'habilitandoMsg'    => __('Habilitando inscrição…', 'participe-ibram'),
                'inabilitandoMsg'   => __('Inabilitando inscrição…', 'participe-ibram'),
                'sucessoHabilitar'  => __('Inscrição habilitada com sucesso.', 'participe-ibram'),
                'sucessoInabilitar' => __('Inscrição inabilitada com sucesso.', 'participe-ibram'),
                'erroGenerico'      => __('Falha ao processar a requisição.', 'participe-ibram'),
                'motivoMinChars'    => __('O motivo deve ter pelo menos 50 caracteres.', 'participe-ibram'),
            ],
        ]);
    }
    ?>
</script>
<?php
PageLayout::close();
