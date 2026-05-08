<?php
/**
 * Entidade que representa uma linha de `wp_pi_email_queue`.
 *
 * @package Ibram\ParticipeIbram\Domain\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Email;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Mensagem enfileirada para envio assíncrono.
 *
 * As propriedades são imutáveis ao nível do objeto: mutações de status passam
 * por métodos `with*` que retornam uma NOVA instância. Isso evita que o
 * worker mute a entidade enquanto outro processo a lê.
 *
 * Campos refletem 1:1 a tabela `{$wpdb->prefix}pi_email_queue`
 * (SCHEMA.md V001 §"email_queue").
 */
final class MensagemEnfileirada
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_ENVIANDO = 'enviando';
    public const STATUS_ENVIADO  = 'enviado';
    public const STATUS_FALHOU   = 'falhou';

    /** @var array<int,string> */
    private const STATUSES = [
        self::STATUS_PENDENTE,
        self::STATUS_ENVIANDO,
        self::STATUS_ENVIADO,
        self::STATUS_FALHOU,
    ];

    private ?int $id;
    private string $evento;
    private ?int $agenteId;
    private string $destinatario;
    private string $assunto;
    private string $corpoHtml;
    /** @var array<string,mixed>|null */
    private ?array $payloadJson;
    private int $tentativas;
    private string $status;
    private ?string $ultimoErro;
    private DateTimeImmutable $agendadoPara;
    private ?DateTimeImmutable $enviadoEm;
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string,mixed>|null $payloadJson
     */
    private function __construct(
        ?int $id,
        string $evento,
        ?int $agenteId,
        string $destinatario,
        string $assunto,
        string $corpoHtml,
        ?array $payloadJson,
        int $tentativas,
        string $status,
        ?string $ultimoErro,
        DateTimeImmutable $agendadoPara,
        ?DateTimeImmutable $enviadoEm,
        DateTimeImmutable $createdAt
    ) {
        if ($evento === '') {
            throw new InvalidArgumentException('evento nao pode ser vazio.');
        }
        if ($destinatario === '') {
            throw new InvalidArgumentException('destinatario nao pode ser vazio.');
        }
        if ($assunto === '') {
            throw new InvalidArgumentException('assunto nao pode ser vazio.');
        }
        if (strlen($assunto) > 255) {
            throw new InvalidArgumentException('assunto excede 255 chars.');
        }
        if ($corpoHtml === '') {
            throw new InvalidArgumentException('corpoHtml nao pode ser vazio.');
        }
        if ($tentativas < 0) {
            throw new InvalidArgumentException('tentativas nao pode ser negativo.');
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(
                'status invalido: "%s". Esperado: %s.',
                $status,
                implode(', ', self::STATUSES)
            ));
        }

        $this->id            = $id;
        $this->evento        = $evento;
        $this->agenteId      = $agenteId;
        $this->destinatario  = $destinatario;
        $this->assunto       = $assunto;
        $this->corpoHtml     = $corpoHtml;
        $this->payloadJson   = $payloadJson;
        $this->tentativas    = $tentativas;
        $this->status        = $status;
        $this->ultimoErro    = $ultimoErro;
        $this->agendadoPara  = $agendadoPara;
        $this->enviadoEm     = $enviadoEm;
        $this->createdAt     = $createdAt;
    }

    /**
     * Factory para uma mensagem nova (status pendente, sem id).
     *
     * @param array<string,mixed>|null $payloadJson
     */
    public static function paraEnfileirar(
        string $evento,
        ?int $agenteId,
        string $destinatario,
        string $assunto,
        string $corpoHtml,
        ?array $payloadJson,
        DateTimeImmutable $agendadoPara,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            null,
            $evento,
            $agenteId,
            $destinatario,
            $assunto,
            $corpoHtml,
            $payloadJson,
            0,
            self::STATUS_PENDENTE,
            null,
            $agendadoPara,
            null,
            $createdAt
        );
    }

    /**
     * Hidratação a partir de um row do banco. NÃO valida transição (já é estado).
     *
     * @param array<string,mixed>|null $payloadJson
     */
    public static function fromState(
        int $id,
        string $evento,
        ?int $agenteId,
        string $destinatario,
        string $assunto,
        string $corpoHtml,
        ?array $payloadJson,
        int $tentativas,
        string $status,
        ?string $ultimoErro,
        DateTimeImmutable $agendadoPara,
        ?DateTimeImmutable $enviadoEm,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            $id,
            $evento,
            $agenteId,
            $destinatario,
            $assunto,
            $corpoHtml,
            $payloadJson,
            $tentativas,
            $status,
            $ultimoErro,
            $agendadoPara,
            $enviadoEm,
            $createdAt
        );
    }

    /* =====================================================================
     * Acessores
     * ===================================================================== */

    public function id(): ?int
    {
        return $this->id;
    }

    public function evento(): string
    {
        return $this->evento;
    }

    public function agenteId(): ?int
    {
        return $this->agenteId;
    }

    public function destinatario(): string
    {
        return $this->destinatario;
    }

    public function assunto(): string
    {
        return $this->assunto;
    }

    public function corpoHtml(): string
    {
        return $this->corpoHtml;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function payloadJson(): ?array
    {
        return $this->payloadJson;
    }

    public function tentativas(): int
    {
        return $this->tentativas;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function ultimoErro(): ?string
    {
        return $this->ultimoErro;
    }

    public function agendadoPara(): DateTimeImmutable
    {
        return $this->agendadoPara;
    }

    public function enviadoEm(): ?DateTimeImmutable
    {
        return $this->enviadoEm;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function isFalhouPermanente(): bool
    {
        return $this->status === self::STATUS_FALHOU;
    }

    /* =====================================================================
     * Mutações imutáveis (with*)
     * ===================================================================== */

    /**
     * Marca a mensagem como "enviando" (pre-envio). Tipicamente usada como
     * etapa intermediária antes de chamar wp_mail.
     */
    public function marcarEnviando(): self
    {
        $clone = clone $this;
        $clone->status = self::STATUS_ENVIANDO;
        $clone->tentativas = $this->tentativas + 1;

        return $clone;
    }

    /**
     * Marca como enviado com sucesso.
     */
    public function marcarEnviado(DateTimeImmutable $when): self
    {
        $clone = clone $this;
        $clone->status     = self::STATUS_ENVIADO;
        $clone->enviadoEm  = $when;
        $clone->ultimoErro = null;

        return $clone;
    }

    /**
     * Marca falha de envio. Mensagem (string) curta — sem PII e sem stack trace.
     */
    public function marcarFalha(string $erro): self
    {
        $clone = clone $this;
        $clone->status     = self::STATUS_FALHOU;
        $clone->ultimoErro = self::truncate($erro, 1000);

        return $clone;
    }

    /**
     * Reagenda para retry, deixando status como "pendente" novamente.
     */
    public function reagendar(DateTimeImmutable $proxima, string $erro): self
    {
        $clone = clone $this;
        $clone->status        = self::STATUS_PENDENTE;
        $clone->ultimoErro    = self::truncate($erro, 1000);
        $clone->agendadoPara  = $proxima;

        return $clone;
    }

    private static function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
