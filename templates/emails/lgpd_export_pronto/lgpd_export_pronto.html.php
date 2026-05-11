<?php
/**
 * Template HTML — lgpd_export_pronto.
 *
 * Notifica que o ZIP de portabilidade está pronto (LGPD Art. 18, II e V).
 *
 * Vars: nome, download_url, expira_em (ISO8601), dpo_email, unsubscribe_url.
 *
 * NUNCA inclui CPF/RG/conteúdo do export — apenas o nome e o link assinado.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome   = isset($vars['nome']) ? (string) $vars['nome'] : '';
$url    = isset($vars['download_url']) ? (string) $vars['download_url'] : '';
$expira = isset($vars['expira_em']) ? (string) $vars['expira_em'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">
    Seu pacote de dados esta pronto
</h1>

<p style="margin:0 0 12px;">Ola <?php echo $_e($nome); ?>,</p>

<p style="margin:0 0 12px;">
    Geramos o pacote com seus dados pessoais (Portabilidade — LGPD Art. 18, V).
    O arquivo contem suas informacoes em formato JSON e CSV, alem do historico
    de consentimentos e a politica vigente.
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#f0f9ff;border-left:4px solid #1351b4;border-radius:0 4px 4px 0;">
            <strong>Validade do link:</strong> <?php echo $_e($expira); ?><br>
            <strong>Tempo restante:</strong> ate 24 horas
        </td>
    </tr>
</table>

<?php if ($url !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($url); ?>"
       style="display:inline-block;padding:12px 20px;background:#1351b4;color:#ffffff;
              text-decoration:none;border-radius:4px;font-weight:600;">
        Baixar meu pacote
    </a>
</p>
<p style="margin:0 0 12px;font-size:13px;color:#555;">
    Se o botao nao funcionar, copie este endereco:<br>
    <span style="word-break:break-all;color:#1351b4;"><?php echo $_e($url); ?></span>
</p>
<?php endif; ?>

<p style="margin:16px 0 12px;font-size:14px;color:#555;">
    <strong>Importante:</strong> o link e pessoal e expira em 24 horas. Nao compartilhe.
    Apos a expiracao, voce podera solicitar um novo export pelo Painel "Minha conta".
</p>

<p style="margin:16px 0 0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este email.
</p>
