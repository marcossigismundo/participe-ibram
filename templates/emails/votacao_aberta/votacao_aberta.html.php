<?php
/**
 * Template HTML — votacao_aberta (broadcast).
 *
 * Vars: edital_titulo, periodo_votacao, votar_url, unsubscribe_url, dpo_email.
 * Broadcast: SEM PII.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$titulo            = isset($vars['edital_titulo']) ? (string) $vars['edital_titulo'] : '';
$periodoVotacao    = isset($vars['periodo_votacao']) ? (string) $vars['periodo_votacao'] : '';
$votarUrl          = isset($vars['votar_url']) ? (string) $vars['votar_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Votacao aberta</h1>
<p style="margin:0 0 12px;">Ola,</p>
<p style="margin:0 0 12px;">
  Esta aberta a votacao no Participe Ibram.
</p>
<?php if ($titulo !== ''): ?>
<p style="margin:0 0 12px;font-size:18px;"><strong><?= $_e($titulo) ?></strong></p>
<?php endif; ?>
<?php if ($periodoVotacao !== ''): ?>
<p style="margin:0 0 12px;padding:12px;background:#e8f0ff;border-left:4px solid #1351b4;">
  <strong>Periodo de votacao:</strong> <?= $_e($periodoVotacao) ?>
</p>
<?php endif; ?>
<p style="margin:0 0 12px;">
  Sua participacao e essencial para legitimar a representacao social no Ibram.
</p>
<?php if ($votarUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($votarUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Acessar votacao
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
