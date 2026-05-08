<?php
/**
 * Modais explicativos contextuais (TD-10).
 *
 * Cada modal usa role="dialog" + aria-modal="true". O JS Modal.js cuida de
 * focus trap + ESC + restauracao. Os triggers ("?") apontam para os IDs.
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Helper local para renderizar um modal padrao.
 *
 * @param string $id      ID do modal
 * @param string $titulo  Titulo (pt_BR)
 * @param string $conteudo HTML interno (cuide de escape onde dinamico)
 */
$render_modal = static function (string $id, string $titulo, string $conteudo): void {
    ?>
    <div
        class="pi-modal"
        id="<?php echo esc_attr($id); ?>"
        role="dialog"
        aria-modal="true"
        aria-labelledby="<?php echo esc_attr($id); ?>-titulo"
        data-pi-modal
        hidden
    >
        <div class="pi-modal__overlay" aria-hidden="true"></div>
        <div class="pi-modal__dialog" tabindex="-1">
            <header class="pi-modal__header">
                <h2 id="<?php echo esc_attr($id); ?>-titulo" class="pi-modal__title">
                    <?php echo esc_html($titulo); ?>
                </h2>
                <button
                    type="button"
                    class="pi-modal__close"
                    aria-label="<?php echo esc_attr__('Fechar diálogo', 'participe-ibram'); ?>"
                    data-pi-modal-close
                >&times;</button>
            </header>
            <div class="pi-modal__body">
                <?php echo $conteudo; // ja escapado pelos chamadores ?>
            </div>
            <footer class="pi-modal__footer">
                <button type="button" class="pi-btn pi-btn--primario" data-pi-modal-close>
                    <?php esc_html_e('Entendi', 'participe-ibram'); ?>
                </button>
            </footer>
        </div>
    </div>
    <?php
};

// CPF
$render_modal(
    'pi-modal-help-cpf',
    __('Por que pedimos seu CPF?', 'participe-ibram'),
    '<p>' . esc_html__('O CPF é utilizado exclusivamente para identificar você como agente cadastrado e para evitar duplicidades.', 'participe-ibram') . '</p>'
    . '<ul>'
    . '<li>' . esc_html__('Não consultamos seu score nem dados financeiros.', 'participe-ibram') . '</li>'
    . '<li>' . esc_html__('Não compartilhamos com terceiros.', 'participe-ibram') . '</li>'
    . '<li>' . esc_html__('O dado é armazenado de forma criptografada (LGPD art. 6º, VII).', 'participe-ibram') . '</li>'
    . '</ul>'
    . '<p>' . esc_html__('Base legal: LGPD art. 7º, II — cumprimento de obrigação legal.', 'participe-ibram') . '</p>'
);

