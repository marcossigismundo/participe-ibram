<?php
/**
 * Template HTML — cadastro_deferido.
 *
 * Vars: nome, numero_registro, painel_url, unsubscribe_url, dpo_email.
 * Wave 10 adicionará tradução.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome      = isset($vars['nome']) ? (string) $vars['nome'] : '';
$numero    = isset($vars['numero_registro']) ? (string) $vars['numero_registro'] : '';
$painelUrl = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#168821;">Seu cadastro foi deferido</h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<p style="margin:0 0 12px;">
  Seu cadastro no Participe Ibram foi <strong>deferido</strong>. Voce ja pode
  participar de editais e votacoes.
</p>
<p style="margin:0 0 12px;padding:12px;background:#e8f5ea;border-left:4px solid #168821;">
  <strong>Numero de registro:</strong>
  <span style="font-family:monospace;font-size:16px;"><?= $_e($numero) ?></span>
</p>
<p style="margin:0 0 12px;">
  Guarde este numero — ele e a sua identificacao publica como agente cadastrado.
</p>
<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($painelUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Acessar meu painel
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
