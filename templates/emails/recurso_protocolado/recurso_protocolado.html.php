<?php
/**
 * Template HTML — recurso_protocolado.
 *
 * Vars: nome, numero_protocolo (string opcional), data_protocolo, painel_url,
 * unsubscribe_url, dpo_email.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome             = isset($vars['nome']) ? (string) $vars['nome'] : '';
$numeroProtocolo  = isset($vars['numero_protocolo']) ? (string) $vars['numero_protocolo'] : '';
$dataProtocolo    = isset($vars['data_protocolo']) ? (string) $vars['data_protocolo'] : '';
$painelUrl        = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Recurso recebido</h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<p style="margin:0 0 12px;">
  Confirmamos o recebimento do seu recurso. Ele sera analisado conforme os
  prazos e procedimentos estabelecidos na Portaria IBRAM 3230/2024.
</p>
<?php if ($numeroProtocolo !== ''): ?>
<p style="margin:0 0 12px;padding:12px;background:#e8f0ff;border-left:4px solid #1351b4;">
  <strong>Protocolo:</strong>
  <span style="font-family:monospace;font-size:14px;"><?= $_e($numeroProtocolo) ?></span><?php if ($dataProtocolo !== ''): ?>
  <br><strong>Data:</strong> <?= $_e($dataProtocolo) ?>
  <?php endif; ?>
</p>
<?php endif; ?>
<p style="margin:0 0 12px;">
  Voce sera notificado por e-mail quando houver decisao.
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
