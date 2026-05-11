<?php
/**
 * Template HTML — lgpd_anonimizacao_executada.
 *
 * Confirmação enviada APÓS a anonimização. NÃO trata o destinatário pelo nome
 * (já foi anonimizado) e NÃO referencia outros dados pessoais.
 *
 * Vars: solicitacao_id, dpo_email, unsubscribe_url.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$solicitacaoId = isset($vars['solicitacao_id']) ? (int) $vars['solicitacao_id'] : 0;
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">
    Anonimizacao concluida
</h1>

<p style="margin:0 0 12px;">Ola,</p>

<p style="margin:0 0 12px;">
    Sua solicitacao de anonimizacao foi executada com sucesso. Seus dados pessoais
    foram removidos ou substituidos por valores anonimos no sistema Participe Ibram,
    conforme previsto na LGPD (Lei 13.709/2018, Art. 18, IV).
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#f0f9ff;border-left:4px solid #1351b4;border-radius:0 4px 4px 0;">
            <strong>Numero da solicitacao:</strong> #<?php echo $_e((string) $solicitacaoId); ?>
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-size:14px;">
    A trilha de auditoria foi preservada por <strong>obrigacao legal</strong>
    (LGPD Art. 16, II) e contera apenas o registro tecnico desta operacao, sem
    seus dados pessoais.
</p>

<p style="margin:0 0 12px;font-size:14px;">
    Voce nao podera mais acessar o sistema com a conta anonimizada. Se desejar
    voltar a participar, sera necessario realizar novo cadastro.
</p>

<p style="margin:16px 0 0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este email.
</p>
