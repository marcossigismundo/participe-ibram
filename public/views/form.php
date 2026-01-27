<?php
/**
 * View do formulário público de cadastro
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

$lgpd_text = get_option('crm_dev_lgpd_text', 'Ao preencher este formulário, você concorda com o tratamento dos seus dados pessoais conforme nossa política de privacidade e a Lei Geral de Proteção de Dados (LGPD).');

$estados = CRM_Dev_Helpers::get_estados();
$generos = CRM_Dev_Helpers::get_generos();
$racas = CRM_Dev_Helpers::get_racas();
$etapas = CRM_Dev_Helpers::get_etapas_participacao();
$tipos_part = CRM_Dev_Helpers::get_tipos_participacao();
$categorias = CRM_Dev_Helpers::get_categorias_representacao();
$eixos = CRM_Dev_Helpers::get_eixos_tematicos();
?>

<div class="crm-public-form-wrapper">
    <div class="crm-public-form-header">
        <h2><?php echo esc_html($atts['titulo']); ?></h2>
        <p><?php echo esc_html($atts['subtitulo']); ?></p>
    </div>

    <!-- Indicador de Etapas -->
    <div class="crm-public-steps">
        <div class="step active" data-step="1"><span>1</span> Dados Pessoais</div>
        <div class="step" data-step="2"><span>2</span> Contato</div>
        <div class="step" data-step="3"><span>3</span> Participação</div>
        <div class="step" data-step="4"><span>4</span> Interesses</div>
    </div>

    <form id="crm-public-form" class="crm-public-form">
        <!-- Etapa 1: Dados Pessoais -->
        <div class="form-step active" data-step="1">
            <h3>Dados Pessoais</h3>

            <div class="form-group">
                <label for="pub-nome">Nome Completo <span class="required">*</span></label>
                <input type="text" id="pub-nome" name="nome_completo" required>
            </div>

            <div class="form-group">
                <label for="pub-nome-social">Nome Social</label>
                <input type="text" id="pub-nome-social" name="nome_social">
                <span class="field-hint">Nome pelo qual você prefere ser chamado(a)</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pub-nascimento">Data de Nascimento</label>
                    <input type="date" id="pub-nascimento" name="data_nascimento">
                </div>

                <div class="form-group">
                    <label for="pub-genero">Gênero</label>
                    <select id="pub-genero" name="genero">
                        <option value="">Selecione...</option>
                        <?php foreach ($generos as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="pub-raca">Raça/Etnia</label>
                <select id="pub-raca" name="raca_etnia">
                    <option value="">Selecione...</option>
                    <?php foreach ($racas as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Pessoa com Deficiência?</label>
                <div class="radio-inline">
                    <label><input type="radio" name="pessoa_deficiencia" value="nao" checked> Não</label>
                    <label><input type="radio" name="pessoa_deficiencia" value="sim"> Sim</label>
                </div>
            </div>

            <div class="form-group conditional" id="pub-deficiencia-desc" style="display: none;">
                <label for="pub-deficiencia-texto">Descrição da Deficiência</label>
                <input type="text" id="pub-deficiencia-texto" name="deficiencia_descricao">
            </div>
        </div>

        <!-- Etapa 2: Contato e Localização -->
        <div class="form-step" data-step="2">
            <h3>Informações de Contato</h3>

            <div class="form-group">
                <label for="pub-email">Email <span class="required">*</span></label>
                <input type="email" id="pub-email" name="email" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pub-telefone">Telefone</label>
                    <input type="tel" id="pub-telefone" name="telefone" placeholder="(00) 00000-0000">
                </div>

                <div class="form-group">
                    <label for="pub-whatsapp">WhatsApp</label>
                    <input type="tel" id="pub-whatsapp" name="whatsapp" placeholder="(00) 00000-0000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pub-estado">Estado</label>
                    <select id="pub-estado" name="estado">
                        <option value="">Selecione...</option>
                        <?php foreach ($estados as $uf => $nome) : ?>
                            <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($nome); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pub-municipio">Município</label>
                    <input type="text" id="pub-municipio" name="municipio">
                </div>
            </div>
        </div>

        <!-- Etapa 3: Participação -->
        <div class="form-step" data-step="3">
            <h3>Participação em Conferências</h3>
            <p class="step-intro">Marque as opções que se aplicam ao seu histórico de participação.</p>

            <div class="form-group">
                <label>Etapa de Participação</label>
                <div class="checkbox-grid">
                    <?php foreach ($etapas as $key => $label) : ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="etapa_participacao[]" value="<?php echo esc_attr($key); ?>">
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Tipo de Participação</label>
                <div class="checkbox-grid">
                    <?php foreach ($tipos_part as $key => $label) : ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="tipo_participacao[]" value="<?php echo esc_attr($key); ?>">
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Eixo Temático de Interesse</label>
                <div class="checkbox-grid">
                    <?php foreach ($eixos as $key => $label) : ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="eixo_tematico[]" value="<?php echo esc_attr($key); ?>">
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Etapa 4: Interesses e Consentimento -->
        <div class="form-step" data-step="4">
            <h3>Interesses em Mobilização Futura</h3>

            <div class="form-group">
                <label>Deseja continuar participando das nossas agendas?</label>
                <div class="radio-inline">
                    <label><input type="radio" name="continuar_participando" value="sim"> Sim</label>
                    <label><input type="radio" name="continuar_participando" value="nao"> Não</label>
                    <label><input type="radio" name="continuar_participando" value="talvez"> Talvez</label>
                </div>
            </div>

            <div class="form-group">
                <label>Tenho interesse em: (marque todas que se aplicam)</label>
                <div class="interest-options">
                    <label class="interest-option">
                        <input type="checkbox" name="interesse_formacao" value="sim">
                        <span class="interest-icon">🎓</span>
                        <span>Formação técnica ou política</span>
                    </label>
                    <label class="interest-option">
                        <input type="checkbox" name="interesse_conteudo" value="sim">
                        <span class="interest-icon">✍️</span>
                        <span>Produção de conteúdo</span>
                    </label>
                    <label class="interest-option">
                        <input type="checkbox" name="interesse_incidencia" value="sim">
                        <span class="interest-icon">🏛️</span>
                        <span>Incidência política</span>
                    </label>
                    <label class="interest-option">
                        <input type="checkbox" name="interesse_mobilizacao" value="sim">
                        <span class="interest-icon">📢</span>
                        <span>Mobilização territorial</span>
                    </label>
                    <label class="interest-option">
                        <input type="checkbox" name="interesse_voluntariado" value="sim">
                        <span class="interest-icon">🤝</span>
                        <span>Voluntariado</span>
                    </label>
                    <label class="interest-option">
                        <input type="checkbox" name="interesse_foruns" value="sim">
                        <span class="interest-icon">💬</span>
                        <span>Fóruns temáticos</span>
                    </label>
                </div>
            </div>

            <!-- Consentimento LGPD -->
            <div class="form-group lgpd-consent">
                <label class="consent-label">
                    <input type="checkbox" name="consentimento_lgpd" value="sim" required>
                    <span><?php echo esc_html($lgpd_text); ?></span>
                </label>
            </div>
        </div>

        <!-- Navegação -->
        <div class="form-navigation">
            <button type="button" class="btn btn-secondary" id="btn-prev" style="display: none;">
                ← Anterior
            </button>
            <button type="button" class="btn btn-primary" id="btn-next">
                Próximo →
            </button>
            <button type="submit" class="btn btn-success" id="btn-submit" style="display: none;">
                Enviar Cadastro
            </button>
        </div>
    </form>

    <!-- Mensagem de Sucesso -->
    <div class="crm-public-success" id="success-message" style="display: none;">
        <div class="success-icon">✓</div>
        <h3>Cadastro realizado com sucesso!</h3>
        <p>Obrigado por se juntar a nós. Em breve entraremos em contato.</p>
    </div>
</div>

<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('crm-public-form');
        if (!form) return;

        let currentStep = 1;
        const totalSteps = 4;

        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');
        const btnSubmit = document.getElementById('btn-submit');

        function updateStep() {
            // Atualiza etapas visuais
            document.querySelectorAll('.crm-public-steps .step').forEach((step, index) => {
                step.classList.remove('active', 'completed');
                if (index + 1 < currentStep) step.classList.add('completed');
                if (index + 1 === currentStep) step.classList.add('active');
            });

            // Atualiza formulários
            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.remove('active');
                if (parseInt(step.dataset.step) === currentStep) {
                    step.classList.add('active');
                }
            });

            // Atualiza botões
            btnPrev.style.display = currentStep > 1 ? 'inline-block' : 'none';
            btnNext.style.display = currentStep < totalSteps ? 'inline-block' : 'none';
            btnSubmit.style.display = currentStep === totalSteps ? 'inline-block' : 'none';
        }

        btnNext.addEventListener('click', function() {
            // Validação básica da etapa atual
            if (currentStep === 1) {
                const nome = document.getElementById('pub-nome').value.trim();
                if (!nome) {
                    alert('Por favor, preencha seu nome completo.');
                    return;
                }
            }
            if (currentStep === 2) {
                const email = document.getElementById('pub-email').value.trim();
                if (!email) {
                    alert('Por favor, preencha seu email.');
                    return;
                }
            }

            if (currentStep < totalSteps) {
                currentStep++;
                updateStep();
            }
        });

        btnPrev.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                updateStep();
            }
        });

        // Campo condicional de deficiência
        document.querySelectorAll('input[name="pessoa_deficiencia"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('pub-deficiencia-desc').style.display =
                    this.value === 'sim' ? 'block' : 'none';
            });
        });

        // Envio do formulário
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const data = {};

            formData.forEach((value, key) => {
                if (key.endsWith('[]')) {
                    const cleanKey = key.slice(0, -2);
                    if (!data[cleanKey]) data[cleanKey] = [];
                    data[cleanKey].push(value);
                } else {
                    data[key] = value;
                }
            });

            // Verifica LGPD
            if (!data.consentimento_lgpd) {
                alert('É necessário aceitar os termos de privacidade para continuar.');
                return;
            }

            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Enviando...';

            fetch(crmDevPublic.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'crm_dev_public_register',
                    nonce: crmDevPublic.nonce,
                    'data': JSON.stringify(data)
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    form.style.display = 'none';
                    document.querySelector('.crm-public-steps').style.display = 'none';
                    document.getElementById('success-message').style.display = 'block';
                } else {
                    alert(result.data.message || 'Erro ao enviar. Tente novamente.');
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = 'Enviar Cadastro';
                }
            })
            .catch(error => {
                alert('Erro de comunicação. Tente novamente.');
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Enviar Cadastro';
            });
        });
    });
})();
</script>
