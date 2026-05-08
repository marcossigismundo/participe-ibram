<?php
/**
 * Hydrator: linhas do banco -> entidades de domínio.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Exception;
use Ibram\ParticipeIbram\Core\Audit\AccessTracker;
use Ibram\ParticipeIbram\Core\Encryption\EncryptionException;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro;
use Ibram\ParticipeIbram\Domain\Agente\Representante;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;

/**
 * Converte arrays associativos retornados por `$wpdb->get_row(... ARRAY_A)`
 * em objetos do domínio. Decifra colunas `*_enc` via {@see SodiumCipher} e
 * registra cada decifragem em {@see AccessTracker} (TD-08, TD-14).
 *
 * As entradas são presumidas já recuperadas com prepared statements pelo
 * repositório que invoca o hydrator. Este componente NÃO faz I/O em banco.
 */
final class AgenteHydrator
{
    private SodiumCipher $cipher;
    private AccessTracker $accessTracker;

    public function __construct(SodiumCipher $cipher, AccessTracker $accessTracker)
    {
        $this->cipher        = $cipher;
        $this->accessTracker = $accessTracker;
    }

    /**
     * Constrói {@see Agente} a partir de uma linha de `wp_pi_agentes`.
     *
     * @param array<string,mixed> $row
     */
    public function hydrateAgente(array $row): Agente
    {
        $tipo            = TipoAgente::fromString((string) ($row['tipo'] ?? ''));
        $statusCadastro  = StatusCadastro::fromString((string) ($row['status_cadastro'] ?? ''));
        $numeroRegistro  = !empty($row['numero_registro'])
            ? new NumeroRegistro((string) $row['numero_registro'])
            : null;

        return new Agente(
            isset($row['id']) ? (int) $row['id'] : null,
            $tipo,
            $numeroRegistro,
            $statusCadastro,
            isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            (string) ($row['email_principal'] ?? ''),
            isset($row['telefone']) ? self::nullableString($row['telefone']) : null,
            self::nullableDateTime($row['submetido_em'] ?? null),
            self::nullableDateTime($row['deferido_em'] ?? null),
            self::nullableDateTime($row['publicado_em'] ?? null),
            self::requiredDateTime($row['created_at'] ?? null),
            self::requiredDateTime($row['updated_at'] ?? null),
            self::nullableDateTime($row['deleted_at'] ?? null)
        );
    }

    /**
     * Hydrata `wp_pi_agentes_pf` (decifra CPF/RG/Passaporte; tracka acessos).
     *
     * @param array<string,mixed> $row
     * @param int                 $atorId WP user id solicitante (0 = sistema).
     */
    public function hydrateAgentePF(array $row, int $atorId = 0): AgentePF
    {
        $agenteId = (int) ($row['agente_id'] ?? 0);

        $cpfPlain        = $this->maybeDecrypt($row['cpf_enc']        ?? null, 'agente_pf', $agenteId, 'cpf', $atorId);
        $rgPlain         = $this->maybeDecrypt($row['rg_enc']         ?? null, 'agente_pf', $agenteId, 'rg', $atorId);
        $passaportePlain = $this->maybeDecrypt($row['passaporte_enc'] ?? null, 'agente_pf', $agenteId, 'passaporte', $atorId);

        return new AgentePF(
            $agenteId,
            (string) ($row['nome_completo'] ?? ''),
            self::nullableString($row['nome_social'] ?? null),
            $cpfPlain,
            $rgPlain,
            $passaportePlain,
            self::nullableString($row['nacionalidade'] ?? null),
            self::nullableString($row['faixa_etaria'] ?? null),
            self::nullableString($row['identidade_genero'] ?? null),
            self::nullableString($row['orientacao_sexual'] ?? null),
            self::nullableString($row['raca_cor'] ?? null),
            (string) ($row['pessoa_deficiencia'] ?? AgentePF::PESSOA_DEFICIENCIA_PREFIRO_NAO_INFORMAR),
            self::nullableString($row['deficiencia_descricao'] ?? null),
            self::nullableString($row['recursos_acessibilidade'] ?? null),
            self::nullableString($row['grau_instrucao'] ?? null),
            self::nullableString($row['ocupacao'] ?? null),
            self::nullableString($row['cidade_residencia'] ?? null),
            self::nullableString($row['estado_residencia'] ?? null),
            self::nullableString($row['bairro_residencia'] ?? null),
            isset($row['organizacao_vinculada_id']) && $row['organizacao_vinculada_id'] !== null
                ? (int) $row['organizacao_vinculada_id'] : null,
            self::nullableString($row['apresentacao_md'] ?? null)
        );
    }

