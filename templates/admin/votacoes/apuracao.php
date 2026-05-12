<?php
/**
 * Template — Apuração de Votação (admin).
 *
 * Vars injetadas por ApuracaoController::render():
 *  - \Ibram\ParticipeIbram\Domain\Votacao\Votacao $votacao
 *  - \Ibram\ParticipeIbram\Domain\Edital\Edital  $edital
 *  - int   $totalVotos
 *  - bool  $podeApurar / $podePublicar / $podeExportar
 *  - \Ibram\ParticipeIbram\Presentation\Admin\ListTables\ResultadosListTable|null $resultadosTable
 *  - list<array<string,mixed>> $eleitos
 *  - array{apurar:string,publicar:string,exportar:string,recalcular:string} $nonces
 *  - array{type:string,message:string}|null $flash
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Votacoes
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/** @var \Ibram\ParticipeIbram\Domain\Votacao\Votacao $votacao */
/** @var \Ibram\ParticipeIbram\Domain\Edital\Edital  $edital */
/** @var int $totalVotos */
/** @var bool $podeApurar */
/** @var bool $podePublicar */
/** @var bool $podeExportar */
/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\ResultadosListTable|null $resultadosTable */
/** @var list<array<string,mixed>> $eleitos */
/** @var array<string,string> $nonces */
/** @var array{type:string,message:string}|null $flash */

$status      = $votacao->status()->value();
$hash        = (string) ($votacao->hashPreApuracao() ?? '');
$apuracaoCfg = [
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'votacaoId' => (int) $votacao->id(),
    'nonces'    => $nonces,
    'i18n'      => [
        'erroGenerico'    => __('Erro ao processar requisição.', 'participe-ibram'),
        'sucessoApurar'   => __('Votação apurada com sucesso.', 'participe-ibram'),
        'sucessoPublicar' => __('Resultado publicado.', 'participe-ibram'),
        'sucessoExportar' => __('Relatório gerado.', 'participe-ibram'),
        'hashCopiado'     => __('Hash copiado para a área de transferência.', 'participe-ibram'),
        'hashOk'          => __('Integridade verificada: hashes idênticos.', 'participe-ibram'),
        'hashDiverge'     => __('ATENÇÃO: hashes divergem.', 'participe-ibram'),
    ],
];

$pageTitle = sprintf(
    /* translators: %s: edital title */
    __('Apuração — %s', 'participe-ibram'),
    (string) $edital->titulo()
);

PageLayout::open(
    $pageTitle,
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Votações', 'participe-ibram'), 'url' => VotacaoMenuRegistry::urlVotacoesList()],
        ['label' => __('Apurar', 'participe-ibram')],
    ],
    null,
    [['label' => __('Auditoria', 'participe-ibram'), 'url' => VotacaoMenuRegistry::urlAuditoria((int) $votacao->id())]]
);
?>
<a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<?php if ($flash !== null) : ?>
    <?php
    if ($flash['type'] === 'success') {
        Notice::success($flash['message'], true);
    } else {
        Notice::danger($flash['message'], true);
    }
    ?>
<?php endif; ?>

