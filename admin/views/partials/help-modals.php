<?php
/**
 * Modais de Ajuda para todas as páginas
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderiza o botão de ajuda flutuante
 */
function crm_dev_render_help_button($section) {
    ?>
    <button type="button" class="btn-help-floating" id="btn-help-<?php echo esc_attr($section); ?>" title="<?php esc_html_e('Ajuda', 'crm-developer'); ?>">
        <i class="fas fa-question-circle"></i>
    </button>
    <?php
}

/**
 * Renderiza o modal de ajuda do Dashboard
 */
function crm_dev_render_help_modal_dashboard() {
    ?>
    <div id="modal-help-dashboard" class="crm-dev-modal help-modal">
        <div class="modal-content">
            <div class="modal-header help-header">
                <div class="help-header-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h3><?php esc_html_e('Dashboard', 'crm-developer'); ?></h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body help-body">
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Visão Geral', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('O Dashboard apresenta um resumo completo do seu CRM. Veja rapidamente o total de contatos, taxa de engajamento e distribuição geográfica.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Gráficos Interativos', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Os gráficos mostram distribuição por região, gênero e engajamento. Passe o mouse sobre os elementos para ver detalhes.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-bell"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Próximas Ações', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Veja as interações agendadas e ações pendentes. Contatos sem interação há mais de 30 dias aparecem destacados.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-sync"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Atualização em Tempo Real', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Os dados são atualizados automaticamente quando você adiciona ou modifica contatos e interações.', 'crm-developer'); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer help-footer">
                <button type="button" class="btn btn-primary modal-close-btn"><?php esc_html_e('Entendi!', 'crm-developer'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza o modal de ajuda de Contatos
 */
function crm_dev_render_help_modal_contacts() {
    ?>
    <div id="modal-help-contacts" class="crm-dev-modal help-modal">
        <div class="modal-content">
            <div class="modal-header help-header">
                <div class="help-header-icon">
                    <i class="fas fa-address-book"></i>
                </div>
                <h3><?php esc_html_e('Gestão de Contatos', 'crm-developer'); ?></h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body help-body">
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-search"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Busca e Filtros', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Use a busca para encontrar contatos por nome, email ou telefone. Combine com filtros de estado, status e engajamento para refinar resultados.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-sort"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Ordenação', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Clique nos cabeçalhos das colunas para ordenar. Clique novamente para inverter a ordem (crescente/decrescente).', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-tasks"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Ações em Massa', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Selecione múltiplos contatos para executar ações em lote como alterar status, enviar email ou exportar dados.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-star"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Score de Engajamento', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('O score (0-100) indica o nível de engajamento baseado em interações, participação e interesses. Verde = alto, amarelo = médio, vermelho = baixo.', 'crm-developer'); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer help-footer">
                <button type="button" class="btn btn-primary modal-close-btn"><?php esc_html_e('Entendi!', 'crm-developer'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza o modal de ajuda do Formulário de Contato
 */
function crm_dev_render_help_modal_contact_form() {
    ?>
    <div id="modal-help-contact-form" class="crm-dev-modal help-modal">
        <div class="modal-content">
            <div class="modal-header help-header">
                <div class="help-header-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3><?php esc_html_e('Cadastro de Contato', 'crm-developer'); ?></h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body help-body">
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-list-ol"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Formulário em Etapas', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('O cadastro é dividido em 6 etapas para facilitar o preenchimento. Apenas o nome completo é obrigatório, os demais campos são opcionais.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-camera"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Foto do Contato', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Adicione uma foto de perfil opcional clicando em "Selecionar Foto". A imagem será exibida na visualização do contato.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-map"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Região Automática', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Ao selecionar o estado, a região é preenchida automaticamente (Norte, Nordeste, etc.).', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('LGPD', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Marque o consentimento LGPD quando o contato autorizar o uso de seus dados. Isso é importante para envio de emails.', 'crm-developer'); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer help-footer">
                <button type="button" class="btn btn-primary modal-close-btn"><?php esc_html_e('Entendi!', 'crm-developer'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza o modal de ajuda de Importar/Exportar
 */
function crm_dev_render_help_modal_import_export() {
    ?>
    <div id="modal-help-import-export" class="crm-dev-modal help-modal">
        <div class="modal-content">
            <div class="modal-header help-header">
                <div class="help-header-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <h3><?php esc_html_e('Importar/Exportar', 'crm-developer'); ?></h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body help-body">
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-file-upload"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Importar Contatos', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Importe contatos de arquivos Excel (.xlsx) ou CSV. Arraste o arquivo ou clique para selecionar. O sistema detecta automaticamente as colunas.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-columns"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Mapeamento de Colunas', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Na etapa de mapeamento, associe as colunas do seu arquivo aos campos do CRM. Colunas com nomes similares são mapeadas automaticamente.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-file-download"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Exportar Contatos', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Exporte todos ou apenas contatos filtrados. Escolha entre Excel, CSV ou PDF. Selecione quais campos incluir na exportação.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-download"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Modelo de Planilha', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Baixe o modelo de planilha para ver o formato correto de importação com todas as colunas disponíveis.', 'crm-developer'); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer help-footer">
                <button type="button" class="btn btn-primary modal-close-btn"><?php esc_html_e('Entendi!', 'crm-developer'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza o modal de ajuda de Relatórios
 */
function crm_dev_render_help_modal_reports() {
    ?>
    <div id="modal-help-reports" class="crm-dev-modal help-modal">
        <div class="modal-content">
            <div class="modal-header help-header">
                <div class="help-header-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3><?php esc_html_e('Relatórios e Análises', 'crm-developer'); ?></h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body help-body">
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-filter"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Filtros Avançados', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Combine múltiplos filtros para análises detalhadas: período, região, estado, status, engajamento, gênero, raça e eixo temático.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-eye"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Tipos de Visualização', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Alterne entre visualizações: Geográfico (mapas), Demográfico (gênero, raça, idade), Participação, Engajamento, Mobilização e Temporal.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-chart-pie"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Gráficos Dinâmicos', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Alterne entre tipos de gráfico (barras, pizza, linhas) clicando nos ícones no canto dos cards.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-print"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Exportar e Imprimir', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Use os botões para exportar relatórios em Excel ou imprimir diretamente. A tabela de dados detalhados também pode ser exportada.', 'crm-developer'); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer help-footer">
                <button type="button" class="btn btn-primary modal-close-btn"><?php esc_html_e('Entendi!', 'crm-developer'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza o modal de ajuda de Configurações
 */
function crm_dev_render_help_modal_settings() {
    ?>
    <div id="modal-help-settings" class="crm-dev-modal help-modal">
        <div class="modal-content">
            <div class="modal-header help-header">
                <div class="help-header-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h3><?php esc_html_e('Configurações do CRM', 'crm-developer'); ?></h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body help-body">
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-sliders-h"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Opções Gerais', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Configure opções gerais do CRM como nome da organização, paginação padrão e comportamentos do sistema.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-database"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Banco de Dados', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Visualize informações sobre o banco de dados, quantidade de registros e realize manutenção quando necessário.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Permissões', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Administradores têm acesso completo. Outros usuários podem ter permissões limitadas baseadas em suas roles.', 'crm-developer'); ?></p>
                    </div>
                </div>
                <div class="help-section">
                    <div class="help-icon"><i class="fas fa-info-circle"></i></div>
                    <div class="help-content">
                        <h4><?php esc_html_e('Versão e Suporte', 'crm-developer'); ?></h4>
                        <p><?php esc_html_e('Veja a versão atual do plugin e informações do sistema para suporte técnico.', 'crm-developer'); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer help-footer">
                <button type="button" class="btn btn-primary modal-close-btn"><?php esc_html_e('Entendi!', 'crm-developer'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza o JavaScript para os modais de ajuda
 */
function crm_dev_render_help_modal_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Abrir modal de ajuda
        $('[id^="btn-help-"]').on('click', function() {
            const section = $(this).attr('id').replace('btn-help-', '');
            $('#modal-help-' + section).addClass('show');
        });

        // Fechar modais de ajuda
        $('.help-modal .modal-close, .help-modal .modal-close-btn').on('click', function() {
            $(this).closest('.help-modal').removeClass('show');
        });

        // Fechar clicando fora
        $('.help-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).removeClass('show');
            }
        });

        // Fechar com ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.help-modal.show').removeClass('show');
            }
        });
    });
    </script>
    <?php
}
