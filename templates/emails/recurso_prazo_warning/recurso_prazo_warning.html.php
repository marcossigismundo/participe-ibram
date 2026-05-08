<?php
/**
 * Template HTML — recurso_prazo_warning (D+2 antes do fim do prazo).
 *
 * Vars: nome, dias_restantes (int), data_limite, painel_url, unsubscribe_url, dpo_email.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome           = isset($vars['nome']) ? (string) $vars['nome'] : '';
$diasRestantes  = isset($vars['dias_restantes']) ? (string) $vars['dias_restantes'] : '';
$dataLimite     = isset($vars['data_limite']) ? (string) $vars['data_limite'] : '';
$painelUrl      = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#d4ac0d;">Prazo para recurso encerra em breve</h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<p style="margin:0 0 12px;padding:12px;background:#fff8e1;border-left:4px solid #d4ac0d;">
  <?php if ($diasRestantes !== ''): ?>
  <strong>Dias restantes:</strong> <?= $_e($diasRestantes) ?>
  <?php endif; ?>
  <?php if ($dataLimite !== ''): ?>
  <br><strong>Data limite:</strong> <?= $_e($dataLimite) ?>
  <?php endif; ?>
</p>
<p style="margin:0 0 12px;">
  Voce ainda pode protocolar recurso para o indeferimento do seu cadastro.
  Apos o encerramento do prazo, a decisao se torna definitiva.
</p>
<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($painelUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Protocolar recurso agora
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
