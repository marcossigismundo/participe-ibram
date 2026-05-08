<?php
/**
 * Entidade Documento — espelha `wp_pi_documentos` (SCHEMA §2).
 *
 * @package Ibram\ParticipeIbram\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Documento;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Entidade imutável que representa um documento anexado a um agente ou inscrição.
 *
 * Não há setters de mutação aberta. As únicas transições admitidas são as de
 * validação ({@see marcarValidado()} e {@see marcarInvalido()}) — toda outra
 * mudança implica criar uma nova instância.
 *
 * Convenção: identificadores numéricos não-negativos; `agente_id` e
 * `inscricao_id` são opcionais individualmente, porém pelo menos um deve ser
 * informado para preservar referência ao agregado pai. A regra é validada na
 * camada de aplicação (handlers); aqui mantemos invariantes locais.
 */
final class Documento
{
    private ?int $id;

    private ?int $agenteId;

    private ?int $inscricaoId;

    private int $tipoDocumentoId;

    private string $arquivoPath;

    private string $nomeOriginal;

    private string $mimeReal;

    private int $tamanhoBytes;

    private string $hashSha256;

    private int $uploadedBy;

    private DateTimeImmutable $uploadedAt;

    private bool $validado;

    private ?DateTimeImmutable $validadoEm;

    private ?int $validadoPor;

    private ?string $observacoesValidacao;

    /**
     * @param int|null               $id
     * @param int|null               $agenteId
     * @param int|null               $inscricaoId
     * @param int                    $tipoDocumentoId
     * @param string                 $arquivoPath          Path relativo ao base do PrivateFileStorage.
     * @param string                 $nomeOriginal         Nome original sanitizado.
     * @param string                 $mimeReal             MIME detectado por `finfo_file` (R5 V-15).
     * @param int                    $tamanhoBytes         Tamanho em bytes (>= 0).
     * @param string                 $hashSha256           Hash SHA-256 hexadecimal (64 chars).
     * @param int                    $uploadedBy           WP user id que efetuou o upload.
     * @param DateTimeImmutable      $uploadedAt
     * @param bool                   $validado
     * @param DateTimeImmutable|null $validadoEm
     * @param int|null               $validadoPor
     * @param string|null            $observacoesValidacao
     *
     * @throws InvalidArgumentException Quando invariantes locais falham.
     */
    public function __construct(
        ?int $id,
        ?int $agenteId,
        ?int $inscricaoId,
        int $tipoDocumentoId,
        string $arquivoPath,
        string $nomeOriginal,
        string $mimeReal,
        int $tamanhoBytes,
        string $hashSha256,
        int $uploadedBy,
        DateTimeImmutable $uploadedAt,
        bool $validado = false,
        ?DateTimeImmutable $validadoEm = null,
        ?int $validadoPor = null,
        ?string $observacoesValidacao = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Documento.id deve ser positivo quando informado.');
        }
        if ($agenteId !== null && $agenteId <= 0) {
            throw new InvalidArgumentException('Documento.agenteId deve ser positivo quando informado.');
        }
        if ($inscricaoId !== null && $inscricaoId <= 0) {
            throw new InvalidArgumentException('Documento.inscricaoId deve ser positivo quando informado.');
        }
        if ($tipoDocumentoId <= 0) {
            throw new InvalidArgumentException('Documento.tipoDocumentoId deve ser positivo.');
        }
        if (trim($arquivoPath) === '') {
            throw new InvalidArgumentException('Documento.arquivoPath nao pode ser vazio.');
        }
        if (trim($nomeOriginal) === '') {
            throw new InvalidArgumentException('Documento.nomeOriginal nao pode ser vazio.');
        }
        if (trim($mimeReal) === '') {
            throw new InvalidArgumentException('Documento.mimeReal nao pode ser vazio.');
        }
        if ($tamanhoBytes < 0) {
            throw new InvalidArgumentException('Documento.tamanhoBytes nao pode ser negativo.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $hashSha256)) {
            throw new InvalidArgumentException('Documento.hashSha256 deve ser SHA-256 hexadecimal (64 caracteres).');
        }
        if ($uploadedBy <= 0) {
            throw new InvalidArgumentException('Documento.uploadedBy deve ser positivo.');
        }
        if ($validado && $validadoEm === null) {
            throw new InvalidArgumentException('Documento.validadoEm e obrigatorio quando validado=true.');
        }
        if ($validadoPor !== null && $validadoPor <= 0) {
            throw new InvalidArgumentException('Documento.validadoPor deve ser positivo quando informado.');
        }

        $this->id                   = $id;
        $this->agenteId             = $agenteId;
        $this->inscricaoId          = $inscricaoId;
        $this->tipoDocumentoId      = $tipoDocumentoId;
        $this->arquivoPath          = $arquivoPath;
        $this->nomeOriginal         = $nomeOriginal;
        $this->mimeReal             = $mimeReal;
        $this->tamanhoBytes         = $tamanhoBytes;
        $this->hashSha256           = strtolower($hashSha256);
        $this->uploadedBy           = $uploadedBy;
        $this->uploadedAt           = $uploadedAt;
        $this->validado             = $validado;
        $this->validadoEm           = $validadoEm;
        $this->validadoPor          = $validadoPor;
        $this->observacoesValidacao = $observacoesValidacao;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function agenteId(): ?int
    {
        return $this->agenteId;
    }

    public function inscricaoId(): ?int
    {
        return $this->inscricaoId;
    }

    public function tipoDocumentoId(): int
    {
        return $this->tipoDocumentoId;
    }

    public function arquivoPath(): string
    {
        return $this->arquivoPath;
    }

    public function nomeOriginal(): string
    {
        return $this->nomeOriginal;
    }

    public function mimeReal(): string
    {
        return $this->mimeReal;
    }

    public function tamanhoBytes(): int
    {
        return $this->tamanhoBytes;
    }

    public function hashSha256(): string
    {
        return $this->hashSha256;
    }

    public function uploadedBy(): int
    {
        return $this->uploadedBy;
    }

    public function uploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function isValidado(): bool
    {
        return $this->validado;
    }

    public function validadoEm(): ?DateTimeImmutable
    {
        return $this->validadoEm;
    }

    public function validadoPor(): ?int
    {
        return $this->validadoPor;
    }

    public function observacoesValidacao(): ?string
    {
        return $this->observacoesValidacao;
    }

    /**
     * Atribui ID após persistência (chamado pelo repositório).
     */
    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID deve ser positivo.');
        }

