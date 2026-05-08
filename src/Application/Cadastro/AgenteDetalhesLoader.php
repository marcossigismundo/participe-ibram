<?php
/**
 * Port: carrega detalhes tipológicos + representantes de um agente já
 * persistido (read-side complement do `AgenteRepository::save()`).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;

/**
 * Os handlers de Cadastro precisam, em transições de status, repassar
 * `detalhes` e `representantes` ao `AgenteRepository::save()` — mas só têm em
 * mãos o ID do agente. Esta porta isola essa leitura para evitar acoplamento
 * direto com a infraestrutura.
 *
 * Implementação concreta (Wpdb) reside em
 * {@see \Ibram\ParticipeIbram\Infrastructure\Repository\WpdbAgenteDetalhesLoader}.
 */
interface AgenteDetalhesLoader
{
    /**
     * @return AgentePF|AgenteOR|AgenteSM Detalhes coerentes com o tipo do agente.
     */
    public function loadDetalhes(int $agenteId, string $tipoAgente): object;

    /**
     * @return array<int,\Ibram\ParticipeIbram\Domain\Agente\Representante>
     */
    public function loadRepresentantes(int $agenteId): array;
}
