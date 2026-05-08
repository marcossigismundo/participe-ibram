<?php
/**
 * Plugin activation / deactivation handlers.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

/**
 * Handles activation and deactivation of the plugin.
 *
 * On activation:
 *  - Validates the runtime (PHP, WP, libsodium).
 *  - Creates the platform roles and capabilities (Portaria 3230/2024).
 *  - Mirrors capabilities into the `administrator` role.
 *  - Triggers the migration runner if available (Wave I2).
 *  - Flushes rewrite rules.
 *
 * On deactivation:
 *  - Only flushes rewrite rules. No data is removed (LGPD: retention may
 *    be required by legal basis).
 */
final class Activator
{
    /**
     * Capability sets per role (TD-08, SCHEMA.md §8).
     *
     * @return array<string, array{label: string, caps: list<string>}>
     */
    private static function rolesDefinition(): array
    {
        return [
            'pi_administrador' => [
                'label' => __('Participe Ibram - Administrador', 'participe-ibram'),
                'caps'  => self::allCapabilities(),
            ],
            // Capability `pi_administrar_email` é incluída em `allCapabilities()`
            // e replicada no admin core/`administrator` via `add_cap` abaixo.
            'pi_analista' => [
                'label' => __('Participe Ibram - Analista', 'participe-ibram'),
                'caps'  => [
                    'pi_listar_cadastros',
                    'pi_analisar_cadastro',
                    'pi_deferir',
                    'pi_indeferir',
                    'pi_visualizar_documentos',
                    'pi_visualizar_dados_sensiveis',
                ],
            ],
            'pi_presidencia' => [
                'label' => __('Participe Ibram - Presidência', 'participe-ibram'),
                'caps'  => [
                    'pi_listar_cadastros',
                    'pi_analisar_cadastro',
                    'pi_deferir',
                    'pi_indeferir',
                    'pi_visualizar_documentos',
                    'pi_visualizar_dados_sensiveis',
                    'pi_decidir_recurso_presidencia',
                ],
            ],
            'pi_gestor_edital' => [
                'label' => __('Participe Ibram - Gestor de Edital', 'participe-ibram'),
                'caps'  => [
                    'pi_criar_edital',
                    'pi_editar_edital',
                    'pi_publicar_edital',
                    'pi_decidir_habilitacao',
                ],
            ],
            'pi_apuracao' => [
                'label' => __('Participe Ibram - Apuração', 'participe-ibram'),
                'caps'  => [
                    'pi_apurar_votacao',
                    'pi_publicar_resultado',
                ],
            ],
            'pi_dpo' => [
                'label' => __('Participe Ibram - DPO', 'participe-ibram'),
                'caps'  => [
                    'pi_atender_solicitacao_titular',
                    'pi_visualizar_audit_log',
                    'pi_anonimizar_titular',
                ],
            ],
            'pi_agente' => [
                'label' => __('Participe Ibram - Agente', 'participe-ibram'),
                'caps'  => [
                    'pi_editar_proprio_cadastro',
                    'pi_inscrever_em_edital',
                    'pi_votar',
                    'pi_solicitar_direitos_titular',
                ],
            ],
        ];
    }

    /**
     * Full capability list (used by `pi_administrador` and the WP
     * `administrator` overlay).
     *
     * @return list<string>
     */
    private static function allCapabilities(): array
    {
        return [
            'pi_listar_cadastros',
            'pi_analisar_cadastro',
            'pi_deferir',
            'pi_indeferir',
            'pi_visualizar_documentos',
            'pi_visualizar_dados_sensiveis',
            'pi_decidir_recurso_presidencia',
            'pi_criar_edital',
            'pi_editar_edital',
            'pi_publicar_edital',
            'pi_decidir_habilitacao',
            'pi_apurar_votacao',
            'pi_publicar_resultado',
            'pi_atender_solicitacao_titular',
            'pi_visualizar_audit_log',
            'pi_anonimizar_titular',
            'pi_editar_proprio_cadastro',
            'pi_inscrever_em_edital',
            'pi_votar',
            'pi_solicitar_direitos_titular',
            'pi_gerenciar_configuracoes',
            'pi_gerenciar_vocabularios',
            'pi_administrar_email',
        ];
    }

