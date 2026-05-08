<?php
/**
 * Wizard de cadastro - Sistema/Secretaria (SM).
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$agente_id   = $agente_id   ?? '';
$sucesso_url = $sucesso_url ?? '';

$steps = [
    ['id' => 'pi-passo-sm-orgao',        'titulo' => __('Órgão', 'participe-ibram')],
    ['id' => 'pi-passo-sm-marco-legal',  'titulo' => __('Marco Legal', 'participe-ibram')],
    ['id' => 'pi-passo-sm-representante','titulo' => __('Representante Legal', 'participe-ibram')],
    ['id' => 'pi-passo-sm-documentos',   'titulo' => __('Documentos', 'participe-ibram')],
    ['id' => 'pi-passo-sm-lgpd',         'titulo' => __('LGPD & Submissão', 'participe-ibram')],
];
$total = count($steps);
?>
<div class="participe-ibram-scope">
    <a class="pi-skip-link" href="#pi-conteudo-principal"><?php esc_html_e('Pular para o conteúdo principal', 'participe-ibram'); ?></a>

    <main id="pi-conteudo-principal" tabindex="-1">

        <header class="pi-wizard__header">
            <h1><?php esc_html_e('Cadastro de Agente — Sistema de Museu / Secretaria', 'participe-ibram'); ?></h1>
            <p class="pi-passo-instrucoes">
                <?php esc_html_e('Para sistemas de museus, secretarias municipais/estaduais de cultura e órgãos equivalentes (Portaria IBRAM 3230/2024, alínea d).', 'participe-ibram'); ?>
            </p>
        </header>

        <form
            class="pi-wizard"
            data-pi-wizard
            data-tipo="SM"
            data-agente-id="<?php echo esc_attr((string) $agente_id); ?>"
            <?php if ($sucesso_url) : ?>data-sucesso-url="<?php echo esc_url($sucesso_url); ?>"<?php endif; ?>
            novalidate
            aria-label="<?php echo esc_attr__('Cadastro de agente Sistema/Secretaria', 'participe-ibram'); ?>"
        >
            <nav class="pi-wizard__nav" aria-label="<?php echo esc_attr__('Progresso do cadastro', 'participe-ibram'); ?>">
                <ol class="pi-wizard__steps">
                    <?php foreach ($steps as $i => $step) :
                        $num = $i + 1;
                        $is_first = $i === 0;
                        ?>
                        <li class="pi-wizard__step<?php echo $is_first ? ' is-current' : ''; ?>" <?php echo $is_first ? 'aria-current="step"' : ''; ?>>
                            <button type="button" class="pi-wizard__step-btn" data-acao="ir-para" data-passo="<?php echo (int) $i; ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Ir para o passo %1$d de %2$d: %3$s', 'participe-ibram'), $num, $total, $step['titulo'])); ?>">
                                <span class="pi-wizard__step-num" aria-hidden="true"><?php echo (int) $num; ?></span>
                                <span class="pi-wizard__step-label"><?php echo esc_html($step['titulo']); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <p class="pi-wizard__autosave-status sr-only" role="status" aria-live="polite" aria-atomic="true"></p>

            <!-- ============== PASSO 1: ORGAO ============== -->
            <section id="pi-passo-sm-orgao" class="pi-wizard-panel" aria-labelledby="pi-passo-sm-orgao-titulo">
                <h2 id="pi-passo-sm-orgao-titulo" tabindex="-1"><?php esc_html_e('Passo 1 de 5: Órgão', 'participe-ibram'); ?></h2>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Identificação do órgão', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-sm-nome-orgao"><?php esc_html_e('Nome do órgão', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-sm-nome-orgao" name="nome_orgao" required autocomplete="organization" aria-required="true" aria-describedby="pi-sm-nome-orgao-erro">
                        <p id="pi-sm-nome-orgao-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <fieldset id="pi-sm-esfera-fieldset" data-pi-radio-group>
                        <legend><?php esc_html_e('Esfera', 'participe-ibram'); ?> <span aria-hidden="true">*</span></legend>
                        <div class="pi-radio-group" id="pi-sm-esfera">
                            <label><input type="radio" name="esfera" value="federal" required aria-required="true"> <?php esc_html_e('Federal', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="esfera" value="estadual"> <?php esc_html_e('Estadual', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="esfera" value="distrital"> <?php esc_html_e('Distrital', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="esfera" value="municipal"> <?php esc_html_e('Municipal', 'participe-ibram'); ?></label>
                        </div>
                    </fieldset>

                    <fieldset id="pi-sm-tipo-fieldset" data-pi-radio-group>
                        <legend><?php esc_html_e('Tipo', 'participe-ibram'); ?> <span aria-hidden="true">*</span></legend>
                        <div class="pi-radio-group" id="pi-sm-tipo">
                            <label><input type="radio" name="tipo" value="sistema_museu" required aria-required="true"> <?php esc_html_e('Sistema de Museu', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="tipo" value="secretaria_cultura"> <?php esc_html_e('Secretaria de Cultura', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="tipo" value="orgao_equivalente"> <?php esc_html_e('Órgão equivalente', 'participe-ibram'); ?></label>
                        </div>
                    </fieldset>

                    <div class="pi-campo">
                        <label for="pi-sm-cidade"><?php esc_html_e('Cidade', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-sm-cidade" name="cidade" required autocomplete="address-level2" aria-required="true" aria-describedby="pi-sm-cidade-erro">
                        <p id="pi-sm-cidade-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-uf"><?php esc_html_e('UF', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <select id="pi-sm-uf" name="uf" required autocomplete="address-level1" aria-required="true" aria-describedby="pi-sm-uf-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                            <?php
                            $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($ufs as $uf) :
                                ?>
                                <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($uf); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="pi-sm-uf-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-site"><?php esc_html_e('Site institucional (opcional)', 'participe-ibram'); ?></label>
                        <input type="url" id="pi-sm-site" name="site" autocomplete="url">
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 2: MARCO LEGAL ============== -->
            <section id="pi-passo-sm-marco-legal" class="pi-wizard-panel" aria-labelledby="pi-passo-sm-marco-legal-titulo" hidden>
                <h2 id="pi-passo-sm-marco-legal-titulo" tabindex="-1"><?php esc_html_e('Passo 2 de 5: Marco Legal', 'participe-ibram'); ?></h2>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Marco legal de instituição', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-sm-lei-instituicao"><?php esc_html_e('Lei de instituição (número e ano)', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-sm-lei-instituicao" name="lei_instituicao" required aria-required="true" aria-describedby="pi-sm-lei-instituicao-dica pi-sm-lei-instituicao-erro">
                        <p id="pi-sm-lei-instituicao-dica" class="pi-campo__dica"><?php esc_html_e('Ex.: Lei nº 11.904, de 14 de janeiro de 2009.', 'participe-ibram'); ?></p>
                        <p id="pi-sm-lei-instituicao-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-data-lei"><?php esc_html_e('Data da lei', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="date" id="pi-sm-data-lei" name="data_lei" required aria-required="true" aria-describedby="pi-sm-data-lei-erro">
                        <p id="pi-sm-data-lei-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-decreto-regulamentacao"><?php esc_html_e('Decreto de regulamentação (opcional)', 'participe-ibram'); ?></label>
                        <input type="text" id="pi-sm-decreto-regulamentacao" name="decreto_regulamentacao">
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 3: REPRESENTANTE LEGAL ============== -->
            <section id="pi-passo-sm-representante" class="pi-wizard-panel" aria-labelledby="pi-passo-sm-representante-titulo" hidden>
                <h2 id="pi-passo-sm-representante-titulo" tabindex="-1"><?php esc_html_e('Passo 3 de 5: Representante Legal', 'participe-ibram'); ?></h2>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Dados do representante legal', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-sm-rep-nome"><?php esc_html_e('Nome completo do(a) representante', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-sm-rep-nome" name="rep_nome" required autocomplete="name" aria-required="true" aria-describedby="pi-sm-rep-nome-erro">
                        <p id="pi-sm-rep-nome-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-rep-cpf"><?php esc_html_e('CPF', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                            <button type="button" class="pi-help-button"
                                aria-label="<?php echo esc_attr__('Por que pedimos CPF?', 'participe-ibram'); ?>"
                                aria-haspopup="dialog" aria-controls="pi-modal-help-cpf"
                                data-pi-modal-open="pi-modal-help-cpf">?</button>
                        </label>
                        <input type="text" id="pi-sm-rep-cpf" name="rep_cpf" inputmode="numeric"
                            data-mask="cpf" data-validate="cpf"
                            required aria-required="true" aria-describedby="pi-sm-rep-cpf-erro">
                        <p id="pi-sm-rep-cpf-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-rep-cargo"><?php esc_html_e('Cargo/função', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-sm-rep-cargo" name="rep_cargo" required aria-required="true" aria-describedby="pi-sm-rep-cargo-erro">
                        <p id="pi-sm-rep-cargo-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-rep-email"><?php esc_html_e('E-mail funcional', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="email" id="pi-sm-rep-email" name="rep_email" required autocomplete="email" aria-required="true" aria-describedby="pi-sm-rep-email-erro">
                        <p id="pi-sm-rep-email-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-sm-rep-telefone"><?php esc_html_e('Telefone funcional', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="tel" id="pi-sm-rep-telefone" name="rep_telefone" required inputmode="tel"
                            data-mask="phone" data-validate="phone"
                            aria-required="true" aria-describedby="pi-sm-rep-telefone-erro">
                        <p id="pi-sm-rep-telefone-erro" class="pi-campo__erro" hidden></p>
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 4: DOCUMENTOS ============== -->
            <section id="pi-passo-sm-documentos" class="pi-wizard-panel" aria-labelledby="pi-passo-sm-documentos-titulo" hidden>
                <h2 id="pi-passo-sm-documentos-titulo" tabindex="-1"><?php esc_html_e('Passo 4 de 5: Documentos', 'participe-ibram'); ?></h2>

                <div id="pi-sm-doc-lei" class="pi-upload" data-pi-fileupload data-tipo-codigo="lei_instituicao" data-mime="application/pdf" data-max-bytes="10485760">
                    <label for="pi-sm-doc-lei-input">
                        <?php esc_html_e('Lei de instituição (PDF)', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                    </label>
                    <input type="file" id="pi-sm-doc-lei-input" accept=".pdf" required aria-required="true" aria-describedby="pi-sm-doc-lei-dica">
                    <p id="pi-sm-doc-lei-dica" class="pi-campo__dica"><?php esc_html_e('PDF. Tamanho máximo: 10 MB.', 'participe-ibram'); ?></p>
                </div>

                <div id="pi-sm-doc-oficio" class="pi-upload" data-pi-fileupload data-tipo-codigo="oficio_indicacao" data-mime="application/pdf" data-max-bytes="5242880">
                    <label for="pi-sm-doc-oficio-input">
                        <?php esc_html_e('Ofício de indicação do(a) representante', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                    </label>
                    <input type="file" id="pi-sm-doc-oficio-input" accept=".pdf" required aria-required="true" aria-describedby="pi-sm-doc-oficio-dica">
                    <p id="pi-sm-doc-oficio-dica" class="pi-campo__dica"><?php esc_html_e('PDF. Tamanho máximo: 5 MB.', 'participe-ibram'); ?>
                        <a href="<?php echo esc_url(home_url('/wp-json/pi/v1/wizard/modelo/oficio_indicacao_sm')); ?>"><?php esc_html_e('Baixar modelo preenchido', 'participe-ibram'); ?></a>.
                    </p>
                </div>

                <div id="pi-sm-doc-rg-rep" class="pi-upload" data-pi-fileupload data-tipo-codigo="rg" data-mime="application/pdf,image/jpeg,image/png" data-max-bytes="5242880">
                    <label for="pi-sm-doc-rg-rep-input">
                        <?php esc_html_e('RG do(a) representante (opcional)', 'participe-ibram'); ?>
                    </label>
                    <input type="file" id="pi-sm-doc-rg-rep-input" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="pi-sm-doc-rg-rep-dica">
                    <p id="pi-sm-doc-rg-rep-dica" class="pi-campo__dica"><?php esc_html_e('PDF, JPG ou PNG. Máximo 5 MB.', 'participe-ibram'); ?></p>
                </div>

                <div id="pi-sm-doc-cpf-rep" class="pi-upload" data-pi-fileupload data-tipo-codigo="cpf" data-mime="application/pdf,image/jpeg,image/png" data-max-bytes="5242880">
                    <label for="pi-sm-doc-cpf-rep-input">
                        <?php esc_html_e('CPF do(a) representante (opcional)', 'participe-ibram'); ?>
                    </label>
                    <input type="file" id="pi-sm-doc-cpf-rep-input" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="pi-sm-doc-cpf-rep-dica">
                    <p id="pi-sm-doc-cpf-rep-dica" class="pi-campo__dica"><?php esc_html_e('PDF, JPG ou PNG. Máximo 5 MB.', 'participe-ibram'); ?></p>
                </div>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 5: LGPD ============== -->
            <?php
            $step_id    = 'pi-passo-sm-lgpd';
            $step_num   = 5;
            $step_total = 5;
            include __DIR__ . '/step-lgpd.php';
            ?>
        </form>

        <?php require __DIR__ . '/help-modals.php'; ?>
    </main>
</div>
