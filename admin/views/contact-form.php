<?php
/**
 * View do formulário de contato
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inclui modais de ajuda
require_once CRM_DEV_PLUGIN_DIR . 'admin/views/partials/help-modals.php';

$contact_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$contact = $contact_id ? CRM_Dev_Contacts::get_contact($contact_id) : array();
$is_edit = !empty($contact);

// Deserializa campos array
$array_fields = array('etapa_participacao', 'tipo_participacao', 'categoria_representacao', 'eixo_tematico');
foreach ($array_fields as $field) {
    if (!empty($contact[$field])) {
        $unserialized = maybe_unserialize($contact[$field]);
        $contact[$field] = is_array($unserialized) ? $unserialized : array($unserialized);
    } else {
        $contact[$field] = array();
    }
}

// Helpers
$estados = CRM_Dev_Helpers::get_estados();
$generos = CRM_Dev_Helpers::get_generos();
$racas = CRM_Dev_Helpers::get_racas();
$etapas = CRM_Dev_Helpers::get_etapas_participacao();
$tipos_part = CRM_Dev_Helpers::get_tipos_participacao();
$categorias = CRM_Dev_Helpers::get_categorias_representacao();
$eixos = CRM_Dev_Helpers::get_eixos_tematicos();

function get_value($contact, $field, $default = '') {
    return isset($contact[$field]) ? $contact[$field] : $default;
}

function is_checked($contact, $field, $value) {
    if (!isset($contact[$field])) return false;
    if (is_array($contact[$field])) {
        return in_array($value, $contact[$field]);
    }
    return $contact[$field] === $value;
}
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <div class="header-title-row">
            <div>
                <h1>
                    <i class="fas fa-<?php echo $is_edit ? 'user-edit' : 'user-plus'; ?>"></i>
                    <?php echo $is_edit ? __('Editar Contato', 'crm-developer') : __('Novo Contato', 'crm-developer'); ?>
                </h1>
                <p class="crm-dev-subtitle">
                    <?php echo $is_edit
                        ? __('Atualize as informações do contato abaixo', 'crm-developer')
                        : __('Preencha as informações do novo contato', 'crm-developer'); ?>
                </p>
            </div>
            <?php crm_dev_render_help_button('contact-form'); ?>
        </div>
    </div>

    <div class="crm-dev-form-container">
        <!-- Navegação das Etapas -->
        <div class="crm-dev-steps">
            <div class="step active" data-step="1">
                <span class="step-number">1</span>
                <span class="step-title"><?php _e('Dados Pessoais', 'crm-developer'); ?></span>
            </div>
            <div class="step" data-step="2">
                <span class="step-number">2</span>
                <span class="step-title"><?php _e('Contato e Localização', 'crm-developer'); ?></span>
            </div>
            <div class="step" data-step="3">
                <span class="step-number">3</span>
                <span class="step-title"><?php _e('Participação', 'crm-developer'); ?></span>
            </div>
            <div class="step" data-step="4">
                <span class="step-number">4</span>
                <span class="step-title"><?php _e('Perfil Sociopolítico', 'crm-developer'); ?></span>
            </div>
            <div class="step" data-step="5">
                <span class="step-number">5</span>
                <span class="step-title"><?php _e('Mobilização', 'crm-developer'); ?></span>
            </div>
            <div class="step" data-step="6">
                <span class="step-number">6</span>
                <span class="step-title"><?php _e('Dados Complementares', 'crm-developer'); ?></span>
            </div>
        </div>

        <form id="contact-form" class="crm-dev-form">
            <input type="hidden" name="id" value="<?php echo $contact_id; ?>">

            <!-- Etapa 1: Dados Pessoais -->
            <div class="form-step active" data-step="1">
                <div class="step-header">
                    <h2><i class="fas fa-user"></i> <?php _e('Dados Pessoais', 'crm-developer'); ?></h2>
                    <p class="step-description">
                        <?php _e('Informações básicas de identificação do contato. O nome completo é obrigatório.', 'crm-developer'); ?>
                    </p>
                </div>

                <div class="form-grid">
                    <!-- Foto do Contato -->
                    <div class="form-group full-width">
                        <label><?php _e('Foto do Contato', 'crm-developer'); ?></label>
                        <div class="contact-photo-upload">
                            <div class="photo-preview" id="photo-preview">
                                <?php
                                $foto_id = get_value($contact, 'foto_id');
                                $foto_url = $foto_id ? wp_get_attachment_image_url($foto_id, 'thumbnail') : '';
                                if ($foto_url) :
                                ?>
                                    <img src="<?php echo esc_url($foto_url); ?>" alt="">
                                    <button type="button" class="btn-remove-photo" title="<?php _e('Remover foto', 'crm-developer'); ?>">&times;</button>
                                <?php else : ?>
                                    <div class="photo-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="photo-actions">
                                <input type="hidden" name="foto_id" id="foto_id" value="<?php echo esc_attr($foto_id); ?>">
                                <button type="button" class="button" id="btn-select-photo">
                                    <i class="fas fa-camera"></i> <?php _e('Selecionar Foto', 'crm-developer'); ?>
                                </button>
                                <span class="field-help"><?php _e('Opcional. Imagem de perfil do contato.', 'crm-developer'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="nome_completo"><?php _e('Nome Completo', 'crm-developer'); ?> <span class="required">*</span></label>
                        <input type="text" id="nome_completo" name="nome_completo" value="<?php echo esc_attr(get_value($contact, 'nome_completo')); ?>" required>
                        <span class="field-help"><?php _e('Nome civil completo conforme documento', 'crm-developer'); ?></span>
                    </div>

                    <div class="form-group full-width">
                        <label for="nome_social"><?php _e('Nome Social', 'crm-developer'); ?></label>
                        <input type="text" id="nome_social" name="nome_social" value="<?php echo esc_attr(get_value($contact, 'nome_social')); ?>">
                        <span class="field-help"><?php _e('Nome pelo qual a pessoa prefere ser chamada (se diferente do nome civil)', 'crm-developer'); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="data_nascimento"><?php _e('Data de Nascimento', 'crm-developer'); ?></label>
                        <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo esc_attr(get_value($contact, 'data_nascimento')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="genero"><?php _e('Gênero', 'crm-developer'); ?></label>
                        <select id="genero" name="genero">
                            <option value=""><?php _e('Selecione...', 'crm-developer'); ?></option>
                            <?php foreach ($generos as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected(get_value($contact, 'genero'), $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="raca_etnia"><?php _e('Raça/Etnia (Padrão IBGE)', 'crm-developer'); ?></label>
                        <select id="raca_etnia" name="raca_etnia">
                            <option value=""><?php _e('Selecione...', 'crm-developer'); ?></option>
                            <?php foreach ($racas as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected(get_value($contact, 'raca_etnia'), $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Pessoa com Deficiência?', 'crm-developer'); ?></label>
                        <div class="radio-group">
                            <label><input type="radio" name="pessoa_deficiencia" value="nao" <?php checked(get_value($contact, 'pessoa_deficiencia', 'nao'), 'nao'); ?>> <?php _e('Não', 'crm-developer'); ?></label>
                            <label><input type="radio" name="pessoa_deficiencia" value="sim" <?php checked(get_value($contact, 'pessoa_deficiencia'), 'sim'); ?>> <?php _e('Sim', 'crm-developer'); ?></label>
                        </div>
                    </div>

                    <div class="form-group full-width" id="deficiencia-desc-group" style="<?php echo get_value($contact, 'pessoa_deficiencia') === 'sim' ? '' : 'display:none;'; ?>">
                        <label for="deficiencia_descricao"><?php _e('Descrição da Deficiência', 'crm-developer'); ?></label>
                        <textarea id="deficiencia_descricao" name="deficiencia_descricao" rows="2"><?php echo esc_textarea(get_value($contact, 'deficiencia_descricao')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Etapa 2: Contato e Localização -->
            <div class="form-step" data-step="2">
                <div class="step-header">
                    <h2><i class="fas fa-map-marker-alt"></i> <?php _e('Contato e Localização', 'crm-developer'); ?></h2>
                    <p class="step-description">
                        <?php _e('Informações de contato e endereço. Pelo menos um meio de contato (email ou telefone) é recomendado.', 'crm-developer'); ?>
                    </p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="email"><?php _e('Email', 'crm-developer'); ?></label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr(get_value($contact, 'email')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefone"><?php _e('Telefone', 'crm-developer'); ?></label>
                        <input type="tel" id="telefone" name="telefone" value="<?php echo esc_attr(get_value($contact, 'telefone')); ?>" placeholder="(00) 00000-0000">
                    </div>

                    <div class="form-group">
                        <label for="whatsapp"><?php _e('WhatsApp', 'crm-developer'); ?></label>
                        <input type="tel" id="whatsapp" name="whatsapp" value="<?php echo esc_attr(get_value($contact, 'whatsapp')); ?>" placeholder="(00) 00000-0000">
                        <span class="field-help"><?php _e('Número com DDD para contato via WhatsApp', 'crm-developer'); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="estado"><?php _e('Estado', 'crm-developer'); ?></label>
                        <select id="estado" name="estado">
                            <option value=""><?php _e('Selecione...', 'crm-developer'); ?></option>
                            <?php foreach ($estados as $uf => $nome) : ?>
                                <option value="<?php echo esc_attr($uf); ?>" <?php selected(get_value($contact, 'estado'), $uf); ?>><?php echo esc_html($nome); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="municipio"><?php _e('Município', 'crm-developer'); ?></label>
                        <input type="text" id="municipio" name="municipio" value="<?php echo esc_attr(get_value($contact, 'municipio')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="regiao"><?php _e('Região', 'crm-developer'); ?></label>
                        <input type="text" id="regiao" name="regiao" value="<?php echo esc_attr(get_value($contact, 'regiao')); ?>" readonly>
                        <span class="field-help"><?php _e('Preenchido automaticamente com base no estado', 'crm-developer'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Etapa 3: Participação em Conferência -->
            <div class="form-step" data-step="3">
                <div class="step-header">
                    <h2><i class="fas fa-calendar-check"></i> <?php _e('Participação em Conferência', 'crm-developer'); ?></h2>
                    <p class="step-description">
                        <?php _e('Registre as informações sobre a participação em conferências e eventos. Múltiplas opções podem ser selecionadas.', 'crm-developer'); ?>
                    </p>
                </div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><?php _e('Etapa de Participação', 'crm-developer'); ?></label>
                        <div class="checkbox-group">
                            <?php foreach ($etapas as $key => $label) : ?>
                                <label>
                                    <input type="checkbox" name="etapa_participacao[]" value="<?php echo esc_attr($key); ?>" <?php echo is_checked($contact, 'etapa_participacao', $key) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label><?php _e('Tipo de Participação', 'crm-developer'); ?></label>
                        <div class="checkbox-group">
                            <?php foreach ($tipos_part as $key => $label) : ?>
                                <label>
                                    <input type="checkbox" name="tipo_participacao[]" value="<?php echo esc_attr($key); ?>" <?php echo is_checked($contact, 'tipo_participacao', $key) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label><?php _e('Categoria de Representação', 'crm-developer'); ?></label>
                        <div class="checkbox-group">
                            <?php foreach ($categorias as $key => $label) : ?>
                                <label>
                                    <input type="checkbox" name="categoria_representacao[]" value="<?php echo esc_attr($key); ?>" <?php echo is_checked($contact, 'categoria_representacao', $key) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label><?php _e('Eixo Temático de Interesse Principal', 'crm-developer'); ?></label>
                        <div class="checkbox-group">
                            <?php foreach ($eixos as $key => $label) : ?>
                                <label>
                                    <input type="checkbox" name="eixo_tematico[]" value="<?php echo esc_attr($key); ?>" <?php echo is_checked($contact, 'eixo_tematico', $key) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etapa 4: Perfil Sociopolítico -->
            <div class="form-step" data-step="4">
                <div class="step-header">
                    <h2><i class="fas fa-users-cog"></i> <?php _e('Perfil Sociopolítico e Territorial', 'crm-developer'); ?></h2>
                    <p class="step-description">
                        <?php _e('Informações sobre atuação social, política e territorial do contato.', 'crm-developer'); ?>
                    </p>
                </div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="comunidade_territorio"><?php _e('Comunidade/Território Tradicional', 'crm-developer'); ?></label>
                        <input type="text" id="comunidade_territorio" name="comunidade_territorio" value="<?php echo esc_attr(get_value($contact, 'comunidade_territorio')); ?>">
                        <span class="field-help"><?php _e('Se pertence a alguma comunidade ou território tradicional', 'crm-developer'); ?></span>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Participa de coletivos ou movimentos?', 'crm-developer'); ?></label>
                        <div class="radio-group">
                            <label><input type="radio" name="participa_coletivos" value="nao" <?php checked(get_value($contact, 'participa_coletivos'), 'nao'); ?>> <?php _e('Não', 'crm-developer'); ?></label>
                            <label><input type="radio" name="participa_coletivos" value="sim" <?php checked(get_value($contact, 'participa_coletivos'), 'sim'); ?>> <?php _e('Sim', 'crm-developer'); ?></label>
                        </div>
                    </div>

                    <div class="form-group" id="coletivos-desc-group" style="<?php echo get_value($contact, 'participa_coletivos') === 'sim' ? '' : 'display:none;'; ?>">
                        <label for="coletivos_descricao"><?php _e('Quais coletivos/movimentos?', 'crm-developer'); ?></label>
                        <textarea id="coletivos_descricao" name="coletivos_descricao" rows="2"><?php echo esc_textarea(get_value($contact, 'coletivos_descricao')); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="tempo_atuacao_ambiental"><?php _e('Atua com temas ambientais há quanto tempo?', 'crm-developer'); ?></label>
                        <input type="text" id="tempo_atuacao_ambiental" name="tempo_atuacao_ambiental" value="<?php echo esc_attr(get_value($contact, 'tempo_atuacao_ambiental')); ?>" placeholder="Ex: 5 anos">
                    </div>

                    <div class="form-group">
                        <label><?php _e('Atua com justiça social ou climática?', 'crm-developer'); ?></label>
                        <div class="radio-group">
                            <label><input type="radio" name="atua_justica_climatica" value="nao" <?php checked(get_value($contact, 'atua_justica_climatica'), 'nao'); ?>> <?php _e('Não', 'crm-developer'); ?></label>
                            <label><input type="radio" name="atua_justica_climatica" value="sim" <?php checked(get_value($contact, 'atua_justica_climatica'), 'sim'); ?>> <?php _e('Sim', 'crm-developer'); ?></label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Possui papel de liderança?', 'crm-developer'); ?></label>
                        <div class="radio-group">
                            <label><input type="radio" name="papel_lideranca" value="nao" <?php checked(get_value($contact, 'papel_lideranca'), 'nao'); ?>> <?php _e('Não', 'crm-developer'); ?></label>
                            <label><input type="radio" name="papel_lideranca" value="sim" <?php checked(get_value($contact, 'papel_lideranca'), 'sim'); ?>> <?php _e('Sim', 'crm-developer'); ?></label>
                        </div>
                    </div>

                    <div class="form-group" id="lideranca-desc-group" style="<?php echo get_value($contact, 'papel_lideranca') === 'sim' ? '' : 'display:none;'; ?>">
                        <label for="lideranca_descricao"><?php _e('Descreva o papel de liderança', 'crm-developer'); ?></label>
                        <textarea id="lideranca_descricao" name="lideranca_descricao" rows="2"><?php echo esc_textarea(get_value($contact, 'lideranca_descricao')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Etapa 5: Mobilização Futura -->
            <div class="form-step" data-step="5">
                <div class="step-header">
                    <h2><i class="fas fa-bullhorn"></i> <?php _e('Possibilidades de Mobilização Futura', 'crm-developer'); ?></h2>
                    <p class="step-description">
                        <?php _e('Interesses e disponibilidade para participação em ações futuras.', 'crm-developer'); ?>
                    </p>
                </div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><?php _e('Deseja continuar participando das agendas?', 'crm-developer'); ?></label>
                        <div class="radio-group">
                            <label><input type="radio" name="continuar_participando" value="sim" <?php checked(get_value($contact, 'continuar_participando'), 'sim'); ?>> <?php _e('Sim', 'crm-developer'); ?></label>
                            <label><input type="radio" name="continuar_participando" value="nao" <?php checked(get_value($contact, 'continuar_participando'), 'nao'); ?>> <?php _e('Não', 'crm-developer'); ?></label>
                            <label><input type="radio" name="continuar_participando" value="talvez" <?php checked(get_value($contact, 'continuar_participando'), 'talvez'); ?>> <?php _e('Talvez', 'crm-developer'); ?></label>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label><?php _e('Interesses em:', 'crm-developer'); ?></label>
                        <div class="interests-grid">
                            <div class="interest-item">
                                <label>
                                    <input type="checkbox" name="interesse_formacao" value="sim" <?php checked(get_value($contact, 'interesse_formacao'), 'sim'); ?>>
                                    <span class="interest-icon"><i class="fas fa-graduation-cap"></i></span>
                                    <span class="interest-label"><?php _e('Formação técnica ou política', 'crm-developer'); ?></span>
                                </label>
                            </div>
                            <div class="interest-item">
                                <label>
                                    <input type="checkbox" name="interesse_conteudo" value="sim" <?php checked(get_value($contact, 'interesse_conteudo'), 'sim'); ?>>
                                    <span class="interest-icon"><i class="fas fa-pen"></i></span>
                                    <span class="interest-label"><?php _e('Produção de conteúdo', 'crm-developer'); ?></span>
                                </label>
                            </div>
                            <div class="interest-item">
                                <label>
                                    <input type="checkbox" name="interesse_incidencia" value="sim" <?php checked(get_value($contact, 'interesse_incidencia'), 'sim'); ?>>
                                    <span class="interest-icon"><i class="fas fa-landmark"></i></span>
                                    <span class="interest-label"><?php _e('Incidência política', 'crm-developer'); ?></span>
                                </label>
                            </div>
                            <div class="interest-item">
                                <label>
                                    <input type="checkbox" name="interesse_mobilizacao" value="sim" <?php checked(get_value($contact, 'interesse_mobilizacao'), 'sim'); ?>>
                                    <span class="interest-icon"><i class="fas fa-bullhorn"></i></span>
                                    <span class="interest-label"><?php _e('Mobilização territorial', 'crm-developer'); ?></span>
                                </label>
                            </div>
                            <div class="interest-item">
                                <label>
                                    <input type="checkbox" name="interesse_voluntariado" value="sim" <?php checked(get_value($contact, 'interesse_voluntariado'), 'sim'); ?>>
                                    <span class="interest-icon"><i class="fas fa-hands-helping"></i></span>
                                    <span class="interest-label"><?php _e('Voluntariado', 'crm-developer'); ?></span>
                                </label>
                            </div>
                            <div class="interest-item">
                                <label>
                                    <input type="checkbox" name="interesse_foruns" value="sim" <?php checked(get_value($contact, 'interesse_foruns'), 'sim'); ?>>
                                    <span class="interest-icon"><i class="fas fa-comments"></i></span>
                                    <span class="interest-label"><?php _e('Participação em fóruns temáticos', 'crm-developer'); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etapa 6: Dados Complementares -->
            <div class="form-step" data-step="6">
                <div class="step-header">
                    <h2><i class="fas fa-clipboard-list"></i> <?php _e('Dados Complementares', 'crm-developer'); ?></h2>
                    <p class="step-description">
                        <?php _e('Informações adicionais para gestão pública e controle interno.', 'crm-developer'); ?>
                    </p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="cargo_publico"><?php _e('Cargo Público (se aplicável)', 'crm-developer'); ?></label>
                        <input type="text" id="cargo_publico" name="cargo_publico" value="<?php echo esc_attr(get_value($contact, 'cargo_publico')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="vinculacao_institucional"><?php _e('Vinculação Institucional', 'crm-developer'); ?></label>
                        <input type="text" id="vinculacao_institucional" name="vinculacao_institucional" value="<?php echo esc_attr(get_value($contact, 'vinculacao_institucional')); ?>">
                        <span class="field-help"><?php _e('Órgão, secretaria ou instituição', 'crm-developer'); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="status"><?php _e('Status do Contato', 'crm-developer'); ?></label>
                        <select id="status" name="status">
                            <option value="ativo" <?php selected(get_value($contact, 'status', 'ativo'), 'ativo'); ?>><?php _e('Ativo', 'crm-developer'); ?></option>
                            <option value="inativo" <?php selected(get_value($contact, 'status'), 'inativo'); ?>><?php _e('Inativo', 'crm-developer'); ?></option>
                            <option value="pendente" <?php selected(get_value($contact, 'status'), 'pendente'); ?>><?php _e('Pendente', 'crm-developer'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Consentimento LGPD', 'crm-developer'); ?></label>
                        <div class="radio-group">
                            <label><input type="radio" name="consentimento_lgpd" value="nao" <?php checked(get_value($contact, 'consentimento_lgpd', 'nao'), 'nao'); ?>> <?php _e('Não obtido', 'crm-developer'); ?></label>
                            <label><input type="radio" name="consentimento_lgpd" value="sim" <?php checked(get_value($contact, 'consentimento_lgpd'), 'sim'); ?>> <?php _e('Obtido', 'crm-developer'); ?></label>
                        </div>
                        <?php if (get_value($contact, 'data_consentimento')) : ?>
                            <span class="field-help"><?php _e('Consentido em:', 'crm-developer'); ?> <?php echo CRM_Dev_Helpers::format_datetime(get_value($contact, 'data_consentimento')); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="observacoes"><?php _e('Observações', 'crm-developer'); ?></label>
                        <textarea id="observacoes" name="observacoes" rows="4"><?php echo esc_textarea(get_value($contact, 'observacoes')); ?></textarea>
                        <span class="field-help"><?php _e('Anotações internas sobre o contato', 'crm-developer'); ?></span>
                    </div>
                </div>

                <!-- Resumo -->
                <div class="form-summary">
                    <h3><i class="fas fa-check-circle"></i> <?php _e('Pronto para salvar!', 'crm-developer'); ?></h3>
                    <p><?php _e('Revise as informações nas etapas anteriores se necessário. Clique em Salvar para concluir.', 'crm-developer'); ?></p>
                </div>
            </div>

            <!-- Navegação do formulário -->
            <div class="form-navigation">
                <button type="button" id="btn-prev-step" class="button" style="display: none;">
                    <i class="fas fa-arrow-left"></i> <?php _e('Anterior', 'crm-developer'); ?>
                </button>

                <div class="form-nav-info">
                    <span id="current-step-label"><?php _e('Etapa 1 de 6', 'crm-developer'); ?></span>
                </div>

                <button type="button" id="btn-next-step" class="button button-primary">
                    <?php _e('Próxima', 'crm-developer'); ?> <i class="fas fa-arrow-right"></i>
                </button>

                <button type="submit" id="btn-save" class="button button-primary" style="display: none;">
                    <i class="fas fa-save"></i> <?php _e('Salvar Contato', 'crm-developer'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let currentStep = 1;
    const totalSteps = 6;

    // Mapeamento estado -> região
    const regiaoMap = {
        'AC': 'Norte', 'AP': 'Norte', 'AM': 'Norte', 'PA': 'Norte', 'RO': 'Norte', 'RR': 'Norte', 'TO': 'Norte',
        'AL': 'Nordeste', 'BA': 'Nordeste', 'CE': 'Nordeste', 'MA': 'Nordeste', 'PB': 'Nordeste', 'PE': 'Nordeste', 'PI': 'Nordeste', 'RN': 'Nordeste', 'SE': 'Nordeste',
        'DF': 'Centro-Oeste', 'GO': 'Centro-Oeste', 'MT': 'Centro-Oeste', 'MS': 'Centro-Oeste',
        'ES': 'Sudeste', 'MG': 'Sudeste', 'RJ': 'Sudeste', 'SP': 'Sudeste',
        'PR': 'Sul', 'RS': 'Sul', 'SC': 'Sul'
    };

    // Atualiza região quando muda estado
    $('#estado').on('change', function() {
        const estado = $(this).val();
        $('#regiao').val(regiaoMap[estado] || '');
    });

    // Seleção de foto do contato
    $('#btn-select-photo').on('click', function(e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            alert('<?php _e('Biblioteca de mídia não disponível', 'crm-developer'); ?>');
            return;
        }

        const mediaUploader = wp.media({
            title: '<?php _e('Selecionar Foto do Contato', 'crm-developer'); ?>',
            button: { text: '<?php _e('Usar esta Foto', 'crm-developer'); ?>' },
            library: { type: 'image' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            const thumbUrl = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            $('#foto_id').val(attachment.id);
            $('#photo-preview').html(
                '<img src="' + thumbUrl + '" alt="">' +
                '<button type="button" class="btn-remove-photo" title="<?php _e('Remover foto', 'crm-developer'); ?>">&times;</button>'
            );
        });

        mediaUploader.open();
    });

    // Remover foto
    $(document).on('click', '.btn-remove-photo', function(e) {
        e.preventDefault();
        $('#foto_id').val('');
        $('#photo-preview').html(
            '<div class="photo-placeholder"><i class="fas fa-user"></i></div>'
        );
    });

    // Campos condicionais
    $('input[name="pessoa_deficiencia"]').on('change', function() {
        $('#deficiencia-desc-group').toggle($(this).val() === 'sim');
    });

    $('input[name="participa_coletivos"]').on('change', function() {
        $('#coletivos-desc-group').toggle($(this).val() === 'sim');
    });

    $('input[name="papel_lideranca"]').on('change', function() {
        $('#lideranca-desc-group').toggle($(this).val() === 'sim');
    });

    // Navegação entre etapas
    function updateStepDisplay() {
        $('.form-step').removeClass('active');
        $(`.form-step[data-step="${currentStep}"]`).addClass('active');

        $('.crm-dev-steps .step').removeClass('active completed');
        for (let i = 1; i < currentStep; i++) {
            $(`.crm-dev-steps .step[data-step="${i}"]`).addClass('completed');
        }
        $(`.crm-dev-steps .step[data-step="${currentStep}"]`).addClass('active');

        $('#btn-prev-step').toggle(currentStep > 1);
        $('#btn-next-step').toggle(currentStep < totalSteps);
        $('#btn-save').toggle(currentStep === totalSteps);

        $('#current-step-label').text(`Etapa ${currentStep} de ${totalSteps}`);

        // Scroll para o topo
        $('.crm-dev-form-container')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $('#btn-next-step').on('click', function() {
        // Valida campos obrigatórios da etapa atual
        if (currentStep === 1) {
            if (!$('#nome_completo').val().trim()) {
                alert('Por favor, preencha o nome completo.');
                $('#nome_completo').focus();
                return;
            }
        }

        if (currentStep < totalSteps) {
            currentStep++;
            updateStepDisplay();
        }
    });

    $('#btn-prev-step').on('click', function() {
        if (currentStep > 1) {
            currentStep--;
            updateStepDisplay();
        }
    });

    // Clique direto nas etapas
    $('.crm-dev-steps .step').on('click', function() {
        const step = parseInt($(this).data('step'));
        if (step <= currentStep || $(this).hasClass('completed')) {
            currentStep = step;
            updateStepDisplay();
        }
    });

    // Salvar formulário
    $('#contact-form').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btn-save');
        const originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Salvando...').prop('disabled', true);

        // Coleta dados do formulário
        const formData = {};
        $(this).serializeArray().forEach(function(item) {
            if (item.name.endsWith('[]')) {
                const name = item.name.slice(0, -2);
                if (!formData[name]) formData[name] = [];
                formData[name].push(item.value);
            } else {
                formData[item.name] = item.value;
            }
        });

        // Processa checkboxes de interesse (que usam value="sim")
        ['interesse_formacao', 'interesse_conteudo', 'interesse_incidencia', 'interesse_mobilizacao', 'interesse_voluntariado', 'interesse_foruns'].forEach(function(field) {
            if ($(`input[name="${field}"]`).is(':checked')) {
                formData[field] = 'sim';
            } else {
                formData[field] = 'nao';
            }
        });

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_contact',
            nonce: crmDevAdmin.nonce,
            id: formData.id || null,
            data: formData
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                window.location.href = '<?php echo admin_url('admin.php?page=crm-developer&section=contacts&action=view&id='); ?>' + response.data.id;
            } else {
                alert(response.data.message || 'Erro ao salvar');
                $btn.html(originalText).prop('disabled', false);
            }
        }).fail(function() {
            alert('Erro de comunicação. Tente novamente.');
            $btn.html(originalText).prop('disabled', false);
        });
    });
});
</script>

<?php
// Modal de ajuda
crm_dev_render_help_modal_contact_form();
crm_dev_render_help_modal_script();
?>
