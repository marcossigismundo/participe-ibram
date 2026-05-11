<?php
/**
 * Template HTML — lgpd_anonimizacao_link.
 *
 * Confirmação de pedido de anonimização (LGPD Art. 18, IV — IRREVERSÍVEL).
 *
 * Vars: nome, confirmacao_url, expira_em (ISO8601), solicitacao_id,
 * minha_conta_url, dpo_email, unsubscribe_url.
 *
 * **NUNCA** carrega CPF/RG/endereço/telefone — apenas nome (PI mínima, R5 V-04).
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome          = isset($vars['nome']) ? (string) $vars['nome'] : '';
$url           = isset($vars['confirmacao_url']) ? (string) $vars['confirmacao_url'] : '';
$expira        = isset($vars['expira_em']) ? (string) $vars['expira_em'] : '';
$solicitacaoId = isset($vars['solicitacao_id']) ? (int) $vars['solicitacao_id'] : 0;
$minhaConta    = isset($vars['minha_conta_url']) ? (string) $vars['minha_conta_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#c00;">
    Acao IRREVERSIVEL: confirme a anonimizacao do seu cadastro
</h1>

<p style="margin:0 0 12px;">Ola <?php echo $_e($nome); ?>,</p>

<p style="margin:0 0 12px;">
    Recebemos seu pedido de <strong>anonimizacao</strong> do cadastro no Participe Ibram
    (LGPD Art. 18, IV). Esta acao e <strong>IRREVERSIVEL</strong>: apos a confirmacao,
    seus dados pessoais serao removidos ou substituidos por valores anonimos.
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#fff3f3;border-left:4px solid #c00;border-radius:0 4px 4px 0;">
            <strong>Numero da solicitacao:</strong> #<?php echo $_e((string) $solicitacaoId); ?><br>
            <strong>Validade do link:</strong> <?php echo $_e($expira); ?> (24 horas)
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-weight:600;">Para confirmar, clique no botao abaixo:</p>

<?php if ($url !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($url); ?>"
       style="display:inline-block;padding:12px 20px;background:#c00;color:#ffffff;
              text-decoration:none;border-radius:4px;font-weight:600;">
        Confirmar anonimizacao
    </a>
</p>
<p style="margin:0 0 12px;font-size:13px;color:#555;">
    Se o botao nao funcionar, copie e cole este endereco no seu navegador:<br>
    <span style="word-break:break-all;color:#1351b4;"><?php echo $_e($url); ?></span>
</p>
<?php endif; ?>

<p style="margin:16px 0 8px;font-size:14px;">
    <strong>O que sera removido ou substituido</strong>
</p>
<ul style="margin:0 0 12px;padding-left:20px;font-size:14px;">
    <li>Nome, CPF/RG/Passaporte, telefone, email</li>
    <li>Arquivos de documentos enviados</li>
</ul>
<p style="margin:0 0 12px;font-size:14px;">
    <strong>O que sera preservado por obrigacao legal (LGPD Art. 16, II)</strong>
</p>
<ul style="margin:0 0 16px;padding-left:20px;font-size:14px;">
    <li>Trilha de auditoria (audit log) — exigida por lei</li>
    <li>Registro de consentimentos pretérito (anonimizado)</li>
</ul>

<p style="margin:16px 0 12px;font-size:14px;color:#555;">
    Se voce <strong>nao</strong> solicitou esta acao, basta ignorar este email — nada sera
    feito. Se preferir, faca login no
    <?php if ($minhaConta !== ''): ?>
        <a href="<?php echo $_e($minhaConta); ?>" style="color:#1351b4;">Painel "Minha conta"</a>
    <?php else: ?>
        Painel "Minha conta"
    <?php endif; ?>
    e revise suas solicitacoes.
</p>

<p style="margin:24px 0 0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este email.
</p>
