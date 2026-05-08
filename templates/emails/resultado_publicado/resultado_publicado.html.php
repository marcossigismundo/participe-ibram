<?php
/**
 * Template HTML — resultado_publicado (broadcast).
 *
 * Vars: edital_titulo, resultado_url, unsubscribe_url, dpo_email.
 * Broadcast: SEM PII (Despacho 98/2025 IBRAM item 7).
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$titulo         = isset($vars['edital_titulo']) ? (string) $vars['edital_titulo'] : '';
$resultadoUrl   = isset($vars['resultado_url']) ? (string) $vars['resultado_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Resultado publicado</h1>
<p style="margin:0 0 12px;">Ola,</p>
<p style="margin:0 0 12px;">
  Foi publicado o resultado oficial do processo
  <?php if ($titulo !== ''): ?><strong><?= $_e($titulo) ?></strong><?php endif; ?>
  no Participe Ibram.
</p>
<p style="margin:0 0 12px;">
  Voce pode consultar o resultado completo (lista de aprovados/eleitos) no link
  abaixo. A publicacao oficial atende ao Despacho 98/2025 do IBRAM.
</p>
<?php if ($resultadoUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($resultadoUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Ver resultado completo
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
