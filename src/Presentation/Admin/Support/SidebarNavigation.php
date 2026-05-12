<?php
/**
 * SidebarNavigation — estrutura de IA (grupos + itens) do sidebar do plugin.
 *
 * Retorna a árvore de navegação da Onda 12 (W12), alinhada ao mapeamento
 * canônico em docs/refactor/W11-IA.md.
 *
 * Cada grupo: ['title' => string|null, 'items' => [...]]
 * Cada item:  ['slug' => string, 'label' => string, 'capability' => string,
 *              'icon' => string, 'is_active' => bool]
 *
 * Itens são filtrados por current_user_can(). Grupos sem itens visíveis são
 * suprimidos em SidebarRenderer::render().
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Fornece a IA canônica do sidebar admin do Participe Ibram.
 *
 * Slugs verificados contra add_submenu_page em todos os *MenuRegistry e
 * *Controller da Onda 12 (ver nota de diferenças no relatório W12).
 */
final class SidebarNavigation
{
    /**
     * Retorna os grupos de navegação filtrados pela capability do usuário atual.
     *
     * @return array<int, array{
     *   title: string|null,
     *   items: array<int, array{slug: string, label: string, capability: string, icon: string, is_active: bool}>
     * }>
     */
    public static function getGroups(): array
    {
        // Determina a página activa (slug) a partir de $_GET['page'].
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $currentPage = isset($_GET['page'])
            ? (string) \sanitize_key(\wp_unslash($_GET['page']))
            : 'participe-ibram';

        $rawGroups = self::rawGroups();
        $result    = [];

        foreach ($rawGroups as $group) {
            $filteredItems = [];
            foreach ($group['items'] as $item) {
                // Omite itens para os quais o utilizador não tem permissão.
                if (function_exists('current_user_can') && !\current_user_can($item['capability'])) {
                    continue;
                }
                $item['is_active']  = ($item['slug'] === $currentPage);
                $filteredItems[]    = $item;
            }

            // Suprime grupos sem itens visíveis.
            if (empty($filteredItems)) {
                continue;
            }

            $result[] = [
                'title' => $group['title'],
                'items' => $filteredItems,
            ];
        }

        return $result;
    }

