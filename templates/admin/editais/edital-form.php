<?php
/**
 * Template — Criar / Editar Edital (admin).
 *
 * Vars injetadas por EditalFormController::renderForm():
 *  - Edital|null $edital          (null = criação)
 *  - array<string,string> $errors
 *  - array{type:string,message:string}|null $flash
 *  - string $nonce
 *  - bool $isNew
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Editais
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Domain\Edital\Edital|null $edital */
/** @var array<string,string> $errors */
/** @var array{type:string,message:string}|null $flash */
/** @var string $nonce */
/** @var bool $isNew */

$isNew    = isset($isNew) ? (bool) $isNew : ($edital === null);
$errors   = isset($errors) && is_array($errors) ? $errors : [];
$flash    = isset($flash) ? $flash : null;
$nonce    = isset($nonce) ? (string) $nonce : '';
$editalId = ($edital !== null && $edital->id() !== null) ? (int) $edital->id() : 0;

$titulo             = $edital !== null ? esc_attr($edital->titulo()) : '';
$descricaoMd        = $edital !== null ? esc_textarea((string) $edital->descricaoMd()) : '';
$fmtDate            = static function (?\DateTimeImmutable $dt): string {
    return $dt !== null ? esc_attr($dt->format('Y-m-d\TH:i')) : '';
};

$pageTitle = $isNew
    ? __('Novo Edital', 'participe-ibram')
    : __('Editar Edital', 'participe-ibram');

