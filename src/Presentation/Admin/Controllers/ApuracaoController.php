<?php
/**
 * ApuracaoController — página admin de apuração de uma votação.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\ResultadosListTable;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/**
 * Página de apuração:
 *   - Header: edital titulo + status + datas + total de votos
 *   - Card pré-apuração: hash em monospace + "Recalcular hash" (AJAX W6-A)
 *   - Tabela de resultados (se apurada)
 *   - Ações: Apurar agora / Publicar Resultado / Exportar relatório
 *
 * Caps:
 *   - render: pi_apurar_votacao
 *   - publicar: pi_publicar_resultado
 */
final class ApuracaoController
{
    public const CAP_APURAR   = VotacaoMenuRegistry::CAP_APURAR;
    public const CAP_PUBLICAR = VotacaoMenuRegistry::CAP_PUBLICAR;

    private VotacaoRepository $votacoesRepo;

    private VotoRepository $votosRepo;

    private ResultadoRepository $resultadosRepo;

    private WpdbEditalRepository $editaisRepo;

    private WpdbCategoriaRepository $categoriasRepo;

    /**
     * Lookup `candidato_inscricao_id` → ['numero_registro', 'nome_publico'].
     *
     * @var callable(int): array<string,mixed>
     */
    private $inscricaoLookup;

    /**
     * @param callable(int): array<string,mixed> $inscricaoLookup
     */
    public function __construct(
        VotacaoRepository $votacoesRepo,
        VotoRepository $votosRepo,
        ResultadoRepository $resultadosRepo,
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        callable $inscricaoLookup
    ) {
        $this->votacoesRepo    = $votacoesRepo;
        $this->votosRepo       = $votosRepo;
        $this->resultadosRepo  = $resultadosRepo;
        $this->editaisRepo     = $editaisRepo;
        $this->categoriasRepo  = $categoriasRepo;
        $this->inscricaoLookup = $inscricaoLookup;
    }

    public function render(int $votacaoId): void
    {
        // R5 V-06: cap check no topo.
        if (!self::userCan(self::CAP_APURAR)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        if ($votacaoId <= 0) {
            self::wpDie(self::tr('Votação não informada.'));
            return;
        }

        try {
            $votacao = $this->votacoesRepo->findById($votacaoId);
        } catch (VotacaoNotFound $e) {
            self::wpDie(self::tr('Votação não encontrada.'));
            return;
        }

        $edital = $this->editaisRepo->findById($votacao->editalId());
        if ($edital === null) {
            self::wpDie(self::tr('Edital associado à votação não foi localizado.'));
            return;
        }

        $totalVotos = $this->votosRepo->contarTotalDaVotacao($votacaoId);
        $status     = $votacao->status()->value();
        $podeApurar   = self::userCan(self::CAP_APURAR)   && $status === 'encerrada';
        $podePublicar = self::userCan(self::CAP_PUBLICAR) && $status === 'apurada';
        $podeExportar = self::userCan(self::CAP_APURAR)   && $status === 'apurada';

        // Resultados (se já apurada).
        $resultados      = $this->resultadosRepo->findByVotacao($votacaoId);
        $resultadosTable = null;
        $eleitos         = [];
        if (!empty($resultados)) {
            // Lookup labels de categoria.
            $categorias = $this->categoriasRepo->findByEdital($votacao->editalId());
            $catLabels  = [];
            foreach ($categorias as $c) {
                $catLabels[(int) $c->id()] = (string) $c->nome();
            }
            $resultadosTable = new ResultadosListTable($this->inscricaoLookup, $catLabels);
            $resultadosTable->setResultados($resultados);
            $resultadosTable->prepare_items();

            foreach ($resultados as $r) {
                if ($r->eleito()) {
                    $lookup    = ($this->inscricaoLookup)($r->candidatoInscricaoId());
                    $lookup    = is_array($lookup) ? $lookup : [];
                    $eleitos[] = [
                        'categoria_id'           => $r->categoriaId(),
                        'categoria_nome'         => $catLabels[$r->categoriaId()] ?? '',
                        'candidato_inscricao_id' => $r->candidatoInscricaoId(),
                        'numero_registro'        => isset($lookup['numero_registro']) ? (string) $lookup['numero_registro'] : '',
                        'nome_publico'           => isset($lookup['nome_publico']) ? (string) $lookup['nome_publico'] : '',
                    ];
                }
            }
        }

        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        $nonces = [
            'apurar'         => self::nonce('pi_admin_apurar_votacao_' . $userId),
            'publicar'       => self::nonce('pi_admin_publicar_resultado_' . $userId),
            'exportar'       => self::nonce('pi_admin_exportar_apuracao_' . $userId),
            'recalcular'     => self::nonce('pi_admin_votacao_recalcular_hash_' . $userId),
        ];

        $flash    = $this->consumeFlash();
        $template = self::templatePath('votacoes/apuracao.php');
        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }
        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    public function setFlash(string $type, string $message): void
    {
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }
        $uid = (int) \get_current_user_id();
        if ($uid <= 0) {
            return;
        }
        \set_transient('pi_admin_votacao_flash_' . $uid, ['type' => $type, 'message' => $message], 60);
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeFlash(): ?array
    {
        if (!function_exists('get_transient') || !function_exists('get_current_user_id')) {
            return null;
        }
        $uid = (int) \get_current_user_id();
        if ($uid <= 0) {
            return null;
        }
        $key  = 'pi_admin_votacao_flash_' . $uid;
        $data = \get_transient($key);
        if (!is_array($data) || !isset($data['type'], $data['message'])) {
            return null;
        }
        if (function_exists('delete_transient')) {
            \delete_transient($key);
        }
        return ['type' => (string) $data['type'], 'message' => (string) $data['message']];
    }

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && \current_user_can($cap);
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function wpDie(string $message): void
    {
        if (function_exists('wp_die')) {
            \wp_die(self::escHtml($message));
        } else {
            echo $message;
            exit;
        }
    }

    private static function nonce(string $action): string
    {
        return function_exists('wp_create_nonce') ? (string) \wp_create_nonce($action) : '';
    }

    private static function templatePath(string $relative): ?string
    {
        $base      = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
