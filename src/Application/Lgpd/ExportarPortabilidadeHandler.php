<?php
/**
 * Exporta dados do titular em ZIP estruturado e PORTÁVEL (LGPD Art. 18 V).
 *
 * Diferença para {@see ExportarDadosTitularHandler}:
 *  - Formato JSON-LD compatível com schema.org (chaves padronizadas).
 *  - Inclui histórico de inscrições, solicitações Art. 18 e FATO de votos.
 *  - Inclui JSON Schema descrevendo o formato do export.
 *  - Inclui README.md com instruções/contato DPO.
 *  - Storage isolado: `exports-portabilidade/{agente_id}/{uuid}.zip`.
 *
 * Voto secreto preservado (CRÍTICO):
 *  - Lista de votos contém SOMENTE `votacao_id, votado_em, hash_recibo`.
 *  - NUNCA inclui `candidato_inscricao_id`, mesmo no export do próprio titular —
 *    voto secreto é direito constitucional e contramedida anti-coerção.
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\Consentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use RuntimeException;

/**
 * Gera o pacote de portabilidade (Art. 18 V LGPD).
 *
 * Estrutura do ZIP:
 *   - dados.json              (JSON-LD schema.org Person)
 *   - dados.json-schema.json  (JSON Schema descrevendo dados.json)
 *   - dados.csv               (versão tabular)
 *   - consentimentos.json     (vigentes + histórico com policy_version + hash)
 *   - politica-aceita.md      (cópia do termo aceito mais recente)
 *   - README.md               (instruções, contato DPO)
 *
 * Storage:
 *   `{privateUploadsDir}/exports-portabilidade/{agente_id}/{uuid}.zip`
 *
 * Audita `lgpd_portabilidade_gerado` em `wp_pi_audit_log` (entity = `lgpd_export`).
 */
final class ExportarPortabilidadeHandler
{
    /** Versão do schema JSON-LD gerado. Subir quando o formato evoluir. */
    public const SCHEMA_VERSION = '1.0.0';

    /** @var callable(int): array<string,mixed> */
    private $dataSubjectResolver;

    /** @var callable(int): list<array{numero_registro:string,categoria_nome:string,edital_titulo:string,status:string,inscrito_em:string}> */
    private $inscricoesResolver;

    /** @var callable(int): list<array{votacao_id:int,votado_em:string,hash_recibo:string}> */
    private $votosFatoResolver;

    private ConsentimentoRepository $consentimentos;
    private TermoRepository $termos;
    private SolicitacaoTitularRepository $solicitacoes;
    private AuditLogger $audit;
    private SecureLogger $logger;
    private string $privateUploadsDir;

    /**
     * @param callable(int): array<string,mixed> $dataSubjectResolver
     * @param callable(int): list<array<string,mixed>> $inscricoesResolver
     * @param callable(int): list<array{votacao_id:int,votado_em:string,hash_recibo:string}> $votosFatoResolver
     */
    public function __construct(
        callable $dataSubjectResolver,
        callable $inscricoesResolver,
        callable $votosFatoResolver,
        ConsentimentoRepository $consentimentos,
        TermoRepository $termos,
        SolicitacaoTitularRepository $solicitacoes,
        AuditLogger $audit,
        SecureLogger $logger,
        string $privateUploadsDir
    ) {
        $this->dataSubjectResolver = $dataSubjectResolver;
        $this->inscricoesResolver  = $inscricoesResolver;
        $this->votosFatoResolver   = $votosFatoResolver;
        $this->consentimentos      = $consentimentos;
        $this->termos              = $termos;
        $this->solicitacoes        = $solicitacoes;
        $this->audit               = $audit;
        $this->logger              = $logger;
        $this->privateUploadsDir   = rtrim($privateUploadsDir, "\\/");
    }

