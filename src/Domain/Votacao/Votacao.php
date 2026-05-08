<?php
/**
 * Entidade Votacao — espelha `wp_pi_votacoes` (SCHEMA §5).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Agregado-raiz da votação.
 *
 * Invariantes:
 *  - `encerramento > abertura`.
 *  - Estado inicial é `agendada`. Transições obedecem {@see StatusVotacao}.
 *  - Para `abrir()`: o estado precisa ser `agendada` e o "agora" deve estar
 *    dentro da janela [abertura, encerramento].
 *  - Para `encerrar()`: o estado precisa ser `aberta`. Recebe o hash do
 *    conjunto pré-apurado, que é congelado neste agregado para auditoria.
 *  - Para `apurar()`: o estado precisa ser `encerrada`.
 *  - Para `cancelar()`: o estado precisa ser `agendada` ou `aberta`.
 *
 * Sem setters expostos — apenas as transições explícitas mutam estado.
 */
final class Votacao
{
    private ?int $id;

    private int $editalId;

    private DateTimeImmutable $abertura;

    private DateTimeImmutable $encerramento;

    private StatusVotacao $status;

    private ModoVotacao $modo;

    private ?string $hashPreApuracao;

    private ?DateTimeImmutable $apuradoEm;

    /**
     * Função de "agora" — injetável para testes determinísticos.
     *
     * @var callable():DateTimeImmutable
     */
    private $clock;

    /**
     * @throws InvalidArgumentException Quando invariantes locais falham.
     */
    public function __construct(
        ?int $id,
        int $editalId,
        DateTimeImmutable $abertura,
        DateTimeImmutable $encerramento,
        StatusVotacao $status,
        ModoVotacao $modo,
        ?string $hashPreApuracao = null,
        ?DateTimeImmutable $apuradoEm = null,
        ?callable $clock = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Votacao.id deve ser positivo quando informado.');
        }
        if ($editalId <= 0) {
            throw new InvalidArgumentException('Votacao.editalId deve ser positivo.');
        }
        if ($encerramento <= $abertura) {
            throw new InvalidArgumentException(
                'Votacao: encerramento deve ser estritamente maior que abertura.'
            );
        }
        if ($hashPreApuracao !== null) {
            self::assertHashHex64($hashPreApuracao, 'hashPreApuracao');
        }

        $this->id              = $id;
        $this->editalId        = $editalId;
        $this->abertura        = $abertura;
        $this->encerramento    = $encerramento;
        $this->status          = $status;
        $this->modo            = $modo;
        $this->hashPreApuracao = $hashPreApuracao;
        $this->apuradoEm       = $apuradoEm;
        $this->clock           = $clock ?? static fn (): DateTimeImmutable
            => new DateTimeImmutable('now');
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('id deve ser positivo.');
        }
        $clone     = clone $this;
        $clone->id = $id;
        return $clone;
    }

    public function editalId(): int
    {
        return $this->editalId;
    }

    public function abertura(): DateTimeImmutable
    {
        return $this->abertura;
    }

    public function encerramento(): DateTimeImmutable
    {
        return $this->encerramento;
    }

    public function status(): StatusVotacao
    {
        return $this->status;
    }

    public function modo(): ModoVotacao
    {
        return $this->modo;
    }

    public function hashPreApuracao(): ?string
    {
        return $this->hashPreApuracao;
    }

    public function apuradoEm(): ?DateTimeImmutable
    {
        return $this->apuradoEm;
    }

    /**
     * Transição agendada → aberta.
     *
     * Exige que o "agora" esteja dentro da janela [abertura, encerramento].
     *
     * @throws IllegalStateTransition Se o estado atual não é `agendada` ou
     *                                a janela temporal não comporta.
     */
    public function abrir(): void
    {
        $alvo = StatusVotacao::aberta();
        if (!$this->status->canTransitionTo($alvo)) {
            throw IllegalStateTransition::between($this->status, $alvo);
        }

        $now = ($this->clock)();
        if ($now < $this->abertura || $now >= $this->encerramento) {
            throw new IllegalStateTransition(
                'Votacao nao pode ser aberta fora da janela [abertura, encerramento].'
            );
        }

        $this->status = $alvo;
    }

    /**
     * Transição aberta → encerrada.
     *
     * Recebe o hash do conjunto de votos pré-apurado para auditoria pública.
     *
     * @param string $hashPreApuracao SHA-256 hex (64 chars).
     */
    public function encerrar(string $hashPreApuracao): void
    {
        self::assertHashHex64($hashPreApuracao, 'hashPreApuracao');

        $alvo = StatusVotacao::encerrada();
        if (!$this->status->canTransitionTo($alvo)) {
            throw IllegalStateTransition::between($this->status, $alvo);
        }

        $this->status          = $alvo;
        $this->hashPreApuracao = $hashPreApuracao;
    }

    /**
     * Transição encerrada → apurada.
     *
     * @throws IllegalStateTransition
     */
    public function apurar(): void
    {
        $alvo = StatusVotacao::apurada();
        if (!$this->status->canTransitionTo($alvo)) {
            throw IllegalStateTransition::between($this->status, $alvo);
        }
        if ($this->hashPreApuracao === null) {
            throw new IllegalStateTransition(
                'Votacao nao pode ser apurada sem hashPreApuracao registrado.'
            );
        }

        $this->status    = $alvo;
        $this->apuradoEm = ($this->clock)();
    }

    /**
     * Transição agendada|aberta → cancelada.
     *
     * @throws IllegalStateTransition
     */
    public function cancelar(): void
    {
        $alvo = StatusVotacao::cancelada();
        if (!$this->status->canTransitionTo($alvo)) {
            throw IllegalStateTransition::between($this->status, $alvo);
        }
        $this->status = $alvo;
    }

    /**
     * Indica se um timestamp arbitrário cai dentro da janela aberta.
     */
    public function dentroDaJanela(DateTimeImmutable $when): bool
    {
        return $when >= $this->abertura && $when < $this->encerramento;
    }

    /**
     * @throws InvalidArgumentException Quando o valor não é 64 chars hex.
     */
    private static function assertHashHex64(string $value, string $fieldName): void
    {
        if (strlen($value) !== 64 || !ctype_xdigit($value)) {
            throw new InvalidArgumentException(sprintf(
                'Votacao.%s deve ser hex de 64 chars.',
                $fieldName
            ));
        }
    }
}
