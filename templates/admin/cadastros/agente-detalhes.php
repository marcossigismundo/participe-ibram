<?php
/**
 * Template — Detalhes do Agente (admin).
 *
 * Vars (injetadas pelo AgenteDetalhesController::render()):
 *  - Agente $agente
 *  - AgentePF|AgenteOR|AgenteSM $detalhes
 *  - list<Representante> $reps
 *  - list<Documento> $documentos
 *  - list<Consentimento> $consentimentos
 *  - list<Analise> $analises
 *  - list<StatusHistorico> $historico
 *  - Recurso|null $recursoRetratacao
 *  - Recurso|null $recursoPresid
 *  - bool $podeRevelar, $podeAssumir, $podeDeferir, $podeIndeferir
 *  - array{assumir:string,iniciar:string,deferir:string,indeferir:string,revelar:string} $nonces
 *  - string $nomeAgente, $tipoLabel, $numeroReg
 *  - array{label:string,variant:string,code:string} $statusBadge
 *  - int $userId
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Cadastros
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Core\Helpers\Json;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Presentation\Admin\Helpers\AgenteSummary;
use Ibram\ParticipeIbram\Presentation\Admin\Helpers\SafeFieldRenderer;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;

/** @var \Ibram\ParticipeIbram\Domain\Agente\Agente $agente */
/** @var AgentePF|AgenteOR|AgenteSM $detalhes */
/** @var list<\Ibram\ParticipeIbram\Domain\Agente\Representante> $reps */
/** @var list<\Ibram\ParticipeIbram\Domain\Documento\Documento> $documentos */
/** @var list<\Ibram\ParticipeIbram\Domain\Consentimento\Consentimento> $consentimentos */
/** @var list<\Ibram\ParticipeIbram\Domain\Analise\Analise> $analises */
/** @var list<\Ibram\ParticipeIbram\Domain\Analise\StatusHistorico> $historico */
/** @var \Ibram\ParticipeIbram\Domain\Analise\Recurso|null $recursoRetratacao */
/** @var \Ibram\ParticipeIbram\Domain\Analise\Recurso|null $recursoPresid */
/** @var bool $podeRevelar */
/** @var bool $podeAssumir */
/** @var bool $podeDeferir */
/** @var bool $podeIndeferir */
/** @var array $nonces */
/** @var string $nomeAgente */
/** @var string $tipoLabel */
/** @var string $numeroReg */
/** @var array $statusBadge */
/** @var int $userId */

