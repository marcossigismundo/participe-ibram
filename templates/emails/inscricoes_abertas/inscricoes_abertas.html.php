<?php
/**
 * Template HTML — inscricoes_abertas (broadcast a elegíveis)
 *
 * Broadcast: NAO inclui {nome} nem dados pessoais (R5 L-03).
 * Vars: edital_titulo, periodo, inscricao_url, unsubscribe_url, dpo_email.
 *
 * Comunicacao obrigatoria institucional — Despacho 98/2025 IBRAM item 7.
 *
 * @var array<string,mixed> $vars
 * @var callable(string|null):string $_e
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$editalTitulo  = isset($vars['edital_titulo']) ? (string) $vars['edital_titulo'] : '';
$periodo       = isset($vars['periodo']) ? (string) $vars['periodo'] : '';
$inscricaoUrl  = isset($vars['inscricao_url']) ? (string) $vars['inscricao_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Inscricoes abertas no Participe Ibram</h1>
<p style="margin:0 0 12px;">Ola,</p>
<p style="margin:0 0 12px;">
    As inscricoes para o edital abaixo estao abertas. Verifique sua elegibilidade
    e inscreva-se pelo sistema.
</p>

<?php if ($editalTitulo !== ''): ?>
<p style="margin:0 0 12px;font-size:18px;"><strong><?php echo $_e($editalTitulo); ?></strong></p>
<?php endif; ?>

<?php if ($periodo !== ''): ?>
<p style="margin:0 0 12px;padding:12px;background:#e8f0ff;border-left:4px solid #1351b4;">
    <strong>Periodo de inscricoes:</strong> <?php echo $_e($periodo); ?>
</p>
<?php endif; ?>

<?php if ($inscricaoUrl !== ''): ?>
<p style="margin:0 0 16px;">
    <a href="<?php echo $_e($inscricaoUrl); ?>"
       style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
              text-decoration:none;border-radius:4px;">
        Ver edital e inscrever-se
    </a>
</p>
<?php endif; ?>

<p style="margin:0;color:#555;font-size:14px;">
    Atenciosamente,<br>
    Equipe do Participe Ibram
</p>
