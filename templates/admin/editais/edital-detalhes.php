<?php
/**
 * Template — Detalhes do Edital (admin) — tabs: Resumo | Categorias | Inscrições | Histórico.
 *
 * Vars injetadas por EditalDetalhesController::render():
 *  - Edital $edital
 *  - list<Categoria> $categorias
 *  - int $totalVagas
 *  - bool $podePublicar, $podeAbrir, $podeEditar
 *  - array{publicar:string,abrir:string} $nonces
 *  - array{type:string,message:string}|null $flash
 *  - int $userId
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Editais
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\EmptyState;
use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Domain\Edital\Edital $edital */
/** @var list<\Ibram\ParticipeIbram\Domain\Edital\Categoria> $categorias */
/** @var int $totalVagas */
/** @var bool $podePublicar */
/** @var bool $podeAbrir */
/** @var bool $podeEditar */
/** @var array $nonces */
/** @var array{type:string,message:string}|null $flash */
/** @var int $userId */

$editalId    = (int) $edital->id();
$status      = $edital->status()->value();
$flash       = isset($flash) ? $flash : null;
$userId      = isset($userId) ? (int) $userId : 0;

// JSON config for edital-detalhes.js (IDs only — no PII).
$jsData = wp_json_encode([
    'editalId'  => $editalId,
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'nonces'    => [
        'publicar' => isset($nonces['publicar']) ? (string) $nonces['publicar'] : '',
        'abrir'    => isset($nonces['abrir'])    ? (string) $nonces['abrir']    : '',
    ],
    'edital'    => [
        'titulo'         => $edital->titulo(),
        'numCategorias'  => count($categorias),
        'totalVagas'     => isset($totalVagas) ? (int) $totalVagas : 0,
        'abertura'       => $edital->abertura() ? $edital->abertura()->format('d/m/Y') : '',
        'encerramentoInscricoes' => $edital->encerramentoInscricoes() ? $edital->encerramentoInscricoes()->format('d/m/Y') : '',
    ],
    'i18n' => [
        'confirmarPublicar'  => __('Confirme a publicação do edital. Esta ação é irreversível.', 'participe-ibram'),
        'confirmarAbrir'     => __('Confirme a abertura das inscrições. Esta ação é irreversível.', 'participe-ibram'),
        'erroGenerico'       => __('Falha ao processar a requisição.', 'participe-ibram'),
        'sucessoPublicar'    => __('Edital publicado com sucesso.', 'participe-ibram'),
        'sucessoAbrir'       => __('Inscrições abertas com sucesso.', 'participe-ibram'),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// Build secondary actions for the page header.
$secondaryActions = [];
if ($podeEditar) {
    $secondaryActions[] = ['label' => __('Editar', 'participe-ibram'), 'url' => EditalMenuRegistry::urlEditalEdit($editalId)];
}

// Primary action: Publicar > Abrir inscrições (handled via JS modal; rendered as button below).
// PageLayout::open uses <a> for primary action — modal triggers are rendered in content.
PageLayout::open(
    $edital->titulo(),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Editais & habilitações', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . EditalMenuRegistry::SLUG_EDITAIS)],
        ['label' => __('Editais', 'participe-ibram'), 'url' => EditalMenuRegistry::urlEditaisList()],
        ['label' => esc_html($edital->titulo())],
    ],
    null,
    $secondaryActions
);
?>
<a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<script id="pi-edital-detalhes-data" type="application/json">
    <?php echo $jsData; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</script>

<div aria-live="polite" aria-atomic="true" id="pi-admin-detalhes-live" class="screen-reader-text"></div>

<?php if ($flash !== null) : ?>
    <?php
    if ($flash['type'] === 'success') {
        Notice::success($flash['message'], true);
    } else {
        Notice::danger($flash['message'], true);
    }
    ?>
<?php endif; ?>

<main id="pi-admin-main" tabindex="-1" data-pi-detalhes>
    <!-- Status badge + modal trigger actions -->
    <div class="pi-edital-header__status-row">
        <span class="pi-status-badge pi-status-badge--<?php echo esc_attr(str_replace('_', '-', $status)); ?>">
            <?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?>
        </span>
        <div class="pi-edital-header__actions">
            <?php if ($podePublicar) : ?>
                <button
                    type="button"
                    class="pi-button pi-button--primary"
                    data-pi-action="publicar"
                    aria-controls="pi-modal-publicar"
                    aria-haspopup="dialog"
                >
                    <?php esc_html_e('Publicar Edital', 'participe-ibram'); ?>
                </button>
            <?php endif; ?>
            <?php if ($podeAbrir) : ?>
                <button
                    type="button"
                    class="pi-button pi-button--primary"
                    data-pi-action="abrir"
                    aria-controls="pi-modal-abrir"
                    aria-haspopup="dialog"
                >
                    <?php esc_html_e('Abrir Inscrições', 'participe-ibram'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs ARIA -->
    <div data-pi-tabs>
        <div role="tablist" aria-label="<?php esc_attr_e('Seções do edital', 'participe-ibram'); ?>" class="pi-tabs-list">
            <button role="tab" id="pi-tab-resumo" aria-selected="true" aria-controls="pi-panel-resumo" tabindex="0">
                <?php esc_html_e('Resumo', 'participe-ibram'); ?>
            </button>
            <button role="tab" id="pi-tab-categorias" aria-selected="false" aria-controls="pi-panel-categorias" tabindex="-1">
                <?php esc_html_e('Categorias', 'participe-ibram'); ?>
            </button>
            <button role="tab" id="pi-tab-inscricoes" aria-selected="false" aria-controls="pi-panel-inscricoes" tabindex="-1">
                <?php esc_html_e('Inscrições', 'participe-ibram'); ?>
            </button>
            <button role="tab" id="pi-tab-historico" aria-selected="false" aria-controls="pi-panel-historico" tabindex="-1">
                <?php esc_html_e('Histórico', 'participe-ibram'); ?>
            </button>
        </div>

        <!-- Resumo -->
        <div role="tabpanel" id="pi-panel-resumo" aria-labelledby="pi-tab-resumo" class="pi-tab-panel">
            <table class="pi-meta-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Status', 'participe-ibram'); ?></th>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Abertura', 'participe-ibram'); ?></th>
                        <td><?php echo $edital->abertura() ? esc_html($edital->abertura()->format('d/m/Y H:i')) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Encerramento inscrições', 'participe-ibram'); ?></th>
                        <td><?php echo $edital->encerramentoInscricoes() ? esc_html($edital->encerramentoInscricoes()->format('d/m/Y H:i')) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Publicação habilitação', 'participe-ibram'); ?></th>
                        <td><?php echo $edital->publicacaoHabilitacao() ? esc_html($edital->publicacaoHabilitacao()->format('d/m/Y H:i')) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Prazo recurso inabilitação', 'participe-ibram'); ?></th>
                        <td><?php echo $edital->prazoRecursoInabilitacao() ? esc_html($edital->prazoRecursoInabilitacao()->format('d/m/Y H:i')) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Abertura votação', 'participe-ibram'); ?></th>
                        <td><?php echo $edital->aberturaVotacao() ? esc_html($edital->aberturaVotacao()->format('d/m/Y H:i')) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Encerramento votação', 'participe-ibram'); ?></th>
                        <td><?php echo $edital->encerramentoVotacao() ? esc_html($edital->encerramentoVotacao()->format('d/m/Y H:i')) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Publicação resultado', 'participe-ibram'); ?></th>
                        <td><?php echo $edital->publicacaoResultado() ? esc_html($edital->publicacaoResultado()->format('d/m/Y H:i')) : '—'; ?></td>
                    </tr>
                </tbody>
            </table>
            <?php if ($edital->descricaoMd() !== null && $edital->descricaoMd() !== '') : ?>
                <div class="pi-descricao">
                    <h2><?php esc_html_e('Descrição', 'participe-ibram'); ?></h2>
                    <div class="pi-markdown-content">
                        <?php echo wp_kses_post($edital->descricaoMd()); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Categorias -->
        <div role="tabpanel" id="pi-panel-categorias" aria-labelledby="pi-tab-categorias" class="pi-tab-panel" hidden>
            <div class="pi-categorias-header">
                <h2><?php esc_html_e('Categorias', 'participe-ibram'); ?></h2>
                <?php if ($podeEditar) : ?>
                    <a
                        href="<?php echo esc_url(EditalMenuRegistry::urlCategoria($editalId)); ?>"
                        class="pi-button pi-button--primary"
                    >
                        <?php esc_html_e('Adicionar categoria', 'participe-ibram'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (count($categorias) === 0) : ?>
                <?php
                EmptyState::render(
                    __('Nenhuma categoria cadastrada', 'participe-ibram'),
                    __('Ainda não há categorias neste edital.', 'participe-ibram'),
                    $podeEditar ? ['label' => __('Adicionar categoria', 'participe-ibram'), 'url' => EditalMenuRegistry::urlCategoria($editalId)] : null,
                    'dashicons-category'
                );
                ?>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped pi-list-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Nome', 'participe-ibram'); ?></th>
                            <th><?php esc_html_e('Vagas', 'participe-ibram'); ?></th>
                            <th><?php esc_html_e('Suplentes', 'participe-ibram'); ?></th>
                            <th><?php esc_html_e('Tipos elegíveis', 'participe-ibram'); ?></th>
                            <?php if ($podeEditar) : ?>
                                <th><?php esc_html_e('Ações', 'participe-ibram'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat) : ?>
                            <tr>
                                <td><?php echo esc_html($cat->nome()); ?></td>
                                <td><?php echo esc_html((string) $cat->numVagas()); ?></td>
                                <td><?php echo esc_html((string) $cat->numSuplentes()); ?></td>
                                <td><?php echo esc_html($cat->tiposAgenteElegivel()); ?></td>
                                <?php if ($podeEditar) : ?>
                                    <td>
                                        <a href="<?php echo esc_url(EditalMenuRegistry::urlCategoria($editalId, (int) $cat->id())); ?>" class="pi-button pi-button--secondary pi-button--sm">
                                            <?php esc_html_e('Editar', 'participe-ibram'); ?>
                                        </a>
                                        <button
                                            type="button"
                                            class="pi-button pi-button--danger pi-button--sm"
                                            data-pi-remover-categoria="<?php echo esc_attr((string) $cat->id()); ?>"
                                            data-edital-id="<?php echo esc_attr((string) $editalId); ?>"
                                            aria-label="<?php echo esc_attr(sprintf(__('Remover categoria %s', 'participe-ibram'), $cat->nome())); ?>"
                                        >
                                            <?php esc_html_e('Remover', 'participe-ibram'); ?>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Inscrições (link para W5-B/W5-C) -->
        <div role="tabpanel" id="pi-panel-inscricoes" aria-labelledby="pi-tab-inscricoes" class="pi-tab-panel" hidden>
            <p><?php esc_html_e('A gestão detalhada de inscrições e habilitações é feita nos módulos de Inscrição e Habilitação.', 'participe-ibram'); ?></p>
            <p><em><?php esc_html_e('(Módulos W5-B e W5-C — disponíveis em breve.)', 'participe-ibram'); ?></em></p>
        </div>

        <!-- Histórico (audit log) -->
        <div role="tabpanel" id="pi-panel-historico" aria-labelledby="pi-tab-historico" class="pi-tab-panel" hidden>
            <?php
            // Carrega os últimos 50 registros de auditoria para este edital.
            global $wpdb;
            if (isset($wpdb)) {
                $tblAudit = $wpdb->prefix . 'pi_audit_log';
                $rows     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->prepare(
                        "SELECT acao, ator_id, dados_depois, criado_em FROM {$tblAudit}
                         WHERE entidade = 'edital' AND entidade_id = %d
                         ORDER BY criado_em DESC LIMIT 50",
                        $editalId
                    ),
                    ARRAY_A
                );
                if (is_array($rows) && count($rows) > 0) :
            ?>
            <table class="wp-list-table widefat striped pi-list-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ação', 'participe-ibram'); ?></th>
                        <th><?php esc_html_e('Ator', 'participe-ibram'); ?></th>
                        <th><?php esc_html_e('Data/hora', 'participe-ibram'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) :
                        $atorId   = (int) $row['ator_id'];
                        $atorUser = $atorId > 0 && function_exists('get_userdata') ? get_userdata($atorId) : false;
                        $atorNome = $atorUser ? (string) $atorUser->display_name : sprintf(__('Usuário #%d', 'participe-ibram'), $atorId);
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $row['acao']); ?></td>
                        <td><?php echo esc_html($atorNome); ?></td>
                        <td><?php echo esc_html((string) $row['criado_em']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
                <?php
                EmptyState::render(
                    __('Sem registros de auditoria', 'participe-ibram'),
                    __('Nenhum registro de auditoria encontrado para este edital.', 'participe-ibram'),
                    null,
                    'dashicons-backup'
                );
                ?>
            <?php endif;
            } ?>
        </div>
    </div><!-- /data-pi-tabs -->

    <!-- Modal: Publicar Edital -->
    <?php if ($podePublicar) : ?>
    <div
        id="pi-modal-publicar"
        class="pi-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pi-modal-publicar-title"
        aria-describedby="pi-modal-publicar-desc"
        hidden
    >
        <div class="pi-modal__overlay" data-pi-modal-close></div>
        <div class="pi-modal__content">
            <button
                type="button"
                class="pi-modal__close"
                data-pi-modal-close
                aria-label="<?php esc_attr_e('Fechar modal', 'participe-ibram'); ?>"
            >&times;</button>
            <h2 id="pi-modal-publicar-title"><?php esc_html_e('Publicar Edital', 'participe-ibram'); ?></h2>
            <p id="pi-modal-publicar-desc"><?php esc_html_e('Confirme os dados antes de publicar. Esta ação é irreversível.', 'participe-ibram'); ?></p>
            <ul class="pi-modal-confirm-list">
                <li><strong><?php esc_html_e('Título:', 'participe-ibram'); ?></strong> <?php echo esc_html($edital->titulo()); ?></li>
                <li><strong><?php esc_html_e('Categorias:', 'participe-ibram'); ?></strong> <?php echo esc_html((string) count($categorias)); ?></li>
                <li><strong><?php esc_html_e('Total de vagas:', 'participe-ibram'); ?></strong> <?php echo esc_html((string) (isset($totalVagas) ? $totalVagas : 0)); ?></li>
                <?php if ($edital->abertura()) : ?>
                <li><strong><?php esc_html_e('Abertura:', 'participe-ibram'); ?></strong> <?php echo esc_html($edital->abertura()->format('d/m/Y')); ?></li>
                <?php endif; ?>
                <?php if ($edital->encerramentoInscricoes()) : ?>
                <li><strong><?php esc_html_e('Encerramento inscrições:', 'participe-ibram'); ?></strong> <?php echo esc_html($edital->encerramentoInscricoes()->format('d/m/Y')); ?></li>
                <?php endif; ?>
            </ul>
            <div class="pi-modal__actions">
                <button type="button" class="pi-button pi-button--primary" data-pi-modal-confirm="publicar">
                    <?php esc_html_e('Confirmar publicação', 'participe-ibram'); ?>
                </button>
                <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
                    <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Abrir Inscrições -->
    <?php if ($podeAbrir) : ?>
    <div
        id="pi-modal-abrir"
        class="pi-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pi-modal-abrir-title"
        aria-describedby="pi-modal-abrir-desc"
        hidden
    >
        <div class="pi-modal__overlay" data-pi-modal-close></div>
        <div class="pi-modal__content">
            <button
                type="button"
                class="pi-modal__close"
                data-pi-modal-close
                aria-label="<?php esc_attr_e('Fechar modal', 'participe-ibram'); ?>"
            >&times;</button>
            <h2 id="pi-modal-abrir-title"><?php esc_html_e('Abrir Inscrições', 'participe-ibram'); ?></h2>
            <p id="pi-modal-abrir-desc"><?php esc_html_e('Confirme a abertura das inscrições. Esta ação é irreversível.', 'participe-ibram'); ?></p>
            <div class="pi-modal__actions">
                <button type="button" class="pi-button pi-button--primary" data-pi-modal-confirm="abrir">
                    <?php esc_html_e('Confirmar abertura', 'participe-ibram'); ?>
                </button>
                <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
                    <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>
<?php
PageLayout::close();
wp_enqueue_script('pi-edital-detalhes');
