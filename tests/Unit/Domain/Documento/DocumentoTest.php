<?php
/**
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Documento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Documento;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Documento\Documento;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DocumentoTest extends TestCase
{
    /**
     * Helper: cria um Documento válido para reutilizar nos testes.
     */
    private static function novoDocumento(): Documento
    {
        return new Documento(
            null,
            42,                                  // agenteId
            null,                                // inscricaoId
            7,                                   // tipoDocumentoId
            '2026/05/42/abc.pdf',                // arquivoPath
            'documento.pdf',                     // nomeOriginal
            'application/pdf',                   // mimeReal
            12345,                               // tamanhoBytes
            str_repeat('a', 64),                 // hashSha256 hex
            10,                                  // uploadedBy
            new DateTimeImmutable('2026-05-06 10:00:00')
        );
    }

    public function test_constroi_documento_valido(): void
    {
        $doc = self::novoDocumento();
        $this->assertSame(42, $doc->agenteId());
        $this->assertSame('application/pdf', $doc->mimeReal());
        $this->assertFalse($doc->isValidado());
        $this->assertNull($doc->validadoEm());
        $this->assertNull($doc->validadoPor());
    }

    public function test_id_negativo_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento(
            -1,
            null,
            null,
            7,
            'a/b/c.pdf',
            'x.pdf',
            'application/pdf',
            1,
            str_repeat('a', 64),
            10,
            new DateTimeImmutable('now')
        );
    }

    public function test_hash_invalido_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento(
            null,
            42,
            null,
            7,
            'a/b/c.pdf',
            'x.pdf',
            'application/pdf',
            1,
            'naoehumhashvalido',
            10,
            new DateTimeImmutable('now')
        );
    }

    public function test_marcar_validado_atualiza_estado(): void
    {
        $doc = self::novoDocumento();
        $doc->marcarValidado(99, 'OK conforme original.');

        $this->assertTrue($doc->isValidado());
        $this->assertSame(99, $doc->validadoPor());
        $this->assertSame('OK conforme original.', $doc->observacoesValidacao());
        $this->assertNotNull($doc->validadoEm());
    }

    public function test_marcar_validado_sem_obs_aceita_null(): void
    {
        $doc = self::novoDocumento();
        $doc->marcarValidado(99);

        $this->assertTrue($doc->isValidado());
        $this->assertNull($doc->observacoesValidacao());
    }

    public function test_marcar_validado_com_ator_invalido_lanca(): void
    {
        $doc = self::novoDocumento();
        $this->expectException(InvalidArgumentException::class);
        $doc->marcarValidado(0, 'qualquer');
    }

    public function test_marcar_invalido_exige_observacao(): void
    {
        $doc = self::novoDocumento();
        $this->expectException(InvalidArgumentException::class);
        $doc->marcarInvalido(99, '   ');
    }

    public function test_marcar_invalido_atualiza_estado(): void
    {
        $doc = self::novoDocumento();
        $doc->marcarInvalido(99, 'Documento ilegivel');

        $this->assertFalse($doc->isValidado());
        $this->assertSame(99, $doc->validadoPor());
        $this->assertSame('Documento ilegivel', $doc->observacoesValidacao());
        $this->assertNotNull($doc->validadoEm());
    }

    public function test_with_id_retorna_nova_instancia(): void
    {
        $doc = self::novoDocumento();
        $persisted = $doc->withId(123);
        $this->assertSame(123, $persisted->id());
        $this->assertNull($doc->id(), 'Original permanece imutável.');
    }

    public function test_with_id_zero_lanca(): void
    {
        $doc = self::novoDocumento();
        $this->expectException(InvalidArgumentException::class);
        $doc->withId(0);
    }

    public function test_construtor_com_validado_sem_data_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Documento(
            null,
            42,
            null,
            7,
            'a/b/c.pdf',
            'x.pdf',
            'application/pdf',
            1,
            str_repeat('a', 64),
            10,
            new DateTimeImmutable('now'),
            true,           // validado
            null            // validadoEm AUSENTE
        );
    }
}
