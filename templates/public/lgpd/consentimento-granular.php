<?php
/**
 * Template — Consentimento granular LGPD.
 *
 * Renderiza checkboxes por finalidade (10 finalidades), agrupadas em
 * "Obrigatórias" (disabled, marcados, tag "OBRIGATÓRIO") e "Opcionais"
 * (não pré-marcadas). Cada finalidade exibe descrição, base legal em
 * texto secundário e referência normativa.
 *
 * Vars esperadas:
 *  - $finalidades   list<array{code:string, label:string, description:string,
 *                              legal_basis:string, legal_reference:string,
 *                              required:bool, sensitive:bool}>
 *  - $policy_version string
 *  - $policy_hash    string
 *  - $termo_url      string  — URL para abrir modal/página do termo
 *  - $dpo_email      string
 *
 * Wave 3 / W3-D — STUB visual; integração com Finalidade::all() em Wave 4.
 *
 * @package Ibram\ParticipeIbram\Templates\Public\LGPD
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var list<array{code:string, label:string, description:string, legal_basis:string, legal_reference:string, required:bool, sensitive:bool}> $finalidades */
$finalidades = $finalidades ?? [
    [
        'code'            => 'identificacao',
        'label'           => __('Identificação e cadastro', 'participe-ibram'),
        'description'     => __('Nome, CPF/CNPJ/Passaporte, contato, vínculo institucional. Sem isso não é possível registrar você como agente.', 'participe-ibram'),
        'legal_basis'     => __('Política pública', 'participe-ibram'),
        'legal_reference' => __('Portaria IBRAM 3230/2024; LGPD Art. 7º, III', 'participe-ibram'),
        'required'        => true,
        'sensitive'       => false,
    ],
    [
        'code'            => 'comunicacao',
        'label'           => __('Comunicação institucional', 'participe-ibram'),
        'description'     => __('Receber notificações sobre seu cadastro, editais e votações.', 'participe-ibram'),
        'legal_basis'     => __('Execução do cadastro', 'participe-ibram'),
        'legal_reference' => __('LGPD Art. 7º, III', 'participe-ibram'),
        'required'        => true,
        'sensitive'       => false,
    ],
    [
        'code'            => 'documentos_legais',
        'label'           => __('Documentos legais comprobatórios', 'participe-ibram'),
        'description'     => __('CPF, RG, CNPJ, ata, estatuto — armazenados criptografados.', 'participe-ibram'),
        'legal_basis'     => __('Obrigação legal', 'participe-ibram'),
        'legal_reference' => __('LGPD Art. 7º, II; Portaria 3230/2024 Art. 6º', 'participe-ibram'),
        'required'        => true,
        'sensitive'       => true,
    ],
    [
        'code'            => 'raca_cor',
        'label'           => __('Dados de raça/cor', 'participe-ibram'),
        'description'     => __('Autodeclaração de raça/cor (IBGE) para política afirmativa de representação. Permite "Prefiro não informar".', 'participe-ibram'),
        'legal_basis'     => __('Obrigação legal + consentimento', 'participe-ibram'),
        'legal_reference' => __('Lei 14.553/2023; LGPD Art. 11, II, "a"', 'participe-ibram'),
        'required'        => false,
        'sensitive'       => true,
    ],
    [
        'code'            => 'genero_orientacao',
        'label'           => __('Identidade de gênero e orientação sexual', 'participe-ibram'),
        'description'     => __('Análise de representatividade nas instâncias. Permite "Prefiro não informar".', 'participe-ibram'),
        'legal_basis'     => __('Política pública + consentimento', 'participe-ibram'),
        'legal_reference' => __('LGPD Art. 11, II, "b"', 'participe-ibram'),
        'required'        => false,
        'sensitive'       => true,
    ],
    [
        'code'            => 'pct',
        'label'           => __('Filiação a povos e comunidades tradicionais', 'participe-ibram'),
        'description'     => __('Para garantir representação de PCT. Conforme Decreto 8.750/2016.', 'participe-ibram'),
        'legal_basis'     => __('Política pública + consentimento', 'participe-ibram'),
        'legal_reference' => __('Decreto 8.750/2016; LGPD Art. 11, II, "b"', 'participe-ibram'),
        'required'        => false,
        'sensitive'       => true,
    ],
    [
        'code'            => 'deficiencia',
        'label'           => __('Dados de deficiência e acessibilidade', 'participe-ibram'),
        'description'     => __('Para garantirmos acessibilidade a você nas atividades.', 'participe-ibram'),
        'legal_basis'     => __('Política pública + consentimento', 'participe-ibram'),
        'legal_reference' => __('LBI 13.146/2015; LGPD Art. 11, II, "b"', 'participe-ibram'),
        'required'        => false,
        'sensitive'       => true,
    ],
    [
        'code'            => 'newsletter',
        'label'           => __('Receber boletim informativo do IBRAM', 'participe-ibram'),
        'description'     => __('Notícias, eventos e programas culturais. Revogável a qualquer tempo.', 'participe-ibram'),
        'legal_basis'     => __('Consentimento', 'participe-ibram'),
        'legal_reference' => __('LGPD Art. 7º, I', 'participe-ibram'),
        'required'        => false,
        'sensitive'       => false,
    ],
    [
        'code'            => 'pesquisas',
        'label'           => __('Convites para pesquisas científicas IBRAM/parceiros', 'participe-ibram'),
        'description'     => __('Convites para participar de pesquisas museológicas. Revogável.', 'participe-ibram'),
        'legal_basis'     => __('Consentimento', 'participe-ibram'),
        'legal_reference' => __('LGPD Art. 7º, I', 'participe-ibram'),
        'required'        => false,
        'sensitive'       => false,
    ],
    [
        'code'            => 'sbm_estatistica',
        'label'           => __('Compartilhar dados pseudonimizados com SBM', 'participe-ibram'),
        'description'     => __('Para estatística do Sistema Brasileiro de Museus. Pseudonimização irreversível.', 'participe-ibram'),
        'legal_basis'     => __('Consentimento', 'participe-ibram'),
        'legal_reference' => __('LGPD Art. 7º, I + Art. 11', 'participe-ibram'),
        'required'        => false,
        'sensitive'       => false,
    ],
];