    /**
     * Plugin activation entry point.
     */
    public static function activate(): void
    {
        self::assertEnvironment();
        self::installRoles();
        self::runMigrations();

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation entry point — never destructive.
     */
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Validate runtime requirements before bootstrapping.
     */
    private static function assertEnvironment(): void
    {
        global $wp_version;

        if (version_compare(PHP_VERSION, PI_MIN_PHP, '<')) {
            wp_die(
                esc_html(sprintf(
                    /* translators: 1: required PHP version, 2: current PHP version. */
                    __('Participe Ibram requer PHP %1$s ou superior. Sua versão atual é %2$s.', 'participe-ibram'),
                    PI_MIN_PHP,
                    PHP_VERSION
                )),
                esc_html__('Versão de PHP incompatível', 'participe-ibram'),
                ['back_link' => true]
            );
        }

        if (isset($wp_version) && version_compare((string) $wp_version, PI_MIN_WP, '<')) {
            wp_die(
                esc_html(sprintf(
                    /* translators: 1: required WP version, 2: current WP version. */
                    __('Participe Ibram requer WordPress %1$s ou superior. Sua versão atual é %2$s.', 'participe-ibram'),
                    PI_MIN_WP,
                    (string) $wp_version
                )),
                esc_html__('Versão do WordPress incompatível', 'participe-ibram'),
                ['back_link' => true]
            );
        }

        if (!function_exists('sodium_crypto_secretbox')) {
            wp_die(
                esc_html__(
                    'Participe Ibram requer a extensão libsodium do PHP (sodium_crypto_secretbox). Instale-a e tente novamente.',
                    'participe-ibram'
                ),
                esc_html__('Extensão libsodium ausente', 'participe-ibram'),
                ['back_link' => true]
            );
        }
    }

    /**
     * Create roles and mirror caps into the administrator role.
     */
    private static function installRoles(): void
    {
        foreach (self::rolesDefinition() as $slug => $definition) {
            $caps = [];
            foreach ($definition['caps'] as $cap) {
                $caps[$cap] = true;
            }
            // `read` is required so users can log in; preserve existing role
            // if it already exists.
            $existing = get_role($slug);
            if ($existing instanceof \WP_Role) {
                foreach ($caps as $cap => $_grant) {
                    $existing->add_cap($cap);
                }
            } else {
                add_role($slug, $definition['label'], array_merge(['read' => true], $caps));
            }
        }

        $administrator = get_role('administrator');
        if ($administrator instanceof \WP_Role) {
            foreach (self::allCapabilities() as $cap) {
                $administrator->add_cap($cap);
            }
        }
    }

    /**
     * Trigger the migrations runner if Wave I2 has shipped it.
     *
     * Graceful: this method must never break activation when the runner is
     * not yet available.
     */
    private static function runMigrations(): void
    {
        $runner = 'Ibram\\ParticipeIbram\\Core\\Database\\MigrationRunner';
        if (!class_exists($runner)) {
            return;
        }
        if (method_exists($runner, 'run')) {
            try {
                /** @psalm-suppress MixedMethodCall */
                $runner::run();
            } catch (\Throwable $e) {
                // Activation should not fatal; log a sanitized message only.
                if (function_exists('do_action')) {
                    do_action('participe_ibram_activation_migration_failed', $e->getMessage());
                }
            }
        }
    }

    /**
     * Remove platform roles. Used by uninstall.php.
     */
    public static function removeRoles(): void
    {
        foreach (array_keys(self::rolesDefinition()) as $slug) {
            remove_role($slug);
        }

        $administrator = get_role('administrator');
        if ($administrator instanceof \WP_Role) {
            foreach (self::allCapabilities() as $cap) {
                $administrator->remove_cap($cap);
            }
        }
    }
}
