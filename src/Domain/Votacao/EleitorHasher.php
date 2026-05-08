<?php
/**
 * Geração canônica do eleitor_hash (HMAC-keyed) para auditabilidade do voto.
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use RuntimeException;

/**
 * Calcula `eleitor_hash = generichash(secret, agente_id || votacao_id)` em hex64.
 *
 * Especificação (TD-06, ARCHITECTURE.md):
 *  - Garante (a) unicidade por par (votação, eleitor) via UNIQUE no banco;
 *  - (b) auditabilidade — a mesma fórmula reconstrói o hash a partir dos IDs;
 *  - (c) anonimato na contagem — sem o secret é inviável reverter o hash.
 *
 * Segregação de chaves (LGPD/segurança):
 *  - Esta classe usa **uma constante separada `PI_VOTING_SECRET`** definida em
 *    `wp-config.php`. NÃO reutiliza `PI_HMAC_KEY` (do {@see SodiumCipher}) nem
 *    `PI_ENC_KEY_*`. Princípio da segregação por finalidade — uma chave para
 *    busca exata de PII (CPF/CNPJ), outra para anonimização do voto.
 *  - Comprometer um domínio não compromete o outro.
 *  - O secret nunca aparece em logs nem em mensagens de exceção.
 *
 * Constante esperada em wp-config.php:
 *
 *   define('PI_VOTING_SECRET', '<base64 de 32 bytes aleatorios>');
 *
 * Gerar com: `php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"`
 */
final class EleitorHasher
{
    /**
     * Chave bruta (32 bytes) carregada da constante `PI_VOTING_SECRET`.
     */
    private string $secret;

    /**
     * @param string|null $secretBase64 Se fornecido, usa este secret (em base64);
     *                                  caso contrário, lê `PI_VOTING_SECRET` do ambiente.
     *                                  Útil para testes — produção sempre passa null.
     *
     * @throws RuntimeException Quando o secret não está definido ou é inválido.
     */
    public function __construct(?string $secretBase64 = null)
    {
        $expected = SODIUM_CRYPTO_GENERICHASH_KEYBYTES; // 32

        if ($secretBase64 === null) {
            if (!\defined('PI_VOTING_SECRET')) {
                throw new RuntimeException(
                    'Constante PI_VOTING_SECRET nao definida em wp-config.php. '
                    . 'Defina como base64 de 32 bytes aleatorios. '
                    . 'NAO reutilize PI_HMAC_KEY nem PI_ENC_KEY_* — chaves devem ser '
                    . 'segregadas por finalidade (LGPD).'
                );
            }
            $secretBase64 = (string) \PI_VOTING_SECRET;
        }

        if ($secretBase64 === '') {
            throw new RuntimeException('PI_VOTING_SECRET vazia.');
        }

        $raw = base64_decode($secretBase64, true);
        if ($raw === false || strlen($raw) !== $expected) {
            throw new RuntimeException(sprintf(
                'PI_VOTING_SECRET invalida (esperado base64 de %d bytes).',
                $expected
            ));
        }

        $this->secret = $raw;
    }

    /**
     * Calcula o `eleitor_hash` canônico em hexadecimal (64 chars).
     *
     * Fórmula: `sodium_bin2hex(sodium_crypto_generichash($agenteId.'|'.$votacaoId, $secret, 32))`.
     *
     * O separador `|` evita ambiguidade entre `(12, 345)` e `(123, 45)`.
     *
     * @throws RuntimeException Em falha do sodium (extremamente raro).
     */
    public function hash(int $agenteId, int $votacaoId): string
    {
        if ($agenteId <= 0) {
            throw new RuntimeException('agenteId deve ser positivo para gerar eleitor_hash.');
        }
        if ($votacaoId <= 0) {
            throw new RuntimeException('votacaoId deve ser positivo para gerar eleitor_hash.');
        }

        $message = $agenteId . '|' . $votacaoId;

        try {
            $bin = sodium_crypto_generichash($message, $this->secret, 32);
        } catch (\Throwable $e) {
            // Mensagem genérica — não revelar detalhes do sodium.
            throw new RuntimeException('Falha ao calcular eleitor_hash.');
        }

        return sodium_bin2hex($bin);
    }

    /**
     * Verifica em tempo constante se o `expectedHash` corresponde aos IDs.
     *
     * Usa {@see hash_equals()} para evitar leak por timing-attack.
     *
     * @param int    $agenteId
     * @param int    $votacaoId
     * @param string $expectedHash Hash hex (64 chars) a ser comparado.
     */
    public function verify(int $agenteId, int $votacaoId, string $expectedHash): bool
    {
        if (strlen($expectedHash) !== 64 || !ctype_xdigit($expectedHash)) {
            return false;
        }

        try {
            $computed = $this->hash($agenteId, $votacaoId);
        } catch (\Throwable $e) {
            return false;
        }

        return hash_equals($computed, $expectedHash);
    }

    /**
     * Apaga o secret da memória ao destruir a instância (best-effort).
     */
    public function __destruct()
    {
        try {
            sodium_memzero($this->secret);
        } catch (\Throwable $e) {
            // Best-effort — alguns builds do PHP lançam ao zerar variável já fora de escopo.
        }
    }
}