    /**
     * @return array{zip_path:string,file_id:string,size:int}
     *
     * @throws RuntimeException
     */
    public function handle(int $agenteId): array
    {
        if ($agenteId < 1) {
            throw new \InvalidArgumentException('agenteId deve ser positivo.');
        }

        $now = new DateTimeImmutable('now');

        // 1. Coleta de dados.
        $personalData     = ($this->dataSubjectResolver)($agenteId);
        $consentEnvelope  = $this->buildConsentimentos($agenteId);
        $termoAtual       = $consentEnvelope['atual'];
        $inscricoes       = ($this->inscricoesResolver)($agenteId);
        $votosFato        = ($this->votosFatoResolver)($agenteId);
        $solicitacoesArt18 = $this->buildSolicitacoes($agenteId);

        // 2. Defesa adicional: garantir que votos NÃO carregam `candidato_inscricao_id`.
        $votosSeguros = self::sanitizeVotos($votosFato);

        // 3. Monta payload JSON-LD.
        $jsonLd = $this->buildJsonLd(
            $agenteId,
            $now,
            $personalData,
            $consentEnvelope,
            $inscricoes,
            $votosSeguros,
            $solicitacoesArt18
        );

        $jsonSchema = $this->buildJsonSchema();
        $consentList = [
            'atual'     => $this->termoToArray($termoAtual),
            'vigentes'  => $consentEnvelope['vigentes'],
            'historico' => $consentEnvelope['historico'],
        ];

        // 4. Cria arquivos do ZIP.
        $targetDir = $this->ensureExportDir($agenteId);
        $uuid      = UuidGenerator::generate();
        $zipPath   = $targetDir . DIRECTORY_SEPARATOR . $uuid . '.zip';

        $this->writeZip($zipPath, [
            'dados.json'              => self::jsonEncode($jsonLd),
            'dados.json-schema.json'  => self::jsonEncode($jsonSchema),
            'dados.csv'               => self::personalDataToCsv($jsonLd),
            'consentimentos.json'     => self::jsonEncode($consentList),
            'politica-aceita.md'      => $this->buildPoliticaAceita($termoAtual),
            'README.md'               => $this->buildReadme($agenteId, $now),
        ]);

        $size = is_file($zipPath) ? (int) filesize($zipPath) : 0;

        // 5. Auditoria.
        $this->audit->log(
            'lgpd_export',
            null,
            'lgpd_portabilidade_gerado',
            null,
            [
                'agente_id'      => $agenteId,
                'arquivo'        => basename($zipPath),
                'tamanho'        => $size,
                'schema_version' => self::SCHEMA_VERSION,
                'votos_count'    => count($votosSeguros),
                'inscricoes_count' => count($inscricoes),
            ]
        );

        $this->logger->info('LGPD portabilidade gerada.', [
            'agente_id' => $agenteId,
            'file'      => basename($zipPath),
            'size'      => $size,
        ]);

        return [
            'zip_path' => $zipPath,
            'file_id'  => $uuid,
            'size'     => $size,
        ];
    }

    // ─── Builders ──────────────────────────────────────────────────────────

