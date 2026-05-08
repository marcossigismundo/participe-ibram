<?php
/**
 * Testes do {@see UnsubscribeTokenizer}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Email;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Email\Templates\UnsubscribeTokenizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Email\Templates\UnsubscribeTokenizer
 */
final class UnsubscribeTokenizerTest extends TestCase
{
    public function test_round_trip_funciona(): void
    {
        $t       = new UnsubscribeTokenizer();
        $expira  = new DateTimeImmutable('+30 days');
        $token   = $t->tokenFor(42, 'comunicacao', $expira);
        $this->assertNotSame('', $token);

        $userId  = 0;
        $purpose = '';
        $exp     = null;
        $ok      = $t->verify($token, $userId, $purpose, $exp);
        $this->assertTrue($ok);
        $this->assertSame(42, $userId);
        $this->assertSame('comunicacao', $purpose);
        $this->assertInstanceOf(DateTimeImmutable::class, $exp);
    }

    public function test_token_modificado_e_rejeitado(): void
    {
        $t       = new UnsubscribeTokenizer();
        $expira  = new DateTimeImmutable('+30 days');
        $token   = $t->tokenFor(42, 'comunicacao', $expira);

        // Tampera no último char (que faz parte do HMAC).
        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'A' ? 'B' : 'A');

        $userId  = 0;
        $purpose = '';
        $exp     = null;
        $ok      = $t->verify($tampered, $userId, $purpose, $exp);
        $this->assertFalse($ok);
        $this->assertSame(0, $userId);
    }

    public function test_token_expirado_e_rejeitado(): void
    {
        $t       = new UnsubscribeTokenizer();
        // Forja um token "válido" mas com timestamp passado: usamos hack via reflection
        // — no teste, basta gerar com 1s no futuro e dormir... para evitar sleep,
        // criamos um token diretamente com timestamp negativo via instanceof + helper:
        // Em vez disso, testamos via expira no presente -> rejeitado.

        $this->expectException(\InvalidArgumentException::class);
        $t->tokenFor(42, 'comunicacao', new DateTimeImmutable('-1 second'));
    }

    public function test_purpose_invalido_lanca(): void
    {
        $t = new UnsubscribeTokenizer();
        $this->expectException(\InvalidArgumentException::class);
        $t->tokenFor(42, 'NÃO-VALIDO', new DateTimeImmutable('+1 day'));
    }

    public function test_user_id_invalido_lanca(): void
    {
        $t = new UnsubscribeTokenizer();
        $this->expectException(\InvalidArgumentException::class);
        $t->tokenFor(0, 'comunicacao', new DateTimeImmutable('+1 day'));
    }

    public function test_token_expirado_real_via_payload_forjado_e_rejeitado(): void
    {
        // Reconstroi a estrutura do token internamente para simular expiração
        // sem depender de sleep. Usamos a mesma chave secret() via reflection.
        $t = new UnsubscribeTokenizer();
        $rc = new \ReflectionClass(UnsubscribeTokenizer::class);
        $secretMethod = $rc->getMethod('secret');
        $secretMethod->setAccessible(true);
        $secret = $secretMethod->invoke(null);

        $payload = '42|comunicacao|' . (time() - 3600);
        $hmac    = hash_hmac('sha256', $payload, $secret);
        $raw     = $payload . '|' . $hmac;
        $token   = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $userId  = 0;
        $purpose = '';
        $exp     = null;
        $this->assertFalse($t->verify($token, $userId, $purpose, $exp));
    }

    public function test_token_invalido_base64_e_rejeitado(): void
    {
        $t = new UnsubscribeTokenizer();
        $userId = 0;
        $purpose = '';
        $exp = null;
        $this->assertFalse($t->verify('!!!notbase64!!!', $userId, $purpose, $exp));
        $this->assertFalse($t->verify('', $userId, $purpose, $exp));
    }
}
