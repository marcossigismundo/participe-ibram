<?php
/**
 * Template HTML — dpo_solicitacao_vencida_art18 (urgente)
 *
 * Vars: solicitacao_id, dias_atraso, painel_url, dpo_email, unsubscribe_url.
 * NUNCA inclui PII do titular — apenas ID e dias de atraso.
 *
 * @var array<string,mixed> $vars
 * @var callable(string|null):string $_e
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$solicitacaoId = isset($vars['solicitacao_id']) ? (int) $vars['solicitacao_id'] : 0;
$diasAtraso    = isset($vars['dias_atraso']) ? (int) $vars['dias_atraso'] : 0;
$painelUrl     = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:18px;margin:0 0 16px;color:#d63638;">
    ATENCAO URGENTE — Solicitacao LGPD Vencida
</h1>
<p style="margin:0 0 12px;">Encarregado(a),</p>
<p style="margin:0 0 12px;">
    A solicitacao de direito do titular a seguir <strong style="color:#d63638;">ESTA VENCIDA</strong>
    ha <strong><?php echo $_e((string) $diasAtraso); ?></strong> dia(s).
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#fef2f2;border-left:4px solid #d63638;border-radius:0 4px 4px 0;">
            <strong>Numero da solicitacao:</strong> #<?php echo $_e((string) $solicitacaoId); ?><br>
            <strong>Atraso:</strong> <?php echo $_e((string) $diasAtraso); ?> dia(s) apos o prazo legal de 15 dias uteis
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-size:13px;color:#555;">
    O atraso pode configurar infracao administrativa (LGPD Art.&nbsp;52) e deve ser
    registrado no relatorio de incidentes se aplicavel (prazo ANPD: 3 dias uteis
    para comunicacao ao titular — R2-lgpd.md §8).
</p>

<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($painelUrl); ?>"
       style="display:inline-block;padding:10px 16px;background:#d63638;color:#ffffff;
              text-decoration:none;border-radius:4px;">
        Acessar painel DPO agora
    </a>
</p>
<?php endif; ?>

<p style="margin:0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este e-mail.
</p>