<main id="pi-admin-main" tabindex="-1" data-pi-apuracao>
    <div id="pi-apuracao-live" role="status" aria-live="polite" class="screen-reader-text"></div>

    <section class="pi-card pi-apuracao__resumo" aria-labelledby="pi-apuracao-resumo-heading">
        <h2 id="pi-apuracao-resumo-heading"><?php esc_html_e('Resumo da votação', 'participe-ibram'); ?></h2>
        <dl class="pi-apuracao__dl">
            <div><dt><?php esc_html_e('Status', 'participe-ibram'); ?></dt>
                 <dd><span class="pi-status-badge pi-status-badge--<?php echo esc_attr($status); ?>">
                     <?php echo esc_html($status); ?></span></dd></div>
            <div><dt><?php esc_html_e('Abertura', 'participe-ibram'); ?></dt>
                 <dd><?php echo esc_html($votacao->abertura()->format('d/m/Y H:i')); ?></dd></div>
            <div><dt><?php esc_html_e('Encerramento', 'participe-ibram'); ?></dt>
                 <dd><?php echo esc_html($votacao->encerramento()->format('d/m/Y H:i')); ?></dd></div>
            <div><dt><?php esc_html_e('Total de votos', 'participe-ibram'); ?></dt>
                 <dd>
                     <?php
                     if ($status === 'agendada' || $status === 'aberta') {
                         echo '<span class="pi-muted">' . esc_html__('— (sigiloso até o encerramento)', 'participe-ibram') . '</span>';
                     } else {
                         echo esc_html((string) (int) $totalVotos);
                     }
                     ?>
                 </dd></div>
        </dl>
    </section>

    <section class="pi-card pi-apuracao__hash" aria-labelledby="pi-apuracao-hash-heading">
        <h2 id="pi-apuracao-hash-heading"><?php esc_html_e('Hash de pré-apuração', 'participe-ibram'); ?></h2>
        <p class="pi-card__lead">
            <?php esc_html_e('Este hash é o "selo" do conjunto de votos no momento do encerramento. Qualquer divergência indica adulteração.', 'participe-ibram'); ?>
        </p>
        <?php if ($hash === '') : ?>
            <p class="pi-muted"><?php esc_html_e('Hash ainda não calculado (votação não encerrada).', 'participe-ibram'); ?></p>
        <?php else : ?>
            <pre class="pi-hash-block"><code id="pi-hash-text" tabindex="0"><?php echo esc_html($hash); ?></code></pre>
            <div class="pi-card__actions">
                <button type="button" class="pi-button pi-button--secondary" data-pi-copy="<?php echo esc_attr($hash); ?>">
                    <?php esc_html_e('Copiar hash', 'participe-ibram'); ?>
                </button>
                <button type="button" class="pi-button pi-button--secondary" data-pi-recalcular>
                    <?php esc_html_e('Recalcular e comparar', 'participe-ibram'); ?>
                </button>
            </div>
            <p class="pi-hash-result" id="pi-hash-result" aria-live="polite"></p>
        <?php endif; ?>
        <p>
            <strong><?php esc_html_e('Algoritmo:', 'participe-ibram'); ?></strong>
            <code>sha256</code>
        </p>
        <p>
            <strong><?php esc_html_e('Tie-break:', 'participe-ibram'); ?></strong>
            <code>total_votos DESC, inscrito_em ASC, candidato_inscricao_id ASC</code>
        </p>
    </section>

    <section class="pi-card pi-apuracao__acoes" aria-labelledby="pi-apuracao-acoes-heading">
        <h2 id="pi-apuracao-acoes-heading"><?php esc_html_e('Ações', 'participe-ibram'); ?></h2>
        <div class="pi-card__actions">
            <?php if ($podeApurar) : ?>
                <button type="button" class="pi-button pi-button--primary"
                        data-pi-action="apurar"
                        aria-controls="pi-modal-apurar"
                        aria-haspopup="dialog"><?php esc_html_e('Apurar agora', 'participe-ibram'); ?></button>
            <?php endif; ?>
            <?php if ($podePublicar) : ?>
                <button type="button" class="pi-button pi-button--primary"
                        data-pi-action="publicar"
                        aria-controls="pi-modal-publicar"
                        aria-haspopup="dialog"><?php esc_html_e('Publicar Resultado', 'participe-ibram'); ?></button>
            <?php endif; ?>
            <?php if ($podeExportar) : ?>
                <button type="button" class="pi-button pi-button--secondary"
                        data-pi-action="exportar"><?php esc_html_e('Exportar relatório', 'participe-ibram'); ?></button>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($resultadosTable !== null) : ?>
        <section class="pi-card pi-apuracao__resultados pi-list-table" aria-labelledby="pi-apuracao-resultados-heading">
            <h2 id="pi-apuracao-resultados-heading"><?php esc_html_e('Resultados da apuração', 'participe-ibram'); ?></h2>
            <?php $resultadosTable->display(); ?>
        </section>
    <?php endif; ?>

    <!-- Modal: Apurar -->
    <div id="pi-modal-apurar" class="pi-modal" role="dialog" aria-modal="true"
         aria-labelledby="pi-modal-apurar-title" hidden>
        <div class="pi-modal__overlay" data-pi-modal-close></div>
        <div class="pi-modal__panel pi-modal--warning" role="document">
            <h2 id="pi-modal-apurar-title" class="pi-modal__title"><?php esc_html_e('Confirmar apuração', 'participe-ibram'); ?></h2>
            <div class="pi-modal__body">
                <p><strong><?php esc_html_e('Esta ação é definitiva.', 'participe-ibram'); ?></strong></p>
                <p><?php esc_html_e('Após apurar, as posições serão fixadas e o status se tornará "apurada".', 'participe-ibram'); ?></p>
                <p>
                    <?php esc_html_e('Tie-break aplicado:', 'participe-ibram'); ?>
                    <code>total_votos DESC, inscrito_em ASC, candidato_inscricao_id ASC</code>.
                </p>
            </div>
            <div class="pi-modal__actions">
                <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close><?php esc_html_e('Cancelar', 'participe-ibram'); ?></button>
                <button type="button" class="pi-button pi-button--primary" data-pi-modal-confirm="apurar"><?php esc_html_e('Sim, apurar', 'participe-ibram'); ?></button>
            </div>
        </div>
    </div>

    <!-- Modal: Publicar -->
    <div id="pi-modal-publicar" class="pi-modal" role="dialog" aria-modal="true"
         aria-labelledby="pi-modal-publicar-title" hidden>
        <div class="pi-modal__overlay" data-pi-modal-close></div>
        <div class="pi-modal__panel" role="document">
            <h2 id="pi-modal-publicar-title" class="pi-modal__title"><?php esc_html_e('Confirmar publicação', 'participe-ibram'); ?></h2>
            <div class="pi-modal__body">
                <p><?php esc_html_e('Os seguintes candidatos serão publicados como eleitos:', 'participe-ibram'); ?></p>
                <?php if (empty($eleitos)) : ?>
                    <p class="pi-muted"><?php esc_html_e('Nenhum eleito calculado.', 'participe-ibram'); ?></p>
                <?php else : ?>
                    <ul class="pi-eleitos-list">
                        <?php foreach ($eleitos as $e) : ?>
                            <li>
                                <strong><?php echo esc_html((string) ($e['nome_publico'] ?? '')); ?></strong>
                                — <?php echo esc_html((string) ($e['categoria_nome'] ?? '')); ?>
                                (<?php echo esc_html((string) ($e['numero_registro'] ?? '')); ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="pi-muted"><?php esc_html_e('Após a publicação, o evento "pi_resultado_publicado" é disparado para envio de notificações e atualização do site público.', 'participe-ibram'); ?></p>
            </div>
            <div class="pi-modal__actions">
                <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close><?php esc_html_e('Cancelar', 'participe-ibram'); ?></button>
                <button type="button" class="pi-button pi-button--primary" data-pi-modal-confirm="publicar"><?php esc_html_e('Sim, publicar', 'participe-ibram'); ?></button>
            </div>
        </div>
    </div>

    <script type="application/json" id="pi-apuracao-data"><?php
        echo wp_json_encode($apuracaoCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    ?></script>
</main>
<?php
PageLayout::close();
