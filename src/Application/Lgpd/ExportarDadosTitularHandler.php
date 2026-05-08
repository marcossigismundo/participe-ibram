<?php
/**
 * Exporta dados do titular em ZIP estruturado (LGPD Art. 18, II e V).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\Consentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use RuntimeException;

/**
 * Gera o pacote de exportação (data-export) em formato portável (JSON+CSV).
 *
 * Estrutura do ZIP:
 *  - dados.json
 *  - dados.csv
 *  - consentimentos.json
 *  - compartilhamentos.json (placeholder)
 *  - politica-aceita.md
 *
 * Storage privado:
 *   `wp-content/uploads/participe-ibram-private/exports/{agente_id}/{uuid}.zip`
 *
 * Audita `lgpd_export_gerado` em `wp_pi_audit_log`.
 *
 * O resolvedor de "dados pessoais" é injetado como callable para que este
 * handler não dependa diretamente do Agente Domain (separação por wave).
 */
final class ExportarDadosTitularHandler
{
    /** @var callable(int): array<string,mixed> */
    private $dataSubjectResolver;

    private ConsentimentoRepository $consentimentos;
    private TermoRepository $termos;
    private AuditLogger $audit;
    private SecureLogger $logger;
    private string $privateUploadsDir;

    /**
     * @param callable(int): array<string,mixed> $dataSubjectResolver  Recebe agente_id, retorna payload personal_data.
     * @param string                              $privateUploadsDir   Caminho absoluto do `participe-ibram-private`.
     */
    public function __construct(
        callable $dataSubjectResolver,
        ConsentimentoRepository $consentimentos,
        TermoRepository $termos,
        AuditLogger $audit,
        SecureLogger $logger,
        string $privateUploadsDir
    ) {
        $this->dataSubjectResolver = $dataSubjectResolver;
        $this->consentimentos      = $consentimentos;
        $this->termos              = $termos;
        $this->audit               = $audit;
        $this->logger              = $logger;
        $this->privateUploadsDir   = rtrim($privateUploadsDir, "\\/");
    }

    /**
     * @return string Caminho absoluto do ZIP gerado.
     *
     * @throws RuntimeException Quando a geração do ZIP falha.
     */
    public function handle(int $agenteId): string
    {
        if ($agenteId < 1) {
            throw new \InvalidArgumentException('agenteId deve ser positivo.');
        }

        // 1. Coletar dados.
        $personalData    = ($this->dataSubjectResolver)($agenteId);
        $consentList     = $this->buildConsentimentos($agenteId);
        $termoVigenteMd  = $this->buildPoliticaAceita($consentList['atual']);
        $compartilhamentos = []; // TODO: integrar quando o domínio de compartilhamento existir.

        $payload = $this->buildExportPayload($agenteId, $personalData, $consentList, $compartilhamentos);

        // 2. Garantir diretório.
        $targetDir = $this->ensureExportDir($agenteId);
        $uuid      = UuidGenerator::generate();
        $zipPath   = $targetDir . DIRECTORY_SEPARATOR . $uuid . '.zip';

        // 3. Montar ZIP.
        $this->writeZip($zipPath, [
            'dados.json'              => self::jsonEncode($payload),
            'dados.csv'               => self::personalDataToCsv($personalData),
            'consentimentos.json'     => self::jsonEncode($consentList),
            'compartilhamentos.json'  => self::jsonEncode([
                'items'  => $compartilhamentos,
                'note'   => 'Lista vazia: domínio de compartilhamento ainda não integrado.',
            ]),
            'politica-aceita.md'      => $termoVigenteMd,
        ]);

        // 4. Auditoria.
        $this->audit->log(
            'lgpd_export',
            null,
            'lgpd_export_gerado',
            null,
            [
                'agente_id' => $agenteId,
                'arquivo'   => basename($zipPath),
                'tamanho'   => is_file($zipPath) ? (int) filesize($zipPath) : 0,
            ]
        );

        $this->logger->info('LGPD export gerado.', [
            'agente_id' => $agenteId,
            'file'      => basename($zipPath),
        ]);

        return $zipPath;
    }

