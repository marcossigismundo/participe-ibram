<?php
/**
 * Template — Termo de Privacidade (Participe Ibram).
 *
 * Renderiza o termo vigente para visualização pública. As variáveis
 * `{versao}`, `{data}`, `{nome_dpo}` e `{email_dpo}` são substituídas
 * pelos dados do registro ativo em `TermoRepository::findAtivoCorrente()`.
 *
 * Vars esperadas:
 *  - $termo_versao   string  — ex.: "v2026.05.01"
 *  - $termo_data     string  — data formatada
 *  - $termo_corpo    string  — markdown/HTML do corpo (já renderizado)
 *  - $nome_dpo       string
 *  - $email_dpo      string
 *
 * Wave 3 / W3-D — STUB visual; integração com TermoRepository virá em Wave 4.
 *
 * @package Ibram\ParticipeIbram\Templates\Public\LGPD
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $termo_versao */
$termo_versao = $termo_versao ?? 'v2026.05.01';
/** @var string $termo_data */
$termo_data   = $termo_data   ?? '06/05/2026';
/** @var string $nome_dpo */
$nome_dpo     = $nome_dpo     ?? __('A definir', 'participe-ibram');
/** @var string $email_dpo */
$email_dpo    = $email_dpo    ?? 'encarregado@museus.gov.br';
/** @var string $termo_corpo */
$termo_corpo  = $termo_corpo  ?? '';

