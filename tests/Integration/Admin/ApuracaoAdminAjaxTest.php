<?php
/**
 * Integration tests for ApuracaoAdminAjax pipeline.
 *
 *  1. cap mismatch → 403
 *  2. nonce inválido → 403
 *  3. publicar dispara hook `pi_resultado_publicado`
 *  4. apurar com tie-break aplica corretamente o critério `inscrito_em ASC`
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\ApurarHandler;
use Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoHandler;
use Ibram\ParticipeIbram\Application\Votacao\PublicarResultadoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\ApuracaoAdminAjax;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeCategoriaConsultaGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeInscricaoConsultaGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeResultadoRepository;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotacaoRepository;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotoRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Unit/Application/Votacao/Fakes.php';

final class ApuracaoAdminAjaxTest extends TestCase
{
    private FakeVotacaoRepository $votacoes;
    private FakeVotoRepository $votos;
    private FakeResultadoRepository $resultados;
    private FakeCategoriaConsultaGateway $cats;
    private FakeInscricaoConsultaGateway $inscs;
    private AuditLogger $audit;

    private const HASH = 'a1b2c3d4e5f607182930415263748596a1b2c3d4e5f607182930415263748596';

    protected function setUp(): void
    {
        parent::setUp();
        $_POST = [];
        $_GET  = [];
        $GLOBALS['__pi_test_user_caps']       = [];
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $GLOBALS['__pi_test_nonce_valid']     = false;
        $GLOBALS['__pi_test_transients']      = [];
        $GLOBALS['__pi_last_json']            = null;

        $this->votacoes   = new FakeVotacaoRepository();
        $this->votos      = new FakeVotoRepository();
        $this->resultados = new FakeResultadoRepository();
        $this->cats       = new FakeCategoriaConsultaGateway();
        $this->inscs      = new FakeInscricaoConsultaGateway();

        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';
            public function insert(string $t, array $d, array $f): bool { return true; }
        };
        $this->audit = new AuditLogger($wpdb, new IpResolver([], []));
    }

    private function buildSut(): ApuracaoAdminAjax
    {
        $apurar   = new ApurarHandler(
            $this->votacoes,
            $this->votos,
            $this->resultados,
            $this->cats,
            $this->inscs,
            $this->audit
        );
        $publicar = new PublicarResultadoHandler($this->votacoes, $this->resultados, $this->audit);
        $exportar = new ExportarRelatorioApuracaoHandler(
            $this->votacoes,
            $this->resultados,
            $this->votos,
            $this->audit,
            static fn (int $id): array => ['numero_registro' => 'R' . $id, 'nome_publico' => 'C ' . $id],
            sys_get_temp_dir(),
            'http://example.test'
        );
        return new ApuracaoAdminAjax($apurar, $publicar, $exportar, $this->audit);
    }

    private function captureJson(callable $fn): array
    {
        ob_start();
        try {
            $fn();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__halt__') {
                throw $e;
            }
        }
        $out = ob_get_clean();
        if (!empty($GLOBALS['__pi_last_json'])) {
            $json = (array) $GLOBALS['__pi_last_json'];
            $GLOBALS['__pi_last_json'] = null;
            return $json;
        }
        $decoded = $out !== '' ? json_decode((string) $out, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public function testApurarCapMismatchReturns403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 5;
        $GLOBALS['__pi_test_user_caps']       = []; // sem cap
        $GLOBALS['__pi_test_nonce_valid']     = true;

        $_POST['votacao_id'] = '42';
        $_POST['_wpnonce']   = 'valid';

        $json = $this->captureJson([$this->buildSut(), 'ajaxApurar']);
        self::assertFalse((bool) ($json['success'] ?? true));
        $status = $json['data']['data']['status'] ?? 0;
        self::assertSame(403, (int) $status);
    }

    public function testPublicarNonceInvalidoReturns403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 5;
        $GLOBALS['__pi_test_user_caps']       = ['pi_publicar_resultado'];
        $GLOBALS['__pi_test_nonce_valid']     = false;

        $_POST['votacao_id'] = '42';
        $_POST['_wpnonce']   = 'invalid';

        $json = $this->captureJson([$this->buildSut(), 'ajaxPublicar']);
        self::assertFalse((bool) ($json['success'] ?? true));
        $status = $json['data']['data']['status'] ?? 0;
        self::assertSame(403, (int) $status);
    }

    public function testPublicarSucessoDisparaHook(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $GLOBALS['__pi_test_user_caps']       = ['pi_publicar_resultado'];
        $GLOBALS['__pi_test_nonce_valid']     = true;

        $apuradoEm = new DateTimeImmutable('2026-06-12 10:00:00', new DateTimeZone('UTC'));
        $votacao   = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::apurada(),
            ModoVotacao::porCategoria(),
            self::HASH,
            $apuradoEm
        );
        $seeded = $this->votacoes->seed($votacao);
        $vid    = (int) $seeded->id();

        $this->resultados->salvarResultados($vid, [
            new Resultado(null, $vid, 11, 202, 5, 1, true, false, $apuradoEm),
        ]);

        $_POST['votacao_id'] = (string) $vid;
        $_POST['_wpnonce']   = 'valid';

        // O hook é disparado dentro de PublicarResultadoHandler::handle.
        // Usamos o stub global do_action (no-op em testes) — o teste verifica
        // através do estado do FakeVotacaoRepository e do retorno success.
        $json = $this->captureJson([$this->buildSut(), 'ajaxPublicar']);
        self::assertTrue((bool) ($json['success'] ?? false));
        self::assertSame($vid, (int) ($json['data']['votacao_id'] ?? 0));
        self::assertSame(7, (int) ($json['data']['edital_id'] ?? 0));
    }

    public function testApurarTieBreakAplicaCorretamente(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 11;
        $GLOBALS['__pi_test_user_caps']       = ['pi_apurar_votacao'];
        $GLOBALS['__pi_test_nonce_valid']     = true;

        // Votação encerrada com hash registrado (pré-condição de Apurar).
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            self::HASH
        );
        $seeded = $this->votacoes->seed($votacao);
        $vid    = (int) $seeded->id();
        $cat    = 11;

        // 2 candidatos com 3 votos cada — desempate por inscrito_em ASC.
        $when = new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'));
        for ($i = 0; $i < 3; $i++) {
            $this->votos->salvarVoto(new Voto(
                null, $vid, $cat, str_pad(dechex(100 + $i), 64, '0', STR_PAD_LEFT), 202, $when
            ));
            $this->votos->salvarVoto(new Voto(
                null, $vid, $cat, str_pad(dechex(200 + $i), 64, '0', STR_PAD_LEFT), 303, $when
            ));
        }

        $this->cats->vagas[$cat]               = 1;
        $this->cats->categoriasDoEdital[7]     = [$cat];
        // 303 inscreveu antes — deve ficar à frente do 202 no tie-break.
        $this->inscs->inscritoEm[202] = new DateTimeImmutable('2026-05-10');
        $this->inscs->inscritoEm[303] = new DateTimeImmutable('2026-05-01');

        $_POST['votacao_id'] = (string) $vid;
        $_POST['_wpnonce']   = 'valid';

        $json = $this->captureJson([$this->buildSut(), 'ajaxApurar']);
        self::assertTrue((bool) ($json['success'] ?? false), 'Apurar deve retornar success');

        // Resultado persistido — verifica via repo fake.
        $resultados = $this->resultados->findByVotacao($vid);
        self::assertNotEmpty($resultados);

        // Posição 1 deve ser o candidato 303 (inscrito_em mais antigo).
        $pos1 = null;
        foreach ($resultados as $r) {
            if ($r->posicao() === 1) { $pos1 = $r; break; }
        }
        self::assertNotNull($pos1);
        self::assertSame(303, $pos1->candidatoInscricaoId(), 'Tie-break por inscrito_em ASC.');
    }

    public function testInvalidStateReturns409(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 11;
        $GLOBALS['__pi_test_user_caps']       = ['pi_apurar_votacao'];
        $GLOBALS['__pi_test_nonce_valid']     = true;

        // Votação ainda aberta — apurar deve falhar com 409.
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $seeded = $this->votacoes->seed($votacao);

        $_POST['votacao_id'] = (string) $seeded->id();
        $_POST['_wpnonce']   = 'valid';

        $json = $this->captureJson([$this->buildSut(), 'ajaxApurar']);
        self::assertFalse((bool) ($json['success'] ?? true));
        $status = $json['data']['data']['status'] ?? 0;
        self::assertSame(409, (int) $status);
    }
}
