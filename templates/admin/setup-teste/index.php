<?php
/**
 * Template — Setup de Teste (Wave 8.5).
 *
 * Variáveis injetadas por SetupTesteController::render():
 *  - array       $preflight    Resultados dos checks
 *  - array|null  $flash        Flash message ['type','message']
 *  - array       $credentials  Credenciais salvas [login => ['password','role','user_id']]
 *  - string      $nonce        Nonce para ações POST
 *
 * W11-C: migrado para PageLayout chrome. Inline <style> removido — CSS em
 *        participe-ibram-admin.css (_setup-teste.scss). Breadcrumbs padronizados.
 *
 * @package Ibram\ParticipeIbram\Templates\Admin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

$preflight   = isset($preflight) && is_array($preflight) ? $preflight : [];
$flash       = isset($flash) && is_array($flash) ? $flash : null;
$credentials = isset($credentials) && is_array($credentials) ? $credentials : [];
$nonce       = isset($nonce) ? (string) $nonce : '';

$pageUrl = esc_url(add_query_arg(['page' => 'participe-ibram_setup_teste'], admin_url('admin.php')));

// Gera 6 chaves dummy de dev (só visíveis se constantes não definidas).
$missingConsts = $preflight['constants']['missing_consts'] ?? [];
$dummyDevKeys  = [];
if (!empty($missingConsts)) {
    foreach ($missingConsts as $const) {
        $dummyDevKeys[$const] = base64_encode(random_bytes(32));
    }
}

// Helper de badge de status.
$statusBadge = static function (string $status): string {
    $map = [
        'ok'      => ['class' => 'pi-badge--ok',      'icon' => '✓', 'label' => 'OK'],
        'warning' => ['class' => 'pi-badge--warning',  'icon' => '!', 'label' => 'Atenção'],
        'error'   => ['class' => 'pi-badge--error',    'icon' => '✗', 'label' => 'Erro'],
    ];
    $s = $map[$status] ?? $map['warning'];
    return sprintf(
        '<span class="pi-badge %s" aria-label="%s"><span aria-hidden="true">%s</span> %s</span>',
        esc_attr($s['class']),
        esc_attr($s['label']),
        esc_html($s['icon']),
        esc_html($s['label'])
    );
};

$loginUrl = esc_url(wp_login_url());

PageLayout::open(
    __('Setup de Teste — Participe Ibram', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Ferramentas', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram')],
        ['label' => __('Setup de Teste', 'participe-ibram')],
    ]
);
?>

<a class="pi-skip-link" href="#pi-setup-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<?php Notice::warning(__('AMBIENTE DE TESTE — NÃO usar em produção. Limpe os dados antes de ir ao ar.', 'participe-ibram')); ?>

<?php
if ($flash) {
    $type    = (string) ($flash['type'] ?? 'info');
    $message = (string) ($flash['message'] ?? '');
    if ($type === 'success') {
        Notice::success($message, true);
    } elseif ($type === 'warning') {
        Notice::warning($message, true);
    } else {
        Notice::danger($message, true);
    }
}
?>

<main id="pi-setup-main" tabindex="-1">

    <?php /* ═══════════════════════════════════════════════════════════════
           CARD 1: Pre-flight check
           ═══════════════════════════════════════════════════════════════ */ ?>
    <section class="pi-card" role="region" aria-labelledby="card1-title">
        <div class="pi-card__header">
            <h2 class="pi-card__title" id="card1-title">
                <?php esc_html_e('1. Pre-flight check', 'participe-ibram'); ?>
            </h2>
            <p class="pi-card__subtitle">
                <?php esc_html_e('Verificações automáticas do ambiente.', 'participe-ibram'); ?>
            </p>
        </div>
        <div class="pi-card__body">
            <?php if (empty($preflight)) : ?>
                <p><?php esc_html_e('Nenhuma verificação disponível.', 'participe-ibram'); ?></p>
            <?php else : ?>
                <table class="pi-preflight-table widefat striped" aria-label="<?php esc_attr_e('Resultados pre-flight', 'participe-ibram'); ?>">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Verificação', 'participe-ibram'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'participe-ibram'); ?></th>
                            <th scope="col"><?php esc_html_e('Detalhe', 'participe-ibram'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preflight as $key => $check) : ?>
                            <tr>
                                <td><?php echo esc_html($check['label']); ?></td>
                                <td><?php echo $statusBadge($check['status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                <td><?php echo esc_html($check['detail']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php /* Modal: Como configurar constantes */ ?>
            <?php if (!empty($dummyDevKeys)) : ?>
                <div class="pi-config-hint" style="margin-top:1rem;">
                    <button type="button"
                            class="button button-secondary"
                            aria-haspopup="dialog"
                            onclick="document.getElementById('modal-const').showModal()">
                        <?php esc_html_e('Como configurar as constantes', 'participe-ibram'); ?>
                    </button>
                </div>

                <dialog id="modal-const" class="pi-modal" aria-labelledby="modal-const-title">
                    <div class="pi-modal__content">
                        <h3 id="modal-const-title"><?php esc_html_e('Constantes wp-config.php — Dev (geradas agora)', 'participe-ibram'); ?></h3>
                        <p class="pi-modal__warning" role="alert">
                            <?php esc_html_e('Estas chaves foram geradas agora e são válidas apenas para dev/teste. Guarde-as! Ao fechar este modal, novas chaves serão geradas na próxima visita.', 'participe-ibram'); ?>
                        </p>
                        <pre class="pi-code-block"><code id="const-snippet"><?php
                        $snippet = "// Adicione ao final do wp-config.php (ANTES do 'stop editing' comment):\n\n";
                        foreach ($dummyDevKeys as $const => $val) {
                            $snippet .= "define('" . esc_html($const) . "', '" . esc_html($val) . "');\n";
                        }
                        echo esc_html($snippet);
                        ?></code></pre>
                        <div class="pi-modal__actions">
                            <button type="button"
                                    class="button button-primary"
                                    onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('const-snippet').innerText).then(function(){alert('<?php esc_html_e('Copiado!', 'participe-ibram'); ?>')})">
                                <?php esc_html_e('Copiar snippet', 'participe-ibram'); ?>
                            </button>
                            <button type="button"
                                    class="button"
                                    onclick="document.getElementById('modal-const').close()"
                                    autofocus>
                                <?php esc_html_e('Fechar', 'participe-ibram'); ?>
                            </button>
                        </div>
                    </div>
                </dialog>
            <?php endif; ?>
        </div>
    </section>

    <?php /* ═══════════════════════════════════════════════════════════════
           CARD 1.5: Re-executar Activator (bug fix 2026-05-11)
           ═══════════════════════════════════════════════════════════════ */ ?>
    <?php
    $activationError   = get_option('pi_activation_last_error', '');
    $activationApplied = get_option('pi_activation_last_applied', []);
    ?>
    <section class="pi-card" role="region" aria-labelledby="card15-title">
        <div class="pi-card__header">
            <h2 class="pi-card__title" id="card15-title">
                <?php esc_html_e('1.5. Re-executar Activator', 'participe-ibram'); ?>
            </h2>
            <p class="pi-card__subtitle">
                <?php esc_html_e('Roda novamente: instala roles, cria diretório privado, aplica migrations, agenda crons. Idempotente — seguro executar quantas vezes precisar.', 'participe-ibram'); ?>
            </p>
        </div>
        <div class="pi-card__body">
            <?php if ($activationError !== '') : ?>
                <div class="notice notice-error inline" role="alert">
                    <p><strong><?php esc_html_e('Última ativação reportou erro:', 'participe-ibram'); ?></strong></p>
                    <pre style="white-space:pre-wrap;background:#fff;padding:10px;border:1px solid #c00;"><?php echo esc_html($activationError); ?></pre>
                </div>
            <?php elseif (is_array($activationApplied) && !empty($activationApplied)) : ?>
                <p class="notice notice-success inline" style="padding:8px 12px;">
                    <?php
                    printf(
                        /* translators: %s = versões aplicadas (ex.: "V001, V002, V003") */
                        esc_html__('Última ativação aplicou: %s', 'participe-ibram'),
                        esc_html(implode(', ', $activationApplied))
                    );
                    ?>
                </p>
            <?php endif; ?>
            <form method="post" action="">
                <?php wp_nonce_field('pi_setup_teste_action'); ?>
                <input type="hidden" name="pi_setup_action" value="reativar">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('▶ Re-executar Activator agora', 'participe-ibram'); ?>
                </button>
                <span class="description" style="margin-left:10px;">
                    <?php esc_html_e('Use este botão se "Tabelas wp_pi_*" ou "Migrations" estão com erro acima.', 'participe-ibram'); ?>
                </span>
            </form>
        </div>
    </section>

    <?php /* ═══════════════════════════════════════════════════════════════
           CARD 2: Criar usuários de teste
           ═══════════════════════════════════════════════════════════════ */ ?>
    <section class="pi-card" role="region" aria-labelledby="card2-title">
        <div class="pi-card__header">
            <h2 class="pi-card__title" id="card2-title">
                <?php esc_html_e('2. Criar usuários de teste', 'participe-ibram'); ?>
            </h2>
            <p class="pi-card__subtitle">
                <?php esc_html_e('Cria 9 usuários com roles e senhas aleatórias. Idempotente: se já existem, renova as senhas.', 'participe-ibram'); ?>
            </p>
        </div>
        <div class="pi-card__body">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=participe-ibram_setup_teste')); ?>">
                <?php wp_nonce_field('pi_setup_teste_action'); ?>
                <input type="hidden" name="pi_setup_action" value="criar_usuarios">
                <button type="submit" class="button button-primary button-large">
                    <?php esc_html_e('Criar 9 usuários de teste', 'participe-ibram'); ?>
                </button>
            </form>

            <?php if (!empty($credentials)) : ?>
                <h3 style="margin-top:1.5rem;"><?php esc_html_e('Credenciais de teste', 'participe-ibram'); ?></h3>
                <p>
                    <button type="button"
                            class="button button-secondary"
                            id="btn-copy-all"
                            onclick="piCopyAllCredentials()">
                        <?php esc_html_e('Copiar tudo', 'participe-ibram'); ?>
                    </button>
                </p>
                <table class="widefat striped pi-cred-table" aria-label="<?php esc_attr_e('Credenciais de teste', 'participe-ibram'); ?>">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Login', 'participe-ibram'); ?></th>
                            <th scope="col"><?php esc_html_e('Role', 'participe-ibram'); ?></th>
                            <th scope="col"><?php esc_html_e('Senha', 'participe-ibram'); ?></th>
                            <th scope="col"><?php esc_html_e('Ação', 'participe-ibram'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="pi-cred-tbody">
                        <?php foreach ($credentials as $login => $data) : ?>
                            <tr>
                                <td><code><?php echo esc_html($login); ?></code></td>
                                <td><?php echo esc_html($data['role']); ?></td>
                                <td>
                                    <code class="pi-password"><?php echo esc_html($data['password']); ?></code>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(wp_login_url(admin_url(), false)); ?>"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <?php esc_html_e('Login', 'participe-ibram'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="pi-cred-hint">
                    <?php esc_html_e('Credenciais também salvas em wp_options (pi_test_credentials) e em TEST-CREDENTIALS.md no root do plugin.', 'participe-ibram'); ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <?php /* ═══════════════════════════════════════════════════════════════
           CARD 3: Seed de dados
           ═══════════════════════════════════════════════════════════════ */ ?>
    <section class="pi-card" role="region" aria-labelledby="card3-title">
        <div class="pi-card__header">
            <h2 class="pi-card__title" id="card3-title">
                <?php esc_html_e('3. Popular dados de teste', 'participe-ibram'); ?>
            </h2>
            <p class="pi-card__subtitle">
                <?php esc_html_e('Insere agentes, edital, inscrição, votação, audit log e solicitação DPO de teste.', 'participe-ibram'); ?>
            </p>
        </div>
        <div class="pi-card__body">
            <ul class="pi-seed-preview">
                <li><?php esc_html_e('3 agentes: PF (DEFERIDO), OR (SUBMETIDO), SM (RASCUNHO)', 'participe-ibram'); ?></li>
                <li><?php esc_html_e('1 edital "Edital de Teste — CCDEM 2026" (PUBLICADO) com 2 categorias', 'participe-ibram'); ?></li>
                <li><?php esc_html_e('1 inscrição do agente PF (HABILITADO)', 'participe-ibram'); ?></li>
                <li><?php esc_html_e('1 votação atrelada ao edital (AGENDADA)', 'participe-ibram'); ?></li>
                <li><?php esc_html_e('3 entries no audit log', 'participe-ibram'); ?></li>
                <li><?php esc_html_e('1 solicitação Art. 18 para o DPO (teste_agente_pf)', 'participe-ibram'); ?></li>
            </ul>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=participe-ibram_setup_teste')); ?>">
                <?php wp_nonce_field('pi_setup_teste_action'); ?>
                <input type="hidden" name="pi_setup_action" value="popular_dados">
                <button type="submit" class="button button-primary button-large">
                    <?php esc_html_e('Popular dados de teste', 'participe-ibram'); ?>
                </button>
            </form>
        </div>
    </section>

    <?php /* ═══════════════════════════════════════════════════════════════
           CARD 4: Cleanup
           ═══════════════════════════════════════════════════════════════ */ ?>
    <section class="pi-card pi-card--danger" role="region" aria-labelledby="card4-title">
        <div class="pi-card__header">
            <h2 class="pi-card__title" id="card4-title">
                <?php esc_html_e('4. Remover dados de teste', 'participe-ibram'); ?>
            </h2>
            <p class="pi-card__subtitle">
                <?php esc_html_e('Remove todos os usuários e dados criados por este setup.', 'participe-ibram'); ?>
            </p>
        </div>
        <div class="pi-card__body">
            <button type="button"
                    class="button pi-btn--danger button-large"
                    aria-haspopup="dialog"
                    onclick="document.getElementById('modal-cleanup').showModal()">
                <?php esc_html_e('Remover dados de teste', 'participe-ibram'); ?>
            </button>

            <dialog id="modal-cleanup" class="pi-modal" aria-labelledby="modal-cleanup-title">
                <div class="pi-modal__content">
                    <h3 id="modal-cleanup-title"><?php esc_html_e('Confirmar remoção', 'participe-ibram'); ?></h3>
                    <p role="alert" class="pi-modal__warning">
                        <?php esc_html_e('Esta ação removerá TODOS os usuários de teste, agentes, editais, inscrições, votações e dados de seed. Esta operação é irreversível.', 'participe-ibram'); ?>
                    </p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=participe-ibram_setup_teste')); ?>">
                        <?php wp_nonce_field('pi_setup_teste_action'); ?>
                        <input type="hidden" name="pi_setup_action" value="cleanup">
                        <label for="confirm-cleanup" style="display:block;margin-bottom:.5rem;font-weight:600;">
                            <?php esc_html_e('Digite CONFIRMAR para prosseguir:', 'participe-ibram'); ?>
                        </label>
                        <input type="text"
                               id="confirm-cleanup"
                               name="pi_confirm_cleanup"
                               class="regular-text"
                               placeholder="CONFIRMAR"
                               autocomplete="off"
                               required
                               pattern="CONFIRMAR"
                               aria-required="true">
                        <div class="pi-modal__actions" style="margin-top:1rem;">
                            <button type="submit" class="button pi-btn--danger">
                                <?php esc_html_e('Sim, remover tudo', 'participe-ibram'); ?>
                            </button>
                            <button type="button"
                                    class="button"
                                    onclick="document.getElementById('modal-cleanup').close()"
                                    autofocus>
                                <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </dialog>
        </div>
    </section>

</main>

<?php /* ── Inline JS: copy helpers + dialog polyfill fallback ─────────── */ ?>
<script>
function piCopyAllCredentials() {
    var rows = [];
    document.querySelectorAll('#pi-cred-tbody tr').forEach(function(tr) {
        var cells = tr.querySelectorAll('td');
        if (cells.length >= 3) {
            rows.push(cells[0].innerText.trim() + '\t' + cells[1].innerText.trim() + '\t' + cells[2].innerText.trim());
        }
    });
    if (!rows.length) { return; }
    var text = 'Login\tRole\tSenha\n' + rows.join('\n');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            var btn = document.getElementById('btn-copy-all');
            if (btn) {
                btn.textContent = '<?php esc_html_e('Copiado!', 'participe-ibram'); ?>';
                setTimeout(function(){ btn.textContent = '<?php esc_html_e('Copiar tudo', 'participe-ibram'); ?>'; }, 2000);
            }
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('<?php esc_html_e('Copiado!', 'participe-ibram'); ?>');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (!window.HTMLDialogElement) {
        document.querySelectorAll('dialog[id]').forEach(function(dialog) {
            dialog.showModal = function() { dialog.setAttribute('open', ''); dialog.style.display = 'block'; };
            dialog.close    = function() { dialog.removeAttribute('open'); dialog.style.display = 'none'; };
        });
    }
    document.querySelectorAll('dialog').forEach(function(dialog) {
        dialog.addEventListener('click', function(e) {
            if (e.target === dialog) { dialog.close(); }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && dialog.open) { dialog.close(); }
        });
    });
});
</script>

<?php PageLayout::close(); ?>
