<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Agente\StatusCadastro}.
 *
 * Cobre TODA a matriz de transições da máquina de estados (TD-05).
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Agente;

use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StatusCadastroTest extends TestCase
{
    public function test_factories_devolvem_valores_canonicos(): void
    {
        $this->assertSame('rascunho', StatusCadastro::rascunho()->value());
        $this->assertSame('submetido', StatusCadastro::submetido()->value());
        $this->assertSame('em_analise', StatusCadastro::emAnalise()->value());
        $this->assertSame('deferido', StatusCadastro::deferido()->value());
        $this->assertSame('deferido_em_retratacao', StatusCadastro::deferidoEmRetratacao()->value());
        $this->assertSame('deferido_em_recurso', StatusCadastro::deferidoEmRecurso()->value());
        $this->assertSame('indeferido_aguardando_recurso', StatusCadastro::indeferidoAguardandoRecurso()->value());
        $this->assertSame('em_retratacao', StatusCadastro::emRetratacao()->value());
        $this->assertSame('em_recurso_presidencia', StatusCadastro::emRecursoPresidencia()->value());
        $this->assertSame('indeferido_final', StatusCadastro::indeferidoFinal()->value());
    }

    public function test_from_string_normaliza_caixa_e_espacos(): void
    {
        $this->assertTrue(StatusCadastro::fromString(' RASCUNHO ')->equals(StatusCadastro::rascunho()));
        $this->assertTrue(StatusCadastro::fromString('Em_Analise')->equals(StatusCadastro::emAnalise()));
    }

    public function test_from_string_rejeita_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StatusCadastro::fromString('estado_inexistente');
    }

    public function test_all_inclui_os_dez_estados_da_portaria(): void
    {
        $this->assertCount(10, StatusCadastro::all());
    }

    /**
     * @dataProvider deferidoProvider
     */
    public function test_is_deferido_cobre_tres_variacoes(string $value, bool $expected): void
    {
        $this->assertSame($expected, StatusCadastro::fromString($value)->isDeferido());
    }

    /**
     * @return iterable<array{string,bool}>
     */
    public function deferidoProvider(): iterable
    {
        yield ['deferido',                true];
        yield ['deferido_em_retratacao',  true];
        yield ['deferido_em_recurso',     true];
        yield ['rascunho',                false];
        yield ['indeferido_final',        false];
        yield ['indeferido_aguardando_recurso', false];
    }

    /**
     * @dataProvider isFinalProvider
     */
    public function test_is_final_marca_estados_terminais(string $value, bool $expected): void
    {
        $this->assertSame($expected, StatusCadastro::fromString($value)->isFinal());
    }

    /**
     * @return iterable<array{string,bool}>
     */
    public function isFinalProvider(): iterable
    {
        yield ['deferido',                          true];
        yield ['deferido_em_retratacao',            true];
        yield ['deferido_em_recurso',               true];
        yield ['indeferido_final',                  true];
        yield ['rascunho',                          false];
        yield ['submetido',                         false];
        yield ['em_analise',                        false];
        yield ['indeferido_aguardando_recurso',     false];
        yield ['em_retratacao',                     false];
        yield ['em_recurso_presidencia',            false];
    }

    public function test_is_indeferido_cobre_aguardando_e_final(): void
    {
        $this->assertTrue(StatusCadastro::indeferidoAguardandoRecurso()->isIndeferido());
        $this->assertTrue(StatusCadastro::indeferidoFinal()->isIndeferido());
        $this->assertFalse(StatusCadastro::deferido()->isIndeferido());
        $this->assertFalse(StatusCadastro::rascunho()->isIndeferido());
    }

    /**
     * Matriz completa do TD-05.
     *
     * @dataProvider transicoesValidasProvider
     */
    public function test_can_transition_to_aceita_transicoes_validas(string $from, string $to): void
    {
        $this->assertTrue(
            StatusCadastro::fromString($from)->canTransitionTo(StatusCadastro::fromString($to)),
            sprintf('Esperado que %s -> %s fosse permitido.', $from, $to)
        );
    }

    /**
     * @return iterable<array{string,string}>
     */
    public function transicoesValidasProvider(): iterable
    {
        yield ['rascunho',                            'submetido'];
        yield ['submetido',                           'em_analise'];
        yield ['em_analise',                          'deferido'];
        yield ['em_analise',                          'indeferido_aguardando_recurso'];
        yield ['indeferido_aguardando_recurso',       'indeferido_final'];
        yield ['indeferido_aguardando_recurso',       'em_retratacao'];
        yield ['em_retratacao',                       'deferido_em_retratacao'];
        yield ['em_retratacao',                       'em_recurso_presidencia'];
        yield ['em_recurso_presidencia',              'deferido_em_recurso'];
        yield ['em_recurso_presidencia',              'indeferido_final'];
    }

    /**
     * Casos negativos representativos: pulos de etapa, voltas, transições a partir
     * de estados terminais.
     *
     * @dataProvider transicoesInvalidasProvider
     */
    public function test_can_transition_to_recusa_transicoes_invalidas(string $from, string $to): void
    {
        $this->assertFalse(
            StatusCadastro::fromString($from)->canTransitionTo(StatusCadastro::fromString($to)),
            sprintf('Esperado que %s -> %s fosse proibido.', $from, $to)
        );
    }

    /**
     * @return iterable<array{string,string}>
     */
    public function transicoesInvalidasProvider(): iterable
    {
        // Pulos
        yield ['rascunho',     'em_analise'];
        yield ['rascunho',     'deferido'];
        yield ['submetido',    'deferido'];
        yield ['submetido',    'rascunho'];
        // Voltas
        yield ['em_analise',   'rascunho'];
        yield ['em_analise',   'submetido'];
        // A partir de estados terminais (deferido*, indeferido_final)
        yield ['deferido',                'rascunho'];
        yield ['deferido',                'em_analise'];
        yield ['deferido_em_retratacao',  'em_analise'];
        yield ['deferido_em_recurso',     'em_analise'];
        yield ['indeferido_final',        'rascunho'];
        yield ['indeferido_final',        'em_retratacao'];
        // Recurso direto sem passar por aguardando
        yield ['em_analise',   'em_retratacao'];
        // Recurso de presidência partindo de aguardando recurso
        yield ['indeferido_aguardando_recurso', 'em_recurso_presidencia'];
        yield ['indeferido_aguardando_recurso', 'deferido'];
    }

    public function test_estados_terminais_nao_transicionam_para_lugar_algum(): void
    {
        $todos = StatusCadastro::all();
        $finais = ['deferido', 'deferido_em_retratacao', 'deferido_em_recurso', 'indeferido_final'];

        foreach ($finais as $finalState) {
            foreach ($todos as $alvo) {
                $this->assertFalse(
                    StatusCadastro::fromString($finalState)->canTransitionTo(StatusCadastro::fromString($alvo)),
                    sprintf('Estado terminal %s nao deve transicionar para %s.', $finalState, $alvo)
                );
            }
        }
    }
}
