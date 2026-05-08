<?php
/**
 * Port: aloca um número de registro do agente.
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

/**
 * Anti-corruption layer mínima sobre {@see \Ibram\ParticipeIbram\Core\Database\SequenceGenerator}.
 *
 * Existe para permitir testes unitários dos handlers de cadastro (a classe
 * `SequenceGenerator` é `final` e exige `wpdb` real). Implementação concreta
 * delega ao gerador de sequência (com lock pessimista).
 */
interface NumeroRegistroAllocator
{
    /**
     * Aloca o próximo número canônico no formato `PI-{TIPO}-{ANO}-{SEQ06}`.
     *
     * @param string $tipo Discriminador (`PF`, `OR`, `SM`).
     */
    public function alocar(string $tipo): string;
}
