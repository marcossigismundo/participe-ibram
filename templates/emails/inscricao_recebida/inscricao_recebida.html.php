<?php
/**
 * Template HTML — inscricao_recebida.
 *
 * Vars: nome, edital_titulo, vaga, painel_url, unsubscribe_url, dpo_email.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome          = isset($vars['nome']) ? (string) $vars['nome'] : '';
$editalTitulo  = isset($vars['edital_titulo']) ? (string) $vars['edital_titulo'] : '';
$vaga          = isset($vars['vaga']) ? (string) $vars['vaga'] : '';
$painelUrl     = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Inscricao recebida</h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<p style="margin:0 0 12px;">
  Confirmamos sua inscricao no edital
  <?php if ($editalTitulo !== ''): ?><strong><?= $_e($editalTitulo) ?></strong><?php endif; ?>.
</p>
<?php if ($vaga !== ''): ?>
<p style="margin:0 0 12px;">
  <strong>Vaga:</strong> <?= $_e($vaga) ?>
</p>
<?php endif; ?>
<p style="margin:0 0 12px;">
  Sua inscricao sera analisada conforme as etapas previstas no edital. Voce
  sera notificado por e-mail sobre o resultado da habilitacao.
</p>
<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($painelUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Acompanhar no painel
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
