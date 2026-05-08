<?php
/**
 * Repositório (interface) para a entidade TipoDocumento.
 *
 * @package Ibram\ParticipeIbram\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Documento;

/**
 * Contrato de leitura/consulta dos tipos de documento configurados.
 *
 * Tipos de documento são geralmente seedados via migration (V001) e raramente
 * editados em runtime; daí o foco em queries.
 */
interface TipoDocumentoRepository
{
    /**
     * @throws DocumentoNotFound Se o tipo não existe.
     */
    public function findById(int $id): TipoDocumento;

    /**
     * Localiza por código canônico (ex.: `cnpj`, `estatuto`).
     *
     * @throws DocumentoNotFound Se o código não existe.
     */
    public function findByCodigo(string $codigo): TipoDocumento;

    /**
     * Lista todos os tipos ativos ordenados por `ordem` ASC, `nome` ASC.
     *
     * @return list<TipoDocumento>
     */
    public function listAtivos(): array;

    /**
     * Lista os tipos obrigatórios para um determinado tipo de agente.
     *
     * Quando `tipoAgente='OR'` e `temCnpj=false`, retorna o conjunto pertinente
     * a coletivos sem CNPJ (ver VOCABULARIES.md §13: "OR sem CNPJ" exige
     * `carta_indicacao_coletivo`). Implementações DEVEM aplicar essa regra.
     *
     * @return list<TipoDocumento>
     */
    public function findObrigatoriosPara(string $tipoAgente, bool $temCnpj = true): array;
}
