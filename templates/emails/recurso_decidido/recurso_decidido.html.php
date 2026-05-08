<?php
/**
 * Template HTML — recurso_decidido.
 *
 * Vars: nome, decisao ('deferido'|'mantido'), instancia ('analise'|'presidencia'),
 * numero_registro (somente quando deferido), painel_url, unsubscribe_url, dpo_email.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome       = isset($vars['nome']) ? (string) $vars['nome'] : '';
$decisao    = isset($vars['decisao']) ? (string) $vars['decisao'] : '';
$instancia  = isset($vars['instancia']) ? (string) $vars['instancia'] : '';
$numero     = isset($vars['numero_registro']) ? (string) $vars['numero_registro'] : '';
$painelUrl  = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';

$deferido = ($decisao === 'deferido' || $decisao === 'reconsiderado');
$cor      = $deferido ? '#168821' : '#d4ac0d';
$titulo   = $deferido ? 'Recurso provido' : 'Decisao do recurso';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:<?= $_e($cor) ?>;"><?= $_e($titulo) ?></h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<?php if ($deferido): ?>
<p style="margin:0 0 12px;">
  Seu recurso<?= $instancia !== '' ? ' (instancia: ' . $_e($instancia) . ')' : '' ?>
  foi <strong>provido</strong> e seu cadastro esta deferido.
</p>
<?php if ($numero !== ''): ?>
<p style="margin:0 0 12px;padding:12px;background:#e8f5ea;border-left:4px solid #168821;">
  <strong>Numero de registro:</strong>
  <span style="font-family:monospace;font-size:16px;"><?= $_e($numero) ?></span>
</p>
<?php endif; ?>
<?php else: ?>
<p style="margin:0 0 12px;">
  Apos analise do recurso<?= $instancia !== '' ? ' (instancia: ' . $_e($instancia) . ')' : '' ?>,
  a decisao anterior foi <strong>mantida</strong>.
</p>
<p style="margin:0 0 12px;">
  O parecer fundamentado da decisao esta disponivel no seu painel.
</p>
<?php endif; ?>
<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($painelUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Ver detalhes no painel
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
