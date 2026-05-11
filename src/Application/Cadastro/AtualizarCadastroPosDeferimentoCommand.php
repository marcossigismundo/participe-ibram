<?php
/**
 * Comando: atualizar cadastro do próprio agente (edição limitada por status).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * Dados imutáveis para o caso de uso de edição da Área autenticada do agente.
 *
 * Distinto de {@see SalvarRascunhoCommand}: este só cobre o ciclo PÓS-criação
 * (rascunho, deferimento e estados intermediários). O handler decide o que
 * é editável conforme `status_cadastro` (whitelist W8-A).
 *
 * Campos enviados em `dados` que não estiverem na whitelist do status atual
 * são REJEITADOS pelo handler (não silenciosamente ignorados).
 */
final class AtualizarCadastroPosDeferimentoCommand
{
    private int $agenteId;
    private int $userId;

    /** @var array<string,mixed> Subset de campos a atualizar (sem agente_id). */
    private array $dados;

    /**
     * @param array<string,mixed> $dados
     */
    public function __construct(int $agenteId, int $userId, array $dados)
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('AtualizarCadastroPosDeferimentoCommand: agenteId obrigatório.');
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('AtualizarCadastroPosDeferimentoCommand: userId obrigatório.');
        }
        $this->agenteId = $agenteId;
        $this->userId   = $userId;
        $this->dados    = $dados;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * @return array<string,mixed>
     */
    public function dados(): array
    {
        return $this->dados;
    }
}
