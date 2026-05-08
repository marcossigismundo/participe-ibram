<?php
/**
 * Template — Criar / Editar Categoria (admin).
 *
 * Vars injetadas por CategoriaController::render():
 *  - Edital $edital
 *  - Categoria|null $categoria    (null = criação)
 *  - bool $podeEditar
 *  - string $nonce
 *  - array{type:string,message:string}|null $flash
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Editais
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;

/** @var \Ibram\ParticipeIbram\Domain\Edital\Edital $edital */
/** @var \Ibram\ParticipeIbram\Domain\Edital\Categoria|null $categoria */
/** @var bool $podeEditar */
/** @var string $nonce */
/** @var array{type:string,message:string}|null $flash */

$editalId    = (int) $edital->id();
$categoriaId = ($categoria !== null && $categoria->id() !== null) ? (int) $categoria->id() : 0;
$isNew       = $categoriaId === 0;
$podeEditar  = isset($podeEditar) && $podeEditar;
$flash       = isset($flash) ? $flash : null;

$pageTitle = $isNew
    ? __('Adicionar Categoria', 'participe-ibram')
    : __('Editar Categoria', 'participe-ibram');
?>
<div class="participe-ibram-scope wrap pi-admin-categoria-form">
  <a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . EditalMenuRegistry::SLUG_ROOT)); ?>"><?php esc_html_e('Participe Ibram', 'participe-ibram'); ?></a>
      </li>
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(EditalMenuRegistry::urlEditaisList()); ?>"><?php esc_html_e('Editais', 'participe-ibram'); ?></a>
      </li>
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(EditalMenuRegistry::urlEditalDetalhes($editalId)); ?>"><?php echo esc_html($edital->titulo()); ?></a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page"><?php echo esc_html($pageTitle); ?></li>
    </ol>
  </nav>

  <?php if ($flash !== null) : ?>
    <div class="notice notice-<?php echo esc_attr($flash['type'] === 'success' ? 'success' : 'error'); ?> is-dismissible" role="alert">
      <p><?php echo esc_html($flash['message']); ?></p>
    </div>
  <?php endif; ?>

  <main id="pi-admin-main" tabindex="-1">
    <h1><?php echo esc_html($pageTitle); ?></h1>

    <?php if (!$podeEditar) : ?>
      <div class="notice notice-warning">
        <p><?php esc_html_e('Este edital não permite edição de categorias no status atual.', 'participe-ibram'); ?></p>
      </div>
    <?php else : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>" novalidate aria-label="<?php esc_attr_e('Formulário de categoria', 'participe-ibram'); ?>">
      <?php wp_nonce_field('pi_admin_salvar_categoria_' . $editalId . '_' . get_current_user_id(), '_wpnonce'); ?>
      <input type="hidden" name="page" value="<?php echo esc_attr(EditalMenuRegistry::SLUG_CATEGORIA); ?>">
      <input type="hidden" name="pi_categoria_action" value="salvar_categoria">
      <input type="hidden" name="edital_id" value="<?php echo esc_attr((string) $editalId); ?>">
      <?php if (!$isNew) : ?>
        <input type="hidden" name="categoria_id" value="<?php echo esc_attr((string) $categoriaId); ?>">
      <?php endif; ?>

      <!-- Nome -->
      <div class="pi-form-field">
        <label for="pi-cat-nome" class="pi-form-label pi-form-label--required">
          <?php esc_html_e('Nome da categoria', 'participe-ibram'); ?>
        </label>
        <input
          type="text"
          id="pi-cat-nome"
          name="nome"
          class="regular-text"
          maxlength="255"
          required
          aria-required="true"
          value="<?php echo $categoria ? esc_attr($categoria->nome()) : ''; ?>"
        >
      </div>

      <!-- Descrição -->
      <div class="pi-form-field">
        <label for="pi-cat-descricao" class="pi-form-label">
          <?php esc_html_e('Descrição', 'participe-ibram'); ?>
          <span class="pi-hint-md"><?php esc_html_e('(Aceita Markdown)', 'participe-ibram'); ?></span>
        </label>
        <textarea
          id="pi-cat-descricao"
          name="descricao_md"
          class="large-text"
          rows="4"
        ><?php echo $categoria ? esc_textarea((string) $categoria->descricaoMd()) : ''; ?></textarea>
      </div>

      <!-- Vagas / Suplentes -->
      <div class="pi-form-row">
        <div class="pi-form-field">
          <label for="pi-cat-vagas" class="pi-form-label pi-form-label--required">
            <?php esc_html_e('Número de vagas', 'participe-ibram'); ?>
          </label>
          <input
            type="number"
            id="pi-cat-vagas"
            name="num_vagas"
            min="1"
            required
            aria-required="true"
            value="<?php echo $categoria ? esc_attr((string) $categoria->numVagas()) : '1'; ?>"
          >
        </div>
        <div class="pi-form-field">
          <label for="pi-cat-suplentes" class="pi-form-label">
            <?php esc_html_e('Número de suplentes', 'participe-ibram'); ?>
          </label>
          <input
            type="number"
            id="pi-cat-suplentes"
            name="num_suplentes"
            min="0"
            value="<?php echo $categoria ? esc_attr((string) $categoria->numSuplentes()) : '0'; ?>"
          >
        </div>
      </div>

      <!-- Tipos de agente elegível -->
      <fieldset class="pi-form-field">
        <legend class="pi-form-label pi-form-label--required">
          <?php esc_html_e('Tipos de agente elegível', 'participe-ibram'); ?>
        </legend>
        <?php
        $tiposChecked = $categoria ? explode(',', $categoria->tiposAgenteElegivel()) : ['PF', 'OR', 'SM'];
        $tiposOpts    = [
            'PF' => __('Pessoa Física', 'participe-ibram'),
            'OR' => __('Organização', 'participe-ibram'),
            'SM' => __('Sistema de Museu', 'participe-ibram'),
        ];
        foreach ($tiposOpts as $val => $lbl) :
        ?>
        <label class="pi-checkbox-label">
          <input
            type="checkbox"
            name="tipos_agente_elegivel[]"
            value="<?php echo esc_attr($val); ?>"
            <?php checked(in_array($val, $tiposChecked, true)); ?>
          >
          <?php echo esc_html($lbl); ?>
        </label>
        <?php endforeach; ?>
      </fieldset>

      <!-- Critérios (Markdown) -->
      <div class="pi-form-field">
        <label for="pi-cat-criterios" class="pi-form-label">
          <?php esc_html_e('Critérios', 'participe-ibram'); ?>
          <span class="pi-hint-md"><?php esc_html_e('(Aceita Markdown)', 'participe-ibram'); ?></span>
        </label>
        <textarea
          id="pi-cat-criterios"
          name="criterios_md"
          class="large-text"
          rows="4"
        ><?php echo $categoria ? esc_textarea((string) $categoria->criteriosMd()) : ''; ?></textarea>
      </div>

      <!-- Ordem -->
      <div class="pi-form-field">
        <label for="pi-cat-ordem" class="pi-form-label"><?php esc_html_e('Ordem de exibição', 'participe-ibram'); ?></label>
        <input
          type="number"
          id="pi-cat-ordem"
          name="ordem"
          min="0"
          value="<?php echo $categoria ? esc_attr((string) $categoria->ordem()) : '0'; ?>"
        >
      </div>

      <div class="pi-form-actions">
        <button type="submit" class="button button-primary">
          <?php echo esc_html($isNew ? __('Adicionar categoria', 'participe-ibram') : __('Salvar alterações', 'participe-ibram')); ?>
        </button>
        <a href="<?php echo esc_url(EditalMenuRegistry::urlEditalDetalhes($editalId)); ?>" class="button button-secondary">
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </a>
      </div>
    </form>
    <?php endif; ?>
  </main>
</div>
