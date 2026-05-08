<?php
/**
 * Entidade raiz do agregado "Agente" (TD-01, TD-02, TD-05).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Núcleo do agente cadastrado: identificador, tipo, status, e timestamps de
 * transições. Os dados específicos de cada tipologia ficam em sub-entidades
 * (`AgentePF`, `AgenteOR`, `AgenteSM`) acessadas via repositório.
 *
 * Toda mudança de status passa por {@see StatusCadastro::canTransitionTo()};
 * tentativas inválidas lançam {@see IllegalStateTransition}.
 */
final class Agente
{
    /** @var int|null Identificador interno (NULL antes do primeiro `save`). */
    private ?int $id;

    private TipoAgente $tipo;

    /** @var NumeroRegistro|null Gerado somente após deferimento (TD-02). */
    private ?NumeroRegistro $numeroRegistro;

    private StatusCadastro $statusCadastro;

    private ?int $userId;
    private string $emailPrincipal;
    private ?string $telefone;

    private ?DateTimeImmutable $submetidoEm;
    private ?DateTimeImmutable $deferidoEm;
    private ?DateTimeImmutable $publicadoEm;

    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private ?DateTimeImmutable $deletedAt;

    /**
     * @param int|null               $id              NULL para novos agregados.
     * @param TipoAgente             $tipo            Discriminador imutável após criado.
     * @param NumeroRegistro|null    $numeroRegistro  Apenas após deferimento.
     * @param StatusCadastro         $statusCadastro  Estado atual.
     * @param int|null               $userId          WP user dono.
     * @param string                 $emailPrincipal  Único na tabela.
     * @param string|null            $telefone        Telefone livre.
     * @param DateTimeImmutable|null $submetidoEm
     * @param DateTimeImmutable|null $deferidoEm
     * @param DateTimeImmutable|null $publicadoEm
     * @param DateTimeImmutable      $createdAt
     * @param DateTimeImmutable      $updatedAt
     * @param DateTimeImmutable|null $deletedAt       Soft-delete LGPD.
     *
     * @throws InvalidArgumentException Quando `emailPrincipal` é vazio ou inconsistência de
     *                                  invariantes (deferido sem número de registro etc.).
     */
    public function __construct(
        ?int $id,
        TipoAgente $tipo,
        ?NumeroRegistro $numeroRegistro,
        StatusCadastro $statusCadastro,
        ?int $userId,
        string $emailPrincipal,
        ?string $telefone,
        ?DateTimeImmutable $submetidoEm,
        ?DateTimeImmutable $deferidoEm,
        ?DateTimeImmutable $publicadoEm,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt
    ) {
        $email = trim($emailPrincipal);
        if ($email === '') {
            throw new InvalidArgumentException('Agente: emailPrincipal nao pode ser vazio.');
        }
        if ($statusCadastro->isDeferido() && $numeroRegistro === null) {
            throw new InvalidArgumentException(
                'Agente: status deferido exige numero_registro.'
            );
        }

        $this->id              = $id;
        $this->tipo            = $tipo;
        $this->numeroRegistro  = $numeroRegistro;
        $this->statusCadastro  = $statusCadastro;
        $this->userId          = $userId;
        $this->emailPrincipal  = $email;
        $this->telefone        = $telefone;
        $this->submetidoEm     = $submetidoEm;
        $this->deferidoEm      = $deferidoEm;
        $this->publicadoEm     = $publicadoEm;
        $this->createdAt       = $createdAt;
        $this->updatedAt       = $updatedAt;
        $this->deletedAt       = $deletedAt;
    }

    /**
     * Cria um novo agente em estado inicial (rascunho).
     */
    public static function novo(
        TipoAgente $tipo,
        string $emailPrincipal,
        ?int $userId = null,
        ?string $telefone = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now = $now ?? new DateTimeImmutable('now');

        return new self(
            null,
            $tipo,
            null,
            StatusCadastro::rascunho(),
            $userId,
            $emailPrincipal,
            $telefone,
            null,
            null,
            null,
            $now,
            $now,
            null
        );
    }