    /**
     * Hydrata `wp_pi_agentes_or` (decifra CNPJ).
     *
     * @param array<string,mixed> $row
     */
    public function hydrateAgenteOR(array $row, int $atorId = 0): AgenteOR
    {
        $agenteId = (int) ($row['agente_id'] ?? 0);
        $cnpjPlain = $this->maybeDecrypt($row['cnpj_enc'] ?? null, 'agente_or', $agenteId, 'cnpj', $atorId);

        $dataFundacao = null;
        $df = $row['data_fundacao'] ?? null;
        if ($df !== null && $df !== '' && $df !== '0000-00-00') {
            try {
                $dataFundacao = new DateTimeImmutable((string) $df);
            } catch (Exception $e) {
                $dataFundacao = null;
            }
        }

        return new AgenteOR(
            $agenteId,
            (string) ($row['nome_organizacao'] ?? ''),
            (string) ($row['tem_cnpj'] ?? AgenteOR::TEM_CNPJ_NAO),
            $cnpjPlain,
            self::nullableString($row['tipo_coletivo'] ?? null),
            self::nullableString($row['abrangencia'] ?? null),
            self::nullableString($row['cidade_sede'] ?? null),
            self::nullableString($row['estado_sede'] ?? null),
            self::nullableString($row['bairro_sede'] ?? null),
            self::nullableString($row['apresentacao_md'] ?? null),
            self::nullableString($row['estrutura_governanca_md'] ?? null),
            $dataFundacao
        );
    }

    /**
     * Hydrata `wp_pi_agentes_sm` (decifra CPF do representante legal).
     *
     * @param array<string,mixed> $row
     */
    public function hydrateAgenteSM(array $row, int $atorId = 0): AgenteSM
    {
        $agenteId = (int) ($row['agente_id'] ?? 0);
        $cpfPlain = $this->maybeDecrypt(
            $row['representante_cpf_enc'] ?? null,
            'agente_sm',
            $agenteId,
            'representante_cpf',
            $atorId
        );

        return new AgenteSM(
            $agenteId,
            (string) ($row['nome_orgao'] ?? ''),
            (string) ($row['esfera'] ?? AgenteSM::ESFERA_FEDERAL),
            (string) ($row['tipo_orgao'] ?? AgenteSM::TIPO_ORGAO_OUTRO),
            (string) ($row['representante_legal_nome'] ?? ''),
            self::nullableString($row['uf'] ?? null),
            self::nullableString($row['municipio'] ?? null),
            self::nullableString($row['lei_instituicao'] ?? null),
            isset($row['ano_lei']) && $row['ano_lei'] !== null ? (int) $row['ano_lei'] : null,
            self::nullableString($row['representante_legal_cargo'] ?? null),
            $cpfPlain
        );
    }

    /**
     * Hydrata `wp_pi_agente_representantes` (decifra CPF).
     *
     * @param array<string,mixed> $row
     */
    public function hydrateRepresentante(array $row, int $atorId = 0): Representante
    {
        $id = isset($row['id']) ? (int) $row['id'] : null;
        $cpfPlain = $this->maybeDecrypt(
            $row['cpf_enc'] ?? null,
            'agente_representante',
            $id ?? 0,
            'cpf',
            $atorId
        );

        return new Representante(
            $id,
            (int) ($row['agente_id'] ?? 0),
            (string) ($row['nome'] ?? ''),
            $cpfPlain,
            self::nullableString($row['email'] ?? null),
            self::nullableString($row['telefone'] ?? null),
            self::nullableString($row['papel'] ?? null),
            (bool) ($row['principal'] ?? false),
            (int) ($row['ordem'] ?? 0)
        );
    }

    /**
     * Decifra (se houver) e registra acesso. `$enc` pode vir como string,
     * resource (BLOB) ou null.
     *
     * @param mixed $enc
     */
    private function maybeDecrypt($enc, string $entidade, int $entidadeId, string $campo, int $atorId): ?string
    {
        if ($enc === null) {
            return null;
        }
        if (is_resource($enc)) {
            $enc = stream_get_contents($enc);
            if ($enc === false) {
                return null;
            }
        }
        if (!is_string($enc) || $enc === '') {
            return null;
        }

        try {
            $plain = $this->cipher->decrypt($enc);
        } catch (EncryptionException $e) {
            // Falha de decifragem: trata como ausente e segue. O caller
            // não deve quebrar a página inteira por isso; auditoria já é
            // feita via `error_log` interno do AuditLogger se necessário.
            return null;
        }

        if ($entidadeId > 0) {
            $this->accessTracker->trackDecryption($entidade, $entidadeId, $campo, $atorId);
        }

        return $plain;
    }

    /**
     * @param mixed $value
     */
    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = (string) $value;

        return $str === '' ? null : $str;
    }

    /**
     * @param mixed $value
     */
    private static function nullableDateTime($value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        try {
            return new DateTimeImmutable((string) $value);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param mixed $value
     */
    private static function requiredDateTime($value): DateTimeImmutable
    {
        $dt = self::nullableDateTime($value);

        return $dt ?? new DateTimeImmutable('now');
    }
}
