<?php
/**
 * Contrato de persistência da fila de e-mail.
 *
 * @package Ibram\ParticipeIbram\Domain\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Email;

use DateTimeImmutable;

/**
 * Porta da camada de domínio sobre `wp_pi_email_queue`.
 *
 * As implementações DEVEM garantir atomicidade do `marcarEnviando` para
 * impedir que dois workers rodando em paralelo enviem o mesmo e-mail
 * (R5 B-09: rate-limit / lock não-atômico era um bug recorrente).
 */
interface EmailQueueRepository
{
    /**
     * Persiste uma mensagem nova (id null no objeto).
     *
     * @return int Id atribuído após o INSERT.
     */
    public function enfileirar(MensagemEnfileirada $mensagem): int;

    /**
     * Mensagens prontas para envio agora — status=pendente AND agendado_para <= NOW().
     *
     * @param int                    $limit  Máximo de linhas a retornar (>=1).
     * @param DateTimeImmutable|null $agora  Override do "agora" (testes).
     *
     * @return array<int,MensagemEnfileirada>
     */
    public function proximasParaEnvio(int $limit, ?DateTimeImmutable $agora = null): array;

    /**
     * Tenta marcar uma linha como "enviando" de forma atômica.
     *
     * Implementação típica: `UPDATE ... SET status='enviando', tentativas=tentativas+1
     * WHERE id=? AND status='pendente'`. Retorna true APENAS se 1 linha afetada.
     *
     * @return bool true se este chamador "ganhou" o lock; false se já estava
     *              sendo processada por outro worker.
     */
    public function marcarEnviando(int $id): bool;

    /**
     * Marca como enviado com sucesso.
     */
    public function marcarEnviado(int $id, DateTimeImmutable $enviadoEm): void;

    /**
     * Registra falha. Se `$retry=true` E ainda há tentativas disponíveis, devolve
     * para "pendente" com `agendado_para` futura (backoff). Caso contrário marca
     * permanentemente como `falhou`.
     */
    public function marcarFalha(
        int $id,
        string $erro,
        int $tentativasAtuais,
        bool $retry,
        DateTimeImmutable $proxima
    ): void;

    /**
     * Listagem paginada (admin). Filtros opcionais.
     *
     * @param array{
     *     evento?: string,
     *     status?: string,
     *     destinatario?: string,
     * } $filtros
     *
     * @return array{items: array<int,MensagemEnfileirada>, total: int, page: int, per_page: int}
     */
    public function listar(array $filtros, int $page = 1, int $perPage = 25): array;

    /**
     * Recupera por id.
     */
    public function findById(int $id): ?MensagemEnfileirada;

    /**
     * Devolve uma mensagem que falhou (status=falhou) para a fila como pendente
     * imediato (admin "Reenviar"). Retorna true em caso de sucesso.
     */
    public function reenviar(int $id, DateTimeImmutable $agendadoPara): bool;
}
