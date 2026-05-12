<?php
/**
 * Tab Configuração SMTP.
 *
 * Vars: snapshot (array), nonce (string).
 * Incluído dentro de email/index.php (já dentro de PageLayout chrome).
 *
 * W11-C: adicionado wrapper .pi-form + .pi-field-group.
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

<div class="pi-form">
    <form id="pi-email-config-form" method="post" action="#" autocomplete="off"
          aria-labelledby="pi-config-title">
        <h2 id="pi-config-title"><?php esc_html_e('Configuração SMTP', 'participe-ibram'); ?></h2>
        <p><?php esc_html_e('Use estes campos para configurar o servidor SMTP. A senha é armazenada cifrada (libsodium).', 'participe-ibram'); ?></p>

        <div class="pi-field-group">
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="pi_smtp_host"><?php esc_html_e('Servidor (host)', 'participe-ibram'); ?></label></th>
                    <td>
                        <input type="text" id="pi_smtp_host" name="host" value="<?php echo esc_attr($host); ?>"
                               class="regular-text" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pi_smtp_port"><?php esc_html_e('Porta', 'participe-ibram'); ?></label></th>
                    <td>
                        <input type="number" id="pi_smtp_port" name="port" value="<?php echo esc_attr((string) $port); ?>"
                               min="1" max="65535" inputmode="numeric">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pi_smtp_encryption"><?php esc_html_e('Encriptação', 'participe-ibram'); ?></label></th>
                    <td>
                        <select id="pi_smtp_encryption" name="encryption">
                            <option value="" <?php echo $enc === '' ? 'selected' : ''; ?>><?php esc_html_e('Nenhuma', 'participe-ibram'); ?></option>
                            <option value="tls" <?php echo $enc === 'tls' ? 'selected' : ''; ?>>STARTTLS</option>
                            <option value="ssl" <?php echo $enc === 'ssl' ? 'selected' : ''; ?>>SSL/TLS</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pi_smtp_user"><?php esc_html_e('Usuário', 'participe-ibram'); ?></label></th>
                    <td>
                        <input type="text" id="pi_smtp_user" name="user" value="<?php echo esc_attr($user); ?>"
                               class="regular-text" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pi_smtp_password"><?php esc_html_e('Senha', 'participe-ibram'); ?></label></th>
                    <td>
                        <input type="password" id="pi_smtp_password" name="password" value=""
                               class="regular-text" autocomplete="new-password"
                               aria-describedby="pi_smtp_password_hint">
                        <p class="description" id="pi_smtp_password_hint">
                            <?php if ($passwordSet) : ?>
                                <?php esc_html_e('Senha atualmente configurada. Deixe em branco para manter inalterada.', 'participe-ibram'); ?>
                            <?php else : ?>
                                <?php esc_html_e('Nenhuma senha configurada ainda.', 'participe-ibram'); ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pi_smtp_from_email"><?php esc_html_e('From: e-mail', 'participe-ibram'); ?></label></th>
                    <td>
                        <input type="email" id="pi_smtp_from_email" name="from_email" value="<?php echo esc_attr($fromEmail); ?>"
                               class="regular-text" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pi_smtp_from_name"><?php esc_html_e('From: nome', 'participe-ibram'); ?></label></th>
                    <td>
                        <input type="text" id="pi_smtp_from_name" name="from_name" value="<?php echo esc_attr($fromName); ?>"
                               class="regular-text" autocomplete="off">
                    </td>
                </tr>
                </tbody>
            </table>
        </div><!-- .pi-field-group -->

        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
        <p class="submit">
            <button type="submit" class="button button-primary" id="pi-email-save-btn">
                <?php esc_html_e('Salvar', 'participe-ibram'); ?>
            </button>
            <button type="button" class="button" id="pi-email-test-btn"
                    data-nonce="<?php echo esc_attr(function_exists('wp_create_nonce') ? wp_create_nonce('pi_admin_email_test_smtp') : ''); ?>">
                <?php esc_html_e('Enviar teste para mim', 'participe-ibram'); ?>
            </button>
            <span id="pi-email-config-feedback" role="status" aria-live="polite" style="margin-left:12px;"></span>
        </p>
    </form>
</div><!-- .pi-form -->
