<?php
/**
 * Value object: evidência de pré-apuração (publicação pública para auditoria).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Snapshot público do conjunto de votos antes da tabulação final.
 *
 * Publicado em `wp_options` (via {@see \Ibram\ParticipeIbram\Application\Votacao\EncerrarVotacaoHandler})
 * e exposto por endpoint REST público. Permite que qualquer interessado
 * verifique posteriormente que os mesmos votos contados foram os mesmos
 * congelados ao encerrar a urna — propriedade essencial para auditabilidade
 * (TD-06 / Despacho 98/2025).
 *
 * Estrutura JSON canônica (ordem de chaves estável + UNESCAPED_SLASHES) para
 * que o hash do JSON seja reproduzível por terceiros.
 */
final class ApuracaoEvidence
{
    private string $hashPreApuracao;

    private int $totalVotos;

    private DateTimeImmutable $calculadoEm;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $hashPreApuracao,
        int $totalVotos,
        DateTimeImmutable $calculadoEm
    ) {
        if (strlen($hashPreApuracao) !== 64 || !ctype_xdigit($hashPreApuracao)) {
            throw new InvalidArgumentException(
                'ApuracaoEvidence.hashPreApuracao deve ser hex de 64 chars.'
            );
        }
        if ($totalVotos < 0) {
            throw new InvalidArgumentException(
                'ApuracaoEvidence.totalVotos nao pode ser negativo.'
            );
        }

        $this->hashPreApuracao = $hashPreApuracao;
        $this->totalVotos      = $totalVotos;
        $this->calculadoEm     = $calculadoEm;
    }

    public function hashPreApuracao(): string
    {
        return $this->hashPreApuracao;
    }

    public function totalVotos(): int
    {
        return $this->totalVotos;
    }

    public function calculadoEm(): DateTimeImmutable
    {
        return $this->calculadoEm;
    }

    /**
     * @return array{hash_pre_apuracao:string,total_votos:int,calculado_em:string}
     */
    public function toArray(): array
    {
        return [
            'hash_pre_apuracao' => $this->hashPreApuracao,
            'total_votos'       => $this->totalVotos,
            'calculado_em'      => $this->calculadoEm->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Serialização canônica (chaves em ordem, slashes não escapados, unicode literal).
     *
     * Permite que o hash deste JSON seja reproduzido por auditores externos.
     */
    public function __toString(): string
    {
        $json = json_encode(
            $this->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        return is_string($json) ? $json : '';
    }
}
