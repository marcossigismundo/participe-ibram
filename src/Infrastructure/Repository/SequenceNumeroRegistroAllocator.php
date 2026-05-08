<?php
/**
 * Implementação concreta de {@see NumeroRegistroAllocator} usando
 * `SequenceGenerator`.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Application\Cadastro\NumeroRegistroAllocator;
use Ibram\ParticipeIbram\Core\Database\SequenceGenerator;

/**
 * Adapter que delega a alocação ao {@see SequenceGenerator} (que faz lock
 * pessimista em `wp_pi_sequencias` por (tipo, ano)).
 */
final class SequenceNumeroRegistroAllocator implements NumeroRegistroAllocator
{
    private SequenceGenerator $sequence;

    public function __construct(SequenceGenerator $sequence)
    {
        $this->sequence = $sequence;
    }

    public function alocar(string $tipo): string
    {
        return $this->sequence->next($tipo);
    }
}
