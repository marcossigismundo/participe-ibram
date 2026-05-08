<?php
/**
 * Handler: gera ZIP com relatório completo de apuração para download oficial.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\Ports\InscricaoConsultaGateway;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use RuntimeException;
use ZipArchive;

/**
 * Caso de uso: empacota apuracao.json + apuracao.csv + metodologia.md +
 * hash-pre-apuracao.txt em um único ZIP, salva em
 * `wp-content/uploads/participe-ibram-private/apuracao/{votacao_id}/{uuid}.zip`
 * e devolve o path absoluto + URL (para download admin via
 * {@see ApuracaoAdminAjax}).
 *
 * **Garantia de não-vazamento de PII (Onda 10 audita esta linha)**:
 *  - apuracao.json contém APENAS: votacao_id, edital_id, apurado_em,
 *    hash_pre_apuracao, total_votos, e por categoria
 *    `[candidato_inscricao_id, numero_registro, nome_publico, total_votos,
 *     posicao, eleito, suplente]`.
 *  - **NUNCA** contém: cpf, cpf_enc, cpf_hash, rg, passaporte, email,
 *    telefone, raca_cor, genero, orientacao_sexual, deficiencia,
 *    povos_comunidades_tradicionais, eleitor_hash, ip_hash, agente_id,
 *    user_id, ator_id.
 *  - Whitelist defensiva — passagem por `whitelistEntry()` blinda mesmo se
 *    um caller passar campos extras.
 *
 * Provider: `inscricaoLookup` é injetado para resolver
 * `candidato_inscricao_id → ['numero_registro', 'nome_publico']`.
 * Falha-segura: se provider retornar campos com PII, são filtrados pela
 * whitelist e descartados.
 */
final class ExportarRelatorioApuracaoHandler
{
    /**
     * Diretório PRIVADO — fora de qualquer index público.
     */
    private const DIR_RELATIVE = 'participe-ibram-private/apuracao';

    /**
     * Whitelist defensiva: APENAS estas chaves passam para apuracao.json.
     */
    private const ENTRY_WHITELIST = [
        'candidato_inscricao_id',
        'numero_registro',
        'nome_publico',
        'total_votos',
        'posicao',
        'eleito',
        'suplente',
    ];

    private VotacaoRepository $votacaoRepo;
    private ResultadoRepository $resultadoRepo;
    private VotoRepository $votoRepo;
    private AuditLogger $audit;

    /**
     * Resolve `candidato_inscricao_id` → array com `numero_registro` e
     * `nome_publico`. Retorna `[]` se desconhecido.
     *
     * @var callable(int): array<string,mixed>
     */
    private $inscricaoLookup;

    /**
     * Override do diretório base de uploads (testabilidade).
     */
    private ?string $uploadsBaseDir;

    /**
     * Override da URL base de uploads (testabilidade).
     */
    private ?string $uploadsBaseUrl;

    /**
     * @param callable(int): array<string,mixed> $inscricaoLookup
     */
    public function __construct(
        VotacaoRepository $votacaoRepo,
        ResultadoRepository $resultadoRepo,
        VotoRepository $votoRepo,
        AuditLogger $audit,
        callable $inscricaoLookup,
        ?string $uploadsBaseDir = null,
        ?string $uploadsBaseUrl = null
    ) {
        $this->votacaoRepo     = $votacaoRepo;
        $this->resultadoRepo   = $resultadoRepo;
        $this->votoRepo        = $votoRepo;
        $this->audit           = $audit;
        $this->inscricaoLookup = $inscricaoLookup;
        $this->uploadsBaseDir  = $uploadsBaseDir;
        $this->uploadsBaseUrl  = $uploadsBaseUrl;
    }

