<?php
/**
 * Template HTML — cadastro_submetido.
 *
 * Vars esperadas: nome (string), data_submissao (string formatada), painel_url (string),
 * unsubscribe_url, dpo_email.
 *
 * Wave 10 adicionará tradução. Este arquivo é estático em pt_BR por enquanto.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome           = isset($vars['nome']) ? (string) $vars['nome'] : '';
$dataSubmissao  = isset($vars['data_submissao']) ? (string) $vars['data_submissao'] : '';
$painelUrl      = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Recebemos sua submissao</h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<p style="margin:0 0 12px;">
  Recebemos sua submissao ao Cadastro de Agentes para Participacao Social do
  Instituto Brasileiro de Museus (Ibram).
</p>
<?php if ($dataSubmissao !== ''): ?>
<p style="margin:0 0 12px;">
  <strong>Data da submissao:</strong> <?= $_e($dataSubmissao) ?>
</p>
<?php endif; ?>
<p style="margin:0 0 12px;">
  Sua submissao sera analisada conforme a Portaria IBRAM 3230/2024. Voce sera
  notificado por e-mail sobre o resultado da analise. Nao e necessario
  reenviar o formulario.
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
