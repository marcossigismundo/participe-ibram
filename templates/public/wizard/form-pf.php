<?php
/**
 * Wizard de cadastro - Pessoa Fisica (PF).
 *
 * Variaveis esperadas:
 *  - $agente_id (int|string|null)  ID se editando rascunho
 *  - $api_url   (string)           ex: home_url('/wp-json/pi/v1')
 *  - $rest_nonce (string)          wp_create_nonce('wp_rest')
 *  - $sucesso_url (string|null)
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$agente_id   = $agente_id   ?? '';
$sucesso_url = $sucesso_url ?? '';

$steps = [
    ['id' => 'pi-passo-pf-identificacao', 'titulo' => __('Identificação', 'participe-ibram')],
    ['id' => 'pi-passo-pf-demografia',    'titulo' => __('Demografia', 'participe-ibram')],
    ['id' => 'pi-passo-pf-contato',       'titulo' => __('Endereço & Contato', 'participe-ibram')],
    ['id' => 'pi-passo-pf-atuacao',       'titulo' => __('Atuação', 'participe-ibram')],
    ['id' => 'pi-passo-pf-documentos',    'titulo' => __('Documentos', 'participe-ibram')],
    ['id' => 'pi-passo-pf-lgpd',          'titulo' => __('LGPD & Submissão', 'participe-ibram')],
];
$total = count($steps);
?>
<div class="participe-ibram-scope">
    <a class="pi-skip-link" href="#pi-conteudo-principal"><?php esc_html_e('Pular para o conteúdo principal', 'participe-ibram'); ?></a>

    <main id="pi-conteudo-principal" tabindex="-1">

        <header class="pi-wizard__header">
            <h1><?php esc_html_e('Cadastro de Agente — Pessoa Física', 'participe-ibram'); ?></h1>
            <p class="pi-passo-instrucoes">
                <?php esc_html_e('Preencha os passos abaixo. Os campos marcados com', 'participe-ibram'); ?>
                <span aria-hidden="true">*</span>
                <span class="sr-only"><?php esc_html_e('asterisco', 'participe-ibram'); ?></span>
                <?php esc_html_e('são obrigatórios. Seu rascunho é salvo automaticamente.', 'participe-ibram'); ?>
            </p>
        </header>

        <form
            class="pi-wizard"
            data-pi-wizard
            data-tipo="PF"
            data-agente-id="<?php echo esc_attr((string) $agente_id); ?>"
            <?php if ($sucesso_url) : ?>data-sucesso-url="<?php echo esc_url($sucesso_url); ?>"<?php endif; ?>
            novalidate
            aria-label="<?php echo esc_attr__('Cadastro de agente Pessoa Física', 'participe-ibram'); ?>"
        >
            <nav class="pi-wizard__nav" aria-label="<?php echo esc_attr__('Progresso do cadastro', 'participe-ibram'); ?>">
                <ol class="pi-wizard__steps">
                    <?php foreach ($steps as $i => $step) :
                        $num = $i + 1;
                        $is_first = $i === 0;
                        ?>
                        <li
                            class="pi-wizard__step<?php echo $is_first ? ' is-current' : ''; ?>"
                            <?php echo $is_first ? 'aria-current="step"' : ''; ?>
                        >
                            <button
                                type="button"
                                class="pi-wizard__step-btn"
                                data-acao="ir-para"
                                data-passo="<?php echo (int) $i; ?>"
                                aria-label="<?php
                                    echo esc_attr(sprintf(
                                        /* translators: 1: numero do passo, 2: total, 3: titulo */
                                        __('Ir para o passo %1$d de %2$d: %3$s', 'participe-ibram'),
                                        $num,
                                        $total,
                                        $step['titulo']
                                    ));
                                ?>"
                            >
                                <span class="pi-wizard__step-num" aria-hidden="true"><?php echo (int) $num; ?></span>
                                <span class="pi-wizard__step-label"><?php echo esc_html($step['titulo']); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <p class="pi-wizard__autosave-status sr-only" role="status" aria-live="polite" aria-atomic="true"></p>

            <!-- ===================== PASSO 1: IDENTIFICACAO ===================== -->
            <section
                id="pi-passo-pf-identificacao"
                class="pi-wizard-panel"
                aria-labelledby="pi-passo-pf-identificacao-titulo"
            >
                <h2 id="pi-passo-pf-identificacao-titulo" tabindex="-1">
                    <?php esc_html_e('Passo 1 de 6: Identificação', 'participe-ibram'); ?>
                </h2>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Dados de identificação', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-pf-nome-completo">
                            <?php esc_html_e('Nome completo', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text" id="pi-pf-nome-completo" name="nome_completo" required
                            autocomplete="name" aria-required="true"
                            aria-describedby="pi-pf-nome-completo-dica pi-pf-nome-completo-erro"
                        >
                        <p id="pi-pf-nome-completo-dica" class="pi-campo__dica"><?php esc_html_e('Como aparece no seu documento de identidade.', 'participe-ibram'); ?></p>
                        <p id="pi-pf-nome-completo-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-nome-social">
                            <?php esc_html_e('Nome social', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text" id="pi-pf-nome-social" name="nome_social" required
                            autocomplete="nickname" aria-required="true"
                            aria-describedby="pi-pf-nome-social-dica pi-pf-nome-social-erro"
                        >
                        <p id="pi-pf-nome-social-dica" class="pi-campo__dica"><?php esc_html_e('Nome pelo qual você prefere ser chamado(a). Pode repetir o nome completo.', 'participe-ibram'); ?></p>
                        <p id="pi-pf-nome-social-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-cpf">
                            <?php esc_html_e('CPF', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                            <button
                                type="button" class="pi-help-button"
                                aria-label="<?php echo esc_attr__('Por que pedimos CPF?', 'participe-ibram'); ?>"
                                aria-haspopup="dialog" aria-controls="pi-modal-help-cpf"
                                data-pi-modal-open="pi-modal-help-cpf"
                            >?</button>
                        </label>
                        <input
                            type="text" id="pi-pf-cpf" name="cpf" required
                            inputmode="numeric" autocomplete="off"
                            data-mask="cpf" data-validate="cpf"
                            aria-required="true"
                            aria-describedby="pi-pf-cpf-dica pi-pf-cpf-erro"
                        >
                        <p id="pi-pf-cpf-dica" class="pi-campo__dica"><?php esc_html_e('Formato: 000.000.000-00.', 'participe-ibram'); ?></p>
                        <p id="pi-pf-cpf-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-data-nascimento">
                            <?php esc_html_e('Data de nascimento', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <input
                            type="date" id="pi-pf-data-nascimento" name="data_nascimento" required
                            autocomplete="bday" aria-required="true"
                            aria-describedby="pi-pf-data-nascimento-erro"
                        >
                        <p id="pi-pf-data-nascimento-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-nacionalidade">
                            <?php esc_html_e('Nacionalidade', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <select
                            id="pi-pf-nacionalidade" name="nacionalidade" required
                            aria-required="true"
                            data-pi-vocabulario="nacionalidades"
                            aria-describedby="pi-pf-nacionalidade-erro"
                        >
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                        </select>
                        <p id="pi-pf-nacionalidade-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-passaporte"><?php esc_html_e('Passaporte (estrangeiros)', 'participe-ibram'); ?></label>
                        <input type="text" id="pi-pf-passaporte" name="passaporte" autocomplete="off">
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes" aria-label="<?php echo esc_attr__('Navegação do formulário', 'participe-ibram'); ?>">
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ===================== PASSO 2: DEMOGRAFIA ===================== -->
            <section id="pi-passo-pf-demografia" class="pi-wizard-panel" aria-labelledby="pi-passo-pf-demografia-titulo" hidden>
                <h2 id="pi-passo-pf-demografia-titulo" tabindex="-1">
                    <?php esc_html_e('Passo 2 de 6: Demografia', 'participe-ibram'); ?>
                </h2>

                <p class="pi-passo-instrucoes">
                    <?php esc_html_e('Estes dados são usados para políticas afirmativas. Você pode optar por não informar.', 'participe-ibram'); ?>
                </p>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Dados demográficos', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-pf-faixa-etaria">
                            <?php esc_html_e('Faixa etária', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <select id="pi-pf-faixa-etaria" name="faixa_etaria" required aria-required="true"
                                data-pi-vocabulario="faixas_etarias" aria-describedby="pi-pf-faixa-etaria-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                        </select>
                        <p id="pi-pf-faixa-etaria-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-genero">
                            <?php esc_html_e('Identidade de gênero', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <select id="pi-pf-genero" name="identidade_genero" required aria-required="true"
                                data-pi-vocabulario="identidades_genero" aria-describedby="pi-pf-genero-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                        </select>
                        <p id="pi-pf-genero-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-orientacao-sexual"><?php esc_html_e('Orientação sexual', 'participe-ibram'); ?></label>
                        <select id="pi-pf-orientacao-sexual" name="orientacao_sexual" data-pi-vocabulario="orientacoes_sexuais">
                            <option value=""><?php esc_html_e('Prefiro não informar', 'participe-ibram'); ?></option>
                        </select>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-raca-cor">
                            <?php esc_html_e('Raça/cor', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <select id="pi-pf-raca-cor" name="raca_cor" required aria-required="true"
                                data-pi-vocabulario="racas_cor" aria-describedby="pi-pf-raca-cor-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                        </select>
                        <p id="pi-pf-raca-cor-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-grau-instrucao">
                            <?php esc_html_e('Grau de instrução', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        </label>
                        <select id="pi-pf-grau-instrucao" name="grau_instrucao" required aria-required="true"
                                data-pi-vocabulario="graus_instrucao" aria-describedby="pi-pf-grau-instrucao-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                        </select>
                        <p id="pi-pf-grau-instrucao-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <fieldset id="pi-pf-pcd-fieldset">
                        <legend>
                            <?php esc_html_e('Pessoa com Deficiência (PCD)', 'participe-ibram'); ?>
                            <button type="button" class="pi-help-button"
                                aria-label="<?php echo esc_attr__('Sobre auto-declaração PCD', 'participe-ibram'); ?>"
                                aria-haspopup="dialog" aria-controls="pi-modal-help-pcd"
                                data-pi-modal-open="pi-modal-help-pcd">?</button>
                        </legend>
                        <div class="pi-radio-group">
                            <label><input type="radio" name="pcd" value="nao" checked> <?php esc_html_e('Não', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="pcd" value="sim"> <?php esc_html_e('Sim', 'participe-ibram'); ?></label>
                            <label><input type="radio" name="pcd" value="prefiro_nao_informar"> <?php esc_html_e('Prefiro não informar', 'participe-ibram'); ?></label>
                        </div>
                    </fieldset>

                    <fieldset id="pi-pf-pct-fieldset" data-pi-checkbox-group>
                        <legend>
                            <?php esc_html_e('Povos e Comunidades Tradicionais (PCT)', 'participe-ibram'); ?>
                            <button type="button" class="pi-help-button"
                                aria-label="<?php echo esc_attr__('Sobre PCT (Decreto 8.750/2016)', 'participe-ibram'); ?>"
                                aria-haspopup="dialog" aria-controls="pi-modal-help-pct"
                                data-pi-modal-open="pi-modal-help-pct">?</button>
                        </legend>
                        <p class="pi-campo__dica"><?php esc_html_e('Marque um ou mais grupos com os quais você se identifica (opcional). Decreto 8.750/2016, art. 4º.', 'participe-ibram'); ?></p>
                        <div id="pi-pf-pct-grupos" class="pi-checkbox-grid" data-pi-vocabulario="povos_comunidades_tradicionais" data-pi-checkbox-group-name="pct_grupos">
                            <!-- 28+ checkboxes injetados via JS getVocabulario('povos_comunidades_tradicionais') -->
                        </div>
                    </fieldset>
                </fieldset>

                <nav class="pi-wizard__acoes" aria-label="<?php echo esc_attr__('Navegação do formulário', 'participe-ibram'); ?>">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ===================== PASSO 3: CONTATO ===================== -->
            <section id="pi-passo-pf-contato" class="pi-wizard-panel" aria-labelledby="pi-passo-pf-contato-titulo" hidden>
                <h2 id="pi-passo-pf-contato-titulo" tabindex="-1">
                    <?php esc_html_e('Passo 3 de 6: Endereço & Contato', 'participe-ibram'); ?>
                </h2>

                <fieldset>
                    <legend><?php esc_html_e('Endereço', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-pf-cep"><?php esc_html_e('CEP', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-pf-cep" name="cep" required inputmode="numeric"
                               data-mask="cep" data-validate="cep" autocomplete="postal-code"
                               aria-required="true" aria-describedby="pi-pf-cep-dica pi-pf-cep-erro">
                        <p id="pi-pf-cep-dica" class="pi-campo__dica"><?php esc_html_e('Formato: 00000-000.', 'participe-ibram'); ?></p>
                        <p id="pi-pf-cep-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-logradouro"><?php esc_html_e('Logradouro', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-pf-logradouro" name="logradouro" required autocomplete="street-address" aria-required="true" aria-describedby="pi-pf-logradouro-erro">
                        <p id="pi-pf-logradouro-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-numero"><?php esc_html_e('Número', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-pf-numero" name="numero" required aria-required="true" aria-describedby="pi-pf-numero-erro">
                        <p id="pi-pf-numero-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-complemento"><?php esc_html_e('Complemento', 'participe-ibram'); ?></label>
                        <input type="text" id="pi-pf-complemento" name="complemento">
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-bairro"><?php esc_html_e('Bairro', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-pf-bairro" name="bairro" required aria-required="true" aria-describedby="pi-pf-bairro-erro">
                        <p id="pi-pf-bairro-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-cidade"><?php esc_html_e('Cidade', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-pf-cidade" name="cidade" required autocomplete="address-level2" aria-required="true" aria-describedby="pi-pf-cidade-erro">
                        <p id="pi-pf-cidade-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-uf"><?php esc_html_e('UF', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <select id="pi-pf-uf" name="uf" required autocomplete="address-level1" aria-required="true" aria-describedby="pi-pf-uf-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                            <?php
                            $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($ufs as $uf) :
                                ?>
                                <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($uf); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="pi-pf-uf-erro" class="pi-campo__erro" hidden></p>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><?php esc_html_e('Contato', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-pf-email"><?php esc_html_e('E-mail', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="email" id="pi-pf-email" name="email" required autocomplete="email" aria-required="true" aria-describedby="pi-pf-email-erro">
                        <p id="pi-pf-email-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-telefone"><?php esc_html_e('Telefone', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="tel" id="pi-pf-telefone" name="telefone" required inputmode="tel"
                               data-mask="phone" data-validate="phone" autocomplete="tel"
                               aria-required="true" aria-describedby="pi-pf-telefone-dica pi-pf-telefone-erro">
                        <p id="pi-pf-telefone-dica" class="pi-campo__dica"><?php esc_html_e('Formato: (00) 00000-0000.', 'participe-ibram'); ?></p>
                        <p id="pi-pf-telefone-erro" class="pi-campo__erro" hidden></p>
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ===================== PASSO 4: ATUACAO ===================== -->
            <section id="pi-passo-pf-atuacao" class="pi-wizard-panel" aria-labelledby="pi-passo-pf-atuacao-titulo" hidden>
                <h2 id="pi-passo-pf-atuacao-titulo" tabindex="-1">
                    <?php esc_html_e('Passo 4 de 6: Atuação', 'participe-ibram'); ?>
                </h2>

                <fieldset>
                    <legend class="sr-only"><?php esc_html_e('Dados de atuação profissional', 'participe-ibram'); ?></legend>

                    <div class="pi-campo">
                        <label for="pi-pf-ocupacao"><?php esc_html_e('Ocupação principal', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <select id="pi-pf-ocupacao" name="ocupacao" required aria-required="true"
                                data-pi-vocabulario="ocupacoes" aria-describedby="pi-pf-ocupacao-erro">
                            <option value=""><?php esc_html_e('Selecione...', 'participe-ibram'); ?></option>
                        </select>
                        <p id="pi-pf-ocupacao-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <div class="pi-campo">
                        <label for="pi-pf-instituicao"><?php esc_html_e('Instituição/local de atuação', 'participe-ibram'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="pi-pf-instituicao" name="instituicao" required autocomplete="organization" aria-required="true" aria-describedby="pi-pf-instituicao-dica pi-pf-instituicao-erro">
                        <p id="pi-pf-instituicao-dica" class="pi-campo__dica"><?php esc_html_e('Ex.: Museu Nacional, MAR, Pinacoteca, ponto de memória.', 'participe-ibram'); ?></p>
                        <p id="pi-pf-instituicao-erro" class="pi-campo__erro" hidden></p>
                    </div>

                    <fieldset data-pi-checkbox-group>
                        <legend><?php esc_html_e('Áreas temáticas de atuação', 'participe-ibram'); ?> <span aria-hidden="true">*</span></legend>
                        <p class="pi-campo__dica"><?php esc_html_e('Selecione uma ou mais áreas.', 'participe-ibram'); ?></p>
                        <div id="pi-pf-areas-tematicas" class="pi-checkbox-grid" data-pi-vocabulario="areas_tematicas" data-pi-checkbox-group-name="areas_tematicas"></div>
                        <p id="pi-pf-areas-tematicas-erro" class="pi-campo__erro" hidden></p>
                    </fieldset>

                    <fieldset data-pi-checkbox-group>
                        <legend><?php esc_html_e('Instâncias de participação (opcional)', 'participe-ibram'); ?></legend>
                        <div id="pi-pf-instancias-participacao" class="pi-checkbox-grid" data-pi-vocabulario="instancias_participacao" data-pi-checkbox-group-name="instancias_participacao"></div>
                    </fieldset>

                    <div class="pi-campo">
                        <label for="pi-pf-experiencia"><?php esc_html_e('Experiência relevante (opcional)', 'participe-ibram'); ?></label>
                        <textarea id="pi-pf-experiencia" name="experiencia" rows="5" maxlength="3000"></textarea>
                    </div>
                </fieldset>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ===================== PASSO 5: DOCUMENTOS ===================== -->
            <section id="pi-passo-pf-documentos" class="pi-wizard-panel" aria-labelledby="pi-passo-pf-documentos-titulo" hidden>
                <h2 id="pi-passo-pf-documentos-titulo" tabindex="-1">
                    <?php esc_html_e('Passo 5 de 6: Documentos', 'participe-ibram'); ?>
                </h2>

                <p class="pi-passo-instrucoes">
                    <?php esc_html_e('Anexe seus documentos. Formatos aceitos e tamanho máximo são exibidos em cada campo.', 'participe-ibram'); ?>
                </p>

                <div
                    id="pi-pf-doc-rg"
                    class="pi-upload"
                    data-pi-fileupload
                    data-tipo-codigo="rg"
                    data-mime="application/pdf,image/jpeg,image/png"
                    data-max-bytes="5242880"
                >
                    <label for="pi-pf-doc-rg-input">
                        <?php esc_html_e('RG ou documento de identidade', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                        <button type="button" class="pi-help-button"
                            aria-label="<?php echo esc_attr__('Sobre o documento de identidade', 'participe-ibram'); ?>"
                            aria-haspopup="dialog" aria-controls="pi-modal-help-rg"
                            data-pi-modal-open="pi-modal-help-rg">?</button>
                    </label>
                    <input type="file" id="pi-pf-doc-rg-input" name="doc_rg" accept=".pdf,.jpg,.jpeg,.png" required aria-required="true" aria-describedby="pi-pf-doc-rg-dica">
                    <p id="pi-pf-doc-rg-dica" class="pi-campo__dica"><?php esc_html_e('PDF, PNG ou JPG. Tamanho máximo: 5 MB.', 'participe-ibram'); ?></p>
                </div>

                <div
                    id="pi-pf-doc-cpf"
                    class="pi-upload"
                    data-pi-fileupload
                    data-tipo-codigo="cpf"
                    data-mime="application/pdf,image/jpeg,image/png"
                    data-max-bytes="5242880"
                >
                    <label for="pi-pf-doc-cpf-input">
                        <?php esc_html_e('Comprovante de inscrição no CPF', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                    </label>
                    <input type="file" id="pi-pf-doc-cpf-input" name="doc_cpf" accept=".pdf,.jpg,.jpeg,.png" required aria-required="true" aria-describedby="pi-pf-doc-cpf-dica">
                    <p id="pi-pf-doc-cpf-dica" class="pi-campo__dica"><?php esc_html_e('PDF, PNG ou JPG. Tamanho máximo: 5 MB.', 'participe-ibram'); ?></p>
                </div>

                <div
                    id="pi-pf-doc-carta"
                    class="pi-upload"
                    data-pi-fileupload
                    data-tipo-codigo="carta_apresentacao"
                    data-mime="application/pdf"
                    data-max-bytes="5242880"
                >
                    <label for="pi-pf-doc-carta-input">
                        <?php esc_html_e('Carta de apresentação e intenções', 'participe-ibram'); ?> <span aria-hidden="true">*</span>
                    </label>
                    <input type="file" id="pi-pf-doc-carta-input" name="doc_carta" accept=".pdf" required aria-required="true" aria-describedby="pi-pf-doc-carta-dica">
                    <p id="pi-pf-doc-carta-dica" class="pi-campo__dica"><?php esc_html_e('Apenas PDF. Tamanho máximo: 5 MB. Não tem o documento? ', 'participe-ibram'); ?>
                        <a href="<?php echo esc_url(home_url('/wp-json/pi/v1/wizard/modelo/carta_apresentacao_pf')); ?>"><?php esc_html_e('Baixar modelo preenchido', 'participe-ibram'); ?></a>.
                    </p>
                </div>

                <nav class="pi-wizard__acoes">
                    <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar"><span aria-hidden="true">&larr;</span> <?php esc_html_e('Voltar', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar"><?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?></button>
                    <button type="button" class="pi-btn pi-btn--primario" data-acao="avancar"><?php esc_html_e('Avançar', 'participe-ibram'); ?> <span aria-hidden="true">&rarr;</span></button>
                </nav>
            </section>

            <!-- ===================== PASSO 6: LGPD ===================== -->
            <?php
            $step_id    = 'pi-passo-pf-lgpd';
            $step_num   = 6;
            $step_total = 6;
            include __DIR__ . '/step-lgpd.php';
            ?>
        </form>

        <?php require __DIR__ . '/help-modals.php'; ?>
    </main>
</div>