    /**
     * Sanitiza votos — REMOVE `candidato_inscricao_id` mesmo se viesse no input.
     * Voto secreto preservado em camada de aplicação (defesa em profundidade).
     *
     * @param list<array<string,mixed>> $votos
     * @return list<array{votacao_id:int,votado_em:string,hash_recibo:string}>
     */
    private static function sanitizeVotos(array $votos): array
    {
        $out = [];
        foreach ($votos as $v) {
            if (!is_array($v)) {
                continue;
            }
            $out[] = [
                'votacao_id'  => isset($v['votacao_id']) ? (int) $v['votacao_id'] : 0,
                'votado_em'   => isset($v['votado_em']) ? (string) $v['votado_em'] : '',
                'hash_recibo' => isset($v['hash_recibo']) ? (string) $v['hash_recibo'] : '',
                // `candidato_inscricao_id` INTENCIONALMENTE OMITIDO — voto secreto.
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $personalData
     * @param array{atual:?Termo,vigentes:list<array<string,mixed>>,historico:list<array<string,mixed>>} $consentEnvelope
     * @param list<array<string,mixed>> $inscricoes
     * @param list<array{votacao_id:int,votado_em:string,hash_recibo:string}> $votos
     * @param list<array<string,mixed>> $solicitacoes
     *
     * @return array<string,mixed>
     */
    private function buildJsonLd(
        int $agenteId,
        DateTimeImmutable $now,
        array $personalData,
        array $consentEnvelope,
        array $inscricoes,
        array $votos,
        array $solicitacoes
    ): array {
        $person = self::mapToSchemaPerson($personalData);

        return [
            '@context'       => 'https://schema.org',
            '@type'          => 'DataDownload',
            'schemaVersion'  => self::SCHEMA_VERSION,
            'name'           => 'Pacote de Portabilidade LGPD — Participe Ibram',
            'description'    => 'Dados pessoais do titular conforme Art. 18, V da Lei 13.709/2018.',
            'license'        => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'dateCreated'    => $now->format(\DateTimeInterface::ATOM),
            'request_id'     => 'dsr_' . $now->format('Y-m-d') . '_' . UuidGenerator::generateShort(8),
            'dataController' => [
                '@type' => 'GovernmentOrganization',
                'name'  => 'Instituto Brasileiro de Museus (IBRAM)',
                'url'   => 'https://www.gov.br/museus',
            ],
            'dataProtectionOfficer' => [
                '@type' => 'ContactPoint',
                'name'  => function_exists('get_option') ? (string) \get_option('pi_dpo_nome', '') : '',
                'email' => self::dpoContact(),
                'contactType' => 'Encarregado pelo Tratamento de Dados Pessoais',
            ],
            'mainEntity'     => $person,
            'personal_data_raw' => $personalData,  // espelho do snapshot interno (compat ExportarDadosTitularHandler).
            'inscricoes'     => $inscricoes,
            'votos'          => $votos,
            'solicitacoes_art18' => $solicitacoes,
            'consents'       => $consentEnvelope['vigentes'],
            'consents_history' => $consentEnvelope['historico'],
            'policy' => [
                'policy_version' => $consentEnvelope['atual'] !== null ? $consentEnvelope['atual']->versao() : null,
                'policy_hash'    => $consentEnvelope['atual'] !== null ? $consentEnvelope['atual']->hashConteudo() : null,
                'policy_url'     => function_exists('home_url') ? (string) \home_url('/politica-de-privacidade') : null,
            ],
            'agente_id'      => $agenteId,
        ];
    }

    /**
     * Mapeia dados internos para schema.org Person quando possível.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function mapToSchemaPerson(array $data): array
    {
        $person = ['@type' => 'Person'];

        // Conhecer chaves canônicas usadas em data-subject resolver:
        if (isset($data['nome_civil']) && is_string($data['nome_civil'])) {
            $partes = preg_split('/\s+/', trim($data['nome_civil']), 2);
            if (is_array($partes)) {
                $person['givenName']  = $partes[0] ?? '';
                $person['familyName'] = $partes[1] ?? '';
            }
        } elseif (isset($data['nome_social']) && is_string($data['nome_social'])) {
            $person['name'] = $data['nome_social'];
        }
        if (isset($data['nome_social']) && is_string($data['nome_social']) && $data['nome_social'] !== '') {
            $person['alternateName'] = $data['nome_social'];
        }
        if (isset($data['email_principal']) && is_string($data['email_principal'])) {
            $person['email'] = $data['email_principal'];
        } elseif (isset($data['email']) && is_string($data['email'])) {
            $person['email'] = $data['email'];
        }
        if (isset($data['telefone']) && is_string($data['telefone'])) {
            $person['telephone'] = $data['telefone'];
        }
        if (isset($data['data_nascimento']) && is_string($data['data_nascimento'])) {
            $person['birthDate'] = $data['data_nascimento'];
        }
        if (isset($data['endereco']) && is_array($data['endereco'])) {
            $end = $data['endereco'];
            $person['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => isset($end['logradouro']) ? (string) $end['logradouro'] : null,
                'addressLocality' => isset($end['municipio']) ? (string) $end['municipio'] : null,
                'addressRegion'   => isset($end['uf']) ? (string) $end['uf'] : null,
                'postalCode'      => isset($end['cep']) ? (string) $end['cep'] : null,
                'addressCountry'  => 'BR',
            ];
        }
        // CPF como identifier estruturado.
        if (isset($data['cpf']) && is_string($data['cpf']) && $data['cpf'] !== '') {
            $person['identifier'] = [
                '@type'           => 'PropertyValue',
                'propertyID'      => 'br-cpf',
                'value'           => $data['cpf'],
            ];
        }
        return $person;
    }

    /**
     * @return array{atual:?Termo,vigentes:list<array<string,mixed>>,historico:list<array<string,mixed>>}
     */
    private function buildConsentimentos(int $agenteId): array
    {
        $todos    = $this->consentimentos->findTodosPorAgente($agenteId);
        $vigentes = [];
        $historico = [];

        $byFin = [];
        foreach ($todos as $c) {
            $byFin[$c->finalidade()->value()][] = $c;
        }
        foreach ($byFin as $list) {
            $ultima = end($list) ?: null;
            if ($ultima !== null) {
                $vigentes[] = self::consentimentoToArray($ultima);
            }
            foreach ($list as $c) {
                $historico[] = self::consentimentoToArray($c);
            }
        }

        $termoAtual = null;
        foreach ($vigentes as $v) {
            if (($v['status'] ?? '') === 'aceito') {
                $termoAtual = $this->termos->findById((int) ($v['termo_id'] ?? 0));
                if ($termoAtual !== null) {
                    break;
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
     * @return list<array<string,mixed>>
     */
    private function buildSolicitacoes(int $agenteId): array
    {
        $abertas = $this->solicitacoes->findAbertasPorAgente($agenteId);
        $out = [];
        foreach ($abertas as $s) {
            /** @var SolicitacaoTitular $s */
            $out[] = [
                'id'             => $s->id(),
                'tipo'           => $s->tipo(),
                'status'         => $s->status(),
                'protocolada_em' => $s->protocoladaEm()->format(\DateTimeInterface::ATOM),
                'atendida_em'    => $s->atendidaEm() !== null ? $s->atendidaEm()->format(\DateTimeInterface::ATOM) : null,
                // detalhes_md e resposta_md ficam internos (podem conter PII de terceiros).
            ];
        }
        return $out;
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

    /**
     * @return array<string,mixed>|null
     */
    private function termoToArray(?Termo $termo): ?array
    {
        if ($termo === null) {
            return null;
        }
        return [
            'id'          => $termo->id(),
            'versao'      => $termo->versao(),
            'hash_sha256' => $termo->hashConteudo(),
            'ativo_em'    => $termo->ativoEm()->format(\DateTimeInterface::ATOM),
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

    private function buildReadme(int $agenteId, DateTimeImmutable $generatedAt): string
    {
        $dpo = self::dpoContact();
        $iso = $generatedAt->format(\DateTimeInterface::ATOM);

        return <<<MARKDOWN
# Pacote de Portabilidade LGPD — Participe Ibram

Este pacote contém os seus dados pessoais tratados pelo Instituto Brasileiro de
Museus (IBRAM) no sistema Participe Ibram, conforme **Art. 18, inciso V da Lei
13.709/2018 (LGPD)**.

**Gerado em:** {$iso}
**Agente ID interno:** {$agenteId}
**Versão do schema:** %s

## Arquivos

| Arquivo | Conteúdo |
|---------|----------|
| `dados.json` | Seus dados em formato JSON-LD compatível com schema.org (Person + DataDownload). Permite importação por qualquer ferramenta que entenda schema.org. |
| `dados.json-schema.json` | Esquema JSON formal que descreve a estrutura de `dados.json`. |
| `dados.csv` | Versão tabular dos campos básicos (Excel/LibreOffice). Inclui BOM UTF-8. |
| `consentimentos.json` | Histórico completo de consentimentos, com versão e hash do termo aceito. |
| `politica-aceita.md` | Cópia da política de privacidade vigente no momento do seu aceite mais recente. |
| `README.md` | Este arquivo. |

## Voto secreto

Os votos eletrônicos que você registrou aparecem em `dados.json` apenas como
**fato de voto** (`votacao_id`, `votado_em`, `hash_recibo`). O candidato em quem
você votou **NÃO é incluído** neste export — o voto secreto é direito
constitucional e preservado mesmo no seu próprio export (LGPD Art. 18 não
sobrescreve o sigilo do voto).

Para verificar a integridade de cada voto, use o `hash_recibo` no endpoint
público `/wp-json/pi/v1/publico/votacao/{id}/audit-public`.

## Direitos e contato

- **Encarregado/a pelo Tratamento de Dados (DPO):** {$dpo}
- **Outros direitos LGPD (Art. 18):**
  - Acesso, retificação, exclusão, anonimização e oposição: utilize seu painel
    em "Minha Conta → Privacidade" no Participe Ibram.
  - Prazo legal de resposta: **15 dias corridos** (Art. 19 LGPD).

## Reprodutibilidade / integridade

Você pode validar este export comparando o hash do termo aceito com o publicado
em `https://www.gov.br/museus/.../politica-de-privacidade`. Discrepâncias devem
ser reportadas ao DPO.

---
Este pacote foi gerado automaticamente. Se você não solicitou esta exportação,
ignore-o e notifique imediatamente o DPO.
MARKDOWN
            . "\n"
            . sprintf('<!-- schema_version: %s -->', self::SCHEMA_VERSION);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildJsonSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id'     => 'https://www.gov.br/museus/schemas/participe-ibram/portabilidade-' . self::SCHEMA_VERSION . '.json',
            'title'   => 'Participe Ibram — Pacote de Portabilidade LGPD',
            'type'    => 'object',
            'required' => ['@context', '@type', 'schemaVersion', 'mainEntity', 'dateCreated'],
            'properties' => [
                '@context'      => ['type' => 'string', 'const' => 'https://schema.org'],
                '@type'         => ['type' => 'string', 'const' => 'DataDownload'],
                'schemaVersion' => ['type' => 'string'],
                'dateCreated'   => ['type' => 'string', 'format' => 'date-time'],
                'mainEntity'    => [
                    'type' => 'object',
                    'properties' => [
                        '@type'      => ['type' => 'string', 'const' => 'Person'],
                        'givenName'  => ['type' => 'string'],
                        'familyName' => ['type' => 'string'],
                        'email'      => ['type' => 'string', 'format' => 'email'],
                        'telephone'  => ['type' => 'string'],
                        'birthDate'  => ['type' => 'string'],
                        'address'    => ['type' => 'object'],
                        'identifier' => ['type' => 'object'],
                    ],
                ],
                'consents'         => ['type' => 'array', 'items' => ['type' => 'object']],
                'consents_history' => ['type' => 'array', 'items' => ['type' => 'object']],
                'inscricoes'       => ['type' => 'array', 'items' => ['type' => 'object']],
                'solicitacoes_art18' => ['type' => 'array', 'items' => ['type' => 'object']],
                'votos' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['votacao_id', 'votado_em', 'hash_recibo'],
                        'properties' => [
                            'votacao_id'  => ['type' => 'integer'],
                            'votado_em'   => ['type' => 'string', 'format' => 'date-time'],
                            'hash_recibo' => ['type' => 'string'],
                            // candidato_inscricao_id INTENCIONALMENTE NÃO permitido (voto secreto).
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'policy' => [
                    'type' => 'object',
                    'properties' => [
                        'policy_version' => ['type' => ['string', 'null']],
                        'policy_hash'    => ['type' => ['string', 'null']],
                        'policy_url'     => ['type' => ['string', 'null']],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $jsonLd
     */
    private static function personalDataToCsv(array $jsonLd): string
    {
        $rows = [['campo', 'valor']];
        self::flattenForCsv($jsonLd, '', $rows);

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }
        fwrite($fp, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel.
        foreach ($rows as $row) {
            fputcsv($fp, $row, ',', '"');
        }
        rewind($fp);
        $csv = stream_get_contents($fp) ?: '';
        fclose($fp);
        return $csv;
    }

    /**
     * @param array<mixed,mixed> $data
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
     * @param array<string,string> $files
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
        $target = $base . DIRECTORY_SEPARATOR . 'exports-portabilidade' . DIRECTORY_SEPARATOR . $agenteId;

        if (!is_dir($target)) {
            if (!@mkdir($target, 0750, true) && !is_dir($target)) {
                throw new RuntimeException(sprintf('Não foi possível criar diretório "%s".', $target));
            }
        }
        // .htaccess deny-all + index.php vazio.
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
     * @param mixed $value
     */
    private static function jsonEncode($value): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        $out   = function_exists('wp_json_encode') ? \wp_json_encode($value, $flags) : json_encode($value, $flags);
        return is_string($out) ? $out : '{}';
    }

    private static function dpoContact(): string
    {
        if (function_exists('get_option')) {
            $email = (string) \get_option('pi_dpo_email', '');
            if ($email !== '') {
                return $email;
            }
        }
        return 'encarregado@museus.gov.br';
    }
}
