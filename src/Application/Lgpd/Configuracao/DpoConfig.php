<?php
/**
 * Wrapper sobre WordPress options para configuração do DPO (Encarregado LGPD).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd\Configuracao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd\Configuracao;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use InvalidArgumentException;

/**
 * Armazena e recupera os dados do DPO (Data Protection Officer / Encarregado).
 *
 * Options usadas:
 *  - `pi_dpo_email`    — e-mail do DPO (obrigatório, validado)
 *  - `pi_dpo_nome`     — nome do DPO
 *  - `pi_dpo_telefone` — telefone opcional
 *
 * Toda mudança é auditada via AuditLogger com o ID do ator que realizou a alteração.
 */
final class DpoConfig
{
    public const OPTION_EMAIL    = 'pi_dpo_email';
    public const OPTION_NOME     = 'pi_dpo_nome';
    public const OPTION_TELEFONE = 'pi_dpo_telefone';

    private AuditLogger $audit;

    public function __construct(AuditLogger $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Retorna o e-mail do DPO configurado, ou null se ausente/inválido.
     */
    public static function getEmail(): ?string
    {
        if (!function_exists('get_option')) {
            return null;
        }
        $email = (string) \get_option(self::OPTION_EMAIL, '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * Retorna o nome do DPO, ou null se não configurado.
     */
    public static function getNome(): ?string
    {
        if (!function_exists('get_option')) {
            return null;
        }
        $nome = (string) \get_option(self::OPTION_NOME, '');

        return $nome !== '' ? $nome : null;
    }

    /**
     * Retorna o telefone do DPO, ou null se não configurado.
     */
    public static function getTelefone(): ?string
    {
        if (!function_exists('get_option')) {
            return null;
        }
        $tel = (string) \get_option(self::OPTION_TELEFONE, '');

        return $tel !== '' ? $tel : null;
    }

    /**
     * Salva configuração do DPO e audita a mudança.
     *
     * @param array<string,string> $data   Chaves: email, nome, telefone (opcional).
     * @param int                  $atorId ID do usuário WordPress que realizou a mudança.
     *
     * @throws InvalidArgumentException Quando o e-mail for inválido.
     */
    public function setConfig(array $data, int $atorId): void
    {
        $email    = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
        $nome     = isset($data['nome']) ? sanitize_text_field((string) $data['nome']) : '';
        $telefone = isset($data['telefone']) ? sanitize_text_field((string) $data['telefone']) : '';

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(sprintf(
                'E-mail DPO inválido: "%s".',
                $email
            ));
        }

        $antes = [
            'email'    => self::getEmail() ?? '',
            'nome'     => self::getNome() ?? '',
            'telefone' => self::getTelefone() ?? '',
        ];

        if ($email !== '') {
            \update_option(self::OPTION_EMAIL, $email, false);
        }
        if ($nome !== '') {
            \update_option(self::OPTION_NOME, $nome, false);
        }
        \update_option(self::OPTION_TELEFONE, $telefone, false);

        $depois = [
            'email'    => $email,
            'nome'     => $nome,
            'telefone' => $telefone,
        ];

        $this->audit->log(
            'dpo_config',
            null,
            'atualizar',
            $antes,
            $depois,
            $atorId
        );
    }
}
