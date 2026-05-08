<?php
/**
 * Enum-like (PHP 7.4+) com os eventos de e-mail suportados pelo Participe Ibram.
 *
 * @package Ibram\ParticipeIbram\Domain\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Email;

use InvalidArgumentException;

/**
 * Catálogo canônico dos 11 eventos de comunicação automática (Despacho 98/2025
 * IBRAM item 7 + ARCHITECTURE TD-13).
 *
 * Cada evento conhece:
 *  - o nome do template (subjacente em `templates/emails/<evento>/<evento>.*`);
 *  - o template do assunto (string com placeholders `{nome}`, `{numero_registro}`...);
 *  - a audiência padrão (`agente_individual`, `todos_cadastrados`, `analistas`).
 *
 * A classe é deliberadamente um VO imutável: o construtor é privado e o único
 * jeito de obter uma instância é via {@see fromString} ou as factories.
 */
final class EventoEmail
{
    public const CADASTRO_SUBMETIDO     = 'cadastro_submetido';
    public const CADASTRO_DEFERIDO      = 'cadastro_deferido';
    public const CADASTRO_INDEFERIDO    = 'cadastro_indeferido';
    public const RECURSO_PROTOCOLADO    = 'recurso_protocolado';
    public const RECURSO_DECIDIDO       = 'recurso_decidido';
    public const RECURSO_PRAZO_WARNING  = 'recurso_prazo_warning';
    public const EDITAL_PUBLICADO       = 'edital_publicado';
    public const INSCRICAO_RECEBIDA     = 'inscricao_recebida';
    public const HABILITACAO_DECIDIDA   = 'habilitacao_decidida';
    public const VOTACAO_ABERTA         = 'votacao_aberta';
    public const RESULTADO_PUBLICADO    = 'resultado_publicado';

    public const AUDIENCIA_INDIVIDUAL   = 'agente_individual';
    public const AUDIENCIA_BROADCAST    = 'todos_cadastrados';
    public const AUDIENCIA_ANALISTAS    = 'analistas';

    /** @var array<int,string> Lista canônica em ordem de fluxo. */
    private const ALLOWED = [
        self::CADASTRO_SUBMETIDO,
        self::CADASTRO_DEFERIDO,
        self::CADASTRO_INDEFERIDO,
        self::RECURSO_PROTOCOLADO,
        self::RECURSO_DECIDIDO,
        self::RECURSO_PRAZO_WARNING,
        self::EDITAL_PUBLICADO,
        self::INSCRICAO_RECEBIDA,
        self::HABILITACAO_DECIDIDA,
        self::VOTACAO_ABERTA,
        self::RESULTADO_PUBLICADO,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'EventoEmail invalido: "%s". Esperado um de: %s.',
                $value,
                implode(', ', self::ALLOWED)
            ));
        }
        $this->value = $value;
    }

    /**
     * Factory normalizadora.
     *
     * @throws InvalidArgumentException Quando o valor não está no catálogo.
     */
    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    /**
     * @return array<int,string>
     */
    public static function values(): array
    {
        return self::ALLOWED;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Nome do template (igual ao value() no momento — separado por desacoplamento).
     */
    public function template(): string
    {
        return $this->value;
    }

    /**
     * Assunto com placeholders. Substituições feitas pelo {@see Application\Email\Templates\EmailRenderer}.
     */
    public function assuntoTemplate(): string
    {
        switch ($this->value) {
            case self::CADASTRO_SUBMETIDO:
                return 'Recebemos sua submissao no Participe Ibram';
            case self::CADASTRO_DEFERIDO:
                return 'Seu cadastro no Participe Ibram foi deferido — {numero_registro}';
            case self::CADASTRO_INDEFERIDO:
                return 'Decisao sobre seu cadastro no Participe Ibram';
            case self::RECURSO_PROTOCOLADO:
                return 'Recebemos seu recurso no Participe Ibram';
            case self::RECURSO_DECIDIDO:
                return 'Decisao do recurso no Participe Ibram';
            case self::RECURSO_PRAZO_WARNING:
                return 'Aviso: prazo para recurso encerra em breve';
            case self::EDITAL_PUBLICADO:
                return 'Novo edital publicado no Participe Ibram';
            case self::INSCRICAO_RECEBIDA:
                return 'Inscricao recebida no Participe Ibram';
            case self::HABILITACAO_DECIDIDA:
                return 'Resultado da habilitacao no Participe Ibram';
            case self::VOTACAO_ABERTA:
                return 'Votacao aberta no Participe Ibram';
            case self::RESULTADO_PUBLICADO:
                return 'Resultado publicado no Participe Ibram';
            default:
                return 'Comunicacao do Participe Ibram'; // unreachable
        }
    }

    /**
     * Audiência padrão para o evento.
     */
    public function audienciaPadrao(): string
    {
        switch ($this->value) {
            case self::EDITAL_PUBLICADO:
            case self::VOTACAO_ABERTA:
            case self::RESULTADO_PUBLICADO:
                return self::AUDIENCIA_BROADCAST;

            // Demais eventos são individuais (notificam o agente envolvido).
            case self::CADASTRO_SUBMETIDO:
            case self::CADASTRO_DEFERIDO:
            case self::CADASTRO_INDEFERIDO:
            case self::RECURSO_PROTOCOLADO:
            case self::RECURSO_DECIDIDO:
            case self::RECURSO_PRAZO_WARNING:
            case self::INSCRICAO_RECEBIDA:
            case self::HABILITACAO_DECIDIDA:
            default:
                return self::AUDIENCIA_INDIVIDUAL;
        }
    }

    /**
     * Indica se é um envio em massa que exige paginação para distribuir carga.
     */
    public function isBroadcast(): bool
    {
        return $this->audienciaPadrao() === self::AUDIENCIA_BROADCAST;
    }
}
