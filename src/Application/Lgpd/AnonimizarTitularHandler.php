<?php
/**
 * Handler de anonimização irreversível (LGPD Art. 18, IV + LGPD.md §6).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use RuntimeException;

/**
 * Anonimiza um titular (agente) de forma irreversível.
 *
 * Operações (LGPD.md §6):
 *  - `nome_completo` ← `[ANON-{shortid}]`
 *  - `cpf_enc`, `rg_enc`, `passaporte_enc` ← NULL
 *  - `email_principal` ← `anon-{id}@participe-ibram.local`
 *  - `telefone` ← NULL
 *  - Documentos físicos: deleta arquivos do disco; preserva registro com tipo
 *    + hash + tamanho.
 *  - `deleted_at` ← NOW()
 *  - `wp_pi_audit_log` é PRESERVADO (Art. 16, II — obrigação legal).
 *
 * Audita um único evento `anonimizacao_executada` listando os campos limpos.
 *
 * O private uploads dir é injetado para que o handler consiga apagar arquivos
 * físicos referenciados em `wp_pi_documentos.arquivo_path`.
 */
final class AnonimizarTitularHandler
{
    /** @var \wpdb */
    private $wpdb;

    private AuditLogger $audit;
    private SecureLogger $logger;
    private string $privateUploadsDir;

    private string $tableAgentes;
    private string $tableAgentesPF;
    private string $tableDocumentos;

    public function __construct(
        $wpdb,
        AuditLogger $audit,
        SecureLogger $logger,
        string $privateUploadsDir
    ) {
        $this->wpdb              = $wpdb;
        $this->audit             = $audit;
        $this->logger            = $logger;
        $this->privateUploadsDir = rtrim($privateUploadsDir, "\\/");

        $prefix = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableAgentes    = $prefix . 'pi_agentes';
        $this->tableAgentesPF  = $prefix . 'pi_agentes_pf';
        $this->tableDocumentos = $prefix . 'pi_documentos';
    }

    /**
     * @return array<string,mixed> Resumo da operação (campos limpos, docs apagados).
     */
    public function handle(int $agenteId, ?int $atorId = null): array
    {
        if ($agenteId < 1) {
            throw new \InvalidArgumentException('agenteId deve ser positivo.');
        }

        $shortId  = UuidGenerator::generateShort(8);
        $anonNome = sprintf('[ANON-%s]', $shortId);
        $anonMail = sprintf('anon-%d@participe-ibram.local', $agenteId);
        $now      = gmdate('Y-m-d H:i:s');

        $camposLimpos = [];

        // 1. Atualizar wp_pi_agentes (email_principal, telefone, deleted_at).
        $rowsA = $this->wpdb->update(
            $this->tableAgentes,
            [
                'email_principal' => $anonMail,
                'telefone'        => null,
                'deleted_at'      => $now,
            ],
            ['id' => $agenteId],
            ['%s', '%s', '%s'],
            ['%d']
        );
        if ($rowsA === false) {
            throw new RuntimeException('Falha ao anonimizar wp_pi_agentes.');
        }
        $camposLimpos[] = 'agentes.email_principal';
        $camposLimpos[] = 'agentes.telefone';
        $camposLimpos[] = 'agentes.deleted_at';

        // 2. Atualizar wp_pi_agentes_pf (nome, encripts).
        $rowsPF = $this->wpdb->update(
            $this->tableAgentesPF,
            [
                'nome_completo'  => $anonNome,
                'nome_social'    => null,
                'cpf_enc'        => null,
                'cpf_hash'       => null,
                'rg_enc'         => null,
                'passaporte_enc' => null,
            ],
            ['agente_id' => $agenteId],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        // Se o agente é OR/SM, $rowsPF será 0 (nada a fazer); só tratar erro fatal.
        if ($rowsPF === false) {
            throw new RuntimeException('Falha ao anonimizar wp_pi_agentes_pf.');
        }
        if ((int) $rowsPF > 0) {
            $camposLimpos[] = 'agentes_pf.nome_completo';
            $camposLimpos[] = 'agentes_pf.nome_social';
            $camposLimpos[] = 'agentes_pf.cpf_enc';
            $camposLimpos[] = 'agentes_pf.cpf_hash';
            $camposLimpos[] = 'agentes_pf.rg_enc';
            $camposLimpos[] = 'agentes_pf.passaporte_enc';
        }

        // 3. Documentos: deletar arquivos físicos; manter registro com tipo + hash + tamanho.
        $deleted = $this->purgeDocumentos($agenteId);
        if ($deleted['arquivos_apagados'] > 0) {
            $camposLimpos[] = 'documentos.arquivo_path';
            $camposLimpos[] = 'documentos.nome_original';
        }

        // 4. Auditar a operação.
        $this->audit->log(
            'agente',
            $agenteId,
            'anonimizacao_executada',
            null,
            [
                'agente_id'         => $agenteId,
                'campos_limpos'     => $camposLimpos,
                'documentos'        => $deleted,
                'ator_id'           => $atorId,
                'short_id'          => $shortId,
            ],
            $atorId
        );

        $this->logger->info('Anonimização executada.', [
            'agente_id' => $agenteId,
            'docs'      => $deleted['arquivos_apagados'],
        ]);

        return [
            'agente_id'     => $agenteId,
            'short_id'      => $shortId,
            'campos_limpos' => $camposLimpos,
            'documentos'    => $deleted,
        ];
    }

    /**
     * Apaga arquivos físicos dos documentos do agente. NÃO deleta o registro
     * em `wp_pi_documentos` — preservamos tipo, hash e tamanho como evidência.
     *
     * @return array{arquivos_apagados:int,arquivos_ignorados:int,registros:int}
     */
    private function purgeDocumentos(int $agenteId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT id, arquivo_path FROM {$this->tableDocumentos} WHERE agente_id = %d",
            $agenteId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows) || $rows === []) {
            return ['arquivos_apagados' => 0, 'arquivos_ignorados' => 0, 'registros' => 0];
        }

        $apagados   = 0;
        $ignorados  = 0;

        foreach ($rows as $row) {
            $relPath = isset($row['arquivo_path']) ? (string) $row['arquivo_path'] : '';
            if ($relPath === '') {
                $ignorados++;
                continue;
            }
            $absPath = $this->resolvePath($relPath);
            if ($absPath === null) {
                $ignorados++;
                continue;
            }
            if (is_file($absPath) && @unlink($absPath)) {
                $apagados++;
            } else {
                $ignorados++;
            }

            // Esvazia o caminho e nome original; mantém hash, mime, tamanho.
            $this->wpdb->update(
                $this->tableDocumentos,
                [
                    'arquivo_path'  => '',
                    'nome_original' => '[ANON]',
                ],
                ['id' => (int) $row['id']],
                ['%s', '%s'],
                ['%d']
            );
        }

        return [
            'arquivos_apagados'  => $apagados,
            'arquivos_ignorados' => $ignorados,
            'registros'          => count($rows),
        ];
    }

    /**
     * Resolve um caminho relativo dentro do private uploads (defende contra path traversal).
     */
    private function resolvePath(string $rel): ?string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return null;
        }
        $abs = $this->privateUploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

        // Garante que o arquivo está mesmo abaixo do private dir.
        $real     = realpath($abs);
        $realBase = realpath($this->privateUploadsDir);
        if ($real === false || $realBase === false) {
            return null;
        }
        if (strpos($real, $realBase) !== 0) {
            return null;
        }

        return $real;
    }
}
