<?php
/**
 * Entidade TipoDocumento — espelha `wp_pi_tipos_documento` (SCHEMA §2).
 *
 * @package Ibram\ParticipeIbram\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Documento;

use InvalidArgumentException;

/**
 * Tipo de documento exigido por agentes/inscrições (cnpj, estatuto, ata_posse, ...).
 *
 * Imutável — qualquer alteração deve gerar nova instância pelo repositório.
 * Lista canônica seed em `VOCABULARIES.md` §13.
 */
final class TipoDocumento
{
    private ?int $id;

    private string $codigo;

    private string $nome;

    private ?string $descricao;

    private ?string $obrigatorioPara;

    private string $mimePermitidos;

    private int $tamanhoMaxKb;

    private bool $ativo;

    private int $ordem;

    public function __construct(
        ?int $id,
        string $codigo,
        string $nome,
        ?string $descricao,
        ?string $obrigatorioPara,
        string $mimePermitidos,
        int $tamanhoMaxKb,
        bool $ativo = true,
        int $ordem = 0
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('TipoDocumento.id deve ser positivo quando informado.');
        }
        if (trim($codigo) === '') {
            throw new InvalidArgumentException('TipoDocumento.codigo nao pode ser vazio.');
        }
        if (trim($nome) === '') {
            throw new InvalidArgumentException('TipoDocumento.nome nao pode ser vazio.');
        }
        if (trim($mimePermitidos) === '') {
            throw new InvalidArgumentException('TipoDocumento.mimePermitidos nao pode ser vazio.');
        }
        if ($tamanhoMaxKb <= 0) {
            throw new InvalidArgumentException('TipoDocumento.tamanhoMaxKb deve ser positivo.');
        }
        if ($ordem < 0) {
            throw new InvalidArgumentException('TipoDocumento.ordem nao pode ser negativo.');
        }

        $this->id              = $id;
        $this->codigo          = strtolower(trim($codigo));
        $this->nome            = trim($nome);
        $this->descricao       = $descricao !== null ? trim($descricao) : null;
        $this->obrigatorioPara = $obrigatorioPara !== null ? trim($obrigatorioPara) : null;
        $this->mimePermitidos  = trim($mimePermitidos);
        $this->tamanhoMaxKb    = $tamanhoMaxKb;
        $this->ativo           = $ativo;
        $this->ordem           = $ordem;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function descricao(): ?string
    {
        return $this->descricao;
    }

    public function obrigatorioPara(): ?string
    {
        return $this->obrigatorioPara;
    }

    public function mimePermitidosCsv(): string
    {
        return $this->mimePermitidos;
    }

    public function tamanhoMaxKb(): int
    {
        return $this->tamanhoMaxKb;
    }

    public function tamanhoMaxBytes(): int
    {
        return $this->tamanhoMaxKb * 1024;
    }

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function ordem(): int
    {
        return $this->ordem;
    }

    /**
     * Lista normalizada de MIMEs permitidos.
     *
     * @return list<string>
     */
    public function mimePermitidosArray(): array
    {
        $parts = array_map('trim', explode(',', $this->mimePermitidos));
        $parts = array_filter($parts, static fn (string $m): bool => $m !== '');
        $parts = array_map('strtolower', $parts);

        return array_values(array_unique($parts));
    }

    /**
     * Verifica se este tipo é obrigatório para um determinado tipo de agente.
     *
     * Ex.: `obrigatorio_para = 'PF,OR'` retorna true para "PF" e "OR".
     * Comparação case-insensitive.
     */
    public function isObrigatorioParaTipoAgente(string $tipoAgente): bool
    {
        if ($this->obrigatorioPara === null || $this->obrigatorioPara === '') {
            return false;
        }
        $needle = strtoupper(trim($tipoAgente));
        if ($needle === '') {
            return false;
        }
        $tipos = array_map(
            static fn (string $t): string => strtoupper(trim($t)),
            explode(',', $this->obrigatorioPara)
        );
        $tipos = array_filter($tipos, static fn (string $t): bool => $t !== '');

        return in_array($needle, $tipos, true);
    }

    /**
     * Verifica se um MIME-type está permitido para este tipo de documento.
     */
    public function permiteMime(string $mime): bool
    {
        $needle = strtolower(trim($mime));
        if ($needle === '') {
            return false;
        }

        return in_array($needle, $this->mimePermitidosArray(), true);
    }
}