/** @var string $policy_version */
$policy_version = $policy_version ?? 'v2026.05.01';
/** @var string $policy_hash */
$policy_hash    = $policy_hash    ?? '';
/** @var string $termo_url */
$termo_url      = $termo_url      ?? '#pi-modal-termo';
/** @var string $dpo_email */
$dpo_email      = $dpo_email      ?? 'encarregado@museus.gov.br';

$obrigatorias = array_values(array_filter(
    $finalidades,
    static function (array $f): bool {
        return !empty($f['required']);
    }
));
$opcionais = array_values(array_filter(
    $finalidades,
    static function (array $f): bool {
        return empty($f['required']);
    }
));
?>
<section class="participe-ibram-scope" aria-labelledby="pi-consent-title">
  <div class="pi-consent-form">
    <header class="pi-consent-form__header">
      <h2 id="pi-consent-title" class="pi-consent-form__title">
        <?php esc_html_e('Termo de Privacidade', 'participe-ibram'); ?>
      </h2>
      <span class="pi-consent-form__version"><?php echo esc_html($policy_version); ?></span>
    </header>

    <p class="pi-consent-form__intro">
      <?php esc_html_e('Para te incluir no Cadastro de Agentes de Participação Social, precisamos do seu consentimento para tratar os seguintes grupos de dados:', 'participe-ibram'); ?>
    </p>

    <a href="<?php echo esc_url($termo_url); ?>"
       class="pi-consent-form__termo-link"
       data-pi-modal-trigger="pi-modal-termo"
       aria-haspopup="dialog">
      <?php esc_html_e('Ver termo completo', 'participe-ibram'); ?>
    </a>

    <?php /* eMAG R44 | WCAG 1.3.1 — agrupar campos em fieldset/legend */ ?>
    <fieldset class="pi-consent-form__group pi-consent-form__group--required">
      <legend class="pi-consent-form__group-title">
        <?php esc_html_e('Tratamentos obrigatórios (política pública)', 'participe-ibram'); ?>
      </legend>

      <?php foreach ($obrigatorias as $idx => $f) :
          $cb_id = 'pi-consent-' . sanitize_html_class($f['code']);
          ?>
        <div class="pi-consent-form__purpose pi-consent-form__purpose--required">
          <?php /* checkbox marcado e disabled — campo enviado via hidden abaixo */ ?>
          <input type="checkbox"
                 id="<?php echo esc_attr($cb_id); ?>"
                 checked
                 disabled
                 aria-readonly="true"
                 aria-describedby="<?php echo esc_attr($cb_id); ?>-desc">
          <input type="hidden"
                 name="consent[<?php echo esc_attr($f['code']); ?>]"
                 value="1">

          <div class="pi-consent-form__purpose-meta">
            <label for="<?php echo esc_attr($cb_id); ?>" class="pi-consent-form__purpose-label">
              <?php echo esc_html($f['label']); ?>
              <span class="pi-consent-form__purpose-tag pi-consent-form__purpose-tag--required">
                <?php esc_html_e('Obrigatório', 'participe-ibram'); ?>
              </span>
            </label>
            <p id="<?php echo esc_attr($cb_id); ?>-desc" class="pi-consent-form__purpose-description">
              <?php echo esc_html($f['description']); ?>
            </p>
            <p class="pi-consent-form__legal-basis">
              <strong><?php esc_html_e('Base legal:', 'participe-ibram'); ?></strong>
              <?php echo esc_html($f['legal_basis']); ?>
              <?php if (!empty($f['legal_reference'])) : ?>
                <span class="pi-text-xs"> — <?php echo esc_html($f['legal_reference']); ?></span>
              <?php endif; ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="pi-consent-form__group">
      <legend class="pi-consent-form__group-title">
        <?php esc_html_e('Tratamentos opcionais (consentimento granular)', 'participe-ibram'); ?>
      </legend>

      <?php foreach ($opcionais as $idx => $f) :
          $cb_id = 'pi-consent-' . sanitize_html_class($f['code']);
          ?>
        <div class="pi-consent-form__purpose pi-consent-form__purpose--optional">
          <input type="checkbox"
                 id="<?php echo esc_attr($cb_id); ?>"
                 name="consent[<?php echo esc_attr($f['code']); ?>]"
                 value="1"
                 aria-describedby="<?php echo esc_attr($cb_id); ?>-desc">

          <div class="pi-consent-form__purpose-meta">
            <label for="<?php echo esc_attr($cb_id); ?>" class="pi-consent-form__purpose-label">
              <?php echo esc_html($f['label']); ?>
              <span class="pi-consent-form__purpose-tag pi-consent-form__purpose-tag--optional">
                <?php esc_html_e('Opcional', 'participe-ibram'); ?>
              </span>
            </label>
            <p id="<?php echo esc_attr($cb_id); ?>-desc" class="pi-consent-form__purpose-description">
              <?php echo esc_html($f['description']); ?>
            </p>
            <p class="pi-consent-form__legal-basis">
              <strong><?php esc_html_e('Base legal:', 'participe-ibram'); ?></strong>
              <?php echo esc_html($f['legal_basis']); ?>
              <?php if (!empty($f['legal_reference'])) : ?>
                <span class="pi-text-xs"> — <?php echo esc_html($f['legal_reference']); ?></span>
              <?php endif; ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>

    <?php /* Hidden inputs para versão e hash (auditoria do consentimento) */ ?>
    <input type="hidden" name="policy_version" value="<?php echo esc_attr($policy_version); ?>">
    <input type="hidden" name="policy_hash"    value="<?php echo esc_attr($policy_hash); ?>">

    <footer class="pi-consent-form__footer">
      <p class="pi-consent-form__dpo-info">
        <?php esc_html_e('Encarregado de Dados (DPO):', 'participe-ibram'); ?>
        <a href="mailto:<?php echo esc_attr($dpo_email); ?>"><?php echo esc_html($dpo_email); ?></a>
      </p>
    </footer>
  </div>
</section>
