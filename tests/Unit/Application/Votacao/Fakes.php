<?php
/**
 * Fakes em memória para os testes de Application/Votacao.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Votacao;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Votacao\Ports\AgenteVotanteGateway;
use Ibram\ParticipeIbram\Application\Votacao\Ports\CategoriaConsultaGateway;
use Ibram\ParticipeIbram\Application\Votacao\Ports\InscricaoConsultaGateway;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use Ibram\ParticipeIbram\Domain\Votacao\VotoDuplicado;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;

final class FakeVotacaoRepository implements VotacaoRepository
{
    /** @var array<int,Votacao> */
    private array $byId = [];

    private int $nextId = 1;

    public function seed(Votacao $v): Votacao
    {
        $id = $v->id() ?? $this->nextId++;
        $v  = $v->id() === null ? $v->withId($id) : $v;
        $this->byId[$id] = $v;
        return $v;
    }

    public function findById(int $id): Votacao
    {
        if (!isset($this->byId[$id])) {
            throw VotacaoNotFound::withId($id);
        }
        return $this->byId[$id];
    }

    public function findByEdital(int $editalId): ?Votacao
    {
        foreach ($this->byId as $v) {
            if ($v->editalId() === $editalId) {
                return $v;
            }
        }
        return null;
    }

    public function save(Votacao $votacao): int
    {
        $id = $votacao->id() ?? $this->nextId++;
        $this->byId[$id] = $votacao;
        return $id;
    }
}

final class FakeVotoRepository implements VotoRepository
{
    /** @var list<Voto> */
    public array $votos = [];

    private int $nextId = 1;

    public function existeVoto(int $votacaoId, int $categoriaId, string $eleitorHash): bool
    {
        foreach ($this->votos as $v) {
            if (
                $v->votacaoId() === $votacaoId
                && $v->categoriaId() === $categoriaId
                && hash_equals($v->eleitorHash(), $eleitorHash)
            ) {
                return true;
            }
        }
        return false;
    }

    public function salvarVoto(Voto $voto): int
    {
        if ($this->existeVoto($voto->votacaoId(), $voto->categoriaId(), $voto->eleitorHash())) {
            throw VotoDuplicado::paraVotacaoCategoria($voto->votacaoId(), $voto->categoriaId());
        }
        $id            = $this->nextId++;
        $this->votos[] = $voto->withId($id);
        return $id;
    }

    public function contarPorCandidato(int $votacaoId, int $categoriaId): array
    {
        $out = [];
        foreach ($this->votos as $v) {
            if ($v->votacaoId() === $votacaoId && $v->categoriaId() === $categoriaId) {
                $out[$v->candidatoInscricaoId()] = ($out[$v->candidatoInscricaoId()] ?? 0) + 1;
            }
        }
        return $out;
    }

    public function gerarHashPreApuracao(int $votacaoId): string
    {
        $linhas = [];
        foreach ($this->votos as $v) {
            if ($v->votacaoId() !== $votacaoId) {
                continue;
            }
            $linhas[] = $v->categoriaId() . '|' . $v->eleitorHash() . '|'
                . $v->candidatoInscricaoId() . '|' . $v->votadoEm()->format('Y-m-d H:i:s');
        }
        sort($linhas);
        return hash('sha256', implode("\n", $linhas) . (count($linhas) > 0 ? "\n" : ''));
    }

    public function contarTotalDaVotacao(int $votacaoId): int
    {
        $total = 0;
        foreach ($this->votos as $v) {
            if ($v->votacaoId() === $votacaoId) {
                $total++;
            }
        }
        return $total;
    }
}

final class FakeResultadoRepository implements ResultadoRepository
{
    /** @var list<Resultado> */
    public array $resultados = [];

    public function findByVotacao(int $votacaoId): array
    {
        $out = [];
        foreach ($this->resultados as $r) {
            if ($r->votacaoId() === $votacaoId) {
                $out[] = $r;
            }
        }
        return $out;
    }

    public function findEleitos(int $votacaoId): array
    {
        $out = [];
        foreach ($this->resultados as $r) {
            if ($r->votacaoId() === $votacaoId && $r->eleito()) {
                $out[] = $r;
            }
        }
        return $out;
    }

    public function salvarResultados(int $votacaoId, array $resultados): void
    {
        // Substitui por completo.
        $this->resultados = array_values(array_filter(
            $this->resultados,
            static fn (Resultado $r) => $r->votacaoId() !== $votacaoId
        ));
        foreach ($resultados as $r) {
            $this->resultados[] = $r;
        }
    }
}

final class FakeAgenteVotanteGateway implements AgenteVotanteGateway
{
    /** @var array<int,bool> */
    public array $deferidos = [];

    /** @var array<int,string> */
    public array $tipos = [];

    public function estaDeferido(int $agenteId): bool
    {
        return $this->deferidos[$agenteId] ?? false;
    }

    public function tipoAgente(int $agenteId): ?string
    {
        return $this->tipos[$agenteId] ?? null;
    }
}

final class FakeCategoriaConsultaGateway implements CategoriaConsultaGateway
{
    /** @var array<int,int> */
    public array $editalDe = [];

    /** @var array<int,list<string>> */
    public array $tiposAceitos = [];

    /** @var array<int,int> */
    public array $vagas = [];

    /** @var array<int,int> */
    public array $suplentes = [];

    /** @var array<int,list<int>> */
    public array $categoriasDoEdital = [];

    public function editalIdDaCategoria(int $categoriaId): ?int
    {
        return $this->editalDe[$categoriaId] ?? null;
    }

    public function aceitaTipoAgente(int $categoriaId, string $tipoAgente): bool
    {
        $lista = $this->tiposAceitos[$categoriaId] ?? ['PF', 'OR', 'SM'];
        return in_array($tipoAgente, $lista, true);
    }

    public function numVagas(int $categoriaId): int
    {
        return $this->vagas[$categoriaId] ?? 1;
    }

    public function numSuplentes(int $categoriaId): int
    {
        return $this->suplentes[$categoriaId] ?? 0;
    }

    public function listarCategoriasDoEdital(int $editalId): array
    {
        return $this->categoriasDoEdital[$editalId] ?? [];
    }
}

final class FakeInscricaoConsultaGateway implements InscricaoConsultaGateway
{
    /** @var array<string,bool>  key = "inscricaoId|categoriaId" */
    public array $habilitadas = [];

    /** @var array<int,DateTimeImmutable> */
    public array $inscritoEm = [];

    public function isCandidatoFinalHabilitado(int $inscricaoId, int $categoriaId): bool
    {
        return $this->habilitadas[$inscricaoId . '|' . $categoriaId] ?? false;
    }

    public function inscritoEm(int $inscricaoId): ?DateTimeImmutable
    {
        return $this->inscritoEm[$inscricaoId] ?? null;
    }
}
