<?php
/**
 * Template — Auditoria interna de Votação (admin).
 *
 * Vars:
 *  - \Ibram\ParticipeIbram\Domain\Votacao\Votacao $votacao
 *  - int $totalVotos
 *  - int $ipsUnicos
 *  - list<array{ocorrido_em:string,categoria_id:int,eleitor_hash_mask:string,
 *               candidato_inscricao_id:int,ip_hash_mask:?string}> $eventosMascarados
 *  - array<int,array{categoria_id:int,total:int}> $porCat
 *  - array<int,array{dia:string,total:int}> $porDia
 *  - int $page
 *  - array{recalcular:string} $nonces
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
/** @var int $totalVotos */
/** @var int $ipsUnicos */
/** @var array $eventosMascarados */
/** @var array $porCat */
/** @var array $porDia */
/** @var int $page */
/** @var array<string,string> $nonces */

$cfg = [
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'votacaoId' => (int) $votacao->id(),
    'nonces'    => $nonces,
];

$pageTitle = sprintf(
    /* translators: %d: votacao id */
    __('Auditoria — Votação #%d', 'participe-ibram'),
    (int) $votacao->id()
);

PageLayout::open(
    $pageTitle,
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Votações', 'participe-ibram'), 'url' => VotacaoMenuRegistry::urlVotacoesList()],
        ['label' => __('Apuração', 'participe-ibram'), 'url' => VotacaoMenuRegistry::urlApurar((int) $votacao->id())],
        ['label' => __('Auditoria', 'participe-ibram')],
    ],
    null,
    [['label' => __('Voltar à apuração', 'participe-ibram'), 'url' => VotacaoMenuRegistry::urlApurar((int) $votacao->id())]]
);
?>
<a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<main id="pi-admin-main" tabindex="-1" data-pi-auditoria>
    <?php
    Notice::info(__('Esta página revela apenas dados anonimizados. NÃO mostra agente_id, ator_id, IPs reais ou qualquer dado pessoal.', 'participe-ibram'));
    ?>

    <section class="pi-card" aria-labelledby="pi-aud-stats-heading">
        <h2 id="pi-aud-stats-heading"><?php esc_html_e('Estatísticas', 'participe-ibram'); ?></h2>
        <ul class="pi-stats">
            <li><strong><?php esc_html_e('Total de votos:', 'participe-ibram'); ?></strong> <?php echo esc_html((string) $totalVotos); ?></li>
            <li><strong><?php esc_html_e('IPs únicos (hash):', 'participe-ibram'); ?></strong> <?php echo esc_html((string) $ipsUnicos); ?></li>
            <li><strong><?php esc_html_e('Por categoria:', 'participe-ibram'); ?></strong>
                <?php
                $parts = [];
                foreach ($porCat as $row) {
                    $parts[] = sprintf('Cat #%d: %d', (int) $row['categoria_id'], (int) $row['total']);
                }
                echo esc_html(implode(' · ', $parts) ?: '—');
                ?>
            </li>
        </ul>
    </section>

    <section class="pi-card" aria-labelledby="pi-aud-temporal-heading">
        <h2 id="pi-aud-temporal-heading"><?php esc_html_e('Distribuição temporal (por dia)', 'participe-ibram'); ?></h2>
        <table class="widefat striped pi-list-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Dia', 'participe-ibram'); ?></th>
                    <th scope="col"><?php esc_html_e('Votos', 'participe-ibram'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($porDia)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('Sem dados.', 'participe-ibram'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($porDia as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['dia']); ?></td>
                            <td><?php echo esc_html((string) (int) $row['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="pi-card" aria-labelledby="pi-aud-integridade-heading">
        <h2 id="pi-aud-integridade-heading"><?php esc_html_e('Verificação de integridade', 'participe-ibram'); ?></h2>
        <p>
            <?php esc_html_e('Recalcula o hash dos votos atuais e compara com o hash registrado em pré-apuração. Divergência indica adulteração.', 'participe-ibram'); ?>
        </p>
        <button type="button" class="pi-button pi-button--secondary" data-pi-recalcular>
            <?php esc_html_e('Verificar integridade', 'participe-ibram'); ?>
        </button>
        <p id="pi-hash-result" class="pi-hash-result" aria-live="polite"></p>
    </section>

    <section class="pi-card" aria-labelledby="pi-aud-eventos-heading">
        <h2 id="pi-aud-eventos-heading"><?php esc_html_e('Eventos voto_registrado (anonimizados)', 'participe-ibram'); ?></h2>
        <table class="widefat striped pi-list-table" aria-describedby="pi-aud-eventos-desc">
            <caption id="pi-aud-eventos-desc" class="screen-reader-text">
                <?php esc_html_e('Lista de votos com identificadores mascarados.', 'participe-ibram'); ?>
            </caption>
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Ocorrido em', 'participe-ibram'); ?></th>
                    <th scope="col"><?php esc_html_e('Categoria', 'participe-ibram'); ?></th>
                    <th scope="col"><?php esc_html_e('Hash do eleitor (8 chars)', 'participe-ibram'); ?></th>
                    <th scope="col"><?php esc_html_e('Candidato (id)', 'participe-ibram'); ?></th>
                    <th scope="col"><?php esc_html_e('Hash do IP (8 chars)', 'participe-ibram'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($eventosMascarados)) : ?>
                    <tr><td colspan="5"><?php esc_html_e('Sem eventos registrados.', 'participe-ibram'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($eventosMascarados as $ev) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $ev['ocorrido_em']); ?></td>
                            <td><?php echo esc_html((string) (int) $ev['categoria_id']); ?></td>
                            <td><code><?php echo esc_html((string) $ev['eleitor_hash_mask']); ?></code></td>
                            <td><?php echo esc_html((string) (int) $ev['candidato_inscricao_id']); ?></td>
                            <td><?php echo $ev['ip_hash_mask'] !== null ? '<code>' . esc_html((string) $ev['ip_hash_mask']) . '</code>' : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <nav class="pi-pagination" aria-label="<?php esc_attr_e('Paginação', 'participe-ibram'); ?>">
            <?php
            $base    = VotacaoMenuRegistry::urlAuditoria((int) $votacao->id());
            $prevUrl = $page > 1 ? add_query_arg('paged', $page - 1, $base) : null;
            $nextUrl = count($eventosMascarados) >= 50 ? add_query_arg('paged', $page + 1, $base) : null;
            ?>
            <?php if ($prevUrl !== null) : ?>
                <a class="pi-button pi-button--secondary" href="<?php echo esc_url($prevUrl); ?>"><?php esc_html_e('« Anterior', 'participe-ibram'); ?></a>
            <?php endif; ?>
            <span class="pi-pagination__current"><?php echo esc_html(sprintf(__('Página %d', 'participe-ibram'), $page)); ?></span>
            <?php if ($nextUrl !== null) : ?>
                <a class="pi-button pi-button--secondary" href="<?php echo esc_url($nextUrl); ?>"><?php esc_html_e('Próxima »', 'participe-ibram'); ?></a>
            <?php endif; ?>
        </nav>
    </section>

    <script type="application/json" id="pi-auditoria-data"><?php
        echo wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    ?></script>
</main>
<?php
PageLayout::close();
