<?php
/**
 * Template HTML — dpo_alerta_solicitacao_art18
 *
 * Vars: solicitacao_id, dias_restantes, painel_url, dpo_email, unsubscribe_url.
 * NUNCA inclui nome/CPF/dados pessoais do titular — apenas ID e prazo.
 *
 * @var array<string,mixed> $vars
 * @var callable(string|null):string $_e
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$solicitacaoId  = isset($vars['solicitacao_id']) ? (int) $vars['solicitacao_id'] : 0;
$diasRestantes  = isset($vars['dias_restantes']) ? (int) $vars['dias_restantes'] : 0;
$painelUrl      = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
$urgente        = $diasRestantes <= 2;
$corBorda       = $urgente ? '#d63638' : '#dba617';
$labelUrgencia  = $urgente ? 'URGENTE — ' : '';
?>
<h1 style="font-size:18px;margin:0 0 16px;color:#1351b4;">
    <?php echo $_e($labelUrgencia); ?>Alerta LGPD — Solicitacao Art.&nbsp;18 vencendo
</h1>
<p style="margin:0 0 12px;">Encarregado(a),</p>
<p style="margin:0 0 12px;">
    Existe uma solicitacao de direito do titular (LGPD Art.&nbsp;18) que vence
    em <strong><?php echo $_e((string) $diasRestantes); ?></strong> dia(s).
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#fff8e1;border-left:4px solid <?php echo $_e($corBorda); ?>;border-radius:0 4px 4px 0;">
            <strong>Numero da solicitacao:</strong> #<?php echo $_e((string) $solicitacaoId); ?><br>
            <strong>Prazo restante:</strong> <?php echo $_e((string) $diasRestantes); ?> dia(s)
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-size:13px;color:#555;">
    O prazo legal para atendimento e de 15 dias uteis (LGPD Art.&nbsp;18 c/c ARCHITECTURE TD-08).
    O nao atendimento no prazo pode gerar penalidades administrativas (LGPD Art.&nbsp;52).
</p>

<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($painelUrl); ?>"
       style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
              text-decoration:none;border-radius:4px;">
        Acessar painel DPO
    </a>
</p>
<?php endif; ?>

<p style="margin:0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este e-mail.
</p>
