<?php
/**
 * Port: consulta de Categoria do Edital, do ponto de vista da Votação.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Ports
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Ports;

/**
 * Forward reference para o domínio Edital (Wave D5 / Onda 3).
 *
 * O domínio Votação só precisa de duas perguntas sobre Categoria:
 *  1. A categoria pertence ao edital da votação?
 *  2. A categoria aceita o tipo de agente do eleitor?
 *
 * Adicionalmente expõe `numVagas` e `numSuplentes` para a apuração.
 */
interface CategoriaConsultaGateway
{
    /**
     * Retorna o `edital_id` da categoria, ou null se inexistente.
     */
    public function editalIdDaCategoria(int $categoriaId): ?int;

    /**
     * Indica se a categoria admite o tipo de agente informado (`PF`/`OR`/`SM`).
     */
    public function aceitaTipoAgente(int $categoriaId, string $tipoAgente): bool;

    /**
     * Número de vagas (top-N eleitos).
     */
    public function numVagas(int $categoriaId): int;

    /**
     * Número de suplentes (próximos N após os eleitos).
     */
    public function numSuplentes(int $categoriaId): int;

    /**
     * Lista de categorias do edital em ordem canônica.
     *
     * @return list<int>
     */
    public function listarCategoriasDoEdital(int $editalId): array;
}