$tipo = $agente->getTipo()->value();
$pageData = [
    'agenteId'  => $agente->getId(),
    'tipo'      => $tipo,
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'nonces'    => $nonces,
    'i18n'      => [
        'confirmarDeferir'   => __('Tem certeza que deseja DEFERIR este cadastro? Esta ação é irreversível.', 'participe-ibram'),
        'confirmarIndeferir' => __('Tem certeza que deseja INDEFERIR este cadastro? Será aberto prazo de recurso de 10 dias.', 'participe-ibram'),
        'confirmarRevelar'   => __('Você está prestes a visualizar dados pessoais sensíveis. Esta ação será registrada na auditoria. Deseja continuar?', 'participe-ibram'),
        'erroGenerico'       => __('Falha ao processar a requisição.', 'participe-ibram'),
        'sucessoDeferir'     => __('Cadastro deferido com sucesso.', 'participe-ibram'),
        'sucessoIndeferir'   => __('Cadastro indeferido com sucesso.', 'participe-ibram'),
        'sucessoAssumir'     => __('Análise assumida com sucesso.', 'participe-ibram'),
        'sucessoIniciar'     => __('Análise iniciada com sucesso.', 'participe-ibram'),
    ],
];
?>
<div class="participe-ibram-scope wrap pi-admin-detalhes" data-pi-detalhes>
  <a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

  <header role="banner" class="pi-admin-detalhes__header">
    <h1>
      <?php echo esc_html($nomeAgente); ?>
      <span class="pi-badge pi-badge--tipo pi-badge--tipo-<?php echo esc_attr(strtolower($tipo)); ?>">
        <?php echo esc_html($tipoLabel); ?>
      </span>
      <span class="pi-badge pi-badge--status pi-badge--status-<?php echo esc_attr($statusBadge['variant']); ?>">
        <?php echo esc_html($statusBadge['label']); ?>
      </span>
    </h1>
    <p class="pi-admin-detalhes__numero">
      <strong><?php esc_html_e('Nº Registro:', 'participe-ibram'); ?></strong>
      <?php echo esc_html($numeroReg); ?>
    </p>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . MenuRegistry::SLUG_ROOT)); ?>">
          <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(MenuRegistry::urlFilaAnalise()); ?>">
          <?php esc_html_e('Cadastros', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php echo esc_html($nomeAgente); ?>
      </li>
    </ol>
  </nav>

  <main id="pi-admin-main" tabindex="-1">

    <?php if ($podeAssumir || $podeDeferir || $podeIndeferir) : ?>
    <div class="pi-admin-detalhes__actions" role="group" aria-label="<?php esc_attr_e('Ações principais', 'participe-ibram'); ?>">
      <?php if ($podeAssumir) : ?>
        <button type="button" class="pi-button pi-button--primary"
                data-pi-action="assumir"
                aria-controls="pi-modal-confirm-assumir">
          <?php esc_html_e('Assumir análise', 'participe-ibram'); ?>
        </button>
      <?php endif; ?>
      <?php if ($podeDeferir) : ?>
        <button type="button" class="pi-button pi-button--success"
                data-pi-action="abrir-deferir"
                aria-controls="pi-modal-deferir">
          <?php esc_html_e('Deferir cadastro', 'participe-ibram'); ?>
        </button>
      <?php endif; ?>
      <?php if ($podeIndeferir) : ?>
        <button type="button" class="pi-button pi-button--danger"
                data-pi-action="abrir-indeferir"
                aria-controls="pi-modal-indeferir">
          <?php esc_html_e('Indeferir cadastro', 'participe-ibram'); ?>
        </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div role="status" aria-live="polite" id="pi-admin-detalhes-live" class="screen-reader-text"></div>

    <div class="pi-tabs" data-pi-tabs>
      <div role="tablist" aria-label="<?php esc_attr_e('Seções do cadastro', 'participe-ibram'); ?>" class="pi-tabs__list">
        <button role="tab" id="pi-tab-identificacao" aria-controls="pi-panel-identificacao" aria-selected="true" tabindex="0" class="pi-tabs__tab">
          <?php esc_html_e('Identificação', 'participe-ibram'); ?>
        </button>
        <button role="tab" id="pi-tab-dados" aria-controls="pi-panel-dados" aria-selected="false" tabindex="-1" class="pi-tabs__tab">
          <?php esc_html_e('Dados', 'participe-ibram'); ?>
        </button>
        <button role="tab" id="pi-tab-documentos" aria-controls="pi-panel-documentos" aria-selected="false" tabindex="-1" class="pi-tabs__tab">
          <?php esc_html_e('Documentos', 'participe-ibram'); ?>
        </button>
        <button role="tab" id="pi-tab-consentimentos" aria-controls="pi-panel-consentimentos" aria-selected="false" tabindex="-1" class="pi-tabs__tab">
          <?php esc_html_e('Consentimentos', 'participe-ibram'); ?>
        </button>
        <button role="tab" id="pi-tab-historico" aria-controls="pi-panel-historico" aria-selected="false" tabindex="-1" class="pi-tabs__tab">
          <?php esc_html_e('Histórico', 'participe-ibram'); ?>
        </button>
        <button role="tab" id="pi-tab-analises" aria-controls="pi-panel-analises" aria-selected="false" tabindex="-1" class="pi-tabs__tab">
          <?php esc_html_e('Análises', 'participe-ibram'); ?>
        </button>
      </div>

      <!-- ===== Identificação ===== -->
      <section role="tabpanel" id="pi-panel-identificacao" aria-labelledby="pi-tab-identificacao" class="pi-tabs__panel">
        <h2 class="screen-reader-text"><?php esc_html_e('Identificação', 'participe-ibram'); ?></h2>
        <dl class="pi-defs">
          <div class="pi-defs__row">
            <dt><?php esc_html_e('Nome', 'participe-ibram'); ?></dt>
            <dd><?php echo esc_html($nomeAgente); ?></dd>
          </div>
          <div class="pi-defs__row">
            <dt><?php esc_html_e('Tipo', 'participe-ibram'); ?></dt>
            <dd><?php echo esc_html($tipoLabel); ?></dd>
          </div>
          <div class="pi-defs__row">
            <dt><?php esc_html_e('E-mail principal', 'participe-ibram'); ?></dt>
            <dd>
              <span data-pi-mask="email" data-pi-mask-original="<?php echo esc_attr(SafeFieldRenderer::email($agente->getEmailPrincipal(), false)); ?>">
                <?php echo esc_html(SafeFieldRenderer::email($agente->getEmailPrincipal(), false)); ?>
              </span>
            </dd>
          </div>
          <div class="pi-defs__row">
            <dt><?php esc_html_e('Telefone', 'participe-ibram'); ?></dt>
            <dd>
              <?php echo esc_html(SafeFieldRenderer::phone($agente->getTelefone(), false)); ?>
            </dd>
          </div>
          <div class="pi-defs__row">
            <dt><?php esc_html_e('Status', 'participe-ibram'); ?></dt>
            <dd><?php echo esc_html($statusBadge['label']); ?></dd>
          </div>
          <div class="pi-defs__row">
            <dt><?php esc_html_e('Submetido em', 'participe-ibram'); ?></dt>
            <dd><?php echo esc_html($agente->getSubmetidoEm() !== null ? $agente->getSubmetidoEm()->format('d/m/Y H:i') : '—'); ?></dd>
          </div>
          <div class="pi-defs__row">
            <dt><?php esc_html_e('Deferido em', 'participe-ibram'); ?></dt>
            <dd><?php echo esc_html($agente->getDeferidoEm() !== null ? $agente->getDeferidoEm()->format('d/m/Y H:i') : '—'); ?></dd>
          </div>
        </dl>
      </section>

      <!-- ===== Dados (tipo-aware) ===== -->
      <section role="tabpanel" id="pi-panel-dados" aria-labelledby="pi-tab-dados" class="pi-tabs__panel" hidden>
        <h2 class="screen-reader-text"><?php esc_html_e('Dados', 'participe-ibram'); ?></h2>

        <?php if ($podeRevelar) : ?>
          <p class="pi-admin-detalhes__reveal-control">
            <button type="button" class="pi-button pi-button--secondary pi-button--sm"
                    data-pi-action="revelar-sensivel"
                    aria-pressed="false">
              <?php esc_html_e('Revelar dados sensíveis', 'participe-ibram'); ?>
            </button>
          </p>
        <?php endif; ?>

        <?php if ($detalhes instanceof AgentePF) : ?>
          <dl class="pi-defs">
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Nome completo', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getNomeCompleto()); ?></dd>
            </div>
            <?php if ($detalhes->getNomeSocial()) : ?>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Nome social', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getNomeSocial()); ?></dd>
            </div>
            <?php endif; ?>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('CPF', 'participe-ibram'); ?></dt>
              <dd>
                <span data-pi-sensitive="cpf">
                  <?php echo esc_html(SafeFieldRenderer::cpf($detalhes->getCpfPlain(), false)); ?>
                </span>
              </dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('RG', 'participe-ibram'); ?></dt>
              <dd>
                <span data-pi-sensitive="rg">
                  <?php echo esc_html(SafeFieldRenderer::identidade($detalhes->getRgPlain(), false)); ?>
                </span>
              </dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Passaporte', 'participe-ibram'); ?></dt>
              <dd>
                <span data-pi-sensitive="passaporte">
                  <?php echo esc_html(SafeFieldRenderer::identidade($detalhes->getPassaportePlain(), false)); ?>
                </span>
              </dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Cidade / UF', 'participe-ibram'); ?></dt>
              <dd>
                <?php echo esc_html(($detalhes->getCidadeResidencia() ?? '—') . ' / ' . ($detalhes->getEstadoResidencia() ?? '—')); ?>
              </dd>
            </div>
            <?php if ($detalhes->getApresentacaoMd()) : ?>
            <div class="pi-defs__row pi-defs__row--block">
              <dt><?php esc_html_e('Apresentação', 'participe-ibram'); ?></dt>
              <dd><?php echo wp_kses_post($detalhes->getApresentacaoMd()); ?></dd>
            </div>
            <?php endif; ?>
          </dl>

        <?php elseif ($detalhes instanceof AgenteOR) : ?>
          <dl class="pi-defs">
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Nome da organização', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getNomeOrganizacao()); ?></dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Tem CNPJ?', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getTemCnpj() === 'sim' ? __('Sim', 'participe-ibram') : __('Não', 'participe-ibram')); ?></dd>
            </div>
            <?php if ($detalhes->getTemCnpj() === 'sim') : ?>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('CNPJ', 'participe-ibram'); ?></dt>
              <dd>
                <span data-pi-sensitive="cnpj">
                  <?php echo esc_html(SafeFieldRenderer::cnpj($detalhes->getCnpjPlain(), false)); ?>
                </span>
              </dd>
            </div>
            <?php endif; ?>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Cidade / UF', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html(($detalhes->getCidadeSede() ?? '—') . ' / ' . ($detalhes->getEstadoSede() ?? '—')); ?></dd>
            </div>
            <?php if ($detalhes->getApresentacaoMd()) : ?>
            <div class="pi-defs__row pi-defs__row--block">
              <dt><?php esc_html_e('Apresentação', 'participe-ibram'); ?></dt>
              <dd><?php echo wp_kses_post($detalhes->getApresentacaoMd()); ?></dd>
            </div>
            <?php endif; ?>
          </dl>

        <?php elseif ($detalhes instanceof AgenteSM) : ?>
          <dl class="pi-defs">
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Nome do órgão', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getNomeOrgao()); ?></dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Esfera', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getEsfera()); ?></dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Tipo', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getTipoOrgao()); ?></dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Município / UF', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html(($detalhes->getMunicipio() ?? '—') . ' / ' . ($detalhes->getUf() ?? '—')); ?></dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('Representante legal', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($detalhes->getRepresentanteLegalNome()); ?></dd>
            </div>
            <div class="pi-defs__row">
              <dt><?php esc_html_e('CPF do representante', 'participe-ibram'); ?></dt>
              <dd>
                <span data-pi-sensitive="representante_cpf">
                  <?php echo esc_html(SafeFieldRenderer::cpf($detalhes->getRepresentanteCpfPlain(), false)); ?>
                </span>
              </dd>
            </div>
          </dl>
        <?php endif; ?>

        <?php if (!empty($reps)) : ?>
        <h3><?php esc_html_e('Representantes', 'participe-ibram'); ?></h3>
        <ul class="pi-list">
          <?php foreach ($reps as $rep) : ?>
            <li>
              <strong><?php echo esc_html($rep->getNome()); ?></strong>
              <?php if ($rep->getPapel()) : ?>
                — <?php echo esc_html($rep->getPapel()); ?>
              <?php endif; ?>
              <?php if ($rep->isPrincipal()) : ?>
                <span class="pi-badge pi-badge--info"><?php esc_html_e('Principal', 'participe-ibram'); ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>

      <!-- ===== Documentos ===== -->
      <section role="tabpanel" id="pi-panel-documentos" aria-labelledby="pi-tab-documentos" class="pi-tabs__panel" hidden>
        <h2 class="screen-reader-text"><?php esc_html_e('Documentos', 'participe-ibram'); ?></h2>
        <?php if (empty($documentos)) : ?>
          <p><?php esc_html_e('Nenhum documento anexado.', 'participe-ibram'); ?></p>
        <?php else : ?>
          <table class="pi-table widefat striped">
            <thead>
              <tr>
                <th scope="col"><?php esc_html_e('Nome original', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Tipo MIME', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Tamanho', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Validado', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Ações', 'participe-ibram'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($documentos as $doc) : ?>
                <tr>
                  <td><?php echo esc_html($doc->nomeOriginal()); ?></td>
                  <td><code><?php echo esc_html($doc->mimeReal()); ?></code></td>
                  <td><?php echo esc_html(size_format((int) $doc->tamanhoBytes())); ?></td>
                  <td>
                    <?php if ($doc->isValidado()) : ?>
                      <span class="pi-badge pi-badge--status-success"><?php esc_html_e('Sim', 'participe-ibram'); ?></span>
                    <?php else : ?>
                      <span class="pi-badge pi-badge--status-warning"><?php esc_html_e('Pendente', 'participe-ibram'); ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a class="pi-button pi-button--sm pi-button--secondary"
                       href="<?php echo esc_url(rest_url('pi/v1/documentos/' . (int) $doc->id() . '/download')); ?>"
                       rel="noopener">
                      <?php esc_html_e('Baixar', 'participe-ibram'); ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <!-- ===== Consentimentos ===== -->
      <section role="tabpanel" id="pi-panel-consentimentos" aria-labelledby="pi-tab-consentimentos" class="pi-tabs__panel" hidden>
        <h2 class="screen-reader-text"><?php esc_html_e('Consentimentos', 'participe-ibram'); ?></h2>
        <?php if (empty($consentimentos)) : ?>
          <p><?php esc_html_e('Nenhum consentimento registrado.', 'participe-ibram'); ?></p>
        <?php else : ?>
          <table class="pi-table widefat striped">
            <thead>
              <tr>
                <th scope="col"><?php esc_html_e('Finalidade', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Status', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Termo', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Registrado em', 'participe-ibram'); ?></th>
                <th scope="col"><?php esc_html_e('Revogado em', 'participe-ibram'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($consentimentos as $c) : ?>
                <tr>
                  <td><?php echo esc_html($c->finalidade()->label()); ?></td>
                  <td>
                    <span class="pi-badge pi-badge--status-<?php echo esc_attr($c->status()->value()); ?>">
                      <?php echo esc_html($c->status()->value()); ?>
                    </span>
                  </td>
                  <td>#<?php echo esc_html((string) $c->termoId()); ?></td>
                  <td><?php echo esc_html($c->registradoEm()->format('d/m/Y H:i')); ?></td>
                  <td><?php echo esc_html($c->revogadoEm() !== null ? $c->revogadoEm()->format('d/m/Y H:i') : '—'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <!-- ===== Histórico ===== -->
      <section role="tabpanel" id="pi-panel-historico" aria-labelledby="pi-tab-historico" class="pi-tabs__panel" hidden>
        <h2 class="screen-reader-text"><?php esc_html_e('Histórico de status', 'participe-ibram'); ?></h2>
        <?php if (empty($historico)) : ?>
          <p><?php esc_html_e('Sem eventos registrados.', 'participe-ibram'); ?></p>
        <?php else : ?>
          <ol class="pi-timeline">
            <?php foreach ($historico as $h) : ?>
              <li class="pi-timeline__item">
                <time datetime="<?php echo esc_attr($h->ocorridoEm()->format('c')); ?>" class="pi-timeline__date">
                  <?php echo esc_html($h->ocorridoEm()->format('d/m/Y H:i')); ?>
                </time>
                <p class="pi-timeline__transition">
                  <code><?php echo esc_html($h->statusAnterior()); ?></code>
                  →
                  <code><?php echo esc_html($h->statusNovo()); ?></code>
                </p>
                <?php if ($h->observacao()) : ?>
                  <p class="pi-timeline__obs"><?php echo esc_html($h->observacao()); ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </section>

      <!-- ===== Análises e Recursos ===== -->
      <section role="tabpanel" id="pi-panel-analises" aria-labelledby="pi-tab-analises" class="pi-tabs__panel" hidden>
        <h2><?php esc_html_e('Análises', 'participe-ibram'); ?></h2>
        <?php if (empty($analises)) : ?>
          <p><?php esc_html_e('Nenhuma análise registrada.', 'participe-ibram'); ?></p>
        <?php else : foreach ($analises as $a) : ?>
          <article class="pi-card">
            <header class="pi-card__header">
              <h3 class="pi-card__title">
                <?php echo esc_html(ucfirst($a->decisao())); ?>
                — <?php echo esc_html($a->decididoEm()->format('d/m/Y H:i')); ?>
              </h3>
            </header>
            <div class="pi-card__body">
              <h4><?php esc_html_e('Parecer', 'participe-ibram'); ?></h4>
              <div class="pi-md-content"><?php echo wp_kses_post($a->parecerMd()); ?></div>
              <?php if ($a->fundamentacaoMd()) : ?>
                <h4><?php esc_html_e('Fundamentação', 'participe-ibram'); ?></h4>
                <div class="pi-md-content"><?php echo wp_kses_post($a->fundamentacaoMd()); ?></div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; endif; ?>

        <?php if ($recursoRetratacao !== null || $recursoPresid !== null) : ?>
          <h2><?php esc_html_e('Recursos', 'participe-ibram'); ?></h2>
          <?php foreach (array_filter([$recursoRetratacao, $recursoPresid]) as $r) : ?>
            <article class="pi-card">
              <header class="pi-card__header">
                <h3 class="pi-card__title">
                  <?php echo esc_html(ucfirst($r->fase())); ?>
                  — <?php echo esc_html($r->protocoladoEm()->format('d/m/Y H:i')); ?>
                </h3>
              </header>
              <div class="pi-card__body">
                <p>
                  <strong><?php esc_html_e('Prazo:', 'participe-ibram'); ?></strong>
                  <?php echo esc_html($r->prazoFim()->format('d/m/Y')); ?>
                  <?php if ($r->prazoExpirado()) : ?>
                    <span class="pi-badge pi-badge--status-danger"><?php esc_html_e('Expirado', 'participe-ibram'); ?></span>
                  <?php endif; ?>
                </p>
                <h4><?php esc_html_e('Fundamentação', 'participe-ibram'); ?></h4>
                <div class="pi-md-content"><?php echo wp_kses_post($r->fundamentacaoMd()); ?></div>
                <?php if ($r->isDecidido()) : ?>
                  <h4><?php esc_html_e('Decisão', 'participe-ibram'); ?></h4>
                  <p><?php echo esc_html(ucfirst((string) $r->decisao())); ?>
                     — <?php echo esc_html($r->decididoEm() !== null ? $r->decididoEm()->format('d/m/Y H:i') : '—'); ?></p>
                  <div class="pi-md-content"><?php echo wp_kses_post((string) $r->decisaoMd()); ?></div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <!-- ============== Modais (R4 §6 acessível) ============== -->

  <?php if ($podeAssumir) : ?>
  <div class="pi-modal" id="pi-modal-confirm-assumir" role="dialog" aria-modal="true"
       aria-labelledby="pi-modal-assumir-title" aria-describedby="pi-modal-assumir-desc" hidden>
    <div class="pi-modal__dialog">
      <header class="pi-modal__header">
        <h2 id="pi-modal-assumir-title"><?php esc_html_e('Confirmar atribuição', 'participe-ibram'); ?></h2>
        <button type="button" class="pi-modal__close" data-pi-modal-close
                aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">×</button>
      </header>
      <div class="pi-modal__body">
        <p id="pi-modal-assumir-desc">
          <?php
          printf(
              /* translators: 1: nome do agente, 2: tipo */
              esc_html__('Você está prestes a assumir a análise do cadastro de %1$s (%2$s). Deseja continuar?', 'participe-ibram'),
              '<strong>' . esc_html($nomeAgente) . '</strong>',
              esc_html($tipoLabel)
          );
          ?>
        </p>
      </div>
      <footer class="pi-modal__footer">
        <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </button>
        <button type="button" class="pi-button pi-button--primary" data-pi-confirm="assumir">
          <?php esc_html_e('Confirmar', 'participe-ibram'); ?>
        </button>
      </footer>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($podeDeferir) : ?>
  <div class="pi-modal" id="pi-modal-deferir" role="dialog" aria-modal="true"
       aria-labelledby="pi-modal-deferir-title" hidden>
    <div class="pi-modal__dialog">
      <header class="pi-modal__header">
        <h2 id="pi-modal-deferir-title"><?php esc_html_e('Deferir cadastro', 'participe-ibram'); ?></h2>
        <button type="button" class="pi-modal__close" data-pi-modal-close
                aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">×</button>
      </header>
      <div class="pi-modal__body">
        <p>
          <?php
          printf(
              /* translators: 1: nome, 2: tipo */
              esc_html__('Cadastro: %1$s (%2$s). Esta ação é irreversível e gerará o número de registro definitivo.', 'participe-ibram'),
              '<strong>' . esc_html($nomeAgente) . '</strong>',
              esc_html($tipoLabel)
          );
          ?>
        </p>
        <label for="pi-deferir-parecer">
          <strong><?php esc_html_e('Parecer (Markdown):', 'participe-ibram'); ?></strong>
        </label>
        <textarea id="pi-deferir-parecer" name="parecer_md" rows="6" required class="pi-input pi-input--full"
                  aria-required="true"></textarea>
      </div>
      <footer class="pi-modal__footer">
        <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </button>
        <button type="button" class="pi-button pi-button--success" data-pi-confirm="deferir">
          <?php esc_html_e('Deferir cadastro', 'participe-ibram'); ?>
        </button>
      </footer>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($podeIndeferir) : ?>
  <div class="pi-modal" id="pi-modal-indeferir" role="dialog" aria-modal="true"
       aria-labelledby="pi-modal-indeferir-title" hidden>
    <div class="pi-modal__dialog">
      <header class="pi-modal__header">
        <h2 id="pi-modal-indeferir-title"><?php esc_html_e('Indeferir cadastro', 'participe-ibram'); ?></h2>
        <button type="button" class="pi-modal__close" data-pi-modal-close
                aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">×</button>
      </header>
      <div class="pi-modal__body">
        <p>
          <?php esc_html_e('Será aberto prazo de 10 dias contínuos para recurso a partir da publicação.', 'participe-ibram'); ?>
        </p>
        <label for="pi-indeferir-parecer">
          <strong><?php esc_html_e('Parecer (Markdown):', 'participe-ibram'); ?></strong>
        </label>
        <textarea id="pi-indeferir-parecer" name="parecer_md" rows="5" required
                  class="pi-input pi-input--full" aria-required="true"></textarea>

        <label for="pi-indeferir-fundamentacao">
          <strong><?php esc_html_e('Fundamentação legal (Markdown):', 'participe-ibram'); ?></strong>
        </label>
        <textarea id="pi-indeferir-fundamentacao" name="fundamentacao_md" rows="5" required
                  class="pi-input pi-input--full" aria-required="true"></textarea>
      </div>
      <footer class="pi-modal__footer">
        <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </button>
        <button type="button" class="pi-button pi-button--danger" data-pi-confirm="indeferir">
          <?php esc_html_e('Indeferir cadastro', 'participe-ibram'); ?>
        </button>
      </footer>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($podeRevelar) : ?>
  <div class="pi-modal" id="pi-modal-revelar" role="dialog" aria-modal="true"
       aria-labelledby="pi-modal-revelar-title" hidden>
    <div class="pi-modal__dialog">
      <header class="pi-modal__header">
        <h2 id="pi-modal-revelar-title"><?php esc_html_e('Revelar dados sensíveis', 'participe-ibram'); ?></h2>
        <button type="button" class="pi-modal__close" data-pi-modal-close
                aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">×</button>
      </header>
      <div class="pi-modal__body">
        <p><?php esc_html_e('Esta ação será registrada na auditoria. Confirma a visualização dos dados pessoais sensíveis deste cadastro?', 'participe-ibram'); ?></p>
      </div>
      <footer class="pi-modal__footer">
        <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </button>
        <button type="button" class="pi-button pi-button--primary" data-pi-confirm="revelar">
          <?php esc_html_e('Confirmar e revelar', 'participe-ibram'); ?>
        </button>
      </footer>
    </div>
  </div>
  <?php endif; ?>

  <script type="application/json" id="pi-admin-detalhes-data">
    <?php
    // Json::encodeForScript escapes < > & ' " — safe for inline JSON block.
    echo Json::encodeForScript($pageData);
    ?>
  </script>
</div>
