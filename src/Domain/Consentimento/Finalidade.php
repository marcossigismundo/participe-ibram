<?php
/**
 * Finalidades de tratamento (consentimento granular LGPD).
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4+) das 10 finalidades suportadas pelo Participe Ibram.
 *
 * Mapeada 1:1 ao ENUM da coluna `finalidade` em `wp_pi_consentimentos`
 * (SCHEMA.md §6).
 *
 * Cada finalidade declara base legal explícita conforme R2-lgpd.md §2 e
 * LGPD.md §1. As finalidades obrigatórias (identificação e comunicação) NÃO
 * podem ser revogadas — sua "revogação" exige o cancelamento de todo o
 * cadastro do agente.
 */
final class Finalidade
{
    public const IDENTIFICACAO                = 'identificacao';
    public const COMUNICACAO                  = 'comunicacao';
    public const MAPEAMENTO                   = 'mapeamento';
    public const RECONHECIMENTO_PCT           = 'reconhecimento_pct';
    public const VOTACAO                      = 'votacao';
    public const CANDIDATURA                  = 'candidatura';
    public const DADOS_SENSIVEIS_GENERO       = 'dados_sensiveis_genero';
    public const DADOS_SENSIVEIS_ORIENTACAO   = 'dados_sensiveis_orientacao';
    public const DADOS_SENSIVEIS_SAUDE        = 'dados_sensiveis_saude';
    public const DADOS_SENSIVEIS_RACA         = 'dados_sensiveis_raca';

    /** @var array<int,string> Lista canônica em ordem de UI. */
    private const ALLOWED = [
        self::IDENTIFICACAO,
        self::COMUNICACAO,
        self::MAPEAMENTO,
        self::RECONHECIMENTO_PCT,
        self::VOTACAO,
        self::CANDIDATURA,
        self::DADOS_SENSIVEIS_GENERO,
        self::DADOS_SENSIVEIS_ORIENTACAO,
        self::DADOS_SENSIVEIS_SAUDE,
        self::DADOS_SENSIVEIS_RACA,
    ];

