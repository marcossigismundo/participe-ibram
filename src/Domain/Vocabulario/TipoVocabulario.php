<?php
/**
 * Tipos de vocabulário controlado (TD-07, SCHEMA §7).
 *
 * @package Ibram\ParticipeIbram\Domain\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Vocabulario;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4+) com os 13 tipos canônicos de `wp_pi_vocabularios.tipo`.
 *
 * Lista fechada — qualquer alteração aqui exige migração SQL correspondente
 * e atualização dos seeders V002/V003. Cada constante reflete diretamente o
 * valor textual gravado na coluna `tipo`.
 *
 * @see \Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario
 */
final class TipoVocabulario
{
    public const TIPOS_COLETIVO                = 'tipos_coletivo';
    public const ABRANGENCIAS                  = 'abrangencias';
    public const NACIONALIDADES                = 'nacionalidades';
    public const FAIXAS_ETARIAS                = 'faixas_etarias';
    public const IDENTIDADES_GENERO            = 'identidades_genero';
    public const ORIENTACOES_SEXUAIS           = 'orientacoes_sexuais';
    public const RACAS_COR                     = 'racas_cor';
    public const POVOS_COMUNIDADES_TRADICIONAIS = 'povos_comunidades_tradicionais';
    public const GRAUS_INSTRUCAO               = 'graus_instrucao';
    public const OCUPACOES                     = 'ocupacoes';
    public const AREAS_TEMATICAS               = 'areas_tematicas';
    public const INSTANCIAS_PARTICIPACAO       = 'instancias_participacao';
    public const TIPOS_DOCUMENTO               = 'tipos_documento';

    /**
     * Lista canônica de tipos permitidos.
     *
     * @var array<int,string>
     */
    private const ALLOWED = [
        self::TIPOS_COLETIVO,
        self::ABRANGENCIAS,
        self::NACIONALIDADES,
        self::FAIXAS_ETARIAS,
        self::IDENTIDADES_GENERO,
        self::ORIENTACOES_SEXUAIS,
        self::RACAS_COR,
        self::POVOS_COMUNIDADES_TRADICIONAIS,
        self::GRAUS_INSTRUCAO,
        self::OCUPACOES,
        self::AREAS_TEMATICAS,
        self::INSTANCIAS_PARTICIPACAO,
        self::TIPOS_DOCUMENTO,
    ];

    private string $value;

    /**
     * @param string $value Valor canônico do tipo.
     *
     * @throws InvalidArgumentException Quando o valor não pertence ao enum.
     */
    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'TipoVocabulario invalido: "%s". Esperado: %s.',
                $value,
                implode(', ', self::ALLOWED)
            ));
        }
        $this->value = $value;
    }

    /**
     * Factory normalizadora (lower-case + trim).
     *
     * @throws InvalidArgumentException Quando o valor é desconhecido.
     */
    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    /**
     * Lista canônica de tipos válidos.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return self::ALLOWED;
    }

    /**
     * Indica se a string é um tipo conhecido (sem lançar).
     */
    public static function isValid(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::ALLOWED, true);
    }

    /**
     * Valor canônico (string).
     */
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
}
