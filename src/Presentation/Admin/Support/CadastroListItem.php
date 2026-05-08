<?php
/**
 * DTO read-model para as List Tables de Cadastros (fila de análise + todos).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

use DateTimeImmutable;

/**
 * Struct imutável que agrega Agente + detalhes do tipo + atribuição de
 * análise para evitar N+1 nas list tables.
 *
 * Não inclui CPF/CNPJ (R5 V-01) — listagens administrativas mostram apenas
 * nome, email, número de registro (quando deferido), tipo, status e a
 * atribuição corrente.
 */
final class CadastroListItem
{
    public int $agenteId;
    public string $tipo;
    public string $statusCadastro;
    public ?string $numeroRegistro;
    public string $emailPrincipal;
    public string $nome;
    public ?string $estado;
    public ?DateTimeImmutable $submetidoEm;
    public ?DateTimeImmutable $deferidoEm;
    public ?int $analistaId;
    public ?string $analistaNome;
    public ?int $tempoEmAnaliseDias;

    public function __construct(
        int $agenteId,
        string $tipo,
        string $statusCadastro,
        ?string $numeroRegistro,
        string $emailPrincipal,
        string $nome,
        ?string $estado,
        ?DateTimeImmutable $submetidoEm,
        ?DateTimeImmutable $deferidoEm,
        ?int $analistaId,
        ?string $analistaNome,
        ?int $tempoEmAnaliseDias
    ) {
        $this->agenteId           = $agenteId;
        $this->tipo               = $tipo;
        $this->statusCadastro     = $statusCadastro;
        $this->numeroRegistro     = $numeroRegistro;
        $this->emailPrincipal     = $emailPrincipal;
        $this->nome               = $nome;
        $this->estado             = $estado;
        $this->submetidoEm        = $submetidoEm;
        $this->deferidoEm         = $deferidoEm;
        $this->analistaId         = $analistaId;
        $this->analistaNome       = $analistaNome;
        $this->tempoEmAnaliseDias = $tempoEmAnaliseDias;
    }
}
