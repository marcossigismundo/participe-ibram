<?php
/**
 * Template — DPIA / RIPD Resumo (STUB).
 *
 * Wave 3 / W3-D — STUB com aviso. O documento DPIA completo é mantido
 * externamente (entregue ao Ibram conforme R2-lgpd.md §7 e LGPD.md §9).
 * Este template serve apenas para o usuário descobrir onde solicitar
 * acesso ao documento.
 *
 * @package Ibram\ParticipeIbram\Templates\Public\LGPD
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $dpo_email */
$dpo_email = $dpo_email ?? 'encarregado@museus.gov.br';
?>
<div class="participe-ibram-scope">
  <article class="pi-card" aria-labelledby="pi-dpia-titulo">
    <header class="pi-card__header">
      <h1 id="pi-dpia-titulo" class="pi-card__title">
        <?php esc_html_e('Relatório de Impacto à Proteção de Dados (DPIA / RIPD)', 'participe-ibram'); ?>
      </h1>
      <p class="pi-card__subtitle">
        <?php esc_html_e('Resumo público — documento detalhado disponível sob solicitação', 'participe-ibram'); ?>
      </p>
    </header>

    <div class="pi-card__body">
      <div class="pi-alert pi-alert--warning" role="alert">
        <span class="pi-alert__icon" aria-hidden="true"></span>
        <div class="pi-alert__content">
          <h2 class="pi-alert__title">
            <?php esc_html_e('Em desenvolvimento', 'participe-ibram'); ?>
          </h2>
          <p class="pi-alert__body">
            <?php esc_html_e('O DPIA / RIPD é documentado externamente, conforme a Lei 13.709/2018 (LGPD) Art. 38. Este resumo público está em construção.', 'participe-ibram'); ?>
          </p>
        </div>
      </div>

      <h2><?php esc_html_e('O que é o DPIA', 'participe-ibram'); ?></h2>
      <p>
        <?php esc_html_e('O Relatório de Impacto à Proteção de Dados Pessoais (RIPD) é um documento exigido pela LGPD para tratamentos que envolvem dados sensíveis em larga escala — caso do Cadastro de Agentes da plataforma Participe Ibram.', 'participe-ibram'); ?>
      </p>

      <h2><?php esc_html_e('O que ele contém', 'participe-ibram'); ?></h2>
      <ul>
        <li><?php esc_html_e('Mapeamento dos fluxos de dados.', 'participe-ibram'); ?></li>
        <li><?php esc_html_e('Avaliação de necessidade e proporcionalidade.', 'participe-ibram'); ?></li>
        <li><?php esc_html_e('Riscos identificados (vazamento, acesso indevido, perda).', 'participe-ibram'); ?></li>
        <li><?php esc_html_e('Medidas mitigadoras (criptografia, RBAC, audit log, retenção).', 'participe-ibram'); ?></li>
        <li><?php esc_html_e('Plano de resposta a incidentes.', 'participe-ibram'); ?></li>
        <li><?php esc_html_e('Avaliação de risco residual.', 'participe-ibram'); ?></li>
      </ul>

      <h2><?php esc_html_e('Como solicitar acesso', 'participe-ibram'); ?></h2>
      <p>
        <?php esc_html_e('Solicite cópia do documento ao Encarregado de Dados (DPO):', 'participe-ibram'); ?>
        <a href="mailto:<?php echo esc_attr($dpo_email); ?>"><?php echo esc_html($dpo_email); ?></a>
      </p>
    </div>
  </article>
</div>
