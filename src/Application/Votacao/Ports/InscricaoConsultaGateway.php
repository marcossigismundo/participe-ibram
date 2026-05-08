<?php
/**
 * Port: consulta de Inscrição (candidatura), do ponto de vista da Votação.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Ports
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Ports;

/**
 * Forward reference para o domínio Edital/Inscrição (Wave D5 / Onda 3).
 *
 * Votação só precisa saber se uma inscrição é candidata válida (final_habilitada)
 * naquela categoria. Não acessa portfólio nem outros campos.
 */
interface InscricaoConsultaGateway
{
    /**
     * Indica se a inscrição está com status `final_habilitado` na categoria informada.
     */
    public function isCandidatoFinalHabilitado(int $inscricaoId, int $categoriaId): bool;

    /**
     * Retorna a data de inscrição (`inscrito_em`) — usada como tie-break na apuração.
     *
     * Retorna null se a inscrição não existir ou não tiver `inscrito_em` definido.
     *
     * @return \DateTimeImmutable|null
     */
    public function inscritoEm(int $inscricaoId): ?\DateTimeImmutable;
}
