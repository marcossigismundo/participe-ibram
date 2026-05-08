<?php
/**
 * Template: wizard multi-step de inscrição em edital.
 *
 * Shortcode [pi_inscricao_edital edital_id="..."].
 * Reutiliza Wizard.js + StepDefinitionsInscricao.js (Wave 5).
 * NUNCA exibe nem transmite CPF, email ou outros PII no frontend.
 *
 * Variáveis esperadas:
 *  - $edital_id  (int)    ID do edital.
 *  - $agente_id  (int)    ID do agente logado.
 *  - $api_url    (string) URL base da API REST.
 *  - $rest_nonce (string) Nonce WP REST.
 *
 * Passos:
 *  1. Categoria (seleção)
 *  2. Portfólio (textarea markdown 5000 chars)
 *  3. Documentos (upload via FileUpload.js)
 *  4. Revisão (resumo)
 *  5. Confirmação + LGPD (ConsentForm.js)
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$edital_id  = isset($edital_id)  ? (int) $edital_id  : 0;
$agente_id  = isset($agente_id)  ? (int) $agente_id  : 0;
$api_url    = isset($api_url)    ? (string) $api_url   : '';
$rest_nonce = isset($rest_nonce) ? (string) $rest_nonce : '';

if ($edital_id <= 0) {
    return;
}

$steps = [
    ['id' => 'pi-passo-inscricao-categoria',     'titulo' => __('Categoria', 'participe-ibram')],
    ['id' => 'pi-passo-inscricao-portfolio',      'titulo' => __('Portfólio', 'participe-ibram')],
    ['id' => 'pi-passo-inscricao-documentos',     'titulo' => __('Documentos', 'participe-ibram')],
    ['id' => 'pi-passo-inscricao-revisao',        'titulo' => __('Revisão', 'participe-ibram')],
    ['id' => 'pi-passo-inscricao-confirmacao',    'titulo' => __('Confirmação & LGPD', 'participe-ibram')],
];
$total = count($steps);
?>
<div class="participe-ibram-scope">
    <a class="pi-skip-link" href="#pi-inscricao-principal"><?php esc_html_e('Pular para o formulário de inscrição', 'participe-ibram'); ?></a>

    <main id="pi-inscricao-principal" tabindex="-1">

        <header class="pi-wizard__header">
            <h1><?php esc_html_e('Inscrição em Edital', 'participe-ibram'); ?></h1>
            <p class="pi-passo-instrucoes">
                <?php esc_html_e('Preencha os passos abaixo. Os campos marcados com', 'participe-ibram'); ?>
                <span aria-hidden="true">*</span>
                <span class="sr-only"><?php esc_html_e('asterisco', 'participe-ibram'); ?></span>
                <?php esc_html_e('são obrigatórios.', 'participe-ibram'); ?>
            </p>
        </header>

        <form
            class="pi-wizard"
            data-pi-wizard
            data-tipo="INSCRICAO"
            data-edital-id="<?php echo esc_attr((string) $edital_id); ?>"
            data-agente-id="<?php echo esc_attr((string) $agente_id); ?>"
            novalidate
            aria-label="<?php echo esc_attr__('Formulário de inscrição em edital', 'participe-ibram'); ?>"
        >
            <?php if (function_exists('wp_nonce_field')) : ?>
                <?php wp_nonce_field('pi_inscricao_' . $edital_id, 'pi_inscricao_nonce'); ?>
            <?php endif; ?>

            <!-- Navegação de progresso (aria-current gerenciado pelo Wizard.js) -->
            <nav class="pi-wizard__nav" aria-label="<?php echo esc_attr__('Progresso da inscrição', 'participe-ibram'); ?>">
                <ol class="pi-wizard__steps">
                    <?php foreach ($steps as $i => $step) :
                        $num      = $i + 1;
                        $is_first = ($i === 0);
                        ?>
                        <li
                            class="pi-wizard__step<?php echo $is_first ? ' is-active' : ''; ?>"
                            data-step="<?php echo esc_attr((string) $num); ?>"
                            aria-current="<?php echo $is_first ? 'step' : 'false'; ?>"
                        >
                            <span class="pi-wizard__step-num" aria-hidden="true"><?php echo (int) $num; ?></span>
                            <span class="pi-wizard__step-label"><?php echo esc_html($step['titulo']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <!-- Região de erros acessível -->
            <div
                id="pi-inscricao-erros"
                class="pi-wizard__erros"
                role="alert"
                aria-live="assertive"
                aria-atomic="true"
                hidden
            ></div>

            <!-- Passo 1: Categoria -->
            <section
                id="<?php echo esc_attr($steps[0]['id']); ?>"
                class="pi-wizard__passo is-active"
                data-passo="1"
                aria-labelledby="pi-inscricao-h2-1"
            >
                <h2 id="pi-inscricao-h2-1" tabindex="-1"><?php esc_html_e('Passo 1: Selecionar Categoria', 'participe-ibram'); ?></h2>

                <div class="pi-campo-grupo">
                    <label for="pi-inscricao-categoria" class="pi-label">
                        <?php esc_html_e('Categoria', 'participe-ibram'); ?>
                        <span aria-hidden="true" class="pi-obrigatorio">*</span>
                        <span class="sr-only"><?php esc_html_e('(obrigatório)', 'participe-ibram'); ?></span>
                    </label>
                    <select
                        id="pi-inscricao-categoria"
                        name="categoria_id"
                        class="pi-select"
                        required
                        aria-required="true"
                        aria-describedby="pi-cat-hint"
                    >
                        <option value=""><?php esc_html_e('Carregando categorias…', 'participe-ibram'); ?></option>
                    </select>
                    <p id="pi-cat-hint" class="pi-campo-hint">
                        <?php esc_html_e('Apenas categorias elegíveis para o seu tipo de agente são listadas.', 'participe-ibram'); ?>
                    </p>
                    <div id="pi-cat-desc" class="pi-categoria-desc pi-markdown" aria-live="polite"></div>
                </div>
            </section>

            <!-- Passo 2: Portfólio -->
            <section
                id="<?php echo esc_attr($steps[1]['id']); ?>"
                class="pi-wizard__passo"
                data-passo="2"
                hidden
                aria-labelledby="pi-inscricao-h2-2"
            >
                <h2 id="pi-inscricao-h2-2" tabindex="-1"><?php esc_html_e('Passo 2: Portfólio', 'participe-ibram'); ?></h2>

                <div class="pi-campo-grupo">
                    <label for="pi-inscricao-portfolio" class="pi-label">
                        <?php esc_html_e('Descrição do portfólio (Markdown)', 'participe-ibram'); ?>
                    </label>
                    <textarea
                        id="pi-inscricao-portfolio"
                        name="portfolio_md"
                        class="pi-textarea pi-textarea--lg"
                        maxlength="5000"
                        rows="12"
                        aria-describedby="pi-portfolio-hint pi-portfolio-contador"
                        placeholder="<?php esc_attr_e('Descreva suas experiências, projetos e realizações…', 'participe-ibram'); ?>"
                    ></textarea>
                    <p id="pi-portfolio-hint" class="pi-campo-hint">
                        <?php esc_html_e('Máximo de 5.000 caracteres. Suporta formatação Markdown.', 'participe-ibram'); ?>
                    </p>
                    <p id="pi-portfolio-contador" class="pi-campo-contador" aria-live="polite">
                        <span id="pi-portfolio-chars">0</span> / 5000
                    </p>
                </div>
            </section>

            <!-- Passo 3: Documentos -->
            <section
                id="<?php echo esc_attr($steps[2]['id']); ?>"
                class="pi-wizard__passo"
                data-passo="3"
                hidden
                aria-labelledby="pi-inscricao-h2-3"
            >
                <h2 id="pi-inscricao-h2-3" tabindex="-1"><?php esc_html_e('Passo 3: Documentos', 'participe-ibram'); ?></h2>
                <p class="pi-passo-instrucoes"><?php esc_html_e('Envie os documentos exigidos pela categoria selecionada.', 'participe-ibram'); ?></p>

                <div id="pi-inscricao-documentos-lista" class="pi-documentos-lista">
                    <p class="pi-carregando"><?php esc_html_e('Carregando documentos exigidos…', 'participe-ibram'); ?></p>
                </div>
            </section>

            <!-- Passo 4: Revisão -->
            <section
                id="<?php echo esc_attr($steps[3]['id']); ?>"
                class="pi-wizard__passo"
                data-passo="4"
                hidden
                aria-labelledby="pi-inscricao-h2-4"
            >
                <h2 id="pi-inscricao-h2-4" tabindex="-1"><?php esc_html_e('Passo 4: Revisão', 'participe-ibram'); ?></h2>
                <p><?php esc_html_e('Revise os dados antes de submeter.', 'participe-ibram'); ?></p>

                <dl id="pi-inscricao-resumo" class="pi-resumo">
                    <dt><?php esc_html_e('Edital', 'participe-ibram'); ?></dt>
                    <dd id="pi-resumo-edital"></dd>
                    <dt><?php esc_html_e('Categoria', 'participe-ibram'); ?></dt>
                    <dd id="pi-resumo-categoria"></dd>
                    <dt><?php esc_html_e('Portfólio', 'participe-ibram'); ?></dt>
                    <dd><span id="pi-resumo-portfolio-chars" class="pi-resumo-chars"></span></dd>
                    <dt><?php esc_html_e('Documentos enviados', 'participe-ibram'); ?></dt>
                    <dd id="pi-resumo-documentos"></dd>
                </dl>
            </section>

            <!-- Passo 5: Confirmação + LGPD -->
            <section
                id="<?php echo esc_attr($steps[4]['id']); ?>"
                class="pi-wizard__passo"
                data-passo="5"
                hidden
                aria-labelledby="pi-inscricao-h2-5"
            >
                <h2 id="pi-inscricao-h2-5" tabindex="-1"><?php esc_html_e('Passo 5: Confirmação e LGPD', 'participe-ibram'); ?></h2>

                <!-- ConsentForm.js gerencia este bloco via data-pi-consent -->
                <div
                    data-pi-consent
                    data-contexto="candidatura"
                    class="pi-consent-form"
                >
                    <fieldset>
                        <legend class="pi-consent-form__titulo">
                            <?php esc_html_e('Consentimento para candidatura', 'participe-ibram'); ?>
                        </legend>

                        <div class="pi-campo-grupo pi-campo-grupo--checkbox">
                            <input
                                type="checkbox"
                                id="pi-lgpd-candidatura"
                                name="consentimento_candidatura"
                                required
                                aria-required="true"
                                aria-describedby="pi-lgpd-candidatura-desc"
                            >
                            <label for="pi-lgpd-candidatura" class="pi-label">
                                <?php esc_html_e('Autorizo o tratamento dos dados desta candidatura pelo IBRAM conforme a LGPD.', 'participe-ibram'); ?>
                                <span aria-hidden="true" class="pi-obrigatorio">*</span>
                            </label>
                            <p id="pi-lgpd-candidatura-desc" class="pi-campo-hint">
                                <?php esc_html_e('Os dados informados serão usados exclusivamente para análise desta candidatura.', 'participe-ibram'); ?>
                            </p>
                        </div>

                        <div class="pi-campo-grupo pi-campo-grupo--checkbox">
                            <input
                                type="checkbox"
                                id="pi-lgpd-publicidade"
                                name="consentimento_publicidade"
                                required
                                aria-required="true"
                            >
                            <label for="pi-lgpd-publicidade" class="pi-label">
                                <?php esc_html_e('Estou ciente de que meu nome e número de registro poderão ser publicados na lista de habilitados.', 'participe-ibram'); ?>
                                <span aria-hidden="true" class="pi-obrigatorio">*</span>
                            </label>
                        </div>
                    </fieldset>
                </div>

                <div class="pi-wizard__acoes pi-wizard__acoes--submeter">
                    <button
                        type="submit"
                        id="pi-btn-submeter-inscricao"
                        class="pi-btn pi-btn--primario pi-btn--lg"
                        aria-describedby="pi-submeter-hint"
                    >
                        <?php esc_html_e('Submeter Inscrição', 'participe-ibram'); ?>
                    </button>
                    <p id="pi-submeter-hint" class="pi-campo-hint">
                        <?php esc_html_e('Após submeter, sua inscrição não poderá ser editada.', 'participe-ibram'); ?>
                    </p>
                </div>
            </section>

            <!-- Seção de sucesso (oculta até submissão) -->
            <section
                id="pi-inscricao-sucesso"
                class="pi-wizard__sucesso"
                hidden
                aria-labelledby="pi-sucesso-titulo"
                aria-live="assertive"
            >
                <h2 id="pi-sucesso-titulo"><?php esc_html_e('Inscrição realizada com sucesso!', 'participe-ibram'); ?></h2>
                <p><?php esc_html_e('Sua inscrição foi recebida. Guarde o número de protocolo:', 'participe-ibram'); ?></p>
                <p class="pi-protocolo" id="pi-numero-protocolo" aria-live="polite"></p>
            </section>

            <!-- Ações de navegação entre passos -->
            <div class="pi-wizard__nav-acoes" role="group" aria-label="<?php esc_attr_e('Navegação do formulário', 'participe-ibram'); ?>">
                <button type="button" id="pi-btn-anterior" class="pi-btn pi-btn--secundario" hidden aria-label="<?php esc_attr_e('Passo anterior', 'participe-ibram'); ?>">
                    <?php esc_html_e('Anterior', 'participe-ibram'); ?>
                </button>
                <button type="button" id="pi-btn-proximo" class="pi-btn pi-btn--primario" aria-label="<?php esc_attr_e('Próximo passo', 'participe-ibram'); ?>">
                    <?php esc_html_e('Próximo', 'participe-ibram'); ?>
                </button>
            </div>

        </form><!-- /.pi-wizard -->

    </main>

    <!-- Live region para anúncios de acessibilidade -->
    <div
        id="pi-inscricao-live"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        class="sr-only"
    ></div>
</div>

<script>
// Bootstrap do wizard de inscrição — instancia StepDefinitionsInscricao + Wizard.
// Os módulos são carregados pelo bundler via assets/dist/js/wizard/inscricao.js.
(function() {
    'use strict';
    if (typeof window.PiInscricaoWizard === 'undefined') {
        // Fallback: módulo ainda não carregado, aguarda DOMContentLoaded.
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.PiInscricaoWizard !== 'undefined') {
                window.PiInscricaoWizard.init();
            }
        });
        return;
    }
    window.PiInscricaoWizard.init();
}());
</script>
