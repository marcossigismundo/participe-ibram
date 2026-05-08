<?php
/**
 * Handler para avaliação de habilitação (habilitar/inabilitar) de inscrição.
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Decide a habilitação documental de uma inscrição (TD-06, fase pós-inscrição):
 *  - Valida que o ator tem capability `pi_decidir_habilitacao`.
 *  - Garante que a inscrição esteja em `em_habilitacao` (transição prévia
 *    via `iniciarHabilitacao`).
 *  - Aplica `habilitar(atorId)` ou `inabilitar(motivo, atorId)` na entidade.
 *  - Persiste e dispara `do_action('pi_habilitacao_decidida', $inscricaoId, $deferida, $atorId)`.
 */
final class AvaliarHabilitacaoHandler
{
    public const CAPABILITY = 'pi_decidir_habilitacao';

    private WpdbInscricaoRepository $inscricoesRepo;

    private AuditLogger $audit;

    public function __construct(WpdbInscricaoRepository $inscricoesRepo, AuditLogger $audit)
    {
        $this->inscricoesRepo = $inscricoesRepo;
        $this->audit          = $audit;
    }

    /**
     * @param bool        $habilitar `true` = habilitar, `false` = inabilitar.
     * @param string|null $motivoMd  Obrigatório quando `$habilitar === false`.
     *
     * @throws DomainException
     * @throws RuntimeException Sem capability.
     */
    public function handle(int $inscricaoId, bool $habilitar, ?string $motivoMd, int $atorId): void
    {
        if ($atorId <= 0) {
            throw new InvalidArgumentException('AvaliarHabilitacaoHandler: atorId deve ser positivo.');
        }
        $this->guardCapability($atorId);

        $inscricao = $this->inscricoesRepo->findById($inscricaoId);
        if ($inscricao === null) {
            throw new DomainException(sprintf(
                __('Inscricao %d nao encontrada.', 'participe-ibram'),
                $inscricaoId
            ));
        }

        // Garante a fase: se ainda em `inscrito`, transiciona para `em_habilitacao`.
        if ($inscricao->status()->value() === StatusInscricao::INSCRITO) {
            $inscricao->iniciarHabilitacao();
        }

        if ($inscricao->status()->value() !== StatusInscricao::EM_HABILITACAO) {
            throw new DomainException(sprintf(
                __('Inscricao %d nao esta em fase de habilitacao (status: %s).', 'participe-ibram'),
                $inscricaoId,
                $inscricao->status()->value()
            ));
        }

        if ($habilitar) {
            $inscricao->habilitar($atorId);
        } else {
            $motivo = $motivoMd !== null ? trim($motivoMd) : '';
            if ($motivo === '') {
                throw new InvalidArgumentException(
                    __('Motivo de inabilitacao e obrigatorio.', 'participe-ibram')
                );
            }
            $inscricao->inabilitar($motivo, $atorId);
        }

        $this->inscricoesRepo->save($inscricao);

        $this->audit->log(
            'inscricao',
            $inscricaoId,
            $habilitar ? 'habilitar' : 'inabilitar',
            null,
            ['status' => $inscricao->status()->value()],
            $atorId
        );

        if (function_exists('do_action')) {
            do_action('pi_habilitacao_decidida', $inscricaoId, $habilitar, $atorId);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function guardCapability(int $atorId): void
    {
        if (!function_exists('user_can')) {
            return; // Ambiente de teste sem WordPress; aplicação confia no atorId positivo.
        }
        if (!user_can($atorId, self::CAPABILITY)) {
            throw new RuntimeException(sprintf(
                'Usuario %d nao possui a capability %s.',
                $atorId,
                self::CAPABILITY
            ));
        }
    }
}
