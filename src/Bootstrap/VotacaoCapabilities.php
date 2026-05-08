<?php
/**
 * VotacaoCapabilities — registra capabilities de Votação/Apuração nos roles.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 *
 * HOW TO INTEGRATE (sem tocar em Activator.php):
 *
 *   Opção A — Migration via wp_options (preferida):
 *     `add_action('admin_init', [VotacaoCapabilities::class, 'maybeUpgrade'])`.
 *     O método verifica `pi_caps_version` e aplica as caps idempotentemente.
 *
 *   Opção B — Manual:
 *     dentro de `Activator::activate()`:
 *       foreach (['pi_administrador','pi_apuracao','pi_analista','pi_dpo'] as $r) {
 *           $role = get_role($r);
 *           if ($role) { VotacaoCapabilities::register($role, $r); }
 *       }
 *
 * Nota: as caps `pi_apurar_votacao`, `pi_publicar_resultado` e
 * `pi_visualizar_audit_log` JÁ ESTÃO em `Activator::allCapabilities()` e em
 * roles relevantes. Esta classe é o caminho de upgrade para instalações
 * existentes sem reativar o plugin, e centraliza o mapping role→cap para a
 * Onda 6 (Votação).
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

/**
 * Gerencia a adição idempotente das capabilities da Onda 6 (Votação) em
 * roles existentes sem exigir reativação do plugin.
 *
 * Capabilities gerenciadas:
 *  - pi_apurar_votacao        — executar apuração de votação encerrada
 *  - pi_publicar_resultado    — publicar resultado oficial após apuração
 *  - pi_visualizar_audit_log  — visualizar audit log (apuradores + DPO)
 */
final class VotacaoCapabilities
{
    /**
     * Option key controlando a versão de capabilities aplicadas.
     *
     * Compartilhada com {@see EditalCapabilities} — incrementamos a mesma
     * versão para que upgrades sejam detectados em sequência. Wave 5 = 1,
     * Wave 6 = 2.
     */
    private const VERSION_OPTION = 'pi_caps_version';

    /**
     * Versão alvo após esta classe rodar.
     */
    private const CURRENT_VERSION = 2;

    /**
     * Mapping role → caps que ESTA classe gerencia.
     *
     * @return array<string,list<string>>
     */
    private static function rolesMatrix(): array
    {
        return [
            'administrator' => [
                'pi_apurar_votacao',
                'pi_publicar_resultado',
                'pi_visualizar_audit_log',
            ],
            'pi_administrador' => [
                'pi_apurar_votacao',
                'pi_publicar_resultado',
                'pi_visualizar_audit_log',
            ],
            'pi_apuracao' => [
                'pi_apurar_votacao',
                'pi_publicar_resultado',
                'pi_visualizar_audit_log',
            ],
            'pi_analista' => [
                'pi_apurar_votacao',
                'pi_visualizar_audit_log',
            ],
            'pi_dpo' => [
                'pi_visualizar_audit_log',
            ],
        ];
    }

    /**
     * Lista canônica de capabilities desta classe.
     *
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return [
            'pi_apurar_votacao',
            'pi_publicar_resultado',
            'pi_visualizar_audit_log',
        ];
    }

    /**
     * Adiciona as caps relevantes ao role informado, se houver.
     *
     * Idempotente: `add_cap` aceita re-aplicação.
     */
    public static function register(\WP_Role $role, string $roleName): void
    {
        $matrix = self::rolesMatrix();
        if (!isset($matrix[$roleName])) {
            return;
        }
        foreach ($matrix[$roleName] as $cap) {
            $role->add_cap($cap);
        }
    }

    /**
     * Aplica caps em todos os roles previstos. Idempotente via `pi_caps_version`.
     *
     * Suggested hook:
     *   add_action('admin_init', [VotacaoCapabilities::class, 'maybeUpgrade']);
     */
    public static function maybeUpgrade(): void
    {
        if (!function_exists('get_option') || !function_exists('get_role')) {
            return;
        }

        $installed = (int) \get_option(self::VERSION_OPTION, 0);
        if ($installed >= self::CURRENT_VERSION) {
            return;
        }

        foreach (array_keys(self::rolesMatrix()) as $roleName) {
            $role = \get_role($roleName);
            if ($role instanceof \WP_Role) {
                self::register($role, $roleName);
            }
        }

        if (function_exists('update_option')) {
            \update_option(self::VERSION_OPTION, self::CURRENT_VERSION, false);
        }
    }
}
