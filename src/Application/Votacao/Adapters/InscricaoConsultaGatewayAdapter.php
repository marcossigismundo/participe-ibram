<?php
/**
 * Adapter cross-domain: implementa {@see InscricaoConsultaGateway}.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Adapters
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Adapters;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Votacao\Ports\InscricaoConsultaGateway;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;

/**
 * O domínio Votação só checa "esta inscrição é candidata final habilitada nesta
 * categoria?" — abstrai todo o resto do agregado Edital/Inscricao por trás
 * desta porta.
 */
final class InscricaoConsultaGatewayAdapter implements InscricaoConsultaGateway
{
    private WpdbInscricaoRepository $repo;

    public function __construct(WpdbInscricaoRepository $repo)
    {
        $this->repo = $repo;
    }

    public function isCandidatoFinalHabilitado(int $inscricaoId, int $categoriaId): bool
    {
        if ($inscricaoId <= 0 || $categoriaId <= 0) {
            return false;
        }
        $inscricao = $this->repo->findById($inscricaoId);
        if ($inscricao === null) {
            return false;
        }
        if ($inscricao->categoriaId() !== $categoriaId) {
            return false;
        }

        return $inscricao->status()->value() === StatusInscricao::FINAL_HABILITADO;
    }

    public function inscritoEm(int $inscricaoId): ?DateTimeImmutable
    {
        if ($inscricaoId <= 0) {
            return null;
        }
        $inscricao = $this->repo->findById($inscricaoId);
        if ($inscricao === null) {
            return null;
        }

        return $inscricao->inscritoEm();
    }
}