    /**
     * @return array{
     *   atual: ?Termo,
     *   vigentes: array<int,array<string,mixed>>,
     *   historico: array<int,array<string,mixed>>
     * }
     */
    private function buildConsentimentos(int $agenteId): array
    {
        $todos    = $this->consentimentos->findTodosPorAgente($agenteId);
        $vigentes = [];
        $historico = [];

        // Última decisão por finalidade = vigente; restante = histórico.
        $byFinalidade = [];
        foreach ($todos as $c) {
            $key = $c->finalidade()->value();
            $byFinalidade[$key][] = $c;
        }
        foreach ($byFinalidade as $list) {
            // Última: ordenadas por registradoEm ASC pelo repo, então pegar a final.
            $ultima = end($list) ?: null;
            if ($ultima !== null) {
                $vigentes[] = self::consentimentoToArray($ultima);
            }
            foreach ($list as $c) {
                $historico[] = self::consentimentoToArray($c);
            }
        }

        $termoAtual = null;
        if ($vigentes !== []) {
            // Qualquer termo aceito serve como referência da política aceita corrente.
            foreach ($vigentes as $v) {
                if ($v['status'] === 'aceito') {
                    $termoAtual = $this->termos->findById((int) $v['termo_id']);
                    if ($termoAtual !== null) {
                        break;
                    }
                }
            }
        }
        if ($termoAtual === null) {
            $termoAtual = $this->termos->findAtivoCorrente();
        }

        return [
            'atual'     => $termoAtual,
            'vigentes'  => $vigentes,
            'historico' => $historico,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function consentimentoToArray(Consentimento $c): array
    {
        return [
            'finalidade'    => $c->finalidade()->value(),
            'status'        => $c->status()->value(),
            'termo_id'      => $c->termoId(),
            'registrado_em' => $c->registradoEm()->format(\DateTimeInterface::ATOM),
            'revogado_em'   => $c->revogadoEm() !== null ? $c->revogadoEm()->format(\DateTimeInterface::ATOM) : null,
        ];
    }

    private function buildPoliticaAceita(?Termo $termo): string
    {
        if ($termo === null) {
            return "# Termo não identificado\n\nNão foi possível localizar a versão da política de privacidade vigente.\n";
        }
        $header = sprintf(
            "<!-- versao: %s -->\n<!-- hash_sha256: %s -->\n<!-- ativo_em: %s -->\n\n",
            $termo->versao(),
            $termo->hashConteudo(),
            $termo->ativoEm()->format(\DateTimeInterface::ATOM)
        );

        return $header . $termo->conteudoMd();
    }

    /**
     * Monta o payload conforme R2-lgpd.md §6.2.
     *
     * @param array<string,mixed>      $personalData
     * @param array<string,mixed>      $consentList
     * @param array<int,array<string,mixed>> $compartilhamentos
     *
     * @return array<string,mixed>
     */
    private function buildExportPayload(
        int $agenteId,
        array $personalData,
        array $consentList,
        array $compartilhamentos
    ): array {
        $now = (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);

        return [
            'request_id'   => 'dsr_' . substr($now, 0, 10) . '_' . UuidGenerator::generateShort(8),
            'generated_at' => $now,
            'data_subject' => [
                'agente_id'  => $agenteId,
                'snapshot'   => $personalData,
            ],
            'personal_data' => $personalData,
            'consents'      => $consentList['vigentes'] ?? [],
            'consents_history' => $consentList['historico'] ?? [],
            'shared_with'   => $compartilhamentos,
            'policies'      => [
                'controller'      => 'Instituto Brasileiro de Museus (IBRAM)',
                'dpo_contact'     => self::dpoContact(),
                'policy_version'  => $consentList['atual'] !== null && $consentList['atual'] instanceof Termo
                    ? $consentList['atual']->versao()
                    : null,
                'policy_hash'     => $consentList['atual'] !== null && $consentList['atual'] instanceof Termo
                    ? $consentList['atual']->hashConteudo()
                    : null,
            ],
        ];
    }

    /**
     * @param array<string,string> $files
     *
     * @throws RuntimeException
     */
    private function writeZip(string $zipPath, array $files): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('ZipArchive não disponível: instale ext-zip.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(sprintf('Não foi possível criar o ZIP em "%s".', $zipPath));
        }
        foreach ($files as $name => $contents) {
            if (!$zip->addFromString($name, $contents)) {
                $zip->close();
                throw new RuntimeException(sprintf('Falha ao adicionar "%s" ao ZIP.', $name));
            }
        }
        $zip->close();
        if (!is_file($zipPath)) {
            throw new RuntimeException('ZIP não foi escrito.');
        }
    }

    private function ensureExportDir(int $agenteId): string
    {
        $base   = $this->privateUploadsDir;
        $target = $base . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . $agenteId;

        if (!is_dir($target)) {
            if (!@mkdir($target, 0750, true) && !is_dir($target)) {
                throw new RuntimeException(sprintf('Não foi possível criar diretório "%s".', $target));
            }
        }
        // Defesa em profundidade: garantir .htaccess deny all e index.php vazio.
        $htaccess = $base . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
        }
        $index = $base . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        return $target;
    }

    /**
     * @param array<string,mixed> $personalData
     */
    private static function personalDataToCsv(array $personalData): string
    {
        $rows = [];
        $rows[] = ['campo', 'valor'];
        self::flattenForCsv($personalData, '', $rows);

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }
        // BOM UTF-8 para Excel.
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fp, $row, ',', '"');
        }
        rewind($fp);
        $csv = stream_get_contents($fp) ?: '';
        fclose($fp);

        return $csv;
    }

    /**
     * @param array<mixed,mixed>           $data
     * @param string                       $prefix
     * @param array<int,array<int,string>> $rows
     */
    private static function flattenForCsv(array $data, string $prefix, array &$rows): void
    {
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                self::flattenForCsv($value, $name, $rows);
                continue;
            }
            if (is_bool($value)) {
                $rows[] = [$name, $value ? 'true' : 'false'];
                continue;
            }
            if ($value === null) {
                $rows[] = [$name, ''];
                continue;
            }
            $rows[] = [$name, (string) $value];
        }
    }

    /**
     * @param mixed $value
     */
    private static function jsonEncode($value): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        $out   = function_exists('wp_json_encode') ? wp_json_encode($value, $flags) : json_encode($value, $flags);

        return is_string($out) ? $out : '{}';
    }

    private static function dpoContact(): string
    {
        if (function_exists('get_option')) {
            $email = (string) get_option('pi_dpo_email', '');
            if ($email !== '') {
                return $email;
            }
        }

        return 'encarregado@museus.gov.br';
    }
}
