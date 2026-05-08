<?php
/**
 * Entidade Categoria — espelha `wp_pi_edital_categorias` (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use InvalidArgumentException;

/**
 * Categoria pertencente a um edital. Define vagas, suplentes, tipos de agente
 * elegíveis (CSV PF/OR/SM) e a lista de códigos de tipo de documento exigidos.
 *
 * Cross-domain: a comparação de tipo é feita via STRING (não pelo VO
 * {@see \Ibram\ParticipeIbram\Domain\Agente\TipoAgente}) para preservar
 * desacoplamento entre domínios — handlers convertem `TipoAgente` para string
 * antes de chamar {@see aceitaTipoAgente()}.
 */
final class Categoria
{
    /**
     * Tipos válidos para `tiposAgenteElegivel` (mantemos a string canônica
     * sem importar o VO de outro domínio).
     */
    private const TIPOS_VALIDOS = ['PF', 'OR', 'SM'];

    private ?int $id;
    private int $editalId;
    private string $nome;
    private ?string $descricaoMd;
    private int $numVagas;
    private int $numSuplentes;
    private string $tiposAgenteElegivel;
    private ?string $criteriosMd;

    /**
     * @var array<int,string>
     */
    private array $documentosExigidos;

    private int $ordem;

    /**
     * @param array<int,string> $documentosExigidos Lista de códigos de tipo_documento.
     */
    public function __construct(
        ?int $id,
        int $editalId,
        string $nome,
        ?string $descricaoMd,
        int $numVagas,
        int $numSuplentes,
        string $tiposAgenteElegivel,
        ?string $criteriosMd,
        array $documentosExigidos,
        int $ordem
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Categoria.id deve ser positivo quando informado.');
        }
        if ($editalId <= 0) {
            throw new InvalidArgumentException('Categoria.editalId deve ser positivo.');
        }
        $nomeTrim = trim($nome);
        if ($nomeTrim === '') {
            throw new InvalidArgumentException('Categoria.nome nao pode ser vazio.');
        }
        if (mb_strlen($nomeTrim) > 255) {
            throw new InvalidArgumentException('Categoria.nome excede 255 caracteres.');
        }
        if ($numVagas < 1) {
            throw new InvalidArgumentException('Categoria.numVagas deve ser >= 1.');
        }
        if ($numSuplentes < 0) {
            throw new InvalidArgumentException('Categoria.numSuplentes deve ser >= 0.');
        }
        if ($ordem < 0) {
            throw new InvalidArgumentException('Categoria.ordem deve ser >= 0.');
        }

        $tipos = self::normalizarTiposAgente($tiposAgenteElegivel);
        if ($tipos === '') {
            throw new InvalidArgumentException('Categoria.tiposAgenteElegivel nao pode ser vazio.');
        }

        $documentos = self::normalizarDocumentos($documentosExigidos);

        $this->id                  = $id;
        $this->editalId            = $editalId;
        $this->nome                = $nomeTrim;
        $this->descricaoMd         = $descricaoMd !== null ? $descricaoMd : null;
        $this->numVagas            = $numVagas;
        $this->numSuplentes        = $numSuplentes;
        $this->tiposAgenteElegivel = $tipos;
        $this->criteriosMd         = $criteriosMd !== null ? $criteriosMd : null;
        $this->documentosExigidos  = $documentos;
        $this->ordem               = $ordem;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function editalId(): int
    {
        return $this->editalId;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function descricaoMd(): ?string
    {
        return $this->descricaoMd;
    }

    public function numVagas(): int
    {
        return $this->numVagas;
    }

    public function numSuplentes(): int
    {
        return $this->numSuplentes;
    }

    public function tiposAgenteElegivel(): string
    {
        return $this->tiposAgenteElegivel;
    }

    public function criteriosMd(): ?string
    {
        return $this->criteriosMd;
    }

    /**
     * @return array<int,string>
     */
    public function documentosExigidos(): array
    {
        return $this->documentosExigidos;
    }

    public function ordem(): int
    {
        return $this->ordem;
    }

    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID deve ser positivo.');
        }
        $clone     = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /**
     * Verifica se a categoria aceita um agente do tipo informado.
     *
     * @param string $tipo Tipo canônico (PF/OR/SM); aceita variações de caixa.
     */
    public function aceitaTipoAgente(string $tipo): bool
    {
        $tipoNorm = strtoupper(trim($tipo));
        if (!in_array($tipoNorm, self::TIPOS_VALIDOS, true)) {
            return false;
        }
        $tipos = explode(',', $this->tiposAgenteElegivel);

        return in_array($tipoNorm, $tipos, true);
    }

    /**
     * Normaliza um CSV "PF, or , sm" -> "PF,OR,SM" mantendo apenas valores válidos
     * e únicos, preservando a ordem da entrada.
     *
     * @throws InvalidArgumentException Quando algum valor é desconhecido.
     */
    private static function normalizarTiposAgente(string $csv): string
    {
        $partes = array_map('trim', explode(',', $csv));
        $out    = [];
        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }
            $upper = strtoupper($parte);
            if (!in_array($upper, self::TIPOS_VALIDOS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Categoria.tiposAgenteElegivel: tipo invalido "%s". Esperado %s.',
                    $parte,
                    implode(', ', self::TIPOS_VALIDOS)
                ));
            }
            if (!in_array($upper, $out, true)) {
                $out[] = $upper;
            }
        }

        return implode(',', $out);
    }

    /**
     * @param array<int|string,mixed> $documentos
     *
     * @return array<int,string>
     */
    private static function normalizarDocumentos(array $documentos): array
    {
        $out = [];
        foreach ($documentos as $codigo) {
            if (!is_string($codigo)) {
                throw new InvalidArgumentException('Categoria.documentosExigidos: codigos devem ser strings.');
            }
            $cod = trim($codigo);
            if ($cod === '') {
                continue;
            }
            if (mb_strlen($cod) > 50) {
                throw new InvalidArgumentException(sprintf(
                    'Categoria.documentosExigidos: codigo "%s" excede 50 caracteres.',
                    $cod
                ));
            }
            if (!in_array($cod, $out, true)) {
                $out[] = $cod;
            }
        }

        return $out;
    }
}