    /**
     * @return array{
     *   path: string,
     *   url: string,
     *   filename: string,
     *   bytes: int,
     *   sha256: string
     * }
     */
    public function handle(ExportarRelatorioApuracaoCommand $command): array
    {
        $votacao = $this->votacaoRepo->findById($command->votacaoId());

        if (!$votacao->status()->isApurada()) {
            throw new \DomainException(
                'Relatorio de apuracao soh pode ser exportado apos a apuracao concluida.'
            );
        }

        $resultados = $this->resultadoRepo->findByVotacao($command->votacaoId());
        $totalVotos = $this->votoRepo->contarTotalDaVotacao($command->votacaoId());

        // Monta payload "limpo" — whitelist defensiva.
        $payload = $this->buildJsonPayload($votacao, $resultados, $totalVotos);
        $csv     = $this->buildCsv($payload);
        $methodMd = $this->buildMetodologiaMd();
        $hashTxt  = $this->buildHashTxt($votacao->hashPreApuracao(), $votacao->apuradoEm());

        $jsonStr = $this->jsonEncode($payload);

        // Path destino.
        [$baseDir, $baseUrl] = $this->resolveUploads();
        $relDir   = self::DIR_RELATIVE . '/' . (int) $votacao->id();
        $dirAbs   = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
        $this->ensureDir($dirAbs);

        $uuid     = UuidGenerator::generate();
        $filename = $uuid . '.zip';
        $zipAbs   = $dirAbs . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Falha ao criar arquivo ZIP de apuracao.');
        }
        $zip->addFromString('apuracao.json', $jsonStr);
        $zip->addFromString('apuracao.csv', $csv);
        $zip->addFromString('metodologia.md', $methodMd);
        $zip->addFromString('hash-pre-apuracao.txt', $hashTxt);
        $zip->close();

        $bytes  = (int) (file_exists($zipAbs) ? filesize($zipAbs) : 0);
        $sha256 = $bytes > 0 ? (string) hash_file('sha256', $zipAbs) : '';

        // .htaccess de proteção (Apache) — defesa em profundidade.
        $this->protectDir($dirAbs);

