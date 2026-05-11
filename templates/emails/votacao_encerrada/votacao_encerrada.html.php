<?php
/**
 * Template HTML — votacao_encerrada
 *
 * Destinatario: gestor do edital.
 * Vars: nome, edital_titulo, votacao_id, apuracao_url, painel_url, dpo_email, unsubscribe_url.
 * Inclui somente nome do gestor (sem CPF/dados sensiveis).
 *
 * @var array<string,mixed> $vars
 * @var callable(string|null):string $_e
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome          = isset($vars['nome']) ? (string) $vars['nome'] : 'Gestor';
$editalTitulo  = isset($vars['edital_titulo']) ? (string) $vars['edital_titulo'] : '';
$apuracaoUrl   = isset($vars['apuracao_url']) ? (string) $vars['apuracao_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Votacao encerrada — acao necessaria</h1>
<p style="margin:0 0 12px;">Prezado(a) <?php echo $_e($nome); ?>,</p>
<p style="margin:0 0 12px;">
    A votacao do edital <strong><?php echo $_e($editalTitulo); ?></strong>
    foi encerrada e aguarda apuracao.
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
    <tr>
        <td style="padding:12px;background:#e8f0ff;border-left:4px solid #1351b4;border-radius:0 4px 4px 0;">
            <strong>Proximo passo:</strong> Acessar o painel de apuracao, conferir os votos
            e publicar o resultado.
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-size:13px;color:#555;">
    Conforme Despacho 98/2025 IBRAM item 7, o resultado deve ser publicado apos
    a apuracao e comunicado automaticamente a todos os cadastrados.
</p>

<?php if ($apuracaoUrl !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($apuracaoUrl); ?>"
       style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
              text-decoration:none;border-radius:4px;">
        Iniciar apuracao
    </a>
</p>
<?php endif; ?>

<p style="margin:0;color:#555;font-size:14px;">
    Atenciosamente,<br>
    Equipe do Participe Ibram
</p>
