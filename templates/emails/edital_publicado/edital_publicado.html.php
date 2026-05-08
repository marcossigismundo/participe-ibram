<?php
/**
 * Template HTML — edital_publicado (broadcast).
 *
 * Vars: edital_titulo, edital_resumo, periodo_inscricao, edital_url,
 * unsubscribe_url, dpo_email.
 *
 * Broadcast: NAO inclui {nome} para evitar PII em massa (R5 L-03). Mensagem
 * e generica.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$titulo            = isset($vars['edital_titulo']) ? (string) $vars['edital_titulo'] : '';
$resumo            = isset($vars['edital_resumo']) ? (string) $vars['edital_resumo'] : '';
$periodoInscricao  = isset($vars['periodo_inscricao']) ? (string) $vars['periodo_inscricao'] : '';
$editalUrl         = isset($vars['edital_url']) ? (string) $vars['edital_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Novo edital publicado</h1>
<p style="margin:0 0 12px;">Ola,</p>
<p style="margin:0 0 12px;">
  Foi publicado um novo edital no Participe Ibram.
</p>
<?php if ($titulo !== ''): ?>
<p style="margin:0 0 12px;font-size:18px;"><strong><?= $_e($titulo) ?></strong></p>
<?php endif; ?>
<?php if ($resumo !== ''): ?>
<p style="margin:0 0 12px;"><?= $_e($resumo) ?></p>
<?php endif; ?>
<?php if ($periodoInscricao !== ''): ?>
<p style="margin:0 0 12px;padding:12px;background:#e8f0ff;border-left:4px solid #1351b4;">
  <strong>Periodo de inscricoes:</strong> <?= $_e($periodoInscricao) ?>
</p>
<?php endif; ?>
<?php if ($editalUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($editalUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Ver edital completo
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