        $relPath = $relDir . '/' . $filename;
        $url     = rtrim($baseUrl, '/') . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relPath);

        $this->audit->log(
            'votacao',
            $votacao->id(),
            'exportar_relatorio_apuracao',
            null,
            [
                'votacao_id' => $votacao->id(),
                'edital_id'  => $votacao->editalId(),
                'filename'   => $filename,
                'bytes'      => $bytes,
                'sha256'     => $sha256,
            ],
            $command->atorId()
        );

        return [
            'path'     => $zipAbs,
            'url'      => $url,
            'filename' => $filename,
            'bytes'    => $bytes,
            'sha256'   => $sha256,
        ];
    }

    /**
     * Monta a estrutura JSON, whitelist-blindada.
     *
     * @param list<Resultado> $resultados
     *
     * @return array<string,mixed>
     */
    private function buildJsonPayload(
        \Ibram\ParticipeIbram\Domain\Votacao\Votacao $votacao,
        array $resultados,
        int $totalVotos
    ): array {
        $porCategoria = [];

        foreach ($resultados as $r) {
            $catId = $r->categoriaId();
            if (!isset($porCategoria[$catId])) {
                $porCategoria[$catId] = [];
            }

            $lookup = ($this->inscricaoLookup)($r->candidatoInscricaoId());
            $lookup = is_array($lookup) ? $lookup : [];

            $entry = [
                'candidato_inscricao_id' => $r->candidatoInscricaoId(),
                'numero_registro'        => isset($lookup['numero_registro'])
                    ? (string) $lookup['numero_registro']
                    : '',
                'nome_publico'           => isset($lookup['nome_publico'])
                    ? (string) $lookup['nome_publico']
                    : '',
                'total_votos'            => $r->totalVotos(),
                'posicao'                => $r->posicao(),
                'eleito'                 => $r->eleito(),
                'suplente'               => $r->suplente(),
            ];

            // **Whitelist defensiva** — bloqueia qualquer campo extra que um
            // provider mal-comportado tente injetar (PII guard).
            $porCategoria[$catId][] = self::whitelistEntry($entry);
        }

        $categorias = [];
        foreach ($porCategoria as $catId => $items) {
            // Ordenação canônica final por posicao ASC.
            usort($items, static fn (array $a, array $b): int
                => ($a['posicao'] ?? 0) <=> ($b['posicao'] ?? 0));
            $categorias[] = [
                'categoria_id' => $catId,
                'resultados'   => $items,
            ];
        }
        usort($categorias, static fn (array $a, array $b): int
            => ($a['categoria_id'] ?? 0) <=> ($b['categoria_id'] ?? 0));

        return [
            'votacao_id'        => (int) $votacao->id(),
            'edital_id'         => $votacao->editalId(),
            'modo'              => $votacao->modo()->value(),
            'apurado_em'        => $votacao->apuradoEm() !== null
                ? $votacao->apuradoEm()->format(\DateTimeInterface::ATOM)
                : null,
            'hash_pre_apuracao' => $votacao->hashPreApuracao(),
            'algoritmo_hash'    => 'sha256',
            'total_votos'       => $totalVotos,
            'tie_break_rule'    => 'total_votos DESC, inscrito_em ASC, candidato_inscricao_id ASC',
            'categorias'        => $categorias,
            'gerado_em'         => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Whitelist defensiva: derruba qualquer chave fora da lista permitida.
     *
     * @param array<string,mixed> $entry
     *
     * @return array<string,mixed>
     */
    private static function whitelistEntry(array $entry): array
    {
        $out = [];
        foreach (self::ENTRY_WHITELIST as $key) {
            if (array_key_exists($key, $entry)) {
                $out[$key] = $entry[$key];
            }
        }
        return $out;
    }

    /**
     * Constrói CSV plano (uma linha por (categoria, posicao)).
     *
     * @param array<string,mixed> $payload
     */
    private function buildCsv(array $payload): string
    {
        $rows = [];
        $rows[] = [
            'categoria_id',
            'posicao',
            'candidato_inscricao_id',
            'numero_registro',
            'nome_publico',
            'total_votos',
            'eleito',
            'suplente',
        ];
        $cats = isset($payload['categorias']) && is_array($payload['categorias'])
            ? $payload['categorias'] : [];

        foreach ($cats as $cat) {
            $items = is_array($cat['resultados'] ?? null) ? $cat['resultados'] : [];
            foreach ($items as $entry) {
                $rows[] = [
                    (string) ($cat['categoria_id'] ?? ''),
                    (string) ($entry['posicao'] ?? ''),
                    (string) ($entry['candidato_inscricao_id'] ?? ''),
                    (string) ($entry['numero_registro'] ?? ''),
                    (string) ($entry['nome_publico'] ?? ''),
                    (string) ($entry['total_votos'] ?? ''),
                    !empty($entry['eleito']) ? '1' : '0',
                    !empty($entry['suplente']) ? '1' : '0',
                ];
            }
        }

        $fp = fopen('php://temp', 'w+');
        if ($fp === false) {
            return '';
        }
        // BOM UTF-8 para abrir corretamente no Excel.
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $out = (string) stream_get_contents($fp);
        fclose($fp);
        return $out;
    }

    private function buildMetodologiaMd(): string
    {
        return <<<'MD'
# Metodologia de Apuração — Participe Ibram

Este documento descreve a metodologia oficial de apuração da votação federal,
em conformidade com o Despacho 98/2025 IBRAM e os princípios da
LGPD (Lei nº 13.709/2018).

## 1. Algoritmo de contagem

Para cada categoria do edital, o sistema:

1. Conta votos válidos por candidato (UNIQUE constraint em
   `(votacao_id, categoria_id, eleitor_hash)` impede duplicidade).
2. Ordena candidatos pela seguinte chave determinística:
   1. `total_votos` **DESC** (mais votos primeiro)
   2. `inscrito_em` **ASC** (tie-break: inscrição mais antiga prevalece)
   3. `candidato_inscricao_id` **ASC** (tie-break secundário, estabilizador)
3. Marca os primeiros **N** como eleitos, onde **N = num_vagas** da categoria.
4. Marca os próximos **M** como suplentes, onde **M = num_suplentes**.
5. Demais candidatos recebem `posicao` apenas para histórico.

## 2. Tie-break

A regra de desempate é **fixa, documentada e auditável**:
nenhum sorteio, nenhum critério aleatório, nenhum ID interno volátil.

> Em empate de `total_votos`, prevalece a ordem cronológica de inscrição.
> Empate residual em `inscrito_em` é desempatado por
> `candidato_inscricao_id` ASC.

## 3. Hash de pré-apuração

Antes da apuração, o sistema calcula um hash determinístico do conjunto
de votos:

1. Cada voto é canonicalizado como
   `categoria_id|eleitor_hash|candidato_inscricao_id|votado_em` (formato
   `Y-m-d H:i:s`).
2. Linhas são ordenadas alfabeticamente (estabilidade).
3. Concatenadas com `\n` e o resultado é submetido a `sha256`.

O hash é congelado no banco no momento de **encerrar** a votação e exposto
publicamente na página de transparência da votação. Qualquer divergência
entre esse hash e o hash recalculado posteriormente sinaliza adulteração.

## 4. Identidade do eleitor

O sistema **NÃO armazena** identidade direta do eleitor junto ao voto:
apenas um `eleitor_hash` (HMAC-SHA256 com pepper). Ninguém — nem
administrador — consegue ligar voto a eleitor pela base operacional.

## 5. Reprodutibilidade

Qualquer terceiro (TCU, sociedade civil, jornalismo de dados) pode:

1. Baixar o JSON de auditoria pública via
   `GET /pi/v1/publico/votacao/{id}/audit-public`.
2. Reordenar as linhas conforme item 3 e calcular `sha256`.
3. Comparar com o `hash_pre_apuracao` publicado.

Comando de exemplo:

```sh
sha256sum votacao_audit.txt
```

Caso o hash recalculado bata com o publicado, a integridade do conjunto de
votos está confirmada.
MD;
    }

    private function buildHashTxt(?string $hash, ?DateTimeImmutable $apuradoEm): string
    {
        $hashTxt = $hash !== null && $hash !== '' ? $hash : '(nao calculado)';
        $when    = $apuradoEm !== null
            ? $apuradoEm->format(\DateTimeInterface::ATOM)
            : '(nao registrado)';
        return "Algoritmo: sha256\nApurado em: {$when}\nHash pre-apuracao:\n{$hashTxt}\n";
    }

    private function jsonEncode(array $payload): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        $out   = function_exists('wp_json_encode')
            ? \wp_json_encode($payload, $flags)
            : json_encode($payload, $flags);
        return is_string($out) ? $out : '{}';
    }

    /**
     * @return array{0:string,1:string} [baseDir, baseUrl]
     */
    private function resolveUploads(): array
    {
        if ($this->uploadsBaseDir !== null && $this->uploadsBaseUrl !== null) {
            return [$this->uploadsBaseDir, $this->uploadsBaseUrl];
        }
        if (function_exists('wp_upload_dir')) {
            $info = \wp_upload_dir();
            $base = isset($info['basedir']) ? (string) $info['basedir'] : sys_get_temp_dir();
            $url  = isset($info['baseurl']) ? (string) $info['baseurl'] : '';
            return [$base, $url];
        }
        return [sys_get_temp_dir(), ''];
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (function_exists('wp_mkdir_p')) {
            \wp_mkdir_p($dir);
            return;
        }
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Nao foi possivel criar diretorio de apuracao.');
        }
    }

    /**
     * Defesa em profundidade: deny-all .htaccess + index.html vazio.
     */
    private function protectDir(string $dir): void
    {
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
        }
        $index = $dir . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
    }
}
