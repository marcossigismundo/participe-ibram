<?php
/**
 * Exceção lançada quando um usuário tenta acessar/modificar um recurso que
 * não lhe pertence (violação de ownership).
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\MinhaConta;

use RuntimeException;

/**
 * Sinaliza tentativa de bypass de ownership na área autenticada do agente.
 *
 * Toda instância DEVE ser auditada via {@see \Ibram\ParticipeIbram\Core\Audit\AuditLogger}
 * com `acao='ownership_denied'` antes do throw — ver {@see OwnershipResolver::assertOwnership()}.
 *
 * Mapeada para HTTP 403 com mensagem genérica (não revela existência de outros cadastros).
 */
final class OwnershipDeniedException extends RuntimeException
{
}
