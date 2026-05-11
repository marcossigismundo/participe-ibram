<?php
/**
 * Template parcial — Glossário do Participe Ibram.
 *
 * Incluído dentro do tabpanel do glossário em templates/admin/ajuda/index.php.
 *
 * WCAG 2.1 AA:
 *  - 1.3.1 — estrutura semântica <dl>/<dt>/<dd>
 *  - 2.4.1 — filtro por inicial com live region ARIA
 *  - 2.4.6 — título descritivo
 *  - 2.1.1 — filtro por letra operável por teclado
 *  - 4.1.2 — links âncora com id únicos por termo
 *
 * Layout responsivo: 1 coluna em mobile, 2 colunas em ≥ 768 px.
 * Print: todos os termos visíveis, navegação por letra oculta.
 *
 * @package Ibram\ParticipeIbram\Templates\Admin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $slug Slug do termo para usar como id HTML.
 */
$slug = static function (string $term): string {
    $term = strtolower($term);
    $term = preg_replace('/[^a-z0-9]+/', '-', $term) ?? $term;
    return 'termo-' . trim($term, '-');
};

$ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');

$termos = [
    'A' => [
        'Agente' => __(
            'Pessoa física, organização da sociedade civil ou sistema de museu que se cadastra no CNAC (Cadastro Nacional de Agentes Culturais) do Ibram. '
            . 'Existem três tipologias: Pessoa Física (PF), Organização (OR) e Sistema de Museu (SM). '
            . 'Cada tipologia possui campos e documentos exigidos distintos.',
            'participe-ibram'
        ),
        'Análise técnica' => __(
            'Etapa do processo de habilitação em que a equipe do Ibram examina os documentos e informações submetidos pelo agente. '
            . 'Pode resultar em deferimento, indeferimento ou solicitação de complementação. '
            . 'A análise é registrada no audit log e vinculada ao edital correspondente.',
            'participe-ibram'
        ),
        'Anonimização' => __(
            'Processo irreversível pelo qual dados pessoais deixam de estar relacionados a uma pessoa identificada ou identificável. '
            . 'Após a anonimização, os dados não se enquadram mais no escopo da LGPD (Art. 5.º, III). '
            . 'Distingue-se da pseudonimização, que é reversível mediante uso de chave adicional.',
            'participe-ibram'
        ),
        'Apuração' => __(
            'Fase final do processo eleitoral do Conselho em que os votos computados são contabilizados e o resultado é publicado. '
            . 'O Participe Ibram gera um hash pré-apuração antes de abrir os votos, garantindo auditabilidade. '
            . 'Em caso de empate, aplica-se o critério de tie-break definido no edital.',
            'participe-ibram'
        ),
        'Art. 18' => __(
            'Artigo 18 da Lei n.º 13.709/2018 (LGPD) que lista os direitos dos titulares de dados pessoais: confirmação de tratamento, acesso, correção, anonimização/bloqueio/eliminação, portabilidade, revogação do consentimento e oposição. '
            . 'O DPO do Ibram é responsável por responder solicitações exercendo esses direitos em até 15 dias.',
            'participe-ibram'
        ),
        'ASES Web' => __(
            'Avaliador e Simulador para Acessibilidade de Sítios (ASES Web), ferramenta do governo federal brasileiro para verificação automática de acessibilidade digital conforme eMAG e WCAG. '
            . 'O Participe Ibram deve ser avaliado periodicamente com o ASES Web para garantir conformidade com eMAG 3.1.',
            'participe-ibram'
        ),
        'Audit log append-only' => __(
            'Registro imutável de eventos do sistema onde novos registros são adicionados mas nenhum existente é alterado ou excluído. '
            . 'O Participe Ibram usa a tabela wp_pi_audit_log como trilha de auditoria obrigatória para conformidade LGPD. '
            . 'Apenas o DPO com aprovação formal pode arquivar registros; jamais são deletados.',
            'participe-ibram'
        ),
        'Auditoria' => __(
            'Processo de verificação sistemática de registros e operações do sistema para garantir conformidade, segurança e integridade dos dados. '
            . 'No Participe Ibram, auditoria inclui o audit log de ações administrativas e a auditoria pública da votação. '
            . 'Ver também: Audit log append-only, AccessTracker.',
            'participe-ibram'
        ),
        'Auditoria pública' => __(
            'Conjunto de informações divulgadas após a apuração que permite a qualquer interessado verificar a integridade do resultado eleitoral. '
            . 'Inclui o hash pré-apuração, a lista de eleitores (pseudonimizada), os votos agregados e o resultado final. '
            . 'Não são divulgados: identidades dos eleitores individuais nem votos nominais.',
            'participe-ibram'
        ),
        'AccessTracker' => __(
            'Componente do Participe Ibram responsável por registrar acessos a dados pessoais sensíveis no audit log. '
            . 'Garante rastreabilidade de quem consultou CPFs, endereços e outros dados protegidos pela LGPD. '
            . 'Usa PiiMasker para mascarar os valores nos logs.',
            'participe-ibram'
        ),
    ],
    'C' => [
        'Cadastro' => __(
            'Processo pelo qual um agente cultural submete seus dados ao Ibram para obtenção de registro no CNAC. '
            . 'O cadastro é composto por tipologia, dados gerais, endereço, documentos e aceite de finalidade LGPD. '
            . 'Após análise técnica, o cadastro pode ser deferido ou indeferido.',
            'participe-ibram'
        ),
        'Candidato' => __(
            'Agente cultural habilitado em um edital que concorre a uma ou mais vagas do Conselho. '
            . 'O candidato recebe um identificador interno (não exposto publicamente) e aparece na lista de candidatos do edital. '
            . 'Após a apuração, pode ser classificado como Eleito ou Suplente eleito.',
            'participe-ibram'
        ),
        'Categoria' => __(
            'Classificação dentro de um edital que agrupa candidatos por segmento cultural ou área de atuação. '
            . 'Cada categoria possui um número de vagas titulares e vagas suplentes definidos no edital. '
            . 'A habilitação e a votação são realizadas por categoria.',
            'participe-ibram'
        ),
        'Consentimento granular' => __(
            'Modelo de consentimento LGPD em que o titular autoriza ou recusa cada finalidade de tratamento de dados individualmente, em vez de um aceite único. '
            . 'O Participe Ibram implementa consentimento granular com versionamento: cada versão do termo é registrada e vinculada ao aceite do agente. '
            . 'O agente pode revogar consentimentos específicos sem cancelar o cadastro.',
            'participe-ibram'
        ),
        'Critérios' => __(
            'Requisitos de habilitação definidos no edital que o agente deve atender para participar do processo seletivo. '
            . 'Podem incluir tipo de tipologia, documentos obrigatórios, comprovantes de atuação e restrições legais. '
            . 'A inabilitação ocorre quando o agente não atende aos critérios exigidos.',
            'participe-ibram'
        ),
    ],
    'D' => [
        'Deferimento' => __(
            'Decisão administrativa que aprova o cadastro, habilitação ou recurso de um agente cultural. '
            . 'O deferimento é registrado no audit log, gera notificação ao agente e atualiza o status correspondente. '
            . 'Para cadastros, o deferimento resulta no registro no CNAC.',
            'participe-ibram'
        ),
        'Documentos exigidos' => __(
            'Arquivos que o agente deve anexar para comprovar os critérios de habilitação ou cadastro. '
            . 'São armazenados no diretório privado do plugin (fora do acesso público) e vinculados ao cadastro. '
            . 'A lista de documentos varia por tipologia de agente e por edital.',
            'participe-ibram'
        ),
        'DPO' => __(
            'Data Protection Officer (Encarregado de Proteção de Dados) — pessoa designada pelo controlador para atuar como canal de comunicação entre titulares, controlador e Autoridade Nacional de Proteção de Dados (ANPD). '
            . 'Exigido pelo Art. 41 da LGPD. No plugin, o e-mail do DPO é configurado nas opções LGPD e usado para alertas automáticos.',
            'participe-ibram'
        ),
        'DSGov' => __(
            'Design System do Governo Federal brasileiro, conjunto de padrões visuais, componentes e diretrizes de acessibilidade adotado por sistemas do governo. '
            . 'O Participe Ibram adota os tokens de cor, tipografia e espaçamento do DSGov para garantir identidade visual consistente e conformidade com eMAG.',
            'participe-ibram'
        ),
    ],
    'E' => [
        'Edital' => __(
            'Documento normativo publicado pelo Ibram que define as condições de participação em um processo de seleção para o Conselho. '
            . 'Um edital possui categorias, vagas, critérios, documentos exigidos, datas e status (rascunho, publicado, encerrado). '
            . 'O Despacho n.º 98/2025 regulamenta o formato e os requisitos dos editais.',
            'participe-ibram'
        ),
        'Eleitor' => __(
            'Agente cultural com direito a voto em uma votação do Conselho, conforme critérios definidos no edital. '
            . 'Para preservar o sigilo do voto e a rastreabilidade, cada eleitor recebe um Eleitor_hash único por eleição. '
            . 'O vínculo entre eleitor real e Eleitor_hash é mantido de forma protegida.',
            'participe-ibram'
        ),
        'Eleitor_hash' => __(
            'Identificador pseudônimo gerado a partir do ID do eleitor e de um segredo criptográfico (PI_VOTING_SECRET). '
            . 'Permite verificar que um eleitor votou apenas uma vez sem revelar sua identidade nos registros públicos de auditoria. '
            . 'O hash é gerado por operação HMAC e não é reversível sem o segredo.',
            'participe-ibram'
        ),
        'Eleito' => __(
            'Candidato que obteve votação suficiente para ocupar uma vaga titular do Conselho após a apuração. '
            . 'O resultado de eleito é registrado no edital e notificado ao candidato. '
            . 'Ver também: Suplente eleito.',
            'participe-ibram'
        ),
        'eMAG' => __(
            'Modelo de Acessibilidade em Governo Eletrônico — norma brasileira de acessibilidade digital baseada nas WCAG, adaptada para o contexto do governo federal. '
            . 'O Participe Ibram deve estar em conformidade com eMAG 3.1, que é exigência legal para sistemas do governo federal. '
            . 'Ver também: WCAG 2.1 AA, ASES Web.',
            'participe-ibram'
        ),
    ],
    'F' => [
        'Finalidade (LGPD)' => __(
            'Propósito específico, explícito e legítimo para o qual dados pessoais são coletados e tratados, conforme Art. 6.º, I da LGPD. '
            . 'O Participe Ibram declara cada finalidade em Termos versionados e exige consentimento granular do titular. '
            . 'Dados não podem ser usados para finalidades incompatíveis com as originalmente declaradas.',
            'participe-ibram'
        ),
    ],
    'G' => [
        'gov.br' => __(
            'Plataforma de identidade digital do governo federal brasileiro que permite autenticação de cidadãos com Selos de Confiabilidade (Bronze, Prata e Ouro). '
            . 'O Participe Ibram integra gov.br via OIDC para verificar a identidade dos agentes culturais no cadastro. '
            . 'O nível de confiabilidade (Prata ou Ouro) pode ser exigido para determinadas categorias de edital.',
            'participe-ibram'
        ),
    ],
    'H' => [
        'Habilitação' => __(
            'Etapa do processo seletivo em que a equipe do Ibram verifica se o agente atende aos critérios definidos no edital para concorrer às vagas disponíveis. '
            . 'Resulta em habilitado ou inabilitado. O agente inabilitado pode interpor Recurso de inabilitação.',
            'participe-ibram'
        ),
        'Hash pré-apuração' => __(
            'Resumo criptográfico (hash SHA-256) gerado a partir do estado dos votos antes de a apuração ser aberta publicamente. '
            . 'Serve como prova de integridade: qualquer alteração posterior nos votos mudaria o hash, tornando a fraude detectável. '
            . 'O hash pré-apuração é publicado na auditoria pública do edital.',
            'participe-ibram'
        ),
    ],
    'I' => [
        'Inabilitação' => __(
            'Decisão que exclui o agente do processo seletivo por não atender aos critérios de habilitação do edital. '
            . 'O agente inabilitado recebe notificação com os fundamentos da decisão e pode interpor Recurso de inabilitação dentro do prazo recursal.',
            'participe-ibram'
        ),
        'Indeferimento' => __(
            'Decisão administrativa que reprova o cadastro, habilitação ou recurso de um agente cultural. '
            . 'Indeferimento de cadastro impede o registro no CNAC até que o agente corrija as pendências e resubmeta. '
            . 'Ver também: Recurso à Presidência.',
            'participe-ibram'
        ),
        'Inscrição' => __(
            'Ato pelo qual um agente habilitado manifesta interesse em concorrer a uma vaga em um edital específico. '
            . 'A inscrição pode exigir documentos adicionais além do cadastro base. '
            . 'Uma inscrição válida habilita o agente a participar da votação (quando aplicável).',
            'participe-ibram'
        ),
        'IP hash' => __(
            'Forma de pseudonimização do endereço IP do usuário usada pelo AccessTracker para registrar acessos sem armazenar o IP real em texto claro. '
            . 'O hash é gerado com HMAC-SHA256 e o segredo PI_IP_PEPPER, tornando a reversão impraticável sem o segredo. '
            . 'Garante rastreabilidade sem armazenar dados pessoais desnecessários.',
            'participe-ibram'
        ),
    ],
    'L' => [
        'LGPD' => __(
            'Lei Geral de Proteção de Dados Pessoais (Lei n.º 13.709/2018) — legislação brasileira que regula o tratamento de dados pessoais por pessoas físicas e jurídicas. '
            . 'O Participe Ibram foi desenvolvido em conformidade com a LGPD, implementando consentimento granular, audit log, direitos dos titulares e criptografia de dados sensíveis.',
            'participe-ibram'
        ),
    ],
    'O' => [
        'OIDC' => __(
            'OpenID Connect — protocolo de autenticação baseado em OAuth 2.0 usado pelo gov.br para delegar a verificação de identidade. '
            . 'O Participe Ibram usa OIDC para autenticar agentes pelo portal gov.br sem precisar armazenar senhas. '
            . 'O Selo de confiabilidade é transmitido como claim no token OIDC.',
            'participe-ibram'
        ),
        'Organização (OR)' => __(
            'Tipologia de agente cultural que representa uma pessoa jurídica (associação, fundação, empresa, coletivo formalizado). '
            . 'O cadastro de Organizações exige CNPJ válido e documentos societários. '
            . 'Ver também: Agente, Tipologia.',
            'participe-ibram'
        ),
    ],
    'P' => [
        'Pessoa Física (PF)' => __(
            'Tipologia de agente cultural que representa um indivíduo (artista, gestor, pesquisador). '
            . 'O cadastro de Pessoas Físicas exige CPF válido e pode exigir autenticação gov.br com Selo Prata ou Ouro. '
            . 'Ver também: Agente, Tipologia.',
            'participe-ibram'
        ),
        'PiiMasker' => __(
            'Componente do Participe Ibram que mascara Informações de Identificação Pessoal (PII) nos registros de log. '
            . 'CPFs, e-mails e números de documentos são substituídos por versões truncadas antes de serem gravados no audit log. '
            . 'Garante que logs sejam auditáveis sem expor dados sensíveis.',
            'participe-ibram'
        ),
        'Prazo recursal contínuo (10 dias)' => __(
            'Período de 10 dias corridos a partir da publicação do resultado de habilitação ou análise durante o qual o agente pode interpor recurso. '
            . 'O prazo é calculado automaticamente pelo sistema e monitored pelo cron pi_dpo_alerts_check. '
            . 'Após o prazo, recursos não são aceitos pela plataforma.',
            'participe-ibram'
        ),
        'Pseudonimização' => __(
            'Técnica de proteção de dados que substitui identificadores diretos por pseudônimos (ex.: hash), mantendo a possibilidade de re-identificação mediante uso de chave adicional. '
            . 'Diferentemente da anonimização, dados pseudonimizados ainda se enquadram na LGPD. '
            . 'O Participe Ibram usa pseudonimização para Eleitor_hash e IP hash.',
            'participe-ibram'
        ),
    ],
    'R' => [
        'Recurso à Presidência' => __(
            'Instância recursal final dentro do processo do Ibram, dirigida ao Presidente do Instituto, para casos de indeferimento de cadastro ou inabilitação em edital. '
            . 'O prazo recursal é de 10 dias corridos a partir da ciência da decisão. '
            . 'A decisão da Presidência é definitiva no âmbito administrativo do Ibram.',
            'participe-ibram'
        ),
        'Recurso de inabilitação' => __(
            'Impugnação administrativa que o agente inabilitado pode apresentar contestando os fundamentos da decisão de inabilitação. '
            . 'Deve ser interposto dentro do prazo recursal contínuo de 10 dias. '
            . 'O recurso é analisado pela equipe técnica e sua decisão registrada no audit log.',
            'participe-ibram'
        ),
        'Resultado' => __(
            'Documento oficial publicado após a apuração listando candidatos Eleitos e Suplentes eleitos por categoria. '
            . 'O resultado inclui o hash pré-apuração para auditoria pública. '
            . 'É publicado no edital e notificado a todos os candidatos.',
            'participe-ibram'
        ),
        'Retratação' => __(
            'Ato pelo qual o agente cultural solicita a revisão de sua própria submissão ou declaração antes do prazo de análise. '
            . 'Distingue-se do recurso, que contesta uma decisão da administração. '
            . 'A retratação está sujeita às regras e prazos definidos no edital.',
            'participe-ibram'
        ),
        'RIPD' => __(
            'Relatório de Impacto à Proteção de Dados Pessoais — documento exigido pela LGPD (Art. 38) quando o tratamento de dados pode gerar risco elevado aos titulares. '
            . 'O Ibram deve manter o RIPD atualizado para as operações de tratamento do Participe Ibram. '
            . 'O DPO é o responsável pela elaboração e revisão periódica do RIPD.',
            'participe-ibram'
        ),
        'Revogação' => __(
            'Exercício do direito do titular de retirar o consentimento previamente concedido para uma ou mais finalidades de tratamento de dados (Art. 18, IX da LGPD). '
            . 'O Participe Ibram permite revogação granular: o agente pode revogar finalidades específicas sem cancelar todo o cadastro. '
            . 'A revogação é registrada no audit log com data e hora.',
            'participe-ibram'
        ),
    ],
    'S' => [
        'Secretaria de Cultura' => __(
            'Órgão da administração pública estadual ou municipal responsável pela política cultural regional. '
            . 'No contexto do Participe Ibram, Secretarias de Cultura podem ser responsáveis por Sistemas de Museu cadastrados no CNAC. '
            . 'Ver também: Sistema de Museu (SM).',
            'participe-ibram'
        ),
        'Selo de confiabilidade (Bronze/Prata/Ouro)' => __(
            'Nível de verificação de identidade no portal gov.br: Bronze (cadastro básico), Prata (validação por banco ou biometria), Ouro (validação presencial ou biometria avançada). '
            . 'O Participe Ibram pode exigir Prata ou Ouro para cadastro de Pessoas Físicas conforme a categoria do edital. '
            . 'O nível é transmitido como claim OIDC.',
            'participe-ibram'
        ),
        'Sistema de Museu (SM)' => __(
            'Tipologia de agente cultural que representa uma rede ou sistema municipal/estadual de museus. '
            . 'O cadastro de Sistemas de Museu exige documentação específica da instância governamental responsável. '
            . 'Ver também: Agente, Tipologia.',
            'participe-ibram'
        ),
        'Submissão' => __(
            'Ato de enviar formalmente os dados e documentos de cadastro ou inscrição ao Ibram para análise. '
            . 'Após a submissão, o agente não pode mais editar os dados até que a análise seja concluída. '
            . 'A submissão gera um registro no audit log e envia notificação de recebimento ao agente.',
            'participe-ibram'
        ),
        'Suplente' => __(
            'Posição em uma categoria de edital destinada ao candidato que obteve votação suficiente para substituir um eleito titular em caso de vacância. '
            . 'O número de vagas suplentes é definido no edital. '
            . 'Ver também: Suplente eleito.',
            'participe-ibram'
        ),
        'Suplente eleito' => __(
            'Candidato classificado como suplente após a apuração, com direito a assumir a vaga titular em caso de vacância. '
            . 'A ordem dos suplentes é determinada pela votação obtida dentro da categoria. '
            . 'O suplente eleito é notificado e consta no resultado público do edital.',
            'participe-ibram'
        ),
    ],
    'T' => [
        'Termo versionado' => __(
            'Documento de consentimento ou termos de uso com controle de versão, garantindo que o Ibram possa comprovar qual versão o agente aceitou e quando. '
            . 'Cada nova versão do termo exige novo aceite explícito do agente. '
            . 'O histórico de aceites é mantido no banco de dados e auditável.',
            'participe-ibram'
        ),
        'Tie-break' => __(
            'Critério de desempate aplicado quando dois ou mais candidatos obtêm o mesmo número de votos em uma categoria. '
            . 'Os critérios de tie-break são definidos no edital e aplicados automaticamente pelo sistema durante a apuração. '
            . 'O tie-break é registrado no resultado e auditável.',
            'participe-ibram'
        ),
        'Tipologia' => __(
            'Classificação do agente cultural conforme sua natureza jurídica: Pessoa Física (PF), Organização (OR) ou Sistema de Museu (SM). '
            . 'A tipologia determina quais campos e documentos são obrigatórios no cadastro e quais editais o agente pode acessar.',
            'participe-ibram'
        ),
    ],
    'V' => [
        'Vaga' => __(
            'Posição disponível para preenchimento em uma categoria de edital, podendo ser vaga titular ou vaga suplente. '
            . 'O número de vagas é definido no edital e não pode ser alterado após a publicação. '
            . 'A ocupação de vagas é determinada pelo resultado da votação e apuração.',
            'participe-ibram'
        ),
        'Votação' => __(
            'Processo pelo qual eleitores habilitados escolhem candidatos para ocupar as vagas de uma categoria. '
            . 'O Participe Ibram implementa votação com sigilo garantido via Eleitor_hash e hash pré-apuração. '
            . 'O período de votação é definido no edital e controlado por datas de abertura e encerramento.',
            'participe-ibram'
        ),
    ],
    'W' => [
        'WCAG 2.1 AA' => __(
            'Web Content Accessibility Guidelines versão 2.1, nível AA — padrão internacional de acessibilidade digital publicado pelo W3C. '
            . 'O Participe Ibram adota WCAG 2.1 AA como referência mínima de acessibilidade, em conformidade com o Decreto n.º 5.296/2004 e a eMAG 3.1. '
            . 'Os critérios incluem contraste de cores, navegação por teclado, alternativas textuais e regiões ARIA.',
            'participe-ibram'
        ),
    ],
];