    /** @var array<int,string> Finalidades sem as quais o cadastro não existe. */
    private const OBRIGATORIAS = [
        self::IDENTIFICACAO,
        self::COMUNICACAO,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'Finalidade invalida: "%s". Esperado: %s.',
                $value,
                implode(', ', self::ALLOWED)
            ));
        }
        $this->value = $value;
    }

    /**
     * Factory normalizadora (case-insensitive, trim).
     *
     * @throws InvalidArgumentException Quando o valor não está no enum.
     */
    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        return new self($normalized);
    }

    /**
     * @return array<int,self>
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::ALLOWED as $v) {
            $out[] = new self($v);
        }

        return $out;
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
     * Identificação e comunicação são pré-condição para a existência do cadastro
     * e, portanto, não podem ser "revogadas" — seguem a base legal de política
     * pública (Art. 7º, III LGPD), não consentimento.
     */
    public function isObrigatoria(): bool
    {
        return in_array($this->value, self::OBRIGATORIAS, true);
    }

    /**
     * Indica se a finalidade trata dados sensíveis (Art. 5º, II + Art. 11 LGPD).
     */
    public function isSensivel(): bool
    {
        return in_array($this->value, [
            self::DADOS_SENSIVEIS_GENERO,
            self::DADOS_SENSIVEIS_ORIENTACAO,
            self::DADOS_SENSIVEIS_SAUDE,
            self::DADOS_SENSIVEIS_RACA,
            self::RECONHECIMENTO_PCT,
        ], true);
    }

    /**
     * Rótulo curto i18n-ready para UI.
     */
    public function label(): string
    {
        $key = 'pi.consentimento.finalidade.' . $this->value . '.label';
        $translations = [
            self::IDENTIFICACAO              => 'Identificação e cadastro',
            self::COMUNICACAO                => 'Comunicação institucional',
            self::MAPEAMENTO                 => 'Mapeamento e estatísticas',
            self::RECONHECIMENTO_PCT         => 'Filiação a povos e comunidades tradicionais',
            self::VOTACAO                    => 'Participação em votações',
            self::CANDIDATURA                => 'Candidatura a vagas em instâncias',
            self::DADOS_SENSIVEIS_GENERO     => 'Dados de identidade de gênero',
            self::DADOS_SENSIVEIS_ORIENTACAO => 'Dados de orientação sexual',
            self::DADOS_SENSIVEIS_SAUDE      => 'Dados de saúde / deficiência',
            self::DADOS_SENSIVEIS_RACA       => 'Dados de raça/cor',
        ];

        return self::translate($key, $translations[$this->value]);
    }

    /**
     * Descrição longa i18n-ready (texto exibido na UI granular do termo).
     */
    public function descricao(): string
    {
        $key = 'pi.consentimento.finalidade.' . $this->value . '.descricao';
        $translations = [
            self::IDENTIFICACAO              => 'Nome, CPF/CNPJ/Passaporte, contato e vínculo institucional. Sem isso não é possível registrá-lo como agente.',
            self::COMUNICACAO                => 'Notificações sobre seu cadastro, editais e votações. Comunicações não-essenciais podem ser desativadas depois.',
            self::MAPEAMENTO                 => 'Indicadores agregados de representatividade do cadastro. Dados pseudonimizados.',
            self::RECONHECIMENTO_PCT         => 'Autodeclaração conforme Decreto 8.750/2016. Garante representação de povos e comunidades tradicionais.',
            self::VOTACAO                    => 'Permite que você vote em eleições para conselhos e instâncias do Ibram.',
            self::CANDIDATURA                => 'Permite que você se inscreva como candidato em vagas de conselhos e instâncias.',
            self::DADOS_SENSIVEIS_GENERO     => 'Identidade de gênero — uso para política afirmativa de representação.',
            self::DADOS_SENSIVEIS_ORIENTACAO => 'Orientação sexual — uso para política afirmativa de representação.',
            self::DADOS_SENSIVEIS_SAUDE      => 'Informações de deficiência e acessibilidade — para garantir suportes adequados.',
            self::DADOS_SENSIVEIS_RACA       => 'Raça/cor (autodeclaração IBGE) — exigido pela Lei 14.553/2023.',
        ];

        return self::translate($key, $translations[$this->value]);
    }

    /**
     * Base legal LGPD por finalidade (R2-lgpd.md §2).
     */
    public function baseLegal(): string
    {
        switch ($this->value) {
            case self::IDENTIFICACAO:
            case self::COMUNICACAO:
            case self::MAPEAMENTO:
            case self::VOTACAO:
            case self::CANDIDATURA:
                return 'Art. 7º, III LGPD (execução de políticas públicas — Portaria IBRAM 3230/2024)';

            case self::DADOS_SENSIVEIS_RACA:
                return 'Art. 11, II, "a" LGPD + Lei 14.553/2023 (cumprimento de obrigação legal)';

            case self::RECONHECIMENTO_PCT:
                return 'Art. 11, II, "b" LGPD (execução de políticas públicas — Decreto 8.750/2016)';

            case self::DADOS_SENSIVEIS_GENERO:
            case self::DADOS_SENSIVEIS_ORIENTACAO:
            case self::DADOS_SENSIVEIS_SAUDE:
                return 'Art. 11, II, "a" LGPD (política pública) com consentimento específico (Art. 11, I)';

            default:
                return 'LGPD'; // unreachable
        }
    }

    /**
     * Wrapper para `__()` quando WordPress estiver carregado.
     */
    private static function translate(string $key, string $fallback): string
    {
        if (function_exists('__')) {
            $translated = \__($fallback, 'participe-ibram');

            return is_string($translated) && $translated !== '' ? $translated : $fallback;
        }

        return $fallback;
    }
}
