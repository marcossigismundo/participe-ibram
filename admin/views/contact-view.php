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
            <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts')); ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> <?php esc_html_e('Voltar para lista', 'crm-developer'); ?>
            </a>
        </div>
        <div class="header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts&action=edit&id=' . $contact_id)); ?>" class="button button-primary">
                <i class="fas fa-edit"></i> <?php esc_html_e('Editar', 'crm-developer'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=crm-developer&section=contacts&action=delete&id=' . $contact_id), 'delete_contact_' . $contact_id)); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_html_e('Tem certeza que deseja excluir este contato?', 'crm-developer'); ?>')">
                <i class="fas fa-trash"></i> <?php esc_html_e('Excluir', 'crm-developer'); ?>
            </a>
        </div>
    </div>

    <div class="crm-dev-contact-view">
        <!-- Cabeçalho do Contato -->
        <div class="contact-header-card">
            <div class="contact-avatar <?php echo !empty($contact['foto_id']) ? 'has-photo' : ''; ?>">
                <?php
                $foto_url = !empty($contact['foto_id']) ? wp_get_attachment_image_url($contact['foto_id'], 'thumbnail') : '';
                if ($foto_url) :
                ?>
                    <img src="<?php echo esc_url($foto_url); ?>" alt="<?php echo esc_attr($contact['nome_completo']); ?>">
                <?php else : ?>
                    <span><?php echo strtoupper(substr($contact['nome_completo'], 0, 2)); ?></span>
                <?php endif; ?>
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
                        <h3><i class="fas fa-user"></i> <?php esc_html_e('Dados Pessoais', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label><?php esc_html_e('Data de Nascimento', 'crm-developer'); ?></label>
                                <span>
                                    <?php
                                    echo CRM_Dev_Helpers::format_date($contact['data_nascimento']);
                                    $age = CRM_Dev_Helpers::calculate_age($contact['data_nascimento']);
                                    if ($age) echo " ({$age} anos)";
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label><?php esc_html_e('Gênero', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'genero', $generos); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php esc_html_e('Raça/Etnia', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'raca_etnia', $racas); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php esc_html_e('Pessoa com Deficiência', 'crm-developer'); ?></label>
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
                        <h3><i class="fas fa-calendar-check"></i> <?php esc_html_e('Participação em Conferência', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item full">
                                <label><?php esc_html_e('Etapa de Participação', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['etapa_participacao'], $etapas); ?></span>
                            </div>
                            <div class="info-item full">
                                <label><?php esc_html_e('Tipo de Participação', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['tipo_participacao'], $tipos_part); ?></span>
                            </div>
                            <div class="info-item full">
                                <label><?php esc_html_e('Categoria de Representação', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['categoria_representacao'], $categorias); ?></span>
                            </div>
                            <div class="info-item full">
                                <label><?php esc_html_e('Eixo Temático', 'crm-developer'); ?></label>
                                <span><?php echo display_array_field($contact['eixo_tematico'], $eixos); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Perfil Sociopolítico -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-cog"></i> <?php esc_html_e('Perfil Sociopolítico', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item full">
                                <label><?php esc_html_e('Comunidade/Território', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'comunidade_territorio'); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php esc_html_e('Participa de Coletivos', 'crm-developer'); ?></label>
                                <span>
                                    <?php echo $contact['participa_coletivos'] === 'sim' ? 'Sim' : 'Não'; ?>
                                    <?php if ($contact['coletivos_descricao']) echo ' - ' . esc_html($contact['coletivos_descricao']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label><?php esc_html_e('Tempo de Atuação Ambiental', 'crm-developer'); ?></label>
                                <span><?php echo display_field($contact, 'tempo_atuacao_ambiental'); ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php esc_html_e('Atua com Justiça Climática', 'crm-developer'); ?></label>
                                <span><?php echo $contact['atua_justica_climatica'] === 'sim' ? 'Sim' : 'Não'; ?></span>
                            </div>
                            <div class="info-item">
                                <label><?php esc_html_e('Papel de Liderança', 'crm-developer'); ?></label>
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
                        <h3><i class="fas fa-history"></i> <?php esc_html_e('Histórico de Interações', 'crm-developer'); ?></h3>
                        <button type="button" class="button" id="btn-new-interaction">
                            <i class="fas fa-plus"></i> <?php esc_html_e('Nova Interação', 'crm-developer'); ?>
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
                                                    <?php esc_html_e('Resultado:', 'crm-developer'); ?> <?php echo isset($resultados_interacao[$interaction['resultado']]) ? $resultados_interacao[$interaction['resultado']] : $interaction['resultado']; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($interaction['proxima_acao']) : ?>
                                                <div class="timeline-next-action">
                                                    <strong><?php esc_html_e('Próxima ação:', 'crm-developer'); ?></strong>
                                                    <?php echo esc_html($interaction['proxima_acao']); ?>
                                                    <?php if ($interaction['data_proxima_acao']) : ?>
                                                        <span class="next-action-date">
                                                            (<?php echo CRM_Dev_Helpers::format_date($interaction['data_proxima_acao']); ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php
                                            // Exibir anexos
                                            $anexos = !empty($interaction['anexos']) ? json_decode($interaction['anexos'], true) : array();
                                            if (!empty($anexos)) :
                                            ?>
                                                <div class="timeline-attachments">
                                                    <strong><i class="fas fa-paperclip"></i> <?php esc_html_e('Anexos:', 'crm-developer'); ?></strong>
                                                    <div class="attachments-list">
                                                        <?php foreach ($anexos as $attachment_id) :
                                                            $attachment_url = wp_get_attachment_url($attachment_id);
                                                            $attachment_title = get_the_title($attachment_id);
                                                            $attachment_type = get_post_mime_type($attachment_id);
                                                            $is_image = strpos($attachment_type, 'image') !== false;

                                                            if ($attachment_url) :
                                                        ?>
                                                            <a href="<?php echo esc_url($attachment_url); ?>" target="_blank" class="attachment-item <?php echo $is_image ? 'is-image' : ''; ?>">
                                                                <?php if ($is_image) : ?>
                                                                    <img src="<?php echo esc_url(wp_get_attachment_thumb_url($attachment_id)); ?>" alt="<?php echo esc_attr($attachment_title); ?>">
                                                                <?php else : ?>
                                                                    <i class="fas fa-file"></i>
                                                                    <span><?php echo esc_html($attachment_title); ?></span>
                                                                <?php endif; ?>
                                                            </a>
                                                        <?php
                                                            endif;
                                                        endforeach;
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="timeline-meta">
                                                <?php esc_html_e('Por', 'crm-developer'); ?> <?php echo esc_html($interaction['user_name']); ?>
                                            </div>
                                            <div class="timeline-actions">
                                                <button type="button" class="btn-edit-interaction"
                                                    data-id="<?php echo $interaction['id']; ?>"
                                                    data-tipo="<?php echo esc_attr($interaction['tipo']); ?>"
                                                    data-titulo="<?php echo esc_attr($interaction['titulo']); ?>"
                                                    data-descricao="<?php echo esc_attr($interaction['descricao']); ?>"
                                                    data-resultado="<?php echo esc_attr($interaction['resultado']); ?>"
                                                    data-proxima_acao="<?php echo esc_attr($interaction['proxima_acao']); ?>"
                                                    data-data_proxima_acao="<?php echo esc_attr($interaction['data_proxima_acao']); ?>"
                                                    data-anexos="<?php echo esc_attr($interaction['anexos']); ?>"
                                                    title="<?php esc_html_e('Editar', 'crm-developer'); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn-delete-interaction"
                                                    data-id="<?php echo $interaction['id']; ?>"
                                                    title="<?php esc_html_e('Excluir', 'crm-developer'); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="crm-dev-empty"><?php esc_html_e('Nenhuma interação registrada ainda.', 'crm-developer'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral -->
            <div class="contact-sidebar">
                <!-- Status e Controle -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> <?php esc_html_e('Informações', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="sidebar-info">
                            <div class="info-row">
                                <label><?php esc_html_e('Status', 'crm-developer'); ?></label>
                                <span class="status-badge status-<?php echo $contact['status']; ?>">
                                    <?php echo ucfirst($contact['status']); ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <label><?php esc_html_e('LGPD', 'crm-developer'); ?></label>
                                <span class="status-badge status-<?php echo $contact['consentimento_lgpd'] === 'sim' ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $contact['consentimento_lgpd'] === 'sim' ? 'Consentido' : 'Pendente'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <label><?php esc_html_e('Cadastrado em', 'crm-developer'); ?></label>
                                <span><?php echo CRM_Dev_Helpers::format_datetime($contact['created_at']); ?></span>
                            </div>
                            <div class="info-row">
                                <label><?php esc_html_e('Última atualização', 'crm-developer'); ?></label>
                                <span><?php echo CRM_Dev_Helpers::format_datetime($contact['updated_at']); ?></span>
                            </div>
                            <?php if ($contact['ultima_interacao']) : ?>
                                <div class="info-row">
                                    <label><?php esc_html_e('Última interação', 'crm-developer'); ?></label>
                                    <span><?php echo CRM_Dev_Helpers::format_datetime($contact['ultima_interacao']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <label><?php esc_html_e('Origem', 'crm-developer'); ?></label>
                                <span><?php echo ucfirst($contact['origem'] ?: 'Manual'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interesses de Mobilização -->
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bullhorn"></i> <?php esc_html_e('Mobilização', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="interests-display">
                            <div class="interest-row">
                                <label><?php esc_html_e('Continuar participando', 'crm-developer'); ?></label>
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
                            <h3><i class="fas fa-briefcase"></i> <?php esc_html_e('Dados Complementares', 'crm-developer'); ?></h3>
                        </div>
                        <div class="card-body">
                            <?php if ($contact['cargo_publico']) : ?>
                                <div class="info-row">
                                    <label><?php esc_html_e('Cargo Público', 'crm-developer'); ?></label>
                                    <span><?php echo esc_html($contact['cargo_publico']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($contact['vinculacao_institucional']) : ?>
                                <div class="info-row">
                                    <label><?php esc_html_e('Vinculação Institucional', 'crm-developer'); ?></label>
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
                            <h3><i class="fas fa-sticky-note"></i> <?php esc_html_e('Observações', 'crm-developer'); ?></h3>
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

<!-- Modal de Interação (Nova/Editar) -->
<div id="modal-interaction" class="crm-dev-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus" id="modal-icon"></i> <span id="modal-title"><?php esc_html_e('Nova Interação', 'crm-developer'); ?></span></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form id="interaction-form">
            <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
            <input type="hidden" name="id" id="int-id" value="">

            <div class="form-group">
                <label for="int-tipo"><?php esc_html_e('Tipo de Interação', 'crm-developer'); ?> *</label>
                <select id="int-tipo" name="tipo" required>
                    <?php foreach ($tipos_interacao as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="int-titulo"><?php esc_html_e('Título', 'crm-developer'); ?> *</label>
                <input type="text" id="int-titulo" name="titulo" required>
            </div>

            <div class="form-group">
                <label for="int-descricao"><?php esc_html_e('Descrição', 'crm-developer'); ?></label>
                <textarea id="int-descricao" name="descricao" rows="3"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="int-resultado"><?php esc_html_e('Resultado', 'crm-developer'); ?></label>
                    <select id="int-resultado" name="resultado">
                        <option value=""><?php esc_html_e('Selecione...', 'crm-developer'); ?></option>
                        <?php foreach ($resultados_interacao as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="int-proxima-acao"><?php esc_html_e('Próxima Ação', 'crm-developer'); ?></label>
                <textarea id="int-proxima-acao" name="proxima_acao" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label for="int-data-proxima"><?php esc_html_e('Data da Próxima Ação', 'crm-developer'); ?></label>
                <input type="date" id="int-data-proxima" name="data_proxima_acao">
            </div>

            <div class="form-group">
                <label><?php esc_html_e('Anexos', 'crm-developer'); ?></label>
                <div class="attachments-area">
                    <div id="attachments-preview" class="attachments-preview"></div>
                    <input type="hidden" name="anexos" id="int-anexos" value="">
                    <button type="button" class="button" id="btn-add-attachment">
                        <i class="fas fa-paperclip"></i> <?php esc_html_e('Adicionar Anexo', 'crm-developer'); ?>
                    </button>
                    <span class="field-help"><?php esc_html_e('Imagens, PDFs, documentos (máx. 10MB cada)', 'crm-developer'); ?></span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="button modal-close"><?php esc_html_e('Cancelar', 'crm-developer'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Salvar Interação', 'crm-developer'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Variável para armazenar IDs dos anexos
    let attachmentIds = [];

    // Função para renderizar preview dos anexos
    function renderAttachmentPreviews() {
        const $container = $('#attachments-preview');
        $container.empty();

        if (attachmentIds.length === 0) {
            return;
        }

        attachmentIds.forEach(function(id) {
            // Buscar dados do anexo via AJAX ou usar dados já carregados
            $.post(ajaxurl, {
                action: 'crm_dev_get_attachment_info',
                nonce: crmDevAdmin.nonce,
                attachment_id: id
            }, function(response) {
                if (response.success) {
                    const att = response.data;
                    const isImage = att.type && att.type.indexOf('image') !== -1;

                    let html = '<div class="attachment-preview-item" data-id="' + id + '">';
                    if (isImage && att.thumb_url) {
                        html += '<img src="' + att.thumb_url + '" alt="">';
                    } else {
                        html += '<i class="fas fa-file"></i>';
                    }
                    html += '<span class="attachment-name">' + att.title + '</span>';
                    html += '<button type="button" class="btn-remove-attachment" data-id="' + id + '">&times;</button>';
                    html += '</div>';

                    $container.append(html);
                }
            });
        });

        $('#int-anexos').val(JSON.stringify(attachmentIds));
    }

    // Função para resetar o formulário
    function resetInteractionForm() {
        $('#interaction-form')[0].reset();
        $('#int-id').val('');
        attachmentIds = [];
        $('#attachments-preview').empty();
        $('#int-anexos').val('');
        $('#modal-title').text('<?php esc_html_e('Nova Interação', 'crm-developer'); ?>');
        $('#modal-icon').removeClass('fa-edit').addClass('fa-plus');
    }

    // Abrir modal para nova interação
    $('#btn-new-interaction').on('click', function() {
        resetInteractionForm();
        $('#modal-interaction').show();
    });

    // Abrir modal para editar interação
    $(document).on('click', '.btn-edit-interaction', function() {
        const $btn = $(this);

        // Preencher formulário com dados da interação
        $('#int-id').val($btn.data('id'));
        $('#int-tipo').val($btn.data('tipo'));
        $('#int-titulo').val($btn.data('titulo'));
        $('#int-descricao').val($btn.data('descricao'));
        $('#int-resultado').val($btn.data('resultado'));
        $('#int-proxima-acao').val($btn.data('proxima_acao'));
        $('#int-data-proxima').val($btn.data('data_proxima_acao'));

        // Carregar anexos
        const anexosData = $btn.data('anexos');
        if (anexosData) {
            try {
                attachmentIds = typeof anexosData === 'string' ? JSON.parse(anexosData) : anexosData;
                if (!Array.isArray(attachmentIds)) {
                    attachmentIds = [];
                }
            } catch (e) {
                attachmentIds = [];
            }
        } else {
            attachmentIds = [];
        }
        renderAttachmentPreviews();

        // Atualizar título do modal
        $('#modal-title').text('<?php esc_html_e('Editar Interação', 'crm-developer'); ?>');
        $('#modal-icon').removeClass('fa-plus').addClass('fa-edit');

        $('#modal-interaction').show();
    });

    // Abrir Media Library para adicionar anexo
    $('#btn-add-attachment').on('click', function(e) {
        e.preventDefault();

        // Se a Media Library já está aberta, não abrir novamente
        if (typeof wp === 'undefined' || !wp.media) {
            alert('<?php esc_html_e('Biblioteca de mídia não disponível', 'crm-developer'); ?>');
            return;
        }

        const mediaUploader = wp.media({
            title: '<?php esc_html_e('Selecionar Anexos', 'crm-developer'); ?>',
            button: {
                text: '<?php esc_html_e('Adicionar Anexo(s)', 'crm-developer'); ?>'
            },
            multiple: true
        });

        mediaUploader.on('select', function() {
            const attachments = mediaUploader.state().get('selection').toJSON();
            attachments.forEach(function(attachment) {
                if (attachmentIds.indexOf(attachment.id) === -1) {
                    attachmentIds.push(attachment.id);

                    // Adicionar preview imediatamente
                    const isImage = attachment.type === 'image';
                    const thumbUrl = isImage && attachment.sizes && attachment.sizes.thumbnail
                        ? attachment.sizes.thumbnail.url
                        : (isImage ? attachment.url : '');

                    let html = '<div class="attachment-preview-item" data-id="' + attachment.id + '">';
                    if (isImage && thumbUrl) {
                        html += '<img src="' + thumbUrl + '" alt="">';
                    } else {
                        html += '<i class="fas fa-file"></i>';
                    }
                    html += '<span class="attachment-name">' + attachment.title + '</span>';
                    html += '<button type="button" class="btn-remove-attachment" data-id="' + attachment.id + '">&times;</button>';
                    html += '</div>';

                    $('#attachments-preview').append(html);
                }
            });
            $('#int-anexos').val(JSON.stringify(attachmentIds));
        });

        mediaUploader.open();
    });

    // Remover anexo
    $(document).on('click', '.btn-remove-attachment', function(e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'));
        attachmentIds = attachmentIds.filter(function(aid) { return aid !== id; });
        $(this).closest('.attachment-preview-item').remove();
        $('#int-anexos').val(JSON.stringify(attachmentIds));
    });

    // Excluir interação
    $(document).on('click', '.btn-delete-interaction', function() {
        if (!confirm('<?php esc_html_e('Tem certeza que deseja excluir esta interação?', 'crm-developer'); ?>')) {
            return;
        }

        const id = $(this).data('id');
        const $item = $(this).closest('.timeline-item');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_delete_interaction',
            nonce: crmDevAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $item.fadeOut(300, function() {
                    $(this).remove();
                    // Se não houver mais interações, mostrar mensagem vazia
                    if ($('.timeline-item').length === 0) {
                        $('.interactions-timeline').replaceWith(
                            '<p class="crm-dev-empty"><?php esc_html_e('Nenhuma interação registrada ainda.', 'crm-developer'); ?></p>'
                        );
                    }
                });
            } else {
                alert(response.data.message || '<?php esc_html_e('Erro ao excluir', 'crm-developer'); ?>');
            }
        });
    });

    // Fechar modal
    $('.modal-close').on('click', function() {
        $('#modal-interaction').hide();
        resetInteractionForm();
    });

    // Fechar modal clicando fora
    $('#modal-interaction').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
            resetInteractionForm();
        }
    });

    // Salvar interação
    $('#interaction-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {};
        $(this).serializeArray().forEach(function(item) {
            formData[item.name] = item.value;
        });

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php esc_html_e('Salvando...', 'crm-developer'); ?>');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_interaction',
            nonce: crmDevAdmin.nonce,
            data: formData
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || '<?php esc_html_e('Erro ao salvar', 'crm-developer'); ?>');
                $submitBtn.prop('disabled', false).html(originalText);
            }
        }).fail(function() {
            alert('<?php esc_html_e('Erro de conexão', 'crm-developer'); ?>');
            $submitBtn.prop('disabled', false).html(originalText);
        });
    });
});
</script>
