<?php
/**
 * Entidade Voto — espelha `wp_pi_votos` (SCHEMA §5).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Voto — entidade **estritamente imutável**.
 *
 * Após criação, NÃO existem setters. As únicas formas de obter outra instância
 * são `withId()` (atribuir o id atribuído pelo banco após INSERT) e
 * `withIpHash()` (anexar o hash do IP no momento da persistência). Quaisquer
 * outras "mutações" exigem uma nova entidade — o que é rejeitado pelo modelo
 * de negócio (votos não podem ser corrigidos após registro).
 *
 * Auditoria/LGPD:
 *  - `eleitorHash` é o HMAC computado por {@see EleitorHasher}; não revela a
 *    identidade do eleitor.
 *  - `ipHash` (opcional) é HMAC do IP, não o IP cru — preserva pseudonimização.
 *  - O construtor exige que `eleitorHash` seja hex de 64 chars (CHAR(64)).
 */
final class Voto
{
    private ?int $id;

    private int $votacaoId;

    private int $categoriaId;

    private string $eleitorHash;

    private int $candidatoInscricaoId;

    private DateTimeImmutable $votadoEm;

    private ?string $ipHash;

    /**
     * @throws InvalidArgumentException Quando invariantes locais falham.
     */
    public function __construct(
        ?int $id,
        int $votacaoId,
        int $categoriaId,
        string $eleitorHash,
        int $candidatoInscricaoId,
        DateTimeImmutable $votadoEm,
        ?string $ipHash = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Voto.id deve ser positivo quando informado.');
        }
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('Voto.votacaoId deve ser positivo.');
        }
        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('Voto.categoriaId deve ser positivo.');
        }
        if ($candidatoInscricaoId <= 0) {
            throw new InvalidArgumentException('Voto.candidatoInscricaoId deve ser positivo.');
        }
        self::assertHashHex64($eleitorHash, 'eleitorHash');
        if ($ipHash !== null) {
            self::assertHashHex64($ipHash, 'ipHash');
        }

        $this->id                    = $id;
        $this->votacaoId             = $votacaoId;
        $this->categoriaId           = $categoriaId;
        $this->eleitorHash           = $eleitorHash;
        $this->candidatoInscricaoId  = $candidatoInscricaoId;
        $this->votadoEm              = $votadoEm;
        $this->ipHash                = $ipHash;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }

    public function categoriaId(): int
    {
        return $this->categoriaId;
    }

    public function eleitorHash(): string
    {
        return $this->eleitorHash;
    }

    public function candidatoInscricaoId(): int
    {
        return $this->candidatoInscricaoId;
    }

    public function votadoEm(): DateTimeImmutable
    {
        return $this->votadoEm;
    }

    public function ipHash(): ?string
    {
        return $this->ipHash;
    }

    /**
     * Retorna nova instância com o id atribuído pelo banco. Imutabilidade
     * preservada — a instância original não muda.
     */
    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('id deve ser positivo.');
        }
        return new self(
            $id,
            $this->votacaoId,
            $this->categoriaId,
            $this->eleitorHash,
            $this->candidatoInscricaoId,
            $this->votadoEm,
            $this->ipHash
        );
    }

    /**
     * Retorna nova instância com o ipHash anexado (geralmente no momento da
     * persistência, quando IpResolver é chamado). Não muda a instância atual.
     */
    public function withIpHash(?string $ipHash): self
    {
        return new self(
            $this->id,
            $this->votacaoId,
            $this->categoriaId,
            $this->eleitorHash,
            $this->candidatoInscricaoId,
            $this->votadoEm,
            $ipHash
        );
    }

    /**
     * @throws InvalidArgumentException Quando o valor não é 64 chars hex.
     */
    private static function assertHashHex64(string $value, string $fieldName): void
    {
        if (strlen($value) !== 64 || !ctype_xdigit($value)) {
            throw new InvalidArgumentException(sprintf(
                'Voto.%s deve ser hex de 64 chars (CHAR(64)).',
                $fieldName
            ));
        }
    }
}
