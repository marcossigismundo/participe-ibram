<?php
/**
 * Command imutável para enfileirar um e-mail (single-target).
 *
 * @package Ibram\ParticipeIbram\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Comando para enfileirar UMA mensagem em `wp_pi_email_queue`.
 *
 * Para envios broadcast (todos os agentes deferidos), use
 * {@see BroadcastEmailCommand} via {@see EnfileirarEmailHandler::broadcast}.
 */
final class EnfileirarEmailCommand
{
    private string $evento;
    private ?int $agenteId;
    private string $destinatario;
    /** @var array<string,mixed> */
    private array $vars;
    private ?DateTimeImmutable $agendadoPara;

    /**
     * @param array<string,mixed>   $vars         Variáveis para o renderer.
     * @param DateTimeImmutable|null $agendadoPara Quando null = NOW().
     */
    public function __construct(
        string $evento,
        ?int $agenteId,
        string $destinatario,
        array $vars,
        ?DateTimeImmutable $agendadoPara = null
    ) {
        if ($evento === '') {
            throw new InvalidArgumentException('evento nao pode ser vazio.');
        }
        if ($destinatario === '') {
            throw new InvalidArgumentException('destinatario nao pode ser vazio.');
        }
        if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('destinatario nao e um e-mail valido.');
        }
        if ($agenteId !== null && $agenteId < 1) {
            throw new InvalidArgumentException('agenteId, quando informado, deve ser >= 1.');
        }
        $this->evento       = $evento;
        $this->agenteId     = $agenteId;
        $this->destinatario = $destinatario;
        $this->vars         = $vars;
        $this->agendadoPara = $agendadoPara;
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

    /** @return array<string,mixed> */
    public function vars(): array
    {
        return $this->vars;
    }

    public function agendadoPara(): ?DateTimeImmutable
    {
        return $this->agendadoPara;
    }
}
