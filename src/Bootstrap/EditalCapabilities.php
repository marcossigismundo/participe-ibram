<?php
/**
 * EditalCapabilities — registra capabilities de editais em um WP_Role.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 *
 * HOW TO INTEGRATE (sem tocar em Activator.php):
 *
 *   Opção A — Migration via wp_options:
 *     Chame `EditalCapabilities::maybeUpgrade()` no hook `admin_init` (ou em
 *     `Plugin::boot()`). O método verifica a opção `pi_caps_version` e aplica
 *     as caps ao role `administrator` e `pi_gestor_edital` apenas uma vez.
 *
 *   Opção B — Activator manual:
 *     Dentro de `Activator::activate()` adicione:
 *       $role = get_role('pi_gestor_edital');
 *       if ($role) { \Ibram\ParticipeIbram\Bootstrap\EditalCapabilities::register($role); }
 *
 *   Nota: as três capabilities (pi_criar_edital, pi_editar_edital,
 *   pi_publicar_edital) JÁ ESTÃO no `Activator::rolesDefinition()` para o
 *   role `pi_gestor_edital` e em `allCapabilities()` para `pi_administrador`.
 *   Este arquivo serve como ponto de documentação e como caminho de upgrade
 *   para instalações existentes sem reativar o plugin.
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

/**
 * Gerencia a adição idempotente das capabilities de editais em roles
 * existentes sem exigir reativação do plugin.
 *
 * As capabilities gerenciadas aqui:
 *  - pi_criar_edital   — cria novos editais
 *  - pi_editar_edital  — edita/categorias de editais em rascunho/publicado
 *  - pi_publicar_edital — publica edital / abre inscrições
 */
final class EditalCapabilities
{
    /**
     * Option key que controla idempotência (evita aplicar 2x).
     */
    private const VERSION_OPTION = 'pi_caps_version';

    /**
     * Versão atual das capabilities de editais. Incremente ao adicionar novas.
     */
    private const CURRENT_VERSION = 1;

    /**
     * Lista de capabilities gerenciadas por esta classe.
     *
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return [
            'pi_criar_edital',
            'pi_editar_edital',
            'pi_publicar_edital',
        ];
    }

    /**
     * Adiciona as capabilities ao role informado.
     *
     * Idempotente: `add_cap` é seguro para re-executar.
     */
    public static function register(\WP_Role $role): void
    {
        foreach (self::capabilities() as $cap) {
            $role->add_cap($cap);
        }
    }

    /**
     * Verifica a opção `pi_caps_version` e, se necessário, aplica as caps
     * em `administrator` e `pi_gestor_edital`. Idempotente.
     *
     * Chamada sugerida: `add_action('admin_init', [EditalCapabilities::class, 'maybeUpgrade'])`.
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

        $targetRoles = ['administrator', 'pi_gestor_edital', 'pi_administrador'];
        foreach ($targetRoles as $roleName) {
            $role = \get_role($roleName);
            if ($role instanceof \WP_Role) {
                self::register($role);
            }
        }

        if (function_exists('update_option')) {
            \update_option(self::VERSION_OPTION, self::CURRENT_VERSION, false);
        }
    }
}
