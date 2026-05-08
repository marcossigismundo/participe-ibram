<?php
/**
 * Unit test for {@see Ibram\ParticipeIbram\Bootstrap\VotacaoCapabilities}.
 *
 * Foco: idempotência (`maybeUpgrade()` aplica caps uma única vez) e
 * matriz role→cap correta.
 *
 * Estratégia: o bootstrap dos testes já provê stubs globais de `get_option`
 * e `update_option` (ver `tests/bootstrap.php`). Adicionamos um stub global
 * `get_role` aqui também, idempotentemente.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Bootstrap
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bloco no namespace global — stub de get_role (idempotente).
// `get_option`/`update_option` já vêm de tests/bootstrap.php.
// ---------------------------------------------------------------------------
namespace {
    if (!function_exists('get_role')) {
        function get_role(string $name)
        {
            $store = $GLOBALS['__pi_test_role_store_vct'] ?? [];
            return $store[$name] ?? null;
        }
    }

    // Garante a existência da classe global \WP_Role para o type-hint.
    if (!class_exists(\WP_Role::class, false)) {
        // phpcs:ignore Squiz.PHP.Eval
        eval(
            'class WP_Role {'
            . ' public string $name = "";'
            . ' public array $capabilities = [];'
            . ' public function add_cap($cap, $grant = true): void {}'
            . '}'
        );
    }
}

namespace Ibram\ParticipeIbram\Tests\Unit\Bootstrap {

    use Ibram\ParticipeIbram\Bootstrap\VotacaoCapabilities;
    use PHPUnit\Framework\TestCase;

    /**
     * Fake equivalente a `\WP_Role` que conta `add_cap` calls.
     *
     * @internal
     */
    final class FakeRoleVCT extends \WP_Role
    {
        /** @var list<string> */
        public array $capsApplied = [];

        /** @var list<string> */
        public array $addCapCalls = [];

        public function __construct()
        {
            $this->name         = 'fake';
            $this->capabilities = [];
        }

        public function add_cap($cap, $grant = true): void
        {
            $this->addCapCalls[] = (string) $cap;
            if (!in_array($cap, $this->capsApplied, true)) {
                $this->capsApplied[] = (string) $cap;
            }
            $this->capabilities[(string) $cap] = (bool) $grant;
        }
    }

    /**
     * @covers \Ibram\ParticipeIbram\Bootstrap\VotacaoCapabilities
     */
    final class VotacaoCapabilitiesTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__pi_test_options']        = [];
            $GLOBALS['__pi_test_role_store_vct'] = [
                'administrator'    => new FakeRoleVCT(),
                'pi_administrador' => new FakeRoleVCT(),
                'pi_apuracao'      => new FakeRoleVCT(),
                'pi_analista'      => new FakeRoleVCT(),
                'pi_dpo'           => new FakeRoleVCT(),
            ];
        }

        public function testRegisterAplicaCapsCorretasPorRole(): void
        {
            $matrix = [
                'administrator'    => ['pi_apurar_votacao', 'pi_publicar_resultado', 'pi_visualizar_audit_log'],
                'pi_administrador' => ['pi_apurar_votacao', 'pi_publicar_resultado', 'pi_visualizar_audit_log'],
                'pi_apuracao'      => ['pi_apurar_votacao', 'pi_publicar_resultado', 'pi_visualizar_audit_log'],
                'pi_analista'      => ['pi_apurar_votacao', 'pi_visualizar_audit_log'],
                'pi_dpo'           => ['pi_visualizar_audit_log'],
            ];

            foreach ($matrix as $name => $expected) {
                $role = $GLOBALS['__pi_test_role_store_vct'][$name];
                VotacaoCapabilities::register($role, $name);
                sort($expected);
                $actual = $role->capsApplied;
                sort($actual);
                self::assertSame($expected, $actual, "Caps mismatch for role {$name}");
            }
        }

        public function testMaybeUpgradeIdempotente(): void
        {
            VotacaoCapabilities::maybeUpgrade();
            /** @var FakeRoleVCT $apuracaoRole */
            $apuracaoRole    = $GLOBALS['__pi_test_role_store_vct']['pi_apuracao'];
            $totalAfterFirst = count($apuracaoRole->addCapCalls);

            self::assertGreaterThan(0, $totalAfterFirst);
            self::assertSame(2, (int) ($GLOBALS['__pi_test_options']['pi_caps_version'] ?? 0));

            VotacaoCapabilities::maybeUpgrade();
            self::assertSame(
                $totalAfterFirst,
                count($apuracaoRole->addCapCalls),
                'maybeUpgrade nao deve aplicar caps duas vezes.'
            );
        }

        public function testCapabilitiesListaCanonica(): void
        {
            $caps = VotacaoCapabilities::capabilities();
            sort($caps);
            $expected = ['pi_apurar_votacao', 'pi_publicar_resultado', 'pi_visualizar_audit_log'];
            sort($expected);
            self::assertSame($expected, $caps);
        }
    }
}