$allowed_html = [
    'p'      => [],
    'h2'     => ['id' => true],
    'h3'     => ['id' => true],
    'ul'     => [],
    'ol'     => [],
    'li'     => [],
    'a'      => ['href' => true, 'title' => true, 'rel' => true],
    'strong' => [],
    'em'     => [],
    'br'     => [],
    'span'   => ['class' => true],
];
?>
<div class="participe-ibram-scope">
  <a class="pi-skip-link" href="#pi-termo-conteudo">
    <?php esc_html_e('Pular para o conteúdo do termo', 'participe-ibram'); ?>
  </a>

  <article class="pi-card" aria-labelledby="pi-termo-titulo">
    <header class="pi-card__header">
      <h1 id="pi-termo-titulo" class="pi-card__title">
        <?php esc_html_e('Termo de Tratamento de Dados — Participe Ibram', 'participe-ibram'); ?>
      </h1>
      <p class="pi-card__subtitle">
        <?php
        printf(
            /* translators: 1: versão do termo, 2: data de vigência */
            esc_html__('Versão %1$s — Vigente a partir de %2$s', 'participe-ibram'),
            esc_html($termo_versao),
            esc_html($termo_data)
        );
        ?>
      </p>
    </header>

    <div class="pi-card__body" id="pi-termo-conteudo">
      <?php if ($termo_corpo !== '') : ?>
        <?php echo wp_kses($termo_corpo, $allowed_html); ?>
      <?php else : ?>
        <p>
          <strong><?php esc_html_e('Quem trata seus dados:', 'participe-ibram'); ?></strong>
          <?php esc_html_e('Instituto Brasileiro de Museus (Ibram), autarquia federal vinculada ao Ministério da Cultura, CNPJ 10.898.596/0001-93.', 'participe-ibram'); ?>
        </p>

        <p>
          <strong><?php esc_html_e('Encarregada de Dados (DPO):', 'participe-ibram'); ?></strong>
          <?php echo esc_html($nome_dpo); ?> —
          <a href="mailto:<?php echo esc_attr($email_dpo); ?>"><?php echo esc_html($email_dpo); ?></a>
        </p>

        <h2><?php esc_html_e('Por que coletamos seus dados', 'participe-ibram'); ?></h2>
        <p>
          <?php esc_html_e('Para registrar você como Agente de Participação Social, conforme a Portaria IBRAM nº 3230/2024 e o Decreto nº 8.124/2013. Sem esses dados, não podemos te incluir no Cadastro nem te habilitar a concorrer a vagas em conselhos e instâncias do Ibram.', 'participe-ibram'); ?>
        </p>

        <h2><?php esc_html_e('O que coletamos', 'participe-ibram'); ?></h2>
        <ol>
          <li><strong><?php esc_html_e('Identificação (obrigatório):', 'participe-ibram'); ?></strong>
            <?php esc_html_e('nome, CPF ou CNPJ ou passaporte, e-mail, telefone, endereço.', 'participe-ibram'); ?>
          </li>
          <li><strong><?php esc_html_e('Documentos legais (obrigatório):', 'participe-ibram'); ?></strong>
            <?php esc_html_e('comprovantes conforme o tipo de cadastro — CPF/CNPJ, estatutos, atas, ofícios.', 'participe-ibram'); ?>
          </li>
          <li><strong><?php esc_html_e('Perfil (opcional, granular):', 'participe-ibram'); ?></strong>
            <?php esc_html_e('faixa etária, identidade de gênero, orientação sexual, raça/cor, filiação a povo ou comunidade tradicional, deficiência, escolaridade, ocupação. Você escolhe o que informar — todas têm "Prefiro não informar".', 'participe-ibram'); ?>
          </li>
          <li><strong><?php esc_html_e('Manifestações (obrigatório):', 'participe-ibram'); ?></strong>
            <?php esc_html_e('áreas temáticas de interesse e instâncias em que pretende atuar.', 'participe-ibram'); ?>
          </li>
        </ol>

        <h2><?php esc_html_e('O que NÃO fazemos com seus dados', 'participe-ibram'); ?></h2>
        <ul>
          <li><?php esc_html_e('Não vendemos para terceiros.', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Não usamos para publicidade.', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Não compartilhamos fora do Ibram, exceto por determinação judicial ou Lei de Acesso à Informação (Lei 12.527/2011) — e apenas o estritamente necessário.', 'participe-ibram'); ?></li>
        </ul>

        <h2><?php esc_html_e('Quanto tempo guardamos', 'participe-ibram'); ?></h2>
        <ul>
          <li><?php esc_html_e('Cadastro ativo: enquanto você for agente.', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Após revogação total ou exclusão da conta: anonimização em até 30 dias, com retenção de logs de auditoria por 5 anos (obrigação legal).', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Documentos pessoais (CPF, RG, passaporte): guardados criptografados e descartados quando não mais necessários.', 'participe-ibram'); ?></li>
        </ul>

        <h2><?php esc_html_e('Seus direitos (Art. 18 LGPD)', 'participe-ibram'); ?></h2>
        <p>
          <?php esc_html_e('Você pode pedir, a qualquer momento e sem justificar, acesso aos seus dados, correção, exclusão, portabilidade, oposição, anonimização e revisão de decisões automatizadas. Acesse "Minha conta → Privacidade" ou escreva para o DPO. Resposta em até 15 dias úteis.', 'participe-ibram'); ?>
        </p>

        <h2><?php esc_html_e('Como protegemos', 'participe-ibram'); ?></h2>
        <ul>
          <li><?php esc_html_e('HTTPS sempre, criptografia em repouso (libsodium) para CPF, RG e passaporte.', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Acesso administrativo restrito por perfil; cada acesso a dado sensível é registrado.', 'participe-ibram'); ?></li>
          <li><?php esc_html_e('Backups criptografados, retenção controlada.', 'participe-ibram'); ?></li>
        </ul>

        <p>
          <strong><?php esc_html_e('Reclamações:', 'participe-ibram'); ?></strong>
          <?php esc_html_e('Autoridade Nacional de Proteção de Dados (ANPD) —', 'participe-ibram'); ?>
          <a href="https://www.gov.br/anpd" target="_blank" rel="noopener noreferrer">anpd.gov.br</a>.
        </p>
      <?php endif; ?>
    </div>
  </article>
</div>