// RG
$render_modal(
    'pi-modal-help-rg',
    __('Sobre o documento de identidade', 'participe-ibram'),
    '<p>' . esc_html__('Você pode anexar RG, CNH ou Carteira de Trabalho. O documento serve para validação da identidade na análise.', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('Estrangeiros podem anexar passaporte.', 'participe-ibram') . '</p>'
);

// CNPJ
$render_modal(
    'pi-modal-help-cnpj',
    __('Sobre o CNPJ da organização', 'participe-ibram'),
    '<p>' . esc_html__('Se sua organização possui CNPJ, anexe o cartão de inscrição emitido pela Receita Federal.', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('Coletivos sem CNPJ podem se cadastrar mediante carta de indicação assinada por pelo menos 5 representantes.', 'participe-ibram') . '</p>'
);

// Coletivo sem CNPJ
$render_modal(
    'pi-modal-help-coletivo',
    __('Coletivos sem CNPJ', 'participe-ibram'),
    '<p>' . esc_html__('Conforme a Portaria IBRAM 3230/2024, coletivos não formalizados (alíneas a e c) podem se cadastrar como agentes desde que indiquem representantes por meio de carta com no mínimo 5 assinaturas.', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('A carta deve conter nome, CPF e contato dos signatários.', 'participe-ibram') . '</p>'
);

// Ata de posse
$render_modal(
    'pi-modal-help-ata-posse',
    __('Como obter a ata de posse?', 'participe-ibram'),
    '<p>' . esc_html__('A ata de posse é o documento formal que registra a eleição da diretoria atual da organização.', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('Geralmente está arquivada com o secretário(a) ou registrada em cartório. Se a sua entidade ainda não tem ata formal, anexe o documento equivalente disponível (ata de assembleia, regimento) e descreva no campo "Outros documentos".', 'participe-ibram') . '</p>'
);

// Numero de registro
$render_modal(
    'pi-modal-help-numero-registro',
    __('O que é o número de registro?', 'participe-ibram'),
    '<p>' . esc_html__('Após o deferimento do seu cadastro, o sistema gera um número único no formato:', 'participe-ibram') . '</p>'
    . '<p><code>PI-{TIPO}-{ANO}-000000</code></p>'
    . '<ul>'
    . '<li><strong>PI-PF-2026-000123</strong>: ' . esc_html__('Pessoa Física', 'participe-ibram') . '</li>'
    . '<li><strong>PI-OR-2026-000045</strong>: ' . esc_html__('Organização', 'participe-ibram') . '</li>'
    . '<li><strong>PI-SM-2026-000007</strong>: ' . esc_html__('Sistema/Secretaria', 'participe-ibram') . '</li>'
    . '</ul>'
    . '<p>' . esc_html__('O número é imutável e deve ser informado em todas as inscrições em editais.', 'participe-ibram') . '</p>'
);

// Votacao
$render_modal(
    'pi-modal-help-votacao',
    __('Como funciona a votação?', 'participe-ibram'),
    '<p>' . esc_html__('Agentes cadastrados e habilitados podem votar nos editais de composição do CCDEM e demais instâncias.', 'participe-ibram') . '</p>'
    . '<ul>'
    . '<li>' . esc_html__('Cada agente tem um voto por categoria.', 'participe-ibram') . '</li>'
    . '<li>' . esc_html__('O voto é confirmado em modal de confirmação (não pode ser desfeito).', 'participe-ibram') . '</li>'
    . '<li>' . esc_html__('A contagem é anonimizada por hash criptográfico — não é possível identificar quem votou em quem.', 'participe-ibram') . '</li>'
    . '</ul>'
);

// PCT
$render_modal(
    'pi-modal-help-pct',
    __('Povos e Comunidades Tradicionais', 'participe-ibram'),
    '<p>' . esc_html__('A lista segue o art. 4º, §2º do Decreto 8.750/2016, que reconhece grupos culturalmente diferenciados.', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('A auto-declaração é opcional e usada para políticas afirmativas. A recusa não impede o cadastro.', 'participe-ibram') . '</p>'
);

// PCD
$render_modal(
    'pi-modal-help-pcd',
    __('Pessoa com Deficiência (PCD)', 'participe-ibram'),
    '<p>' . esc_html__('A auto-declaração de PCD é voluntária e usada para fins de acessibilidade e políticas afirmativas, conforme a Lei Brasileira de Inclusão (Lei 13.146/2015).', 'participe-ibram') . '</p>'
);

// LGPD geral
$render_modal(
    'pi-modal-help-lgpd',
    __('Termo completo de consentimento (LGPD)', 'participe-ibram'),
    '<p><strong>' . esc_html__('Controlador:', 'participe-ibram') . '</strong> ' . esc_html__('Instituto Brasileiro de Museus — IBRAM/MinC.', 'participe-ibram') . '</p>'
    . '<p><strong>' . esc_html__('Encarregado (DPO):', 'participe-ibram') . '</strong> ' . esc_html__('dpo@museus.gov.br', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('Este cadastro coleta dados pessoais e dados sensíveis (raça/cor, identidade de gênero, orientação sexual, PCD, PCT) com base no art. 7º, II e art. 11, II, "a" da LGPD.', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('Você tem garantidos os direitos do titular previstos no art. 18 da LGPD: acesso, retificação, exclusão, portabilidade, oposição e anonimização. Solicitações podem ser feitas em "Minha Conta" → "Privacidade".', 'participe-ibram') . '</p>'
    . '<p>' . esc_html__('Os dados são retidos pelo prazo necessário ao cumprimento das obrigações legais (Portaria IBRAM 3230/2024) e excluídos/anonimizados após o término.', 'participe-ibram') . '</p>'
);
