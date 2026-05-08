<?php
/**
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Documento;

use Ibram\ParticipeIbram\Domain\Documento\TipoDocumento;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TipoDocumentoTest extends TestCase
{
    public function test_mime_permitidos_array_normaliza(): void
    {
        $tipo = new TipoDocumento(
            1,
            'cnpj',
            'CNPJ',
            null,
            'OR',
            'application/pdf, image/JPEG ,image/png,application/pdf',
            2048
        );
        $this->assertSame(
            ['application/pdf', 'image/jpeg', 'image/png'],
            $tipo->mimePermitidosArray()
        );
    }

    public function test_permite_mime_case_insensitive(): void
    {
        $tipo = new TipoDocumento(
            1,
            'pdf',
            'PDF',
            null,
            null,
            'application/pdf',
            1024
        );
        $this->assertTrue($tipo->permiteMime('APPLICATION/PDF'));
        $this->assertFalse($tipo->permiteMime('image/png'));
    }

    public function test_obrigatorio_para_csv_simples(): void
    {
        $tipo = new TipoDocumento(
            1,
            'cnpj',
            'CNPJ',
            null,
            'OR',
            'application/pdf',
            2048
        );
        $this->assertTrue($tipo->isObrigatorioParaTipoAgente('OR'));
        $this->assertFalse($tipo->isObrigatorioParaTipoAgente('PF'));
        $this->assertFalse($tipo->isObrigatorioParaTipoAgente('SM'));
    }

    public function test_obrigatorio_para_csv_multiplo(): void
    {
        $tipo = new TipoDocumento(
            1,
            'cpf',
            'CPF',
            null,
            'PF,SM',
            'application/pdf',
            5120
        );
        $this->assertTrue($tipo->isObrigatorioParaTipoAgente('PF'));
        $this->assertTrue($tipo->isObrigatorioParaTipoAgente('SM'));
        $this->assertTrue($tipo->isObrigatorioParaTipoAgente(' pf '), 'normaliza espaco e caixa');
        $this->assertFalse($tipo->isObrigatorioParaTipoAgente('OR'));
    }

    public function test_obrigatorio_para_null_retorna_false(): void
    {
        $tipo = new TipoDocumento(
            1,
            'documentos_coletivo',
            'Documentos do coletivo',
            null,
            null,                                // sem obrigatoriedade
            'application/pdf',
            10240
        );
        $this->assertFalse($tipo->isObrigatorioParaTipoAgente('PF'));
        $this->assertFalse($tipo->isObrigatorioParaTipoAgente('OR'));
        $this->assertFalse($tipo->isObrigatorioParaTipoAgente(''));
    }

    public function test_tamanho_max_bytes(): void
    {
        $tipo = new TipoDocumento(
            1,
            'pdf',
            'PDF',
            null,
            null,
            'application/pdf',
            10
        );
        $this->assertSame(10240, $tipo->tamanhoMaxBytes());
    }

    public function test_codigo_normalizado_em_minusculas(): void
    {
        $tipo = new TipoDocumento(
            1,
            ' Estatuto ',
            'Estatuto',
            null,
            'OR',
            'application/pdf',
            10240
        );
        $this->assertSame('estatuto', $tipo->codigo());
    }

    public function test_codigo_vazio_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TipoDocumento(
            1,
            '',
            'X',
            null,
            null,
            'application/pdf',
            1024
        );
    }

    public function test_tamanho_zero_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TipoDocumento(
            1,
            'pdf',
            'PDF',
            null,
            null,
            'application/pdf',
            0
        );
    }
}
