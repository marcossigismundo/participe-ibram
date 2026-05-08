<?php
/**
 * Tab Configuracao SMTP.
 *
 * Vars: snapshot (array), nonce (string).
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$snap  = isset($vars['snapshot']) && is_array($vars['snapshot']) ? $vars['snapshot'] : [];
$nonce = isset($vars['nonce']) ? (string) $vars['nonce'] : '';

$host        = (string) ($snap['host'] ?? '');
$port        = (int) ($snap['port'] ?? 587);
$enc         = (string) ($snap['encryption'] ?? '');
$user        = (string) ($snap['user'] ?? '');
$fromEmail   = (string) ($snap['from_email'] ?? '');
$fromName    = (string) ($snap['from_name'] ?? '');
$passwordSet = (bool) ($snap['password_set'] ?? false);
?>

<form id="pi-email-config-form" method="post" action="#" autocomplete="off"
      aria-labelledby="pi-config-title">
    <h2 id="pi-config-title"><?= esc_html__('Configuracao SMTP', 'participe-ibram') ?></h2>
    <p><?= esc_html__('Use estes campos para configurar o servidor SMTP. A senha e armazenada cifrada (libsodium).', 'participe-ibram') ?></p>

    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row"><label for="pi_smtp_host"><?= esc_html__('Servidor (host)', 'participe-ibram') ?></label></th>
            <td>
                <input type="text" id="pi_smtp_host" name="host" value="<?= esc_attr($host) ?>"
                       class="regular-text" autocomplete="off">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="pi_smtp_port"><?= esc_html__('Porta', 'participe-ibram') ?></label></th>
            <td>
                <input type="number" id="pi_smtp_port" name="port" value="<?= esc_attr((string) $port) ?>"
                       min="1" max="65535" inputmode="numeric">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="pi_smtp_encryption"><?= esc_html__('Encriptacao', 'participe-ibram') ?></label></th>
            <td>
                <select id="pi_smtp_encryption" name="encryption">
                    <option value="" <?= $enc === '' ? 'selected' : '' ?>><?= esc_html__('Nenhuma', 'participe-ibram') ?></option>
                    <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>STARTTLS</option>
                    <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL/TLS</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="pi_smtp_user"><?= esc_html__('Usuario', 'participe-ibram') ?></label></th>
            <td>
                <input type="text" id="pi_smtp_user" name="user" value="<?= esc_attr($user) ?>"
                       class="regular-text" autocomplete="off">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="pi_smtp_password"><?= esc_html__('Senha', 'participe-ibram') ?></label></th>
            <td>
                <input type="password" id="pi_smtp_password" name="password" value=""
                       class="regular-text" autocomplete="new-password"
                       aria-describedby="pi_smtp_password_hint">
                <p class="description" id="pi_smtp_password_hint">
                    <?php if ($passwordSet): ?>
                        <?= esc_html__('Senha atualmente configurada. Deixe em branco para manter inalterada.', 'participe-ibram') ?>
                    <?php else: ?>
                        <?= esc_html__('Nenhuma senha configurada ainda.', 'participe-ibram') ?>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="pi_smtp_from_email"><?= esc_html__('From: e-mail', 'participe-ibram') ?></label></th>
            <td>
                <input type="email" id="pi_smtp_from_email" name="from_email" value="<?= esc_attr($fromEmail) ?>"
                       class="regular-text" autocomplete="off">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="pi_smtp_from_name"><?= esc_html__('From: nome', 'participe-ibram') ?></label></th>
            <td>
                <input type="text" id="pi_smtp_from_name" name="from_name" value="<?= esc_attr($fromName) ?>"
                       class="regular-text" autocomplete="off">
            </td>
        </tr>
        </tbody>
    </table>

    <input type="hidden" name="_wpnonce" value="<?= esc_attr($nonce) ?>">
    <p class="submit">
        <button type="submit" class="button button-primary" id="pi-email-save-btn">
            <?= esc_html__('Salvar', 'participe-ibram') ?>
        </button>
        <button type="button" class="button" id="pi-email-test-btn"
                data-nonce="<?= esc_attr(function_exists('wp_create_nonce') ? wp_create_nonce('pi_admin_email_test_smtp') : '') ?>">
            <?= esc_html__('Enviar teste para mim', 'participe-ibram') ?>
        </button>
        <span id="pi-email-config-feedback" role="status" aria-live="polite" style="margin-left:12px;"></span>
    </p>
</form>