    /**
     * Retorna a IA completa antes da filtragem de capability.
     *
     * Estrutura canônica W11-A. Slugs corrigidos para os valores reais
     * registados nos *MenuRegistry (ver relatório W12 — seção "IA verification").
     *
     * @return array<int, array{title: string|null, items: list<array{slug: string, label: string, capability: string, icon: string, is_active: bool}>}>
     */
    private static function rawGroups(): array
    {
        return [
            // ── Grupo 1 — Visão Geral (sem cabeçalho, item único) ────────────
            [
                'title' => null,
                'items' => [
                    [
                        'slug'       => 'participe-ibram',
                        'label'      => \__('Painel', 'participe-ibram'),
                        'capability' => 'pi_listar_cadastros',
                        'icon'       => 'dashicons-dashboard',
                        'is_active'  => false, // preenchido em getGroups()
                    ],
                ],
            ],

            // ── Grupo 2 — Análise de cadastros ───────────────────────────────
            [
                'title' => \__('Análise de cadastros', 'participe-ibram'),
                'items' => [
                    [
                        'slug'       => 'participe-ibram_cadastros',
                        'label'      => \__('Fila de análise', 'participe-ibram'),
                        'capability' => 'pi_listar_cadastros',
                        'icon'       => 'dashicons-clipboard',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_agentes',
                        'label'      => \__('Todos os agentes', 'participe-ibram'),
                        'capability' => 'pi_listar_cadastros',
                        'icon'       => 'dashicons-groups',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_recursos_retratacao',
                        'label'      => \__('Recursos — Retratação', 'participe-ibram'),
                        'capability' => 'pi_analisar_cadastro',
                        'icon'       => 'dashicons-undo',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_recursos_presidencia',
                        'label'      => \__('Recursos — Presidência', 'participe-ibram'),
                        'capability' => 'pi_decidir_recurso_presidencia',
                        'icon'       => 'dashicons-businesswoman',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_recursos_prazos',
                        'label'      => \__('Recursos — Prazos', 'participe-ibram'),
                        'capability' => 'pi_listar_cadastros',
                        'icon'       => 'dashicons-clock',
                        'is_active'  => false,
                    ],
                ],
            ],

            // ── Grupo 3 — Editais & habilitações ─────────────────────────────
            [
                'title' => \__('Editais & habilitações', 'participe-ibram'),
                'items' => [
                    [
                        'slug'       => 'participe-ibram_editais',
                        'label'      => \__('Editais', 'participe-ibram'),
                        'capability' => 'pi_listar_cadastros',
                        'icon'       => 'dashicons-megaphone',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_edital_novo',
                        'label'      => \__('Novo edital', 'participe-ibram'),
                        'capability' => 'pi_criar_edital',
                        'icon'       => 'dashicons-plus-alt2',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_habilitacoes',
                        'label'      => \__('Habilitações pendentes', 'participe-ibram'),
                        'capability' => 'pi_decidir_habilitacao',
                        'icon'       => 'dashicons-yes-alt',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_recursos_inabilitacao',
                        'label'      => \__('Recursos de inabilitação', 'participe-ibram'),
                        'capability' => 'pi_decidir_habilitacao',
                        'icon'       => 'dashicons-warning',
                        'is_active'  => false,
                    ],
                ],
            ],

            // ── Grupo 4 — Votações ────────────────────────────────────────────
            [
                'title' => \__('Votações', 'participe-ibram'),
                'items' => [
                    [
                        'slug'       => 'participe-ibram_votacoes',
                        'label'      => \__('Votações', 'participe-ibram'),
                        'capability' => 'pi_apurar_votacao',
                        'icon'       => 'dashicons-thumbs-up',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_votacao_auditoria',
                        'label'      => \__('Auditoria de votação', 'participe-ibram'),
                        'capability' => 'pi_visualizar_audit_log',
                        'icon'       => 'dashicons-search',
                        'is_active'  => false,
                    ],
                ],
            ],

            // ── Grupo 5 — Conformidade & LGPD ────────────────────────────────
            [
                'title' => \__('Conformidade & LGPD', 'participe-ibram'),
                'items' => [
                    [
                        // Slug real: participe-ibram_audit_log (AuditMenuRegistry::SLUG_LOG)
                        'slug'       => 'participe-ibram_audit_log',
                        'label'      => \__('Log de eventos', 'participe-ibram'),
                        'capability' => 'pi_visualizar_audit_log',
                        'icon'       => 'dashicons-list-view',
                        'is_active'  => false,
                    ],
                    [
                        // Slug real: participe-ibram_audit_pii (AuditMenuRegistry::SLUG_PII)
                        // Spec tinha "participe-ibram_audit_log_pii" — CORRIGIDO.
                        'slug'       => 'participe-ibram_audit_pii',
                        'label'      => \__('Acessos a PII', 'participe-ibram'),
                        'capability' => 'pi_visualizar_audit_log',
                        'icon'       => 'dashicons-shield',
                        'is_active'  => false,
                    ],
                    [
                        // Slug real: participe-ibram_audit_decisoes (AuditMenuRegistry::SLUG_DECISOES)
                        // Spec tinha "participe-ibram_audit_log_decisoes" — CORRIGIDO.
                        'slug'       => 'participe-ibram_audit_decisoes',
                        'label'      => \__('Decisões', 'participe-ibram'),
                        'capability' => 'pi_visualizar_audit_log',
                        'icon'       => 'dashicons-hammer',
                        'is_active'  => false,
                    ],
                    [
                        // Slug real: pi-dpo-config (DpoConfigController::MENU_SLUG)
                        // Spec tinha "pi_dpo_config" (underscore) — CORRIGIDO para hífen.
                        // Capability real: pi_administrar_dpo (DpoConfigController::CAPABILITY)
                        // Spec tinha "pi_administrar_lgpd" — CORRIGIDO.
                        'slug'       => 'pi-dpo-config',
                        'label'      => \__('Configuração DPO', 'participe-ibram'),
                        'capability' => 'pi_administrar_dpo',
                        'icon'       => 'dashicons-id-alt',
                        'is_active'  => false,
                    ],
                ],
            ],

            // ── Grupo 6 — Ferramentas ─────────────────────────────────────────
            [
                'title' => \__('Ferramentas', 'participe-ibram'),
                'items' => [
                    [
                        // Slug real: pi-email (EmailController::MENU_SLUG)
                        'slug'       => 'pi-email',
                        'label'      => \__('E-mail', 'participe-ibram'),
                        'capability' => 'pi_administrar_email',
                        'icon'       => 'dashicons-email-alt',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_setup_teste',
                        'label'      => \__('Setup de teste', 'participe-ibram'),
                        'capability' => 'manage_options',
                        'icon'       => 'dashicons-admin-tools',
                        'is_active'  => false,
                    ],
                    [
                        'slug'       => 'participe-ibram_ajuda',
                        'label'      => \__('Ajuda', 'participe-ibram'),
                        'capability' => 'read',
                        'icon'       => 'dashicons-editor-help',
                        'is_active'  => false,
                    ],
                ],
            ],
        ];
    }
}
