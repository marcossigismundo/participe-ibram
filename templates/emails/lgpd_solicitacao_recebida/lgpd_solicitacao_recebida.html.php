<?php
/**
 * Template HTML — lgpd_solicitacao_recebida.
 *
 * Confirmação ao titular quando ele protocola uma solicitação LGPD Art. 18.
 *
 * Vars: nome, solicitacao_id, tipo, minha_conta_url, dpo_email, unsubscribe_url.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome          = isset($vars['nome']) ? (string) $vars['nome'] : '';
$solicitacaoId = isset($vars['solicitacao_id']) ? (int) $vars['solicitacao_id'] : 0;
$tipo          = isset($vars['tipo']) ? (string) $vars['tipo'] : '';
$minhaConta    = isset($vars['minha_conta_url']) ? (string) $vars['minha_conta_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">
    Recebemos sua solicitacao LGPD
</h1>

<p style="margin:0 0 12px;">Ola <?php echo $_e($nome); ?>,</p>

<p style="margin:0 0 12px;">
    Sua solicitacao de direito do titular (LGPD Art. 18) foi protocolada com
    sucesso e sera analisada pelo Encarregado de Tratamento de Dados (DPO).
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#f0f9ff;border-left:4px solid #1351b4;border-radius:0 4px 4px 0;">
            <strong>Numero da solicitacao:</strong> #<?php echo $_e((string) $solicitacaoId); ?><br>
            <strong>Tipo:</strong> <?php echo $_e($tipo); ?><br>
            <strong>Prazo legal de resposta:</strong> ate 15 dias corridos (Art. 19, LGPD)
        </td>
    </tr>
</table>

<?php if ($minhaConta !== ''): ?>
<p style="margin:0 0 16px;">
    Voce pode acompanhar o andamento no
    <a href="<?php echo $_e($minhaConta); ?>" style="color:#1351b4;">Painel "Minha conta"</a>.
</p>
<?php endif; ?>

<p style="margin:16px 0 0;color:#555;font-size:13px;font-style:italic;">
    Esta mensagem e automatica do sistema Participe Ibram. Nao responda este email.
</p>
