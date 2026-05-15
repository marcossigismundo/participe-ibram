<?php
/**
 * Template — Criar / Editar Votação (admin).
 *
 * Vars injetadas por VotacaoFormController::renderForm():
 *  - \Ibram\ParticipeIbram\Domain\Votacao\Votacao|null $votacao  (null = criação)
 *  - array<int,array{id:int,titulo:string}>             $editais  lista para o select
 *  - array<int,string>                                  $modos    valores de ModoVotacao::all()
 *  - bool                                               $isNew
 *  - string                                             $nonce
 *  - array{type:string,message:string}|null             $flash
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Votacoes
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/** @var \Ibram\ParticipeIbram\Domain\Votacao\Votacao|null $votacao */
/** @var array<int,array{id:int,titulo:string}> $editais */
/** @var array<int,string> $modos */
/** @var bool $isNew */
/** @var string $nonce */
/** @var array{type:string,message:string}|null $flash */

$isNew     = isset($isNew) ? (bool) $isNew : ($votacao === null);
$flash     = isset($flash) ? $flash : null;
$nonce     = isset($nonce) ? (string) $nonce : '';
$editais   = isset($editais) && is_array($editais) ? $editais : [];
$modos     = isset($modos) && is_array($modos) ? $modos : ModoVotacao::all();
$votacaoId = ($votacao !== null && $votacao->id() !== null) ? (int) $votacao->id() : 0;

$fmtDate = static function (?\DateTimeImmutable $dt): string {
    return $dt !== null ? esc_attr($dt->format('Y-m-d\TH:i')) : '';
};

$valAbertura     = $votacao !== null ? $fmtDate($votacao->abertura()) : '';
$valEncerramento = $votacao !== null ? $fmtDate($votacao->encerramento()) : '';
$valModo         = $votacao !== null ? $votacao->modo()->value() : ModoVotacao::POR_CATEGORIA;
$valEditalId     = $votacao !== null ? $votacao->editalId() : 0;

$modoLabels = [
    ModoVotacao::POR_CATEGORIA => __('Por categoria', 'participe-ibram'),
    ModoVotacao::GERAL         => __('Geral (único voto)', 'participe-ibram'),
];

$pageTitle = $isNew
    ? __('Nova Votação', 'participe-ibram')
    : __('Editar Votação', 'participe-ibram');

PageLayout::open(
    $pageTitle,
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Votações', 'participe-ibram'), 'url' => VotacaoMenuRegistry::urlVotacoesList()],
        ['label' => $pageTitle],
    ]
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

