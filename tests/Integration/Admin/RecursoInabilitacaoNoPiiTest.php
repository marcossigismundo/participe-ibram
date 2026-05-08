<?php
/**
 * Integration tests — garantia de que a listagem admin de recursos de inabilitação
 * NÃO retorna CPF / e-mail do agente (W5-C, CRÍTICO para Onda 10).
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Admin;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosInabilitacaoListTable;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bootstrap.php';

/**
 * Stub de wpdb mínimo para testar a list table sem banco real.
 */
final class FakeWpdbForPiiTest
{
    public string $prefix = 'wp_';

    /** @var array<int,array<string,mixed>> */
    private array $rows;

    /** @param array<int,array<string,mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function prepare(string $sql, ...$args): string
    {
        // Stub mínimo: apenas retorna o SQL sem placeholders reais.
        return $sql;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function get_results(string $sql, int $output = ARRAY_A): array
    {
        return $this->rows;
    }

    /** @return mixed */
    public function get_var(string $sql)
    {
        return count($this->rows);
    }
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 1);
}
if (!function_exists('get_user_by')) {
    function get_user_by(string $field, $value): object|false
    {
        // Retorna um user stub com CPF no display_name para verificar que não é exposto.
        $obj = new \stdClass();
        $obj->display_name = 'Maria da Silva — CPF 123.456.789-00 — email@test.com';
        $obj->user_email   = 'email@test.com';
        return $obj;
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosInabilitacaoListTable
 */
final class RecursoInabilitacaoNoPiiTest extends TestCase
{
    /** Lista admin de recursos não deve retornar CPF/e-mail em texto plano. */
    public function test_lista_nao_expoe_cpf_email_agente(): void
    {
        $fakeRows = [
            [
                'id'                    => 1,
                'inscricao_id'          => 10,
                'fundamentacao_md'      => 'Fundamentação do recurso de teste.',
                'protocolado_em'        => '2026-04-01 10:00:00',
                'decisao'               => null,
                'decisor_id'            => null,
                'agente_id'             => 99,
                'edital_id'             => 5,
                'categoria_id'          => 3,
                'motivo_inabilitacao_md' => 'Documentação incompleta conforme edital.',
            ],
        ];

        $wpdb = new FakeWpdbForPiiTest($fakeRows);

        // Simula prepare_items
        $table = new RecursosInabilitacaoListTable($wpdb);
        $table->prepare_items();

        $items = $table->items;
        $this->assertNotEmpty($items, 'A tabela deve ter itens');

        $item = $items[0];

        // Campo agente_nome deve estar mascarado — não pode conter CPF completo.
        $agentNome = (string) ($item['agente_nome'] ?? '');
        $this->assertStringNotContainsString('123.456.789-00', $agentNome, 'CPF não deve aparecer na coluna agente_nome');
        $this->assertStringNotContainsString('email@test.com', $agentNome, 'E-mail não deve aparecer na coluna agente_nome');

        // O nome mascarado deve conter '***' (padrão de PiiMasker::maskGeneric).
        $this->assertStringContainsString('***', $agentNome, 'Nome deve estar mascarado com PiiMasker');

        // Nenhum campo do item deve conter CPF ou e-mail pessoal.
        foreach ($item as $key => $value) {
            $valueStr = (string) $value;
            $this->assertStringNotContainsString('123.456.789-00', $valueStr, "Campo '{$key}' não deve conter CPF");
            $this->assertStringNotContainsString('email@test.com', $valueStr, "Campo '{$key}' não deve conter e-mail pessoal");
        }
    }

    /** PiiMasker::maskGeneric deve mascarar nome corretamente. */
    public function test_pii_masker_mascara_nome(): void
    {
        $nome     = 'Maria da Silva';
        $mascarado = PiiMasker::maskGeneric($nome, 2, 2);

        $this->assertStringContainsString('***', $mascarado);
        $this->assertStringNotContainsString('aria da Silv', $mascarado);
        // Deve preservar 2 chars do início e 2 do fim.
        $this->assertStringStartsWith('Ma', $mascarado);
        $this->assertStringEndsWith('va', $mascarado);
    }
}