        return new self(
            $id,
            $this->agenteId,
            $this->inscricaoId,
            $this->tipoDocumentoId,
            $this->arquivoPath,
            $this->nomeOriginal,
            $this->mimeReal,
            $this->tamanhoBytes,
            $this->hashSha256,
            $this->uploadedBy,
            $this->uploadedAt,
            $this->validado,
            $this->validadoEm,
            $this->validadoPor,
            $this->observacoesValidacao
        );
    }

    /**
     * Marca o documento como validado por um analista.
     *
     * @param int         $por WP user id (analista) — deve ter `pi_visualizar_documentos`.
     * @param string|null $obs Observações opcionais (ex.: "OK conforme original").
     *
     * @throws InvalidArgumentException
     */
    public function marcarValidado(int $por, ?string $obs = null): void
    {
        if ($por <= 0) {
            throw new InvalidArgumentException('marcarValidado: ator invalido.');
        }
        $this->validado             = true;
        $this->validadoPor          = $por;
        $this->validadoEm           = new DateTimeImmutable('now');
        $this->observacoesValidacao = $obs !== null ? trim($obs) : null;
    }

    /**
     * Marca o documento como invalidado, exigindo motivo.
     *
     * @param int    $por WP user id (analista).
     * @param string $obs Motivo (obrigatório).
     *
     * @throws InvalidArgumentException
     */
    public function marcarInvalido(int $por, string $obs): void
    {
        if ($por <= 0) {
            throw new InvalidArgumentException('marcarInvalido: ator invalido.');
        }
        $obsTrim = trim($obs);
        if ($obsTrim === '') {
            throw new InvalidArgumentException('marcarInvalido: observacao e obrigatoria.');
        }
        $this->validado             = false;
        $this->validadoPor          = $por;
        $this->validadoEm           = new DateTimeImmutable('now');
        $this->observacoesValidacao = $obsTrim;
    }
}