<main id="pi-admin-main" tabindex="-1">
    <div class="pi-form-layout">
        <!-- Coluna principal -->
        <div class="pi-form-layout__main pi-form">
            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin.php')); ?>"
                novalidate
                aria-label="<?php esc_attr_e('Formulário de votação', 'participe-ibram'); ?>"
            >
                <?php
                $nonceAction = $isNew
                    ? 'pi_admin_criar_votacao_' . get_current_user_id()
                    : 'pi_admin_atualizar_votacao_' . $votacaoId . '_' . get_current_user_id();
                wp_nonce_field($nonceAction, '_wpnonce');
                ?>
                <input type="hidden" name="page"
                    value="<?php echo esc_attr($isNew ? VotacaoMenuRegistry::SLUG_VOTACAO_NOVA : VotacaoMenuRegistry::SLUG_VOTACAO_EDITAR); ?>">
                <input type="hidden" name="pi_votacao_action"
                    value="<?php echo esc_attr($isNew ? 'criar_votacao' : 'atualizar_votacao'); ?>">
                <?php if (!$isNew) : ?>
                    <input type="hidden" name="votacao_id" value="<?php echo esc_attr((string) $votacaoId); ?>">
                <?php endif; ?>

                <!-- Edital -->
                <div class="pi-field-group">
                    <label for="pi-votacao-edital" class="pi-field-group__label pi-field-group__label--required">
                        <?php esc_html_e('Edital', 'participe-ibram'); ?>
                    </label>
                    <?php if ($isNew) : ?>
                        <select
                            id="pi-votacao-edital"
                            name="edital_id"
                            required
                            aria-required="true"
                            aria-describedby="pi-votacao-edital-hint"
                        >
                            <option value=""><?php esc_html_e('— Selecione um edital —', 'participe-ibram'); ?></option>
                            <?php foreach ($editais as $ed) : ?>
                                <option value="<?php echo esc_attr((string) $ed['id']); ?>"
                                    <?php selected($valEditalId, $ed['id']); ?>
                                >
                                    <?php echo esc_html($ed['titulo']); ?>
                                    (<?php echo esc_html('#' . $ed['id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($editais)) : ?>
                            <p class="pi-field-group__error" role="alert">
                                <?php esc_html_e('Nenhum edital disponível (todos já possuem votação ativa ou estão em rascunho).', 'participe-ibram'); ?>
                            </p>
                        <?php endif; ?>
                        <p id="pi-votacao-edital-hint" class="description">
                            <?php esc_html_e('Apenas editais publicados sem votação ativa são listados.', 'participe-ibram'); ?>
                        </p>
                    <?php else : ?>
                        <?php
                        // No modo edição, exibe somente o edital atual (não pode trocar).
                        $editalLabel = '';
                        foreach ($editais as $ed) {
                            if ($ed['id'] === $valEditalId) {
                                $editalLabel = $ed['titulo'] . ' (#' . $ed['id'] . ')';
                                break;
                            }
                        }
                        ?>
                        <input type="hidden" name="edital_id" value="<?php echo esc_attr((string) $valEditalId); ?>">
                        <p class="pi-field-group__static">
                            <?php echo esc_html($editalLabel !== '' ? $editalLabel : (string) $valEditalId); ?>
                        </p>
                        <p id="pi-votacao-edital-hint" class="description">
                            <?php esc_html_e('O edital não pode ser alterado após a criação da votação.', 'participe-ibram'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Abertura -->
                <div class="pi-field-group">
                    <label for="pi-votacao-abertura" class="pi-field-group__label pi-field-group__label--required">
                        <?php esc_html_e('Data e hora de abertura', 'participe-ibram'); ?>
                    </label>
                    <input
                        type="datetime-local"
                        id="pi-votacao-abertura"
                        name="abertura"
                        class="pi-date-input"
                        required
                        aria-required="true"
                        aria-describedby="pi-votacao-abertura-hint"
                        value="<?php echo $valAbertura; ?>"
                    >
                    <p id="pi-votacao-abertura-hint" class="description">
                        <?php esc_html_e('Momento em que a urna será aberta para votação.', 'participe-ibram'); ?>
                    </p>
                </div>

                <!-- Encerramento -->
                <div class="pi-field-group">
                    <label for="pi-votacao-encerramento" class="pi-field-group__label pi-field-group__label--required">
                        <?php esc_html_e('Data e hora de encerramento', 'participe-ibram'); ?>
                    </label>
                    <input
                        type="datetime-local"
                        id="pi-votacao-encerramento"
                        name="encerramento"
                        class="pi-date-input"
                        required
                        aria-required="true"
                        aria-describedby="pi-votacao-encerramento-hint"
                        value="<?php echo $valEncerramento; ?>"
                    >
                    <p id="pi-votacao-encerramento-hint" class="description">
                        <?php esc_html_e('Momento em que a urna será fechada automaticamente (encerramento ≥ abertura).', 'participe-ibram'); ?>
                    </p>
                </div>

                <!-- Modo -->
                <div class="pi-field-group">
                    <label for="pi-votacao-modo" class="pi-field-group__label pi-field-group__label--required">
                        <?php esc_html_e('Modo de votação', 'participe-ibram'); ?>
                    </label>
                    <select
                        id="pi-votacao-modo"
                        name="modo"
                        required
                        aria-required="true"
                        aria-describedby="pi-votacao-modo-hint"
                    >
                        <?php foreach ($modos as $modoVal) : ?>
                            <option
                                value="<?php echo esc_attr($modoVal); ?>"
                                <?php selected($valModo, $modoVal); ?>
                            >
                                <?php echo esc_html($modoLabels[$modoVal] ?? $modoVal); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="pi-votacao-modo-hint" class="description">
                        <?php esc_html_e('"Por categoria" permite um voto por categoria elegível; "Geral" é um único voto por eleitor.', 'participe-ibram'); ?>
                    </p>
                </div>

                <!-- Ações -->
                <div class="pi-form-actions">
                    <button type="submit" class="pi-button pi-button--primary">
                        <?php echo esc_html($isNew ? __('Criar votação', 'participe-ibram') : __('Salvar alterações', 'participe-ibram')); ?>
                    </button>
                    <a href="<?php echo esc_url(VotacaoMenuRegistry::urlVotacoesList()); ?>" class="pi-button pi-button--secondary">
                        <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
                    </a>
                </div>
            </form>
        </div><!-- .pi-form-layout__main -->

        <!-- Sidebar informativa -->
        <aside class="pi-form-layout__sidebar" aria-label="<?php esc_attr_e('Informações da votação', 'participe-ibram'); ?>">
            <div class="pi-timeline-card">
                <h2 class="pi-timeline-card__title"><?php esc_html_e('Sobre votações', 'participe-ibram'); ?></h2>
                <ul class="pi-info-list">
                    <li><?php esc_html_e('Uma votação por edital.', 'participe-ibram'); ?></li>
                    <li><?php esc_html_e('Status inicial: Agendada. A urna abre automaticamente no horário configurado.', 'participe-ibram'); ?></li>
                    <li><?php esc_html_e('Datas e modo só podem ser editados enquanto o status for Agendada.', 'participe-ibram'); ?></li>
                    <li><?php esc_html_e('O cron abre a votação a cada 10 min quando abertura ≤ agora.', 'participe-ibram'); ?></li>
                </ul>
            </div>
            <?php if (!$isNew && $votacao !== null) : ?>
                <div class="pi-status-info">
                    <p>
                        <strong><?php esc_html_e('Status atual:', 'participe-ibram'); ?></strong>
                        <?php echo esc_html($votacao->status()->value()); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Edital #', 'participe-ibram'); ?></strong>
                        <?php echo esc_html((string) $votacao->editalId()); ?>
                    </p>
                </div>
            <?php endif; ?>
        </aside>
    </div><!-- .pi-form-layout -->
</main>
<?php
PageLayout::close();
