<?php
/**
 * Template — Ajuda / Onboarding (Participe Ibram, Wave 7).
 *
 * ARIA tabs: W3C APG "Tabs with Manual Activation" pattern.
 *  - role="tablist", role="tab", role="tabpanel"
 *  - aria-selected, aria-controls, tabindex
 *  - Keyboard: Left/Right/Home/End to navigate tabs
 *
 * WCAG 2.1 AA:
 *  - 1.1.1  — SVG <title> for inline diagrams
 *  - 2.1.1  — all tabs keyboard operable
 *  - 2.4.1  — skip link
 *  - 2.4.6  — descriptive headings per tab
 *  - 3.1.1  — lang declared by WordPress
 *
 * No external images or internet links; all content inline.
 *
 * @package Ibram\ParticipeIbram\Templates\Admin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$glossario_tpl = __DIR__ . '/glossario.php';
?>
<div class="participe-ibram-scope wrap pi-ajuda">
  <a class="pi-skip-link" href="#pi-ajuda-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

  <header class="pi-ajuda__header" role="banner">
    <h1 class="pi-ajuda__title"><?php esc_html_e('Ajuda — Participe Ibram', 'participe-ibram'); ?></h1>
    <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Localização atual', 'participe-ibram'); ?>">
      <ol class="pi-breadcrumb__list">
        <li class="pi-breadcrumb__item">
          <a href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('WordPress', 'participe-ibram'); ?></a>
        </li>
        <li class="pi-breadcrumb__item">
          <a href="<?php echo esc_url(admin_url('admin.php?page=participe-ibram')); ?>">
            <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
          </a>
        </li>
        <li class="pi-breadcrumb__item" aria-current="page">
          <?php esc_html_e('Ajuda', 'participe-ibram'); ?>
        </li>
      </ol>
    </nav>
  </header>

  <main id="pi-ajuda-main" tabindex="-1">

    <?php /* ── Tab list ────────────────────────────────────────────── */ ?>
    <div class="pi-tabs" data-pi-tabs>
      <div role="tablist"
           aria-label="<?php esc_attr_e('Seções de ajuda', 'participe-ibram'); ?>"
           class="pi-tabs__list">

        <button role="tab"
                id="pi-tab-visaogeral"
                aria-selected="true"
                aria-controls="pi-panel-visaogeral"
                tabindex="0"
                class="pi-tabs__tab pi-tabs__tab--active">
          <?php esc_html_e('Visão geral', 'participe-ibram'); ?>
        </button>

        <button role="tab"
                id="pi-tab-fluxo-cadastro"
                aria-selected="false"
                aria-controls="pi-panel-fluxo-cadastro"
                tabindex="-1"
                class="pi-tabs__tab">
          <?php esc_html_e('Fluxo de cadastro', 'participe-ibram'); ?>
        </button>

        <button role="tab"
                id="pi-tab-fluxo-edital"
                aria-selected="false"
                aria-controls="pi-panel-fluxo-edital"
                tabindex="-1"
                class="pi-tabs__tab">
          <?php esc_html_e('Fluxo de edital', 'participe-ibram'); ?>
        </button>

        <button role="tab"
                id="pi-tab-fluxo-recursal"
                aria-selected="false"
                aria-controls="pi-panel-fluxo-recursal"
                tabindex="-1"
                class="pi-tabs__tab">
          <?php esc_html_e('Fluxo recursal', 'participe-ibram'); ?>
        </button>

        <button role="tab"
                id="pi-tab-glossario"
                aria-selected="false"
                aria-controls="pi-panel-glossario"
                tabindex="-1"
                class="pi-tabs__tab">
          <?php esc_html_e('Glossário', 'participe-ibram'); ?>
        </button>

        <button role="tab"
                id="pi-tab-faq"
                aria-selected="false"
                aria-controls="pi-panel-faq"
                tabindex="-1"
                class="pi-tabs__tab">
          <?php esc_html_e('FAQ', 'participe-ibram'); ?>
        </button>

        <button role="tab"
                id="pi-tab-contatos"
                aria-selected="false"
                aria-controls="pi-panel-contatos"
                tabindex="-1"
                class="pi-tabs__tab">
          <?php esc_html_e('Contatos', 'participe-ibram'); ?>
        </button>

      </div><!-- [role=tablist] -->

      <?php /* ── Tab 1: Visão geral ───────────────────────────────── */ ?>
      <section id="pi-panel-visaogeral"
               role="tabpanel"
               aria-labelledby="pi-tab-visaogeral"
               tabindex="0"
               class="pi-tabs__panel">
        <h2><?php esc_html_e('Visão geral', 'participe-ibram'); ?></h2>
        <p>
          <?php esc_html_e('O Participe Ibram é a plataforma de participação cultural do Instituto Brasileiro de Museus (Ibram). Permite que agentes culturais (pessoas físicas, organizações e sistemas municipais) se cadastrem, participem de editais e tenham seus dados tratados conforme a Lei Geral de Proteção de Dados (LGPD) e a Portaria Ibram n.º 3.230/2024.', 'participe-ibram'); ?>
        </p>
        <h3><?php esc_html_e('Bases normativas', 'participe-ibram'); ?></h3>
        <ul>
          <li><?php esc_html_e('Portaria Ibram n.º 3.230/2024 — disciplina o Cadastro Nacional de Agentes Culturais (CNAC).', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Despacho n.º 98/2025 — regulamenta editais e processo CCDEM.', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Lei n.º 13.709/2018 (LGPD) — tratamento de dados pessoais.', 'participe-ibram'); ?></li>
        </ul>
        <h3><?php esc_html_e('Papel deste plugin', 'participe-ibram'); ?></h3>
        <p>
          <?php esc_html_e('Este plugin WordPress implementa o back-office administrativo da plataforma: gerenciamento de cadastros, análise e deferimento, editais, habilitação, votação, apuração e conformidade LGPD (solicitações de titulares de dados).', 'participe-ibram'); ?>
        </p>
      </section>

      <?php /* ── Tab 2: Fluxo de cadastro ─────────────────────────── */ ?>
      <section id="pi-panel-fluxo-cadastro"
               role="tabpanel"
               aria-labelledby="pi-tab-fluxo-cadastro"
               tabindex="0"
               class="pi-tabs__panel"
               hidden>
        <h2><?php esc_html_e('Fluxo de cadastro do agente', 'participe-ibram'); ?></h2>
        <p><?php esc_html_e('O agente cultural passa pelas seguintes etapas para obter o registro no CNAC:', 'participe-ibram'); ?></p>

        <figure role="img" aria-labelledby="fluxo-cadastro-title fluxo-cadastro-desc">
          <svg viewBox="0 0 640 100" xmlns="http://www.w3.org/2000/svg"
               class="pi-flow-svg" focusable="false" aria-hidden="true">
            <title id="fluxo-cadastro-title"><?php esc_html_e('Fluxo de cadastro', 'participe-ibram'); ?></title>
            <desc id="fluxo-cadastro-desc"><?php esc_html_e('Diagrama: Rascunho → Submissão → Análise → Deferimento ou Indeferimento → Recurso (opcional)', 'participe-ibram'); ?></desc>
            <!-- Steps -->
            <?php
            $steps = [
                [60,  '#1351B4', 'Rascunho'],
                [170, '#2C7BE5', 'Submissão'],
                [280, '#F4C430', 'Análise'],
                [390, '#168821', 'Deferimento'],
                [500, '#E52207', 'Indeferimento'],
                [610, '#E06C00', 'Recurso (opt.)'],
            ];
            foreach ($steps as [$cx, $col, $lbl]):
            ?>
            <circle cx="<?php echo esc_attr((string) $cx); ?>" cy="40" r="22" fill="<?php echo esc_attr($col); ?>"/>
            <text x="<?php echo esc_attr((string) $cx); ?>" y="44" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold"><?php echo esc_html($lbl); ?></text>
            <?php if ($cx < 610): ?>
            <line x1="<?php echo esc_attr((string)($cx + 22)); ?>" y1="40" x2="<?php echo esc_attr((string)($cx + 88)); ?>" y2="40" stroke="#888" stroke-width="2" marker-end="url(#arr)"/>
            <?php endif; ?>
            <?php endforeach; ?>
            <defs>
              <marker id="arr" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">
                <path d="M0,0 L6,3 L0,6 Z" fill="#888"/>
              </marker>
            </defs>
          </svg>
          <figcaption class="pi-sr-only">
            <?php esc_html_e('Fluxo completo: Rascunho → Submissão → Análise → Deferimento (registro publicado) ou Indeferimento → Recurso opcional.', 'participe-ibram'); ?>
          </figcaption>
        </figure>

        <ol class="pi-flow-steps">
          <li><strong><?php esc_html_e('Rascunho:', 'participe-ibram'); ?></strong> <?php esc_html_e('Agente inicia o preenchimento e pode salvar parcialmente.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Submissão:', 'participe-ibram'); ?></strong> <?php esc_html_e('Agente declara conformidade e envia para análise. Dados são bloqueados para edição.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Análise:', 'participe-ibram'); ?></strong> <?php esc_html_e('Técnico do CGSIM analisa documentação e informações. Status: em_analise.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Deferimento:', 'participe-ibram'); ?></strong> <?php esc_html_e('Cadastro aprovado; número de registro atribuído e publicado no CNAC.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Indeferimento:', 'participe-ibram'); ?></strong> <?php esc_html_e('Cadastro reprovado com parecer fundamentado. Agente pode interpor recurso em 10 dias contínuos.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Recurso (opcional):', 'participe-ibram'); ?></strong> <?php esc_html_e('Segue fluxo recursal (ver aba Fluxo recursal).', 'participe-ibram'); ?></li>
        </ol>
      </section>

      <?php /* ── Tab 3: Fluxo de edital (CCDEM) ─────────────────── */ ?>
      <section id="pi-panel-fluxo-edital"
               role="tabpanel"
               aria-labelledby="pi-tab-fluxo-edital"
               tabindex="0"
               class="pi-tabs__panel"
               hidden>
        <h2><?php esc_html_e('Fluxo de edital CCDEM (Despacho 98/2025)', 'participe-ibram'); ?></h2>
        <p><?php esc_html_e('O processo de edital para composição do CCDEM segue o rito estabelecido pelo Despacho n.º 98/2025:', 'participe-ibram'); ?></p>

        <figure role="img" aria-labelledby="fluxo-edital-title fluxo-edital-desc">
          <svg viewBox="0 0 760 100" xmlns="http://www.w3.org/2000/svg"
               class="pi-flow-svg" focusable="false" aria-hidden="true">
            <title id="fluxo-edital-title"><?php esc_html_e('Fluxo de edital CCDEM', 'participe-ibram'); ?></title>
            <desc id="fluxo-edital-desc"><?php esc_html_e('Diagrama: Divulgação → Lançamento → Manifestação → Habilitação → Recurso → Votação → Resultado', 'participe-ibram'); ?></desc>
            <?php
            $editalSteps = [
                [50,  '#1351B4', 'Divulgação'],
                [160, '#2C7BE5', 'Lançamento'],
                [270, '#57A0E8', 'Manifestação'],
                [380, '#F4C430', 'Habilitação'],
                [490, '#E06C00', 'Recurso'],
                [600, '#168821', 'Votação'],
                [710, '#0B4F0B', 'Resultado'],
            ];
            foreach ($editalSteps as [$cx, $col, $lbl]):
            ?>
            <circle cx="<?php echo esc_attr((string)$cx); ?>" cy="40" r="22" fill="<?php echo esc_attr($col); ?>"/>
            <text x="<?php echo esc_attr((string)$cx); ?>" y="44" text-anchor="middle" fill="#fff" font-size="8" font-weight="bold"><?php echo esc_html($lbl); ?></text>
            <?php if ($cx < 710): ?>
            <line x1="<?php echo esc_attr((string)($cx+22)); ?>" y1="40" x2="<?php echo esc_attr((string)($cx+88)); ?>" y2="40" stroke="#888" stroke-width="2" marker-end="url(#arr2)"/>
            <?php endif; ?>
            <?php endforeach; ?>
            <defs>
              <marker id="arr2" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">
                <path d="M0,0 L6,3 L0,6 Z" fill="#888"/>
              </marker>
            </defs>
          </svg>
          <figcaption class="pi-sr-only">
            <?php esc_html_e('Etapas: Divulgação → Lançamento → Manifestação de interesse → Habilitação de candidatos → Recurso de inabilitação → Votação → Resultado e publicação.', 'participe-ibram'); ?>
          </figcaption>
        </figure>

        <ol class="pi-flow-steps">
          <li><strong><?php esc_html_e('Divulgação:', 'participe-ibram'); ?></strong> <?php esc_html_e('Edital publicado no Diário Oficial e na plataforma.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Lançamento:', 'participe-ibram'); ?></strong> <?php esc_html_e('Edital aberto para inscrições; prazo e categorias definidos.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Manifestação:', 'participe-ibram'); ?></strong> <?php esc_html_e('Agentes habilitados manifestam interesse em concorrer.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Habilitação:', 'participe-ibram'); ?></strong> <?php esc_html_e('Comissão verifica regularidade dos inscritos; publica lista de habilitados e inabilitados.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Recurso:', 'participe-ibram'); ?></strong> <?php esc_html_e('Prazo de 5 dias úteis para recurso de inabilitação.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Votação:', 'participe-ibram'); ?></strong> <?php esc_html_e('Agentes do CNAC votam pelos candidatos habilitados. Voto secreto via token HMAC.', 'participe-ibram'); ?></li>
          <li><strong><?php esc_html_e('Resultado:', 'participe-ibram'); ?></strong> <?php esc_html_e('Apuração automática; publicação dos eleitos; prazo para recurso de resultado.', 'participe-ibram'); ?></li>
        </ol>
      </section>

      <?php /* ── Tab 4: Fluxo recursal ────────────────────────────── */ ?>
      <section id="pi-panel-fluxo-recursal"
               role="tabpanel"
               aria-labelledby="pi-tab-fluxo-recursal"
               tabindex="0"
               class="pi-tabs__panel"
               hidden>
        <h2><?php esc_html_e('Fluxo recursal', 'participe-ibram'); ?></h2>
        <p><?php esc_html_e('O rito de recurso de cadastro segue dois graus, conforme a Portaria 3.230/2024:', 'participe-ibram'); ?></p>

        <figure role="img" aria-labelledby="fluxo-recursal-title fluxo-recursal-desc">
          <svg viewBox="0 0 400 140" xmlns="http://www.w3.org/2000/svg"
               class="pi-flow-svg" focusable="false" aria-hidden="true">
            <title id="fluxo-recursal-title"><?php esc_html_e('Fluxo recursal', 'participe-ibram'); ?></title>
            <desc id="fluxo-recursal-desc"><?php esc_html_e('Diagrama: Indeferimento → Retratação (autoridade original, 10 dias) → Se mantido: Presidência (10 dias) → Decisão final', 'participe-ibram'); ?></desc>

            <rect x="10"  y="20" width="100" height="40" rx="4" fill="#E52207"/>
            <text x="60"  y="38" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">Indeferimento</text>
            <text x="60"  y="50" text-anchor="middle" fill="#fff" font-size="8">publicado</text>

            <line x1="110" y1="40" x2="148" y2="40" stroke="#888" stroke-width="2" marker-end="url(#arr3)"/>
            <text x="130" y="35" text-anchor="middle" font-size="8" fill="#555">10 dias</text>

            <rect x="150" y="20" width="100" height="40" rx="4" fill="#E06C00"/>
            <text x="200" y="38" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">1.º Grau</text>
            <text x="200" y="50" text-anchor="middle" fill="#fff" font-size="8">Retratação</text>

            <line x1="250" y1="40" x2="288" y2="40" stroke="#888" stroke-width="2" marker-end="url(#arr3)"/>
            <text x="270" y="35" text-anchor="middle" font-size="8" fill="#555">mantido</text>

            <rect x="290" y="20" width="100" height="40" rx="4" fill="#1351B4"/>
            <text x="340" y="38" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">2.º Grau</text>
            <text x="340" y="50" text-anchor="middle" fill="#fff" font-size="8">Presidência</text>

            <text x="340" y="90" text-anchor="middle" font-size="8" fill="#555"><?php esc_html_e('10 dias contínuos', 'participe-ibram'); ?></text>
            <text x="340" y="102" text-anchor="middle" font-size="8" fill="#555"><?php esc_html_e('Decisão final', 'participe-ibram'); ?></text>

            <defs>
              <marker id="arr3" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">
                <path d="M0,0 L6,3 L0,6 Z" fill="#888"/>
              </marker>
            </defs>
          </svg>
          <figcaption class="pi-sr-only">
            <?php esc_html_e('Dois graus: 1.º Retratação pela autoridade que decidiu (10 dias corridos); 2.º Presidência do Ibram (10 dias corridos). Decisão da Presidência é irrecorrível.', 'participe-ibram'); ?>
          </figcaption>
        </figure>

        <h3><?php esc_html_e('1.º Grau — Retratação', 'participe-ibram'); ?></h3>
        <p><?php esc_html_e('O agente pode interpor recurso no prazo de 10 dias contínuos a partir da notificação do indeferimento. O recurso é apreciado pela mesma autoridade que proferiu a decisão, que pode retratar-se ou mantê-la.', 'participe-ibram'); ?></p>

        <h3><?php esc_html_e('2.º Grau — Presidência', 'participe-ibram'); ?></h3>
        <p><?php esc_html_e('Mantido o indeferimento, o agente tem novo prazo de 10 dias contínuos para recorrer à Presidência do Ibram. A decisão da Presidência é definitiva e irrecorrível na esfera administrativa.', 'participe-ibram'); ?></p>
      </section>

      <?php /* ── Tab 5: Glossário ─────────────────────────────────── */ ?>
      <section id="pi-panel-glossario"
               role="tabpanel"
               aria-labelledby="pi-tab-glossario"
               tabindex="0"
               class="pi-tabs__panel"
               hidden>
        <h2><?php esc_html_e('Glossário', 'participe-ibram'); ?></h2>
        <?php if (file_exists($glossario_tpl)): include $glossario_tpl; endif; ?>
      </section>

      <?php /* ── Tab 6: FAQ ───────────────────────────────────────── */ ?>
      <section id="pi-panel-faq"
               role="tabpanel"
               aria-labelledby="pi-tab-faq"
               tabindex="0"
               class="pi-tabs__panel"
               hidden>
        <h2><?php esc_html_e('Perguntas frequentes', 'participe-ibram'); ?></h2>

        <div class="pi-faq">
          <details class="pi-faq__item">
            <summary><?php esc_html_e('Quem pode se cadastrar no CNAC?', 'participe-ibram'); ?></summary>
            <p><?php esc_html_e('Qualquer agente cultural (pessoa física, organização cultural ou sistema municipal de cultura) regularmente situado no território nacional pode solicitar inscrição, mediante comprovação dos requisitos da Portaria 3.230/2024.', 'participe-ibram'); ?></p>
          </details>
          <details class="pi-faq__item">
            <summary><?php esc_html_e('Por quanto tempo o cadastro é válido?', 'participe-ibram'); ?></summary>
            <p><?php esc_html_e('O cadastro deferido é válido por 4 anos, podendo ser renovado mediante atualização dos dados e nova análise.', 'participe-ibram'); ?></p>
          </details>
          <details class="pi-faq__item">
            <summary><?php esc_html_e('Como o sistema garante o sigilo do voto nas votações do CCDEM?', 'participe-ibram'); ?></summary>
            <p><?php esc_html_e('O voto é registrado com hash HMAC do identificador do eleitor e da rodada da votação. Não é possível associar o voto ao eleitor, mas é possível verificar que o eleitor participou sem revelar sua escolha.', 'participe-ibram'); ?></p>
          </details>
          <details class="pi-faq__item">
            <summary><?php esc_html_e('O que acontece com meus dados pessoais?', 'participe-ibram'); ?></summary>
            <p><?php esc_html_e('Seus dados são tratados conforme a LGPD e a política de privacidade do Ibram. Você pode exercer seus direitos de acesso, correção, portabilidade e eliminação por meio do canal do DPO indicado na aba Contatos.', 'participe-ibram'); ?></p>
          </details>
          <details class="pi-faq__item">
            <summary><?php esc_html_e('O que é a Tipologia de agente cultural?', 'participe-ibram'); ?></summary>
            <p><?php esc_html_e('A tipologia classifica o agente em: Pessoa Física (PF), Organização Cultural (OR) ou Sistema Municipal de Cultura (SM). Cada tipologia possui requisitos e documentação específicos.', 'participe-ibram'); ?></p>
          </details>
          <details class="pi-faq__item">
            <summary><?php esc_html_e('Como solicitar a exclusão dos meus dados?', 'participe-ibram'); ?></summary>
            <p><?php esc_html_e('Envie solicitação ao DPO do Ibram com identificação do titular. O prazo de atendimento é de 15 dias úteis. Dados com prazo de guarda legal (auditoria) podem ser anonimizados em vez de excluídos.', 'participe-ibram'); ?></p>
          </details>
          <details class="pi-faq__item">
            <summary><?php esc_html_e('Posso editar meu cadastro após submissão?', 'participe-ibram'); ?></summary>
            <p><?php esc_html_e('Não. Após a submissão os dados ficam bloqueados para garantir a integridade do processo analítico. Em caso de necessidade de correção, entre em contato com o CGSIM antes da conclusão da análise.', 'participe-ibram'); ?></p>
          </details>
        </div>
      </section>

      <?php /* ── Tab 7: Contatos ─────────────────────────────────── */ ?>
      <section id="pi-panel-contatos"
               role="tabpanel"
               aria-labelledby="pi-tab-contatos"
               tabindex="0"
               class="pi-tabs__panel"
               hidden>
        <h2><?php esc_html_e('Contatos', 'participe-ibram'); ?></h2>

        <div class="pi-contacts">
          <article class="pi-contact-card">
            <h3><?php esc_html_e('Encarregado de Dados (DPO)', 'participe-ibram'); ?></h3>
            <p><?php esc_html_e('Responsável pelo atendimento de solicitações de titulares de dados (LGPD arts. 41-43).', 'participe-ibram'); ?></p>
            <p>
              <strong><?php esc_html_e('Instituição:', 'participe-ibram'); ?></strong>
              <?php esc_html_e('Instituto Brasileiro de Museus — Ibram', 'participe-ibram'); ?>
            </p>
            <p>
              <strong><?php esc_html_e('Canal:', 'participe-ibram'); ?></strong>
              <?php esc_html_e('dpo@museus.gov.br (canal oficial — verifique o domínio)', 'participe-ibram'); ?>
            </p>
          </article>

          <article class="pi-contact-card">
            <h3><?php esc_html_e('CGSIM — Coordenação-Geral de Sistemas de Informação e Museus', 'participe-ibram'); ?></h3>
            <p><?php esc_html_e('Responsável técnico pelo Cadastro Nacional de Agentes Culturais (CNAC) e pela plataforma Participe Ibram.', 'participe-ibram'); ?></p>
            <p>
              <strong><?php esc_html_e('Canal:', 'participe-ibram'); ?></strong>
              <?php esc_html_e('cgsim@museus.gov.br (suporte técnico e operacional)', 'participe-ibram'); ?>
            </p>
          </article>

          <article class="pi-contact-card">
            <h3><?php esc_html_e('Autoridade Nacional de Proteção de Dados (ANPD)', 'participe-ibram'); ?></h3>
            <p><?php esc_html_e('Órgão regulador e fiscalizador da LGPD no Brasil. Caso não obtenha resposta satisfatória do DPO, você pode registrar reclamação junto à ANPD.', 'participe-ibram'); ?></p>
            <p>
              <strong><?php esc_html_e('Sítio:', 'participe-ibram'); ?></strong>
              <?php esc_html_e('www.gov.br/anpd (acesse pelo domínio gov.br)', 'participe-ibram'); ?>
            </p>
          </article>
        </div>
      </section>

    </div><!-- .pi-tabs -->
  </main>
</div><!-- .participe-ibram-scope.pi-ajuda -->

<?php
if (function_exists('wp_enqueue_script')) {
    $assetBase = \defined('PI_PLUGIN_URL') ? (string) \PI_PLUGIN_URL : plugin_dir_url(dirname(__DIR__, 2) . '/crm-developer.php');
    $assetBase = rtrim($assetBase, '/');
    wp_enqueue_script('pi-admin-ajuda', $assetBase . '/assets/dist/js/admin/ajuda.js', [], '7.0.0', true);
    wp_enqueue_style('pi-admin-ajuda', $assetBase . '/assets/dist/css/admin-ajuda.css', [], '7.0.0');
}
?>
