<?php
/**
 * Template HTML — habilitacao_decidida.
 *
 * Vars: nome, edital_titulo, decisao ('habilitado'|'inabilitado'),
 * painel_url, unsubscribe_url, dpo_email.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome          = isset($vars['nome']) ? (string) $vars['nome'] : '';
$editalTitulo  = isset($vars['edital_titulo']) ? (string) $vars['edital_titulo'] : '';
$decisao       = isset($vars['decisao']) ? (string) $vars['decisao'] : '';
$painelUrl     = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';

$habilitado = ($decisao === 'habilitado');
$cor        = $habilitado ? '#168821' : '#1351b4';
$titulo     = $habilitado ? 'Voce esta habilitado' : 'Resultado da habilitacao';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:<?= $_e($cor) ?>;"><?= $_e($titulo) ?></h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<p style="margin:0 0 12px;">
  <?php if ($habilitado): ?>
  Sua inscricao no edital <strong><?= $_e($editalTitulo) ?></strong> foi
  <strong>habilitada</strong>. Voce ja pode participar das proximas etapas.
  <?php else: ?>
  Apos analise, sua inscricao no edital <strong><?= $_e($editalTitulo) ?></strong>
  foi <strong>inabilitada</strong>. O parecer fundamentado esta disponivel no
  seu painel. Voce tem direito a apresentar recurso conforme prazos do edital.
  <?php endif; ?>
</p>
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
