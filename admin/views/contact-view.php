<?php
/**
 * View de visualização de contato
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

$contact_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$contact = CRM_Dev_Contacts::get_contact($contact_id);

if (!$contact) {
    echo '<div class="notice notice-error"><p>' . __('Contato não encontrado.', 'crm-developer') . '</p></div>';
    return;
}

$interactions = CRM_Dev_Interactions::get_interactions($contact_id);
$tipos_interacao = CRM_Dev_Helpers::get_tipos_interacao();
$resultados_interacao = CRM_Dev_Helpers::get_resultados_interacao();

// Helpers para exibição
$estados = CRM_Dev_Helpers::get_estados();
$generos = CRM_Dev_Helpers::get_generos();
$racas = CRM_Dev_Helpers::get_racas();
$etapas = CRM_Dev_Helpers::get_etapas_participacao();
$tipos_part = CRM_Dev_Helpers::get_tipos_participacao();
$categorias = CRM_Dev_Helpers::get_categorias_representacao();
$eixos = CRM_Dev_Helpers::get_eixos_tematicos();

function display_array_field($value, $map) {
    $unserialized = maybe_unserialize($value);
    if (!$unserialized) return '-';
    if (!is_array($unserialized)) $unserialized = array($unserialized);

    $labels = array();
    foreach ($unserialized as $key) {
        $labels[] = isset($map[$key]) ? $map[$key] : $key;
    }
    return implode(', ', $labels);
}

function display_field($contact, $field, $map = null, $default = '-') {
    $value = isset($contact[$field]) ? $contact[$field] : '';
    if (empty($value)) return $default;
    if ($map && isset($map[$value])) return $map[$value];
    return $value;
}

$score = $contact['score_engajamento'];
$score_color = CRM_Dev_Helpers::get_score_color($score);
$score_label = CRM_Dev_Helpers::get_score_label($score);
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <div class="header-back">
            <a href="<?php echo admin_url('admin.php?page=crm-developer&section=contacts'); ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> <?php _e('Voltar para lista', 'crm-developer'); ?>
            </a>
        </div>
        <div class="header-actions">
            <a href="<?php echo admin_url('admin.php?page=crm-developer&section=contacts&action=edit&id=' . $contact_id); ?>" class="button button-primary">
                <i class="fas fa-edit"></i> <?php _e('Editar', 'crm-developer'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=crm-developer&section=contacts&action=delete&id=' . $contact_id), 'delete_contact_' . $contact_id); ?>" class="button button-link-delete" onclick="return confirm('<?php _e('Tem certeza que deseja excluir este contato?', 'crm-developer'); ?>')">
                <i class="fas fa-trash"></i> <?php _e('Excluir', 'crm-developer'); ?>
            </a>
        </div>
    </div>

    <div class="crm-dev-contact-view">
        <!-- Cabeçalho do Contato -->
        <div class="contact-header-card">
            <div class="contact-avatar">
                <span><?php echo strtoupper(substr($contact['nome_completo'], 0, 2)); ?></span>
            </div>
            <div class="contact-header-info">
                <h1><?php echo esc_html($contact['nome_social'] ?: $contact['nome_completo']); ?></h1>
                <?php if ($contact['nome_social']) : ?>
                    <p class="nome-civil"><?php echo esc_html($contact['nome_completo']); ?></p>
                <?php endif; ?>

                <div class="contact-quick-info">
                    <?php if ($contact['email']) : ?>
                        <span><i class="fas fa-envelope"></i> <?php echo esc_html($contact['email']); ?></span>
                    <?php endif; ?>
                    <?php if ($contact['whatsapp']) : ?>
                        <a href="<?php echo CRM_Dev_Helpers::get_whatsapp_link($contact['whatsapp']); ?>" target="_blank" class="whatsapp-link">
                            <i class="fab fa-whatsapp"></i> <?php echo CRM_Dev_Helpers::format_phone($contact['whatsapp']); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($contact['municipio'] || $contact['estado']) : ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo esc_html(($contact['municipio'] ? $contact['municipio'] . ' - ' : '') . $contact['estado']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="contact-score-display">
                <div class="score-circle" style="border-color: <?php echo $score_color; ?>">
                    <span class="score-value"><?php echo $score; ?></span>
                </div>
                <span class="score-text" style="color: <?php echo $score_color; ?>"><?php echo $score_label; ?></span>
            </div>
        </div>

        <div class="contact-view-grid">
            <!-- Coluna Principal -->
            <div class="contact-main">
                <!-- Dados Pessoais -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> <?php _e('Dados Pessoais', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label><?php _e('Data de Nascimento', 'crm-developer'); ?></label>
                                <span>
                                    <?php
                                    echo CRM_Dev_Helpers::format_date($contact['data_nascimento']);
                                    $age = CRM_Dev_Helpers::calculate_age($contact['data_nascimento']);
                                    if ($age) echo " ({$age} anos)";
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label><?php _e('Gênero', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'genero', $generos); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php _e('Raça/Etnia', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'raca_etnia', $racas); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php _e('Pessoa com Deficiência', 'crm-developer'); ?></label>
                                <span>
                                    <?php echo $contact['pessoa_deficiencia'] === 'sim' ? 'Sim' : 'Não'; ?>
                                    <?php if ($contact['deficiencia_descricao']) echo ' - ' . esc_html($contact['deficiencia_descricao']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Participação -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-check"></i> <?php _e('Participação em Conferência', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item full">
                                <label><?php _e('Etapa de Participação', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['etapa_participacao'], $etapas); ?></span>
                            </div>
                            <div class="info-item full">
                                <label><?php _e('Tipo de Participação', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['tipo_participacao'], $tipos_part); ?></span>
                            </div>
                            <div class="info-item full">
                                <label><?php _e('Categoria de Representação', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['categoria_representacao'], $categorias); ?></span>
                            </div>
                            <div class="info-item full">
                                <label><?php _e('Eixo Temático', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['eixo_tematico'], $eixos); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Perfil Sociopolítico -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-cog"></i> <?php _e('Perfil Sociopolítico', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item full">
                                <label><?php _e('Comunidade/Território', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'comunidade_territorio'); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php _e('Participa de Coletivos', 'crm-developer'); ?></label>
                                <span>
                                    <?php echo $contact['participa_coletivos'] === 'sim' ? 'Sim' : 'Não'; ?>
                                    <?php if ($contact['coletivos_descricao']) echo ' - ' . esc_html($contact['coletivos_descricao']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label><?php _e('Tempo de Atuação Ambiental', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'tempo_atuacao_ambiental'); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php _e('Atua com Justiça Climática', 'crm-developer'); ?></label>
                                <span><?php echo $contact['atua_justica_climatica'] === 'sim' ? 'Sim' : 'Não'; ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php _e('Papel de Liderança', 'crm-developer'); ?></label>
                                <span>
                                    <?php echo $contact['papel_lideranca'] === 'sim' ? 'Sim' : 'Não'; ?>
                                    <?php if ($contact['lideranca_descricao']) echo ' - ' . esc_html($contact['lideranca_descricao']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Histórico de Interações -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> <?php _e('Histórico de Interações', 'crm-developer'); ?></h3>
                        <button type="button" class="button" id="btn-new-interaction">
                            <i class="fas fa-plus"></i> <?php _e('Nova Interação', 'crm-developer'); ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($interactions)) : ?>
                            <div class="interactions-timeline">
                                <?php foreach ($interactions as $interaction) : ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <div class="timeline-header">
                                                <span class="timeline-type">
                                                    <i class="fas fa-<?php echo $interaction['tipo'] === 'whatsapp' ? 'fab fa-whatsapp' : 'comment'; ?>"></i>
                                                    <?php echo isset($tipos_interacao[$interaction['tipo']]) ? $tipos_interacao[$interaction['tipo']] : $interaction['tipo']; ?>
                                                </span>
                                                <span class="timeline-date"><?php echo CRM_Dev_Helpers::format_datetime($interaction['created_at']); ?></span>
                                            </div>
                                            <h4><?php echo esc_html($interaction['titulo']); ?></h4>
                                            <?php if ($interaction['descricao']) : ?>
                                                <p><?php echo nl2br(esc_html($interaction['descricao'])); ?></p>
                                            <?php endif; ?>
                                            <?php if ($interaction['resultado']) : ?>
                                                <div class="timeline-result resultado-<?php echo $interaction['resultado']; ?>">
                                                    <?php _e('Resultado:', 'crm-developer'); ?> <?php echo isset($resultados_interacao[$interaction['resultado']]) ? $resultados_interacao[$interaction['resultado']] : $interaction['resultado']; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($interaction['proxima_acao']) : ?>
                                                <div class="timeline-next-action">
                                                    <strong><?php _e('Próxima ação:', 'crm-developer'); ?></strong>
                                                    <?php echo esc_html($interaction['proxima_acao']); ?>
                                                    <?php if ($interaction['data_proxima_acao']) : ?>
                                                        <span class="next-action-date">
                                                            (<?php echo CRM_Dev_Helpers::format_date($interaction['data_proxima_acao']); ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="timeline-meta">
                                                <?php _e('Por', 'crm-developer'); ?> <?php echo esc_html($interaction['user_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="crm-dev-empty"><?php _e('Nenhuma interação registrada ainda.', 'crm-developer'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral -->
            <div class="contact-sidebar">
                <!-- Status e Controle -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> <?php _e('Informações', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="sidebar-info">
                            <div class="info-row">
                                <label><?php _e('Status', 'crm-developer'); ?></label>
                                <span class="status-badge status-<?php echo $contact['status']; ?>">
                                    <?php echo ucfirst($contact['status']); ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <label><?php _e('LGPD', 'crm-developer'); ?></label>
                                <span class="status-badge status-<?php echo $contact['consentimento_lgpd'] === 'sim' ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $contact['consentimento_lgpd'] === 'sim' ? 'Consentido' : 'Pendente'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <label><?php _e('Cadastrado em', 'crm-developer'); ?></label>
                                <span><?php echo CRM_Dev_Helpers::format_datetime($contact['created_at']); ?></span>
                            </div>
                            <div class="info-row">
                                <label><?php _e('Última atualização', 'crm-developer'); ?></label>
                                <span><?php echo CRM_Dev_Helpers::format_datetime($contact['updated_at']); ?></span>
                            </div>
                            <?php if ($contact['ultima_interacao']) : ?>
                                <div class="info-row">
                                    <label><?php _e('Última interação', 'crm-developer'); ?></label>
                                    <span><?php echo CRM_Dev_Helpers::format_datetime($contact['ultima_interacao']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <label><?php _e('Origem', 'crm-developer'); ?></label>
                                <span><?php echo ucfirst($contact['origem'] ?: 'Manual'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interesses de Mobilização -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bullhorn"></i> <?php _e('Mobilização', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="interests-display">
                            <div class="interest-row">
                                <label><?php _e('Continuar participando', 'crm-developer'); ?></label>
                                <span class="interest-value <?php echo $contact['continuar_participando']; ?>">
                                    <?php echo $contact['continuar_participando'] === 'sim' ? 'Sim' : ($contact['continuar_participando'] === 'nao' ? 'Não' : 'Talvez'); ?>
                                </span>
                            </div>

                            <div class="interests-tags">
                                <?php
                                $interesses = array(
                                    'interesse_formacao' => 'Formação',
                                    'interesse_conteudo' => 'Conteúdo',
                                    'interesse_incidencia' => 'Incidência',
                                    'interesse_mobilizacao' => 'Mobilização',
                                    'interesse_voluntariado' => 'Voluntariado',
                                    'interesse_foruns' => 'Fóruns',
                                );
                                foreach ($interesses as $key => $label) :
                                    if ($contact[$key] === 'sim') :
                                ?>
                                    <span class="interest-tag"><i class="fas fa-check"></i> <?php echo $label; ?></span>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dados Complementares -->
                <?php if ($contact['cargo_publico'] || $contact['vinculacao_institucional']) : ?>
                    <div class="crm-dev-card">
                        <div class="card-header">
                            <h3><i class="fas fa-briefcase"></i> <?php _e('Dados Complementares', 'crm-developer'); ?></h3>
                        </div>
                        <div class="card-body">
                            <?php if ($contact['cargo_publico']) : ?>
                                <div class="info-row">
                                    <label><?php _e('Cargo Público', 'crm-developer'); ?></label>
                                    <span><?php echo esc_html($contact['cargo_publico']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($contact['vinculacao_institucional']) : ?>
                                <div class="info-row">
                                    <label><?php _e('Vinculação Institucional', 'crm-developer'); ?></label>
                                    <span><?php echo esc_html($contact['vinculacao_institucional']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Observações -->
                <?php if ($contact['observacoes']) : ?>
                    <div class="crm-dev-card">
                        <div class="card-header">
                            <h3><i class="fas fa-sticky-note"></i> <?php _e('Observações', 'crm-developer'); ?></h3>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br(esc_html($contact['observacoes'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Nova Interação -->
<div id="modal-interaction" class="crm-dev-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> <?php _e('Nova Interação', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form id="interaction-form">
            <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">

            <div class="form-group">
                <label for="int-tipo"><?php _e('Tipo de Interação', 'crm-developer'); ?> *</label>
                <select id="int-tipo" name="tipo" required>
                    <?php foreach ($tipos_interacao as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="int-titulo"><?php _e('Título', 'crm-developer'); ?> *</label>
                <input type="text" id="int-titulo" name="titulo" required>
            </div>

            <div class="form-group">
                <label for="int-descricao"><?php _e('Descrição', 'crm-developer'); ?></label>
                <textarea id="int-descricao" name="descricao" rows="3"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="int-resultado"><?php _e('Resultado', 'crm-developer'); ?></label>
                    <select id="int-resultado" name="resultado">
                        <option value=""><?php _e('Selecione...', 'crm-developer'); ?></option>
                        <?php foreach ($resultados_interacao as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="int-proxima-acao"><?php _e('Próxima Ação', 'crm-developer'); ?></label>
                <textarea id="int-proxima-acao" name="proxima_acao" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label for="int-data-proxima"><?php _e('Data da Próxima Ação', 'crm-developer'); ?></label>
                <input type="date" id="int-data-proxima" name="data_proxima_acao">
            </div>

            <div class="modal-footer">
                <button type="button" class="button modal-close"><?php _e('Cancelar', 'crm-developer'); ?></button>
                <button type="submit" class="button button-primary"><?php _e('Salvar Interação', 'crm-developer'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Abrir modal de interação
    $('#btn-new-interaction').on('click', function() {
        $('#modal-interaction').show();
    });

    // Fechar modal
    $('.modal-close').on('click', function() {
        $('#modal-interaction').hide();
    });

    // Fechar modal clicando fora
    $('#modal-interaction').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    // Salvar interação
    $('#interaction-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {};
        $(this).serializeArray().forEach(function(item) {
            formData[item.name] = item.value;
        });

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_interaction',
            nonce: crmDevAdmin.nonce,
            data: formData
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Erro ao salvar');
            }
        });
    });
});
</script>
