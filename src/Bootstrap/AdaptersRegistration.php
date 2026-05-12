<?php
/**
 * Registers cross-domain adapter services in the DI container.
 *
 * Depends on RepositoryRegistration having been called so that
 * repo:agente, repo:categoria, and repo:inscricao are available.
 *
 * Consumed by: W9-C REST+shortcodes (inscrição, votação),
 *              W9-D events+crons (votação handlers).
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Application\Edital\Adapters\AgenteLookupAdapter;
use Ibram\ParticipeIbram\Application\Votacao\Adapters\AgenteVotanteGatewayAdapter;
use Ibram\ParticipeIbram\Application\Votacao\Adapters\CategoriaConsultaGatewayAdapter;
use Ibram\ParticipeIbram\Application\Votacao\Adapters\InscricaoConsultaGatewayAdapter;

/**
 * Registers all adapter:* services.
 *
 * All registrations use singleton() for lazy, cached resolution.
 * Each block is guarded by class_exists() for graceful degradation.
 */
final class AdaptersRegistration
{
    public static function register(Container $container): void
    {
        // ------------------------------------------------------------------
        // lookup:inscricao_publico — callable(int $inscricaoId): array{
        //     numero_registro: string,
        //     nome_publico: string
        // }
        //
        // Resolve um candidato_inscricao_id em identificadores PUBLICOS do
        // agente (whitelist anti-PII para apuração + exportação + tela
        // pública). Substitui o wiring quebrado da Wave 9-B que tentava
        // usar InscricaoConsultaGatewayAdapter como callable.
        //
        // Registrado no topo porque os blocos seguintes têm early-return em
        // class_exists() falho e poderiam pular este registro.
        // ------------------------------------------------------------------
        $container->singleton('lookup:inscricao_publico', static function (Container $c): \Closure {
            $wpdb        = $c->get('core:wpdb');
            $prefix      = (string) $wpdb->prefix;
            $tInscricoes = $prefix . 'pi_inscricoes';
            $tAgentes    = $prefix . 'pi_agentes';

            return static function (int $inscricaoId) use ($wpdb, $tInscricoes, $tAgentes): array {
                $vazio = ['numero_registro' => '', 'nome_publico' => ''];
                if ($inscricaoId <= 0) {
                    return $vazio;
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
                $sql = $wpdb->prepare(
                    "SELECT a.numero_registro AS numero_registro,
                            a.nome_publico    AS nome_publico
                     FROM {$tInscricoes} AS i
                     INNER JOIN {$tAgentes} AS a ON a.id = i.agente_id
                     WHERE i.id = %d
                     LIMIT 1",
                    $inscricaoId
                );
                $row = $wpdb->get_row($sql, ARRAY_A);
                // phpcs:enable
                if (!is_array($row)) {
                    return $vazio;
                }
                return [
                    'numero_registro' => isset($row['numero_registro']) ? (string) $row['numero_registro'] : '',
                    'nome_publico'    => isset($row['nome_publico'])    ? (string) $row['nome_publico']    : '',
                ];
            };
        });

        // ------------------------------------------------------------------
        // adapter:agente_lookup — AgenteLookupAdapter
        // Implements: AgenteLookupPort (used by inscrição handlers)
        // Depends on: repo:agente (AgenteRepository)
        // Consumed by: W9-C inscrição REST controller
        // ------------------------------------------------------------------
        if (!class_exists(AgenteLookupAdapter::class)) {
            return;
        }
        $container->singleton('adapter:agente_lookup', static function (Container $c): AgenteLookupAdapter {
            return new AgenteLookupAdapter($c->get('repo:agente'));
        });

        // ------------------------------------------------------------------
        // adapter:agente_votante — AgenteVotanteGatewayAdapter
        // Implements: AgenteVotanteGateway (anti-corruption layer Votacao->Agente)
        // Depends on: repo:agente (AgenteRepository)
        // Consumed by: W9-C votação REST, W9-D RegistrarVotoHandler
        // ------------------------------------------------------------------
        if (!class_exists(AgenteVotanteGatewayAdapter::class)) {
            return;
        }
        $container->singleton('adapter:agente_votante', static function (Container $c): AgenteVotanteGatewayAdapter {
            return new AgenteVotanteGatewayAdapter($c->get('repo:agente'));
        });

        // ------------------------------------------------------------------
        // adapter:categoria_consulta — CategoriaConsultaGatewayAdapter
        // Implements: CategoriaConsultaGateway (anti-corruption layer Votacao->Edital)
        // Depends on: repo:categoria (WpdbCategoriaRepository — concrete, not interface)
        // Consumed by: W9-C votação REST, W9-D RegistrarVotoHandler
        // ------------------------------------------------------------------
        if (!class_exists(CategoriaConsultaGatewayAdapter::class)) {
            return;
        }
        $container->singleton('adapter:categoria_consulta', static function (Container $c): CategoriaConsultaGatewayAdapter {
            return new CategoriaConsultaGatewayAdapter($c->get('repo:categoria'));
        });

        // ------------------------------------------------------------------
        // adapter:inscricao_consulta — InscricaoConsultaGatewayAdapter
        // Implements: InscricaoConsultaGateway (anti-corruption layer Votacao->Edital)
        // Depends on: repo:inscricao (WpdbInscricaoRepository — concrete, not interface)
        // Consumed by: W9-C votação REST, W9-D RegistrarVotoHandler
        // ------------------------------------------------------------------
        if (!class_exists(InscricaoConsultaGatewayAdapter::class)) {
            return;
        }
        $container->singleton('adapter:inscricao_consulta', static function (Container $c): InscricaoConsultaGatewayAdapter {
            return new InscricaoConsultaGatewayAdapter($c->get('repo:inscricao'));
        });
    }
}
