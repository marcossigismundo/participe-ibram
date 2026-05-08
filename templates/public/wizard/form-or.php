<?php
/**
 * Wizard de cadastro - Organizacao (OR).
 *
 * Inclui toggle "Tem CNPJ?" sim/nao. Se sim: campo CNPJ + tipo formal. Se nao:
 * tipo_coletivo (vocabulario). Para coletivos sem CNPJ: aviso de exigencia
 * de 5+ representantes (Portaria 3230/2024).
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$agente_id   = $agente_id   ?? '';
$sucesso_url = $sucesso_url ?? '';

$steps = [
    ['id' => 'pi-passo-or-identificacao',  'titulo' => __('Identificação da Organização', 'participe-ibram')],
    ['id' => 'pi-passo-or-caracterizacao', 'titulo' => __('Caracterização', 'participe-ibram')],
    ['id' => 'pi-passo-or-localizacao',    'titulo' => __('Localização & Abrangência', 'participe-ibram')],
    ['id' => 'pi-passo-or-representantes', 'titulo' => __('Representantes', 'participe-ibram')],
    ['id' => 'pi-passo-or-documentos',     'titulo' => __('Documentos', 'participe-ibram')],
    ['id' => 'pi-passo-or-lgpd',           'titulo' => __('LGPD & Submissão', 'participe-ibram')],
];
$total = count($steps);
?>
<div class="participe-ibram-scope">
    <a class="pi-skip-link" href="#pi-conteudo-principal"><?php esc_html_e('Pular para o conteúdo principal', 'participe-ibram'); ?></a>

    <main id="pi-conteudo-principal" tabindex="-1">

        <header class="pi-wizard__header">
            <h1><?php esc_html_e('Cadastro de Agente — Organização', 'participe-ibram'); ?></h1>
            <p class="pi-passo-instrucoes">
                <?php esc_html_e('Para organizações formais (com CNPJ) ou coletivos sem CNPJ. Os campos com', 'participe-ibram'); ?>
                <span aria-hidden="true">*</span>
                <span class="sr-only"><?php esc_html_e('asterisco', 'participe-ibram'); ?></span>
                <?php esc_html_e('são obrigatórios.', 'participe-ibram'); ?>
            </p>
        </header>

        <form
            class="pi-wizard"
            data-pi-wizard
            data-tipo="OR"
            data-agente-id="<?php echo esc_attr((string) $agente_id); ?>"
            <?php if ($sucesso_url) : ?>data-sucesso-url="<?php echo esc_url($sucesso_url); ?>"<?php endif; ?>
            novalidate
            aria-label="<?php echo esc_attr__('Cadastro de agente Organização', 'participe-ibram'); ?>"
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

            <!-- ============== PASSO 1: IDENTIFICACAO ============== -->
            <section id="pi-passo-or-identificacao" class="pi-wizard-panel" aria-labelledby="pi-passo-or-identificacao-titulo">
                <h2 id="pi-passo-or-identificacao-titulo" tabindex="-1">
                    <?php esc_html_e('Passo 1 de 6: Identificação da Organização', 'participe-ibram'); ?>
                </h2>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Identificação', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-or-nome">
                            <?php esc_html_e('Nome da organização ou coletivo', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="pi-or-nome" name="nome" required autocomplete="organization" aria-required="true" aria-describedby="pi-or-nome-erro">
                        <p id="pi-or-nome-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <fieldset id="pi-or-tem-cnpj-fieldset" data-pi-radio-group>
                        <legend>
                            <?php esc_html_e('A organização possui CNPJ?', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                            <button type="button" class="pi-help-button"
                                aria-label="<?php echo esc_attr__('Sobre CNPJ e coletivos', 'participe-ibram'); ?>"
                                aria-haspopup="dialog" aria-controls="pi-modal-help-coletivo"
                                data-pi-modal-open="pi-modal-help-coletivo">?</button>
                        </legend>
                        <div class="pi-radio-group" id="pi-or-tem-cnpj">
                            <label><input type="radio" name="tem_cnpj" value="sim" data-pi-toggle-target=".pi-or-com-cnpj" required aria-required="true"> <?php esc_html_e('Sim', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="tem_cnpj" value="nao" data-pi-toggle-target=".pi-or-sem-cnpj"> <?php esc_html_e('Não (coletivo)', 'participe-ibram'); ?></label>
                        </div>
                    </fieldset>

                    <div class="pi-or-com-cnpj" data-pi-toggle-when='{"tem_cnpj":"sim"}'>
                        <div class="pi-campo">
                            <label for="pi-or-cnpj"><?php esc_html_e('CNPJ', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                            <input type="text" id="pi-or-cnpj" name="cnpj" inputmode="numeric"
                                data-mask="cnpj" data-validate="cnpj"
                                aria-describedby="pi-or-cnpj-dica pi-or-cnpj-erro">
                            <p id="pi-or-cnpj-dica" class="pi-campo__dica"><?php esc_html_e('Formato: 00.000.000/0000-00.', 'participe-ibram'); ?></p>
                            <p id="pi-or-cnpj-erro" class="pi-campo__erro" hidden></p>
                        </div>
                    </div>

                    <div class="pi-or-sem-cnpj" data-pi-toggle-when='{"tem_cnpj":"nao"}'>
                        <div class="pi-campo">
                            <label for="pi-or-tipo-coletivo"><?php esc_html_e('Tipo de coletivo', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                            <select id="pi-or-tipo-coletivo" name="tipo_coletivo" data-pi-vocabulario="tipos_coletivo">
                                <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                            </select>
                        </div>
                        <div class="pi-aviso-coletivo" role="note">
                            <p><strong><?php esc_html_e('Atenção:', 'participe-ibram'); ?></strong>
                                <?php esc_html_e('Coletivos sem CNPJ devem indicar pelo menos 5 representantes no passo "Representantes" e anexar carta de indicação assinada (passo "Documentos"), conforme Portaria IBRAM 3230/2024.', 'participe-ibram'); ?></p>
                        </div>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-data-fundacao"><?php esc_html_e('Data de fundação/criação', 'participe-ibram'); ?></label>
                        <input type="date" id="pi-or-data-fundacao" name="data_fundacao" autocomplete="off">
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 2: CARACTERIZACAO ============== -->
            <section id="pi-passo-or-caracterizacao" class="pi-wizard-panel" aria-labelledby="pi-passo-or-caracterizacao-titulo" hidden>
                <h2 id="pi-passo-or-caracterizacao-titulo" tabindex="-1"><?php esc_html_e('Passo 2 de 6: Caracterização', 'participe-ibram'); ?></h2>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Caracterização da organização', 'participe-ibram'); ?></legend>

                    <fieldset data-pi-checkbox-group>
                        <legend><?php esc_html_e('Áreas temáticas de atuação', 'participe-ibram'); ?> <span aria-hidden="true">*</span></legend>
                        <div id="pi-or-areas-tematicas" class="pi-checkbox-grid" data-pi-vocabulario="areas_tematicas" data-pi-checkbox-group-name="areas_tematicas"></div>
                    </fieldset>

                    <div class="pi-campo">
                        <label for="pi-or-missao"><?php esc_html_e('Missão e objetivos', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <textarea id="pi-or-missao" name="missao" rows="5" required aria-required="true" maxlength="3000" aria-describedby="pi-or-missao-erro"></textarea>
                        <p id="pi-or-missao-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-historico"><?php esc_html_e('Histórico (opcional)', 'participe-ibram'); ?></label>
                        <textarea id="pi-or-historico" name="historico" rows="4" maxlength="3000"></textarea>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-publico-alvo"><?php esc_html_e('Público-alvo (opcional)', 'participe-ibram'); ?></label>
                        <textarea id="pi-or-publico-alvo" name="publico_alvo" rows="3" maxlength="1500"></textarea>
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 3: LOCALIZACAO ============== -->
            <section id="pi-passo-or-localizacao" class="pi-wizard-panel" aria-labelledby="pi-passo-or-localizacao-titulo" hidden>
                <h2 id="pi-passo-or-localizacao-titulo" tabindex="-1"><?php esc_html_e('Passo 3 de 6: Localização & Abrangência', 'participe-ibram'); ?></h2>

                <fieldset>
                    <legend><?php esc_html_e('Endereço da sede', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-or-cep"><?php esc_html_e('CEP', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-or-cep" name="cep" required inputmode="numeric"
                            data-mask="cep" data-validate="cep" autocomplete="postal-code"
                            aria-required="true" aria-describedby="pi-or-cep-erro">
                        <p id="pi-or-cep-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-logradouro"><?php esc_html_e('Logradouro', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-or-logradouro" name="logradouro" required autocomplete="street-address" aria-required="true" aria-describedby="pi-or-logradouro-erro">
                        <p id="pi-or-logradouro-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-numero"><?php esc_html_e('Número', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-or-numero" name="numero" required aria-required="true" aria-describedby="pi-or-numero-erro">
                        <p id="pi-or-numero-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-complemento"><?php esc_html_e('Complemento', 'participe-ibram'); ?></label>
                        <input type="text" id="pi-or-complemento" name="complemento">
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-bairro"><?php esc_html_e('Bairro', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-or-bairro" name="bairro" required aria-required="true" aria-describedby="pi-or-bairro-erro">
                        <p id="pi-or-bairro-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-cidade"><?php esc_html_e('Cidade', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-or-cidade" name="cidade" required autocomplete="address-level2" aria-required="true" aria-describedby="pi-or-cidade-erro">
                        <p id="pi-or-cidade-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-uf"><?php esc_html_e('UF', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <select id="pi-or-uf" name="uf" required autocomplete="address-level1" aria-required="true" aria-describedby="pi-or-uf-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                            <?php
                            $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($ufs as $uf) :
                                ?>
                                <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($uf); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="pi-or-uf-erro" class="pi-campo__erro" hidden></p>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><?php esc_html_e('Abrangência e contato', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-or-abrangencia"><?php esc_html_e('Abrangência', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <select id="pi-or-abrangencia" name="abrangencia" required aria-required="true"
                                data-pi-vocabulario="abrangencias" aria-describedby="pi-or-abrangencia-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                        </select>
                        <p id="pi-or-abrangencia-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-email"><?php esc_html_e('E-mail institucional', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="email" id="pi-or-email" name="email" required autocomplete="email" aria-required="true" aria-describedby="pi-or-email-erro">
                        <p id="pi-or-email-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-telefone"><?php esc_html_e('Telefone', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="tel" id="pi-or-telefone" name="telefone" required inputmode="tel"
                            data-mask="phone" data-validate="phone" autocomplete="tel"
                            aria-required="true" aria-describedby="pi-or-telefone-erro">
                        <p id="pi-or-telefone-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-or-site"><?php esc_html_e('Site (opcional)', 'participe-ibram'); ?></label>
                        <input type="url" id="pi-or-site" name="site" autocomplete="url">
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 4: REPRESENTANTES ============== -->
            <section id="pi-passo-or-representantes" class="pi-wizard-panel" aria-labelledby="pi-passo-or-representantes-titulo" hidden>
                <h2 id="pi-passo-or-representantes-titulo" tabindex="-1"><?php esc_html_e('Passo 4 de 6: Representantes', 'participe-ibram'); ?></h2>

                <p class="pi-passo-instrucoes">
                    <?php esc_html_e('Indique de 1 a 10 representantes da organização. Para coletivos sem CNPJ, mínimo de 5.', 'participe-ibram'); ?>
                </p>

                <div
                    id="pi-or-representantes"
                    class="pi-representantes"
                    data-pi-representantes
                    data-min="1"
                    data-min-coletivo="5"
                    data-max="10"
                    role="region"
                    aria-label="<?php echo esc_attr__('Lista de representantes', 'participe-ibram'); ?>"
                >
                    <table class="pi-representantes__tabela">
                        <caption class="sr-only"><?php esc_html_e('Representantes da organização', 'participe-ibram'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Nome', 'participe-ibram'); ?></th>
                                <th scope="col"><?php esc_html_e('CPF', 'participe-ibram'); ?></th>
                                <th scope="col"><?php esc_html_e('Cargo', 'participe-ibram'); ?></th>
                                <th scope="col"><?php esc_html_e('E-mail', 'participe-ibram'); ?></th>
                                <th scope="col"><?php esc_html_e('Ações', 'participe-ibram'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="pi-representantes__corpo">
                            <tr class="pi-representantes__linha" data-index="0">
                                <td>
                                    <label class="sr-only" for="pi-or-rep-nome-0"><?php esc_html_e('Nome do representante 1', 'participe-ibram'); ?></label>
                                    <input type="text" id="pi-or-rep-nome-0" name="representantes[0][nome]" required aria-required="true">
                                </td>
                                <td>
                                    <label class="sr-only" for="pi-or-rep-cpf-0"><?php esc_html_e('CPF do representante 1', 'participe-ibram'); ?></label>
                                    <input type="text" id="pi-or-rep-cpf-0" name="representantes[0][cpf]" inputmode="numeric" data-mask="cpf" data-validate="cpf" required aria-required="true">
                                </td>
                                <td>
                                    <label class="sr-only" for="pi-or-rep-cargo-0"><?php esc_html_e('Cargo do representante 1', 'participe-ibram'); ?></label>
                                    <input type="text" id="pi-or-rep-cargo-0" name="representantes[0][cargo]" required aria-required="true">
                                </td>
                                <td>
                                    <label class="sr-only" for="pi-or-rep-email-0"><?php esc_html_e('E-mail do representante 1', 'participe-ibram'); ?></label>
                                    <input type="email" id="pi-or-rep-email-0" name="representantes[0][email]" required aria-required="true">
                                </td>
                                <td>
                                    <button type="button" class="pi-btn pi-btn--terciario pi-representantes__remover" disabled aria-label="<?php echo esc_attr__('Remover representante 1', 'participe-ibram'); ?>"><?php esc_html_e('Remover', 'participe-ibram'); ?></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="pi-btn pi-btn--secundario pi-representantes__adicionar"><?php esc_html_e('+ Adicionar representante', 'participe-ibram'); ?></button>
                    <p class="pi-representantes__contador" aria-live="polite"></p>
                </div>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 5: DOCUMENTOS ============== -->
            <section id="pi-passo-or-documentos" class="pi-wizard-panel" aria-labelledby="pi-passo-or-documentos-titulo" hidden>
                <h2 id="pi-passo-or-documentos-titulo" tabindex="-1"><?php esc_html_e('Passo 5 de 6: Documentos', 'participe-ibram'); ?></h2>

                <div class="pi-or-com-cnpj" data-pi-toggle-when='{"tem_cnpj":"sim"}'>
                    <div id="pi-or-doc-cnpj" class="pi-upload" data-pi-fileupload data-tipo-codigo="cnpj" data-mime="application/pdf" data-max-bytes="2097152">
                        <label for="pi-or-doc-cnpj-input">
                            <?php esc_html_e('Comprovante de inscrição no CNPJ', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                            <button type="button" class="pi-help-button"
                                aria-label="<?php echo esc_attr__('Sobre o CNPJ', 'participe-ibram'); ?>"
                                aria-haspopup="dialog" aria-controls="pi-modal-help-cnpj"
                                data-pi-modal-open="pi-modal-help-cnpj">?</button>
                        </label>
                        <input type="file" id="pi-or-doc-cnpj-input" accept=".pdf" aria-describedby="pi-or-doc-cnpj-dica">
                        <p id="pi-or-doc-cnpj-dica" class="pi-campo__dica"><?php esc_html_e('PDF. Tamanho máximo: 2 MB.', 'participe-ibram'); ?></p>
                    </div>

                    <div id="pi-or-doc-estatuto" class="pi-upload" data-pi-fileupload data-tipo-codigo="estatuto" data-mime="application/pdf" data-max-bytes="10485760">
                        <label for="pi-or-doc-estatuto-input">
                            <?php esc_html_e('Estatuto social', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <input type="file" id="pi-or-doc-estatuto-input" accept=".pdf" aria-describedby="pi-or-doc-estatuto-dica">
                        <p id="pi-or-doc-estatuto-dica" class="pi-campo__dica"><?php esc_html_e('PDF. Tamanho máximo: 10 MB.', 'participe-ibram'); ?></p>
                    </div>

                    <div id="pi-or-doc-ata-posse" class="pi-upload" data-pi-fileupload data-tipo-codigo="ata_posse" data-mime="application/pdf" data-max-bytes="10485760">
                        <label for="pi-or-doc-ata-posse-input">
                            <?php esc_html_e('Ata de posse da diretoria', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                            <button type="button" class="pi-help-button"
                                aria-label="<?php echo esc_attr__('Como obter ata de posse?', 'participe-ibram'); ?>"
                                aria-haspopup="dialog" aria-controls="pi-modal-help-ata-posse"
                                data-pi-modal-open="pi-modal-help-ata-posse">?</button>
                        </label>
                        <input type="file" id="pi-or-doc-ata-posse-input" accept=".pdf" aria-describedby="pi-or-doc-ata-posse-dica">
                        <p id="pi-or-doc-ata-posse-dica" class="pi-campo__dica"><?php esc_html_e('PDF. Tamanho máximo: 10 MB.', 'participe-ibram'); ?></p>
                    </div>
                </div>

                <div class="pi-or-sem-cnpj" data-pi-toggle-when='{"tem_cnpj":"nao"}'>
                    <div id="pi-or-doc-carta-indicacao" class="pi-upload" data-pi-fileupload data-tipo-codigo="carta_indicacao_coletivo" data-mime="application/pdf" data-max-bytes="10485760">
                        <label for="pi-or-doc-carta-indicacao-input">
                            <?php esc_html_e('Carta de indicação de representante (mín. 5 assinaturas)', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <input type="file" id="pi-or-doc-carta-indicacao-input" accept=".pdf" aria-describedby="pi-or-doc-carta-indicacao-dica">
                        <p id="pi-or-doc-carta-indicacao-dica" class="pi-campo__dica"><?php esc_html_e('PDF. Tamanho máximo: 10 MB.', 'participe-ibram'); ?>
                            <a href="<?php echo esc_url(home_url('/wp-json/pi/v1/wizard/modelo/carta_indicacao_coletivo')); ?>"><?php esc_html_e('Baixar modelo preenchido', 'participe-ibram'); ?></a>.
                        </p>
                    </div>
                </div>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ============== PASSO 6: LGPD ============== -->
            <?php
            $step_id    = 'pi-passo-or-lgpd';
            $step_num   = 6;
            $step_total = 6;
            include __DIR__ . '/step-lgpd.php';
            ?>
        </form>

        <?php require __DIR__ . '/help-modals.php'; ?>
    </main>
</div>
