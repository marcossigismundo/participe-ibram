<?php
/**
 * Template HTML — cadastro_indeferido.
 *
 * Vars: nome, prazo_recurso (string ex. "10 dias corridos"), data_limite_recurso,
 * painel_url, unsubscribe_url, dpo_email.
 *
 * NUNCA inclui parecer integral ou dados sensiveis (R5 L-03). O agente acessa
 * o detalhe via painel autenticado.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

/** @var callable(string|null):string $_e */
$_e = $vars['_e'];

$nome              = isset($vars['nome']) ? (string) $vars['nome'] : '';
$prazo             = isset($vars['prazo_recurso']) ? (string) $vars['prazo_recurso'] : '10 dias corridos';
$dataLimiteRecurso = isset($vars['data_limite_recurso']) ? (string) $vars['data_limite_recurso'] : '';
$painelUrl         = isset($vars['painel_url']) ? (string) $vars['painel_url'] : '';
?>
<h1 style="font-size:20px;margin:0 0 16px;color:#1351b4;">Decisao sobre seu cadastro</h1>
<p style="margin:0 0 12px;">Ola <?= $_e($nome) ?>,</p>
<p style="margin:0 0 12px;">
  Apos analise tecnica, seu cadastro no Participe Ibram foi <strong>indeferido</strong>.
</p>
<p style="margin:0 0 12px;">
  O parecer fundamentado da analise esta disponivel para consulta no seu painel.
  Voce tem direito a apresentar recurso.
</p>
<p style="margin:0 0 12px;padding:12px;background:#fff8e1;border-left:4px solid #d4ac0d;">
  <strong>Prazo para recurso:</strong> <?= $_e($prazo) ?><?php if ($dataLimiteRecurso !== ''): ?>
  &middot; <strong>Data limite:</strong> <?= $_e($dataLimiteRecurso) ?>
  <?php endif; ?>
</p>
<?php if ($painelUrl !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $_e($painelUrl) ?>"
     style="display:inline-block;padding:10px 16px;background:#1351b4;color:#ffffff;
            text-decoration:none;border-radius:4px;">
    Ver parecer e protocolar recurso
  </a>
</p>
<?php endif; ?>
<p style="margin:0;color:#555;font-size:14px;">
  Atenciosamente,<br>
  Equipe do Participe Ibram
</p>
