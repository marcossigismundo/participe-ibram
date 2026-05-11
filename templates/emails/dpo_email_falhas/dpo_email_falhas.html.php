<?php
/**
 * Template HTML — dpo_email_falhas
 *
 * Vars: total_falhas, painel_url, dpo_email, unsubscribe_url.
 * NUNCA inclui PII, enderecos de email das mensagens com falha, ou conteudo
 * das mensagens — apenas a contagem de falhas.
 *
 * @var array<string,mixed> $vars
 * @var callable(string|null):string $_e
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$totalFalhas = isset($vars['total_falhas']) ? (int) $vars['total_falhas'] : 0;
$painelUrl   = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:18px;margin:0 0 16px;color:#dba617;">
    Alerta de Sistema — Falhas na Fila de E-mail
</h1>
<p style="margin:0 0 12px;">Sysadmin,</p>
<p style="margin:0 0 12px;">
    Foram detectadas <strong><?php echo $_e((string) $totalFalhas); ?></strong>
    falha(s) na fila de e-mail do Participe Ibram desde o ultimo monitoramento.
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#fff8e1;border-left:4px solid #dba617;border-radius:0 4px 4px 0;">
            <strong>Falhas detectadas:</strong> <?php echo $_e((string) $totalFalhas); ?><br>
            <em style="font-size:13px;color:#666;">
                Este relatorio contem apenas contagens — nenhum dado pessoal esta incluido.
            </em>
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-weight:600;">Acoes recomendadas:</p>
<ol style="margin:0 0 16px;padding-left:20px;">
    <li style="margin-bottom:4px;">Verificar configuracao SMTP (host, porta, credenciais)</li>
    <li style="margin-bottom:4px;">Verificar logs do servidor de e-mail</li>
    <li style="margin-bottom:4px;">Reenviar mensagens com falha pelo painel</li>
</ol>

<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($painelUrl); ?>"
       style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
              text-decoration:none;border-radius:4px;">
        Acessar painel de e-mail
    </a>
</p>
<?php endif; ?>

<p style="margin:0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este e-mail.
</p>
