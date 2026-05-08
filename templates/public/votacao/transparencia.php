<?php
/**
 * Template — Página pública de transparência da votação.
 *
 * Vars injetadas pelo shortcode `[pi_votacao_transparencia id]`:
 *  - int    $votacao_id
 *  - string $api_url
 *  - string $rest_nonce
 *
 * Carregamento dos dados é feito do lado do cliente (REST público), o que:
 *  - reaproveita a whitelist defensiva do endpoint;
 *  - tira o template do caminho de PII no servidor;
 *  - permite cache HTTP no lado do navegador/CDN.
 *
 * @package Ibram\ParticipeIbram\Templates\Public\Votacao
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var int    $votacao_id */
/** @var string $api_url */
/** @var string $rest_nonce */

$transpUrl = trailingslashit($api_url) . 'publico/votacao/' . (int) $votacao_id . '/transparencia';
$auditUrl  = trailingslashit($api_url) . 'publico/votacao/' . (int) $votacao_id . '/audit-public';

$cfg = [
    'votacaoId' => (int) $votacao_id,
    'transpUrl' => $transpUrl,
    'auditUrl'  => $auditUrl,
    'i18n'      => [
        'erroCarregar'   => __('Não foi possível carregar dados de transparência.', 'participe-ibram'),
        'hashCopiado'    => __('Hash copiado para a área de transferência.', 'participe-ibram'),
        'baixandoAudit'  => __('Preparando download da auditoria pública…', 'participe-ibram'),
        'auditPronto'    => __('Download de auditoria pronto.', 'participe-ibram'),
    ],
];
?>
<div class="participe-ibram-scope pi-transparencia" data-pi-transparencia>
  <h1 class="pi-transparencia__title"><?php esc_html_e('Auditoria pública desta votação', 'participe-ibram'); ?></h1>

  <p class="pi-transparencia__lead">
    <?php esc_html_e('Esta página é a fonte oficial de dados auditáveis desta votação. Todos os campos exibidos são públicos por desenho — nenhum vincula voto a eleitor.', 'participe-ibram'); ?>
  </p>

  <div id="pi-transparencia-live" role="status" aria-live="polite" class="screen-reader-text"></div>

  <article class="pi-card pi-transparencia__hash" aria-labelledby="pi-transparencia-hash-h">
    <h2 id="pi-transparencia-hash-h"><?php esc_html_e('Hash de pré-apuração', 'participe-ibram'); ?></h2>
    <p><?php esc_html_e('O hash abaixo é o "selo digital" do conjunto de votos no instante do encerramento. Se você baixar a auditoria pública e calcular o sha256, deve obter exatamente este valor.', 'participe-ibram'); ?></p>
    <pre class="pi-hash-block"><code data-pi-field="hash_pre_apuracao" tabindex="0" aria-label="<?php esc_attr_e('Hash de pré-apuração', 'participe-ibram'); ?>">—</code></pre>
    <p>
      <strong><?php esc_html_e('Algoritmo:', 'participe-ibram'); ?></strong>
      <code data-pi-field="algoritmo">sha256</code>
    </p>
    <button type="button" class="pi-button pi-button--secondary" data-pi-copy-hash>
      <?php esc_html_e('Copiar hash', 'participe-ibram'); ?>
    </button>
  </article>

  <article class="pi-card" aria-labelledby="pi-transparencia-meta-h">
    <h2 id="pi-transparencia-meta-h"><?php esc_html_e('Dados da votação', 'participe-ibram'); ?></h2>
    <dl class="pi-transparencia__dl">
      <div><dt><?php esc_html_e('Edital', 'participe-ibram'); ?></dt>
           <dd data-pi-field="edital_titulo">—</dd></div>
      <div><dt><?php esc_html_e('Status', 'participe-ibram'); ?></dt>
           <dd data-pi-field="status">—</dd></div>
      <div><dt><?php esc_html_e('Abertura', 'participe-ibram'); ?></dt>
           <dd data-pi-field="abertura">—</dd></div>
      <div><dt><?php esc_html_e('Encerramento', 'participe-ibram'); ?></dt>
           <dd data-pi-field="encerramento">—</dd></div>
      <div><dt><?php esc_html_e('Total de votos', 'participe-ibram'); ?></dt>
           <dd data-pi-field="total_votos">—</dd></div>
      <div><dt><?php esc_html_e('Apurado em', 'participe-ibram'); ?></dt>
           <dd data-pi-field="apurado_em">—</dd></div>
      <div><dt><?php esc_html_e('Resultado publicado em', 'participe-ibram'); ?></dt>
           <dd data-pi-field="publicado_em">—</dd></div>
    </dl>
  </article>

  <article class="pi-card" aria-labelledby="pi-transparencia-metod-h">
    <h2 id="pi-transparencia-metod-h"><?php esc_html_e('Metodologia', 'participe-ibram'); ?></h2>
    <p><?php esc_html_e('A apuração é executada pelo seguinte algoritmo determinístico, em conformidade com o Despacho 98/2025 IBRAM:', 'participe-ibram'); ?></p>
    <ol>
      <li><?php esc_html_e('Para cada categoria, o sistema conta votos válidos por candidato (a constraint UNIQUE no banco impede dupla votação).', 'participe-ibram'); ?></li>
      <li><?php
        echo wp_kses(
            __('Os candidatos são ordenados por <strong>total_votos DESC, inscrito_em ASC, candidato_inscricao_id ASC</strong>. Esta regra de tie-break é fixa, não-aleatória, documentada e auditável.', 'participe-ibram'),
            ['strong' => []]
        );
      ?></li>
      <li><?php esc_html_e('Os primeiros N são marcados como eleitos (N = número de vagas da categoria). Os próximos M como suplentes.', 'participe-ibram'); ?></li>
      <li><?php esc_html_e('Identidade do eleitor nunca é armazenada junto ao voto: apenas um HMAC-SHA256 com pepper (eleitor_hash). Ninguém — nem administradores — consegue ligar voto a eleitor pela base operacional.', 'participe-ibram'); ?></li>
    </ol>
  </article>

  <article class="pi-card" aria-labelledby="pi-transparencia-verif-h">
    <h2 id="pi-transparencia-verif-h"><?php esc_html_e('Como verificar a integridade', 'participe-ibram'); ?></h2>
    <p><?php esc_html_e('Você pode reproduzir a verificação em sua própria máquina:', 'participe-ibram'); ?></p>
    <ol>
      <li><?php esc_html_e('Baixe o log público de auditoria desta votação (botão abaixo).', 'participe-ibram'); ?></li>
      <li><?php esc_html_e('Para cada linha, monte uma string canônica:', 'participe-ibram'); ?>
        <pre><code>categoria_id|eleitor_hash|candidato_inscricao_id|votado_em</code></pre>
      </li>
      <li><?php esc_html_e('Ordene as linhas alfabeticamente (sort estável).', 'participe-ibram'); ?></li>
      <li><?php esc_html_e('Concatene com \\n e calcule o sha256:', 'participe-ibram'); ?>
        <pre><code>sha256sum votacao_audit.txt</code></pre>
      </li>
      <li><?php esc_html_e('Compare o resultado com o hash de pré-apuração mostrado acima. Devem ser idênticos.', 'participe-ibram'); ?></li>
    </ol>
    <button type="button" class="pi-button pi-button--primary" data-pi-baixar-audit>
      <?php esc_html_e('Baixar log público de auditoria (CSV)', 'participe-ibram'); ?>
    </button>
    <p class="pi-muted">
      <?php esc_html_e('Os campos exportados são: ocorrido_em, categoria_id, eleitor_hash, candidato_inscricao_id, ip_hash. Nenhum dado pessoal é exposto.', 'participe-ibram'); ?>
    </p>
  </article>

  <article class="pi-card pi-transparencia__resultados" aria-labelledby="pi-transparencia-result-h" hidden data-pi-resultados>
    <h2 id="pi-transparencia-result-h"><?php esc_html_e('Resultados publicados', 'participe-ibram'); ?></h2>
    <p class="pi-muted"><?php esc_html_e('Os resultados oficiais — quando publicados — aparecem na página de "Resultados" do edital, ligando o número de registro de cada candidato eleito.', 'participe-ibram'); ?></p>
  </article>

  <script type="application/json" id="pi-transparencia-data"><?php
    echo wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
  ?></script>
</div>