// Coletar letras presentes
$letrasPresentes = array_keys($termos);
sort($letrasPresentes);
?>
<section class="pi-glossario" aria-labelledby="pi-glossario-heading">
  <h2 id="pi-glossario-heading"><?php esc_html_e('Glossário do Participe Ibram', 'participe-ibram'); ?></h2>

  <p class="pi-glossario__desc">
    <?php esc_html_e('Definições dos principais termos e conceitos usados na plataforma Participe Ibram.', 'participe-ibram'); ?>
  </p>

  <?php /* Filtro por inicial — acessível por teclado, oculto na impressão */ ?>
  <nav class="pi-glossario__nav" aria-label="<?php esc_attr_e('Filtrar por letra inicial', 'participe-ibram'); ?>" data-pi-glossario-nav>
    <ul class="pi-glossario__letters" role="list">
      <?php foreach ($letrasPresentes as $letra) : ?>
        <li>
          <a href="#pi-glossario-<?php echo esc_attr(strtolower($letra)); ?>"
             class="pi-glossario__letter-link"
             data-letra="<?php echo esc_attr($letra); ?>">
            <?php echo esc_html($letra); ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php /* Live region para anunciar resultado do filtro */ ?>
  <div id="pi-glossario-live"
       role="status"
       aria-live="polite"
       aria-atomic="true"
       class="screen-reader-text"></div>

  <div class="pi-glossario__columns">
    <?php foreach ($termos as $letraGrupo => $termosDaLetra) : ?>
      <section class="pi-glossario__group"
               id="pi-glossario-<?php echo esc_attr(strtolower((string) $letraGrupo)); ?>"
               aria-labelledby="pi-glossario-letra-<?php echo esc_attr(strtolower((string) $letraGrupo)); ?>">
        <h3 id="pi-glossario-letra-<?php echo esc_attr(strtolower((string) $letraGrupo)); ?>"
            class="pi-glossario__letter-heading">
          <?php echo esc_html((string) $letraGrupo); ?>
        </h3>

        <dl class="pi-glossario__list">
          <?php foreach ($termosDaLetra as $termo => $definicao) : ?>
            <dt id="<?php echo esc_attr($slug($termo)); ?>"
                class="pi-glossario__term">
              <?php echo esc_html($termo); ?>
            </dt>
            <dd class="pi-glossario__def">
              <?php echo esc_html($definicao); ?>
            </dd>
          <?php endforeach; ?>
        </dl>
      </section>
    <?php endforeach; ?>
  </div>
</section>