// JSON config for edital-form.js (no PII in payload).
$jsData = wp_json_encode([
    'ajaxUrl'  => admin_url('admin-ajax.php'),
    'i18n'     => [
        'erroCronologia'  => __('As datas devem estar em ordem cronológica correta.', 'participe-ibram'),
        'campoObrigatorio' => __('Este campo é obrigatório para publicação.', 'participe-ibram'),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

PageLayout::open(
    $pageTitle,
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Editais & habilitações', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . EditalMenuRegistry::SLUG_EDITAIS)],
        ['label' => __('Editais', 'participe-ibram'), 'url' => EditalMenuRegistry::urlEditaisList()],
        ['label' => $pageTitle],
    ]
);
?>
<a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<script id="pi-edital-form-data" type="application/json">
    <?php echo $jsData; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- json_encoded with HEX flags. ?>
</script>

<?php if ($flash !== null) : ?>
    <?php
    if ($flash['type'] === 'success') {
        Notice::success($flash['message'], true);
    } else {
        Notice::danger($flash['message'], true);
    }
    ?>
<?php endif; ?>

<main id="pi-admin-main" tabindex="-1" data-pi-edital-form>
    <div class="pi-form-layout">
        <!-- Coluna principal -->
        <div class="pi-form-layout__main pi-form">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>" novalidate aria-label="<?php esc_attr_e('Formulário do edital', 'participe-ibram'); ?>">
                <?php wp_nonce_field('pi_admin_' . ($isNew ? 'criar_edital' : 'atualizar_edital_' . $editalId . '_' . get_current_user_id()), '_wpnonce'); ?>
                <input type="hidden" name="page" value="<?php echo esc_attr($isNew ? EditalMenuRegistry::SLUG_NOVO : EditalMenuRegistry::SLUG_EDITAL); ?>">
                <input type="hidden" name="pi_edital_action" value="<?php echo esc_attr($isNew ? 'criar_edital' : 'atualizar_edital'); ?>">
                <?php if (!$isNew) : ?>
                    <input type="hidden" name="edital_id" value="<?php echo esc_attr((string) $editalId); ?>">
                <?php endif; ?>

                <!-- Título -->
                <div class="pi-field-group <?php echo isset($errors['titulo']) ? 'pi-field-group--error' : ''; ?>">
                    <label for="pi-titulo" class="pi-field-group__label pi-field-group__label--required">
                        <?php esc_html_e('Título', 'participe-ibram'); ?>
                    </label>
                    <input
                        type="text"
                        id="pi-titulo"
                        name="titulo"
                        class="regular-text"
                        maxlength="255"
                        required
                        aria-required="true"
                        aria-describedby="pi-titulo-hint<?php echo isset($errors['titulo']) ? ' pi-titulo-error' : ''; ?>"
                        aria-invalid="<?php echo isset($errors['titulo']) ? 'true' : 'false'; ?>"
                        value="<?php echo $titulo; ?>"
                    >
                    <p id="pi-titulo-hint" class="description"><?php esc_html_e('Título público do edital (máx. 255 caracteres).', 'participe-ibram'); ?></p>
                    <?php if (isset($errors['titulo'])) : ?>
                        <p id="pi-titulo-error" class="pi-field-group__error" role="alert"><?php echo esc_html($errors['titulo']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Descrição (Markdown) -->
                <div class="pi-field-group">
                    <label for="pi-descricao" class="pi-field-group__label">
                        <?php esc_html_e('Descrição', 'participe-ibram'); ?>
                        <span class="pi-hint-md"><?php esc_html_e('(Aceita Markdown)', 'participe-ibram'); ?></span>
                    </label>
                    <textarea
                        id="pi-descricao"
                        name="descricao_md"
                        class="large-text"
                        rows="8"
                        aria-describedby="pi-descricao-hint"
                    ><?php echo $descricaoMd; ?></textarea>
                    <p id="pi-descricao-hint" class="description"><?php esc_html_e('Descrição exibida publicamente. Suporta formatação Markdown.', 'participe-ibram'); ?></p>
                </div>

                <!-- Datas -->
                <fieldset class="pi-field-group pi-dates-fieldset" aria-describedby="pi-dates-legend-hint">
                    <legend class="pi-field-group__label"><?php esc_html_e('Programação de datas', 'participe-ibram'); ?></legend>
                    <p id="pi-dates-legend-hint" class="description"><?php esc_html_e('Todas as datas devem ser preenchidas para publicar o edital. Em rascunho são opcionais.', 'participe-ibram'); ?></p>

                    <div id="pi-date-errors" class="pi-date-errors" aria-live="polite" aria-atomic="true"></div>

                    <?php
                    $dateFields = [
                        'abertura'                   => __('Abertura', 'participe-ibram'),
                        'encerramento_inscricoes'    => __('Encerramento das inscrições', 'participe-ibram'),
                        'publicacao_habilitacao'     => __('Publicação da habilitação', 'participe-ibram'),
                        'prazo_recurso_inabilitacao' => __('Prazo para recurso de inabilitação', 'participe-ibram'),
                        'abertura_votacao'           => __('Abertura da votação', 'participe-ibram'),
                        'encerramento_votacao'       => __('Encerramento da votação', 'participe-ibram'),
                        'publicacao_resultado'       => __('Publicação do resultado', 'participe-ibram'),
                    ];
                    $dateGetters = [
                        'abertura'                   => $edital ? $edital->abertura() : null,
                        'encerramento_inscricoes'    => $edital ? $edital->encerramentoInscricoes() : null,
                        'publicacao_habilitacao'     => $edital ? $edital->publicacaoHabilitacao() : null,
                        'prazo_recurso_inabilitacao' => $edital ? $edital->prazoRecursoInabilitacao() : null,
                        'abertura_votacao'           => $edital ? $edital->aberturaVotacao() : null,
                        'encerramento_votacao'       => $edital ? $edital->encerramentoVotacao() : null,
                        'publicacao_resultado'       => $edital ? $edital->publicacaoResultado() : null,
                    ];
                    foreach ($dateFields as $fieldName => $label) :
                        $fieldId   = 'pi-date-' . str_replace('_', '-', $fieldName);
                        $hasError  = isset($errors[$fieldName]);
                        $value     = $fmtDate($dateGetters[$fieldName]);
                    ?>
                    <div class="pi-field-group pi-field-group--date <?php echo $hasError ? 'pi-field-group--error' : ''; ?>">
                        <label for="<?php echo esc_attr($fieldId); ?>" class="pi-field-group__label">
                            <?php echo esc_html($label); ?>
                        </label>
                        <input
                            type="datetime-local"
                            id="<?php echo esc_attr($fieldId); ?>"
                            name="<?php echo esc_attr($fieldName); ?>"
                            class="pi-date-input"
                            data-pi-date-order="<?php echo esc_attr($fieldName); ?>"
                            aria-invalid="<?php echo $hasError ? 'true' : 'false'; ?>"
                            aria-describedby="<?php echo $hasError ? esc_attr($fieldId . '-error') : ''; ?>"
                            value="<?php echo $value; ?>"
                        >
                        <?php if ($hasError) : ?>
                            <p id="<?php echo esc_attr($fieldId . '-error'); ?>" class="pi-field-group__error" role="alert">
                                <?php echo esc_html($errors[$fieldName]); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </fieldset>

                <div class="pi-form-actions">
                    <button type="submit" class="pi-button pi-button--primary">
                        <?php echo esc_html($isNew ? __('Salvar rascunho', 'participe-ibram') : __('Salvar alterações', 'participe-ibram')); ?>
                    </button>
                    <a href="<?php echo esc_url(EditalMenuRegistry::urlEditaisList()); ?>" class="pi-button pi-button--secondary">
                        <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Sidebar: timeline visual das datas -->
        <aside class="pi-form-layout__sidebar" aria-label="<?php esc_attr_e('Timeline das datas', 'participe-ibram'); ?>">
            <div class="pi-timeline-card">
                <h2 class="pi-timeline-card__title"><?php esc_html_e('Fluxo do edital', 'participe-ibram'); ?></h2>
                <ol class="pi-timeline" aria-label="<?php esc_attr_e('Sequência de datas', 'participe-ibram'); ?>">
                    <?php
                    foreach ($dateFields as $fieldName => $label) :
                        $dt = $dateGetters[$fieldName];
                    ?>
                    <li class="pi-timeline__item <?php echo $dt !== null ? 'pi-timeline__item--set' : 'pi-timeline__item--empty'; ?>">
                        <span class="pi-timeline__label"><?php echo esc_html($label); ?></span>
                        <span class="pi-timeline__value" id="pi-tl-<?php echo esc_attr(str_replace('_', '-', $fieldName)); ?>">
                            <?php echo $dt !== null ? esc_html($dt->format('d/m/Y H:i')) : '<span class="pi-muted">' . esc_html__('—', 'participe-ibram') . '</span>'; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php if ($edital !== null && $edital->status()->value() !== StatusEdital::RASCUNHO) : ?>
                <div class="pi-status-info">
                    <p><?php esc_html_e('Este edital já foi publicado. Apenas o título e a descrição podem ser alterados.', 'participe-ibram'); ?></p>
                </div>
            <?php endif; ?>
        </aside>
    </div><!-- .pi-form-layout -->
</main>
<?php
PageLayout::close();
wp_enqueue_script('pi-edital-form');
