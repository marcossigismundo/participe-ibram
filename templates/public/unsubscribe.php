<?php
/**
 * Página pública de confirmação de unsubscribe.
 *
 * Vars:
 *   state: 'confirm' | 'sucesso' | 'invalid' | 'erro'
 *   token: string (apenas em confirm/erro)
 *   message: string (em invalid/erro)
 *   finalidade: string (em sucesso)
 *   purpose: string (em confirm)
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$state      = isset($vars['state']) ? (string) $vars['state'] : 'confirm';
$token      = isset($vars['token']) ? (string) $vars['token'] : '';
$message    = isset($vars['message']) ? (string) $vars['message'] : '';
$purpose    = isset($vars['purpose']) ? (string) $vars['purpose'] : 'comunicacao';
$finalidade = isset($vars['finalidade']) ? (string) $vars['finalidade'] : '';

$nonce = function_exists('wp_create_nonce')
    ? wp_create_nonce('pi_unsubscribe_confirm')
    : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc_html__('Cancelar comunicacoes - Participe Ibram', 'participe-ibram') ?></title>
    <style>
        body { background:#f0f2f5;color:#1c1c1c;font-family:Arial,Helvetica,sans-serif;
               line-height:1.6;margin:0;padding:32px 16px; }
        main { max-width:600px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;
               box-shadow:0 1px 3px rgba(0,0,0,.06); }
        h1 { color:#1351b4;font-size:24px;margin:0 0 16px; }
        button.primary { background:#1351b4;color:#fff;border:none;padding:10px 20px;
                         border-radius:4px;cursor:pointer;font-size:16px;min-height:44px; }
        button.primary:focus { outline:3px solid #2670e8;outline-offset:2px; }
        a.cancel { display:inline-block;margin-left:12px;color:#1351b4;
                   text-decoration:underline;padding:10px 0; }
        .ok { color:#168821; }
        .error { color:#a80521; }
        @media (prefers-color-scheme: dark) {
            body, main { background:#0b1320 !important; color:#f0f2f5 !important; }
            main { background:#10182a !important; }
        }
    </style>
</head>
<body>
<main role="main">
    <h1 tabindex="-1"><?= esc_html__('Cancelar comunicacoes', 'participe-ibram') ?></h1>

    <?php if ($state === 'sucesso'): ?>
        <p class="ok" role="status" aria-live="polite">
            <strong><?= esc_html__('Cancelamento confirmado.', 'participe-ibram') ?></strong>
        </p>
        <p>
            <?= esc_html__('Voce nao recebera mais comunicacoes nao essenciais do Participe Ibram.', 'participe-ibram') ?>
        </p>
        <p>
            <?= esc_html__('Comunicacoes obrigatorias relacionadas a status do seu cadastro permanecem ativas, conforme base legal de execucao de politica publica (Art. 7, III, LGPD).', 'participe-ibram') ?>
        </p>

    <?php elseif ($state === 'invalid'): ?>
        <p class="error" role="alert">
            <strong><?= esc_html__('Link invalido.', 'participe-ibram') ?></strong>
        </p>
        <p>
            <?= esc_html(
                $message !== ''
                    ? $message
                    : __('Verifique se voce abriu o link mais recente recebido por e-mail.', 'participe-ibram')
            ) ?>
        </p>

    <?php elseif ($state === 'erro'): ?>
        <p class="error" role="alert">
            <strong><?= esc_html__('Ocorreu um erro.', 'participe-ibram') ?></strong>
        </p>
        <p><?= esc_html($message !== '' ? $message : __('Tente novamente em alguns minutos.', 'participe-ibram')) ?></p>

    <?php else: /* confirm */ ?>
        <p>
            <?= esc_html__('Voce esta prestes a cancelar o recebimento de comunicacoes nao essenciais do Participe Ibram.', 'participe-ibram') ?>
        </p>
        <p>
            <?= esc_html__('Esta acao revoga apenas comunicacoes opcionais. Mensagens obrigatorias relacionadas ao seu cadastro continuarao chegando.', 'participe-ibram') ?>
        </p>
        <form method="post" action="" aria-labelledby="pi-unsub-form-title">
            <h2 id="pi-unsub-form-title" class="screen-reader-text">
                <?= esc_html__('Confirmacao do cancelamento', 'participe-ibram') ?>
            </h2>
            <input type="hidden" name="token" value="<?= esc_attr($token) ?>">
            <input type="hidden" name="_wpnonce" value="<?= esc_attr($nonce) ?>">
            <button type="submit" class="primary">
                <?= esc_html__('Confirmar cancelamento', 'participe-ibram') ?>
            </button>
            <a href="<?= esc_url(home_url('/')) ?>" class="cancel">
                <?= esc_html__('Cancelar e voltar', 'participe-ibram') ?>
            </a>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
