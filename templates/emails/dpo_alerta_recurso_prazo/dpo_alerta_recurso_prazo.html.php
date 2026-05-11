<?php
/**
 * Template HTML — dpo_alerta_recurso_prazo
 *
 * Vars: recurso_id, dias_restantes, painel_url, dpo_email, unsubscribe_url.
 * NUNCA inclui dados do agente recorrente — apenas ID do recurso e prazo.
 *
 * @var array<string,mixed> $vars
 * @var callable(string|null):string $_e
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$recursoId     = isset($vars['recurso_id']) ? (int) $vars['recurso_id'] : 0;
$diasRestantes = isset($vars['dias_restantes']) ? (int) $vars['dias_restantes'] : 0;
$painelUrl     = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
$urgente       = $diasRestantes <= 1;
$corBorda      = $urgente ? '#d63638' : '#dba617';
?>
<h1 style="font-size:18px;margin:0 0 16px;color:#1351b4;">
    Alerta DPO — Recurso com prazo vencendo
</h1>
<p style="margin:0 0 12px;">Encarregado(a),</p>
<p style="margin:0 0 12px;">
    O recurso administrativo <strong>#<?php echo $_e((string) $recursoId); ?></strong>
    vence em <strong><?php echo $_e((string) $diasRestantes); ?></strong> dia(s)
    e ainda nao foi decidido.
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#fff8e1;border-left:4px solid <?php echo $_e($corBorda); ?>;border-radius:0 4px 4px 0;">
            <strong>Recurso:</strong> #<?php echo $_e((string) $recursoId); ?><br>
            <strong>Dias restantes:</strong> <?php echo $_e((string) $diasRestantes); ?>
        </td>
    </tr>
</table>

<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($painelUrl); ?>"
       style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
              text-decoration:none;border-radius:4px;">
        Acessar painel de recursos
    </a>
</p>
<?php endif; ?>

<p style="margin:0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este e-mail.
</p>