    // -------- Getters --------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): TipoAgente
    {
        return $this->tipo;
    }

    public function getNumeroRegistro(): ?NumeroRegistro
    {
        return $this->numeroRegistro;
    }

    public function getStatusCadastro(): StatusCadastro
    {
        return $this->statusCadastro;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getEmailPrincipal(): string
    {
        return $this->emailPrincipal;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function getSubmetidoEm(): ?DateTimeImmutable
    {
        return $this->submetidoEm;
    }

    public function getDeferidoEm(): ?DateTimeImmutable
    {
        return $this->deferidoEm;
    }

    public function getPublicadoEm(): ?DateTimeImmutable
    {
        return $this->publicadoEm;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Indica se o agregado já foi persistido pelo menos uma vez.
     */
    public function isPersisted(): bool
    {
        return $this->id !== null;
    }

    /**
     * Atribui o id após o primeiro INSERT.
     *
     * Pacote interno: deve ser chamado apenas pelo repositório. Não é
     * `private` porque PHP não suporta `package-private`; protegido por
     * convenção (e validado: id só pode ser atribuído uma vez).
     *
     * @throws InvalidArgumentException Se id já estiver definido.
     */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('Agente: id ja foi atribuido.');
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('Agente: id deve ser positivo.');
        }
        $this->id = $id;
    }

    // -------- Transições (TD-05) --------

    /**
     * rascunho -> submetido. Marca `submetido_em` com timestamp atual.
     *
     * @throws IllegalStateTransition Se o estado atual não for rascunho.
     */
    public function submeter(?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::submetido();
        $this->guardTransition($target);

        $now = $now ?? new DateTimeImmutable('now');
        $this->statusCadastro = $target;
        $this->submetidoEm    = $now;
        $this->touch($now);
    }

    /**
     * submetido -> em_analise.
     *
     * @throws IllegalStateTransition
     */
    public function iniciarAnalise(?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::emAnalise();
        $this->guardTransition($target);
        $this->statusCadastro = $target;
        $this->touch($now);
    }

    /**
     * em_analise -> deferido (final). Recebe o número de registro gerado.
     *
     * O número deve ser fornecido por quem coordena a operação (geralmente
     * `SequenceGenerator` invocado pelo caso de uso DeferirCadastro). Aqui a
     * entidade apenas valida que ele foi atribuído.
     *
     * @throws IllegalStateTransition Se a transição não for permitida.
     * @throws InvalidArgumentException Se o número de registro for de tipo divergente.
     */
    public function deferir(NumeroRegistro $numero, ?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::deferido();
        $this->guardTransition($target);
        $this->guardNumeroRegistroTipo($numero);

        $now = $now ?? new DateTimeImmutable('now');
        $this->statusCadastro = $target;
        $this->numeroRegistro = $numero;
        $this->deferidoEm     = $now;
        $this->touch($now);
    }

    /**
     * em_analise -> indeferido_aguardando_recurso. Inicia prazo de 10 dias
     * para recurso (Portaria 3230 Art. 7º).
     *
     * @throws IllegalStateTransition
     */
    public function indeferir(?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::indeferidoAguardandoRecurso();
        $this->guardTransition($target);
        $this->statusCadastro = $target;
        $this->touch($now);
    }

    /**
     * indeferido_aguardando_recurso -> em_retratacao.
     *
     * @throws IllegalStateTransition
     */
    public function protocolarRecurso(?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::emRetratacao();
        $this->guardTransition($target);
        $this->statusCadastro = $target;
        $this->touch($now);
    }

    /**
     * em_retratacao -> deferido_em_retratacao (final). Gera número de registro.
     *
     * @throws IllegalStateTransition
     * @throws InvalidArgumentException Se o número for de tipo divergente.
     */
    public function reconsiderar(NumeroRegistro $numero, ?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::deferidoEmRetratacao();
        $this->guardTransition($target);
        $this->guardNumeroRegistroTipo($numero);

        $now = $now ?? new DateTimeImmutable('now');
        $this->statusCadastro = $target;
        $this->numeroRegistro = $numero;
        $this->deferidoEm     = $now;
        $this->touch($now);
    }

    /**
     * em_retratacao -> em_recurso_presidencia (mantém o indeferimento, segue p/ presidência).
     *
     * @throws IllegalStateTransition
     */
    public function manterIndeferimento(?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::emRecursoPresidencia();
        $this->guardTransition($target);
        $this->statusCadastro = $target;
        $this->touch($now);
    }

    /**
     * em_recurso_presidencia -> deferido_em_recurso (final, defere) ou
     * em_recurso_presidencia -> indeferido_final (mantém indeferimento).
     *
     * @param bool                $deferido `true` para deferir, `false` para indeferir.
     * @param NumeroRegistro|null $numero   Obrigatório se `$deferido` for true.
     *
     * @throws IllegalStateTransition
     * @throws InvalidArgumentException Quando `$deferido = true` e `$numero` é null
     *                                  ou quando o tipo do número diverge.
     */
    public function decidirRecursoPresidencia(
        bool $deferido,
        ?NumeroRegistro $numero = null,
        ?DateTimeImmutable $now = null
    ): void {
        if ($deferido) {
            if ($numero === null) {
                throw new InvalidArgumentException(
                    'Agente: numero de registro obrigatorio ao deferir em recurso de presidencia.'
                );
            }
            $target = StatusCadastro::deferidoEmRecurso();
            $this->guardTransition($target);
            $this->guardNumeroRegistroTipo($numero);

            $now = $now ?? new DateTimeImmutable('now');
            $this->statusCadastro = $target;
            $this->numeroRegistro = $numero;
            $this->deferidoEm     = $now;
            $this->touch($now);

            return;
        }

        $target = StatusCadastro::indeferidoFinal();
        $this->guardTransition($target);
        $this->statusCadastro = $target;
        $this->touch($now);
    }

    /**
     * indeferido_aguardando_recurso -> indeferido_final (cron de prazo).
     *
     * @throws IllegalStateTransition
     */
    public function prazoExpirado(?DateTimeImmutable $now = null): void
    {
        $target = StatusCadastro::indeferidoFinal();
        $this->guardTransition($target);
        $this->statusCadastro = $target;
        $this->touch($now);
    }

    /**
     * Marca o instante em que a publicação no site Ibram foi gerada.
     *
     * Não é uma transição de status — é metadado. O caso de uso decide quando
     * chamar (após gerar snapshot/hash/URL conforme TD-14 Art. 8º).
     */
    public function marcarPublicado(?DateTimeImmutable $now = null): void
    {
        $now = $now ?? new DateTimeImmutable('now');
        $this->publicadoEm = $now;
        $this->touch($now);
    }

    /**
     * Soft-delete (LGPD). Não remove o registro, apenas oculta.
     */
    public function softDelete(?DateTimeImmutable $now = null): void
    {
        $now = $now ?? new DateTimeImmutable('now');
        $this->deletedAt = $now;
        $this->touch($now);
    }

    // -------- Helpers privados --------

    /**
     * @throws IllegalStateTransition
     */
    private function guardTransition(StatusCadastro $target): void
    {
        if (!$this->statusCadastro->canTransitionTo($target)) {
            throw IllegalStateTransition::between($this->statusCadastro, $target);
        }
    }

    /**
     * Garante coerência entre o discriminador `tipo` do agente e o `tipo`
     * embutido no número de registro.
     *
     * @throws InvalidArgumentException
     */
    private function guardNumeroRegistroTipo(NumeroRegistro $numero): void
    {
        if ($numero->tipo() !== $this->tipo->value()) {
            throw new InvalidArgumentException(sprintf(
                'NumeroRegistro com tipo "%s" incompativel com Agente do tipo "%s".',
                $numero->tipo(),
                $this->tipo->value()
            ));
        }
    }

    private function touch(?DateTimeImmutable $now): void
    {
        $this->updatedAt = $now ?? new DateTimeImmutable('now');
    }
}
