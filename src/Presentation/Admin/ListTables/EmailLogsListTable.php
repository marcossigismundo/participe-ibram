<?php
/**
 * WP_List_Table para logs de e-mail (fila/logs).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Domain\Email\MensagemEnfileirada;

if (!class_exists('WP_List_Table') && \defined('ABSPATH')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lista paginada de mensagens enfileiradas.
 *
 * Colunas:
 *  - data            (created_at)
 *  - evento
 *  - destinatario    (mascarado via PiiMasker)
 *  - status
 *  - tentativas
 *  - ultimo_erro
 *
 * Action por linha: "Reenviar" (volta para pendente). Visível apenas para
 * status = falhou ou enviado.
 *
 * NUNCA exibe o corpo HTML completo (poderia conter PII em links). Para
 * inspeção, usar a aba Templates (preview com vars de exemplo).
 */
final class EmailLogsListTable extends \WP_List_Table
{
    private EmailQueueRepository $repo;
    /** @var array{evento?:string,status?:string,destinatario?:string} */
    private array $filtros;
    private int $page;
    private int $perPage;

    /**
     * @param array{evento?:string,status?:string,destinatario?:string} $filtros
     */
    public function __construct(
        EmailQueueRepository $repo,
        array $filtros,
        int $page,
        int $perPage
    ) {
        parent::__construct([
            'singular' => 'pi_email_log',
            'plural'   => 'pi_email_logs',
            'ajax'     => false,
            'screen'   => null,
        ]);

        $this->repo    = $repo;
        $this->filtros = $filtros;
        $this->page    = max(1, $page);
        $this->perPage = max(1, min(100, $perPage));
    }

    public function get_columns(): array
    {
        return [
            'data'         => __('Data', 'participe-ibram'),
            'evento'       => __('Evento', 'participe-ibram'),
            'destinatario' => __('Destinatario', 'participe-ibram'),
            'status'       => __('Status', 'participe-ibram'),
            'tentativas'   => __('Tentativas', 'participe-ibram'),
            'ultimo_erro'  => __('Ultimo erro', 'participe-ibram'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [];
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $result = $this->repo->listar($this->filtros, $this->page, $this->perPage);

        $this->items = array_map([$this, 'rowFromMensagem'], $result['items']);

        $this->set_pagination_args([
            'total_items' => (int) $result['total'],
            'per_page'    => $this->perPage,
            'total_pages' => (int) ceil(max(1, (int) $result['total']) / $this->perPage),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function rowFromMensagem(MensagemEnfileirada $m): array
    {
        return [
            'id'            => (int) $m->id(),
            'data'          => $m->createdAt()->format('Y-m-d H:i'),
            'evento'        => $m->evento(),
            'destinatario'  => PiiMasker::maskEmail($m->destinatario()),
            'status'        => $m->status(),
            'tentativas'    => (int) $m->tentativas(),
            'ultimo_erro'   => (string) ($m->ultimoErro() ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        if (!is_array($item) || !isset($item[$column_name])) {
            return '';
        }
        $value = (string) $item[$column_name];

        return esc_html($value);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_data($item): string
    {
        return esc_html((string) ($item['data'] ?? ''));
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_status($item): string
    {
        $status = (string) ($item['status'] ?? '');
        $aria   = '';
        $color  = '#555';
        switch ($status) {
            case MensagemEnfileirada::STATUS_ENVIADO:
                $color = '#168821';
                $aria  = __('Enviado com sucesso', 'participe-ibram');
                break;
            case MensagemEnfileirada::STATUS_FALHOU:
                $color = '#a80521';
                $aria  = __('Falha permanente', 'participe-ibram');
                break;
            case MensagemEnfileirada::STATUS_ENVIANDO:
                $color = '#1351b4';
                $aria  = __('Em envio', 'participe-ibram');
                break;
            case MensagemEnfileirada::STATUS_PENDENTE:
                $color = '#a76b00';
                $aria  = __('Pendente', 'participe-ibram');
                break;
        }

        return sprintf(
            '<span style="color:%s;font-weight:600;" aria-label="%s">%s</span>',
            esc_attr($color),
            esc_attr($aria),
            esc_html($status)
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_destinatario($item): string
    {
        $row_actions = [];
        $id          = (int) ($item['id'] ?? 0);
        $status      = (string) ($item['status'] ?? '');

        if ($id > 0 && in_array($status, [MensagemEnfileirada::STATUS_FALHOU, MensagemEnfileirada::STATUS_ENVIADO], true)) {
            $nonce = function_exists('wp_create_nonce') ? \wp_create_nonce('pi_admin_email_resend') : '';
            $row_actions['reenviar'] = sprintf(
                '<a href="#" class="pi-email-resend" data-id="%d" data-nonce="%s">%s</a>',
                $id,
                esc_attr($nonce),
                esc_html__('Reenviar', 'participe-ibram')
            );
        }

        $email = (string) ($item['destinatario'] ?? '');

        return esc_html($email) . $this->row_actions($row_actions);
    }
}
