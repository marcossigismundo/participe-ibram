<?php
/**
 * Implementação WPDB do {@see AgenteRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use InvalidArgumentException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Database\SequenceGenerator;
use Ibram\ParticipeIbram\Core\Encryption\EncryptionException;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteNotFound;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\DuplicateCnpjException;
use Ibram\ParticipeIbram\Domain\Agente\DuplicateCpfException;
use Ibram\ParticipeIbram\Domain\Agente\Representante;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;

/**
 * Persiste o agregado Agente em quatro tabelas (`agentes`, `agentes_pf|or|sm`,
 * `agente_representantes`).
 *
 * Convenções (SCHEMA §1, ARCHITECTURE TD-18, R5 §7):
 *  - Toda query usa `$wpdb->prepare()` em chamada única (R5 V-03; nunca concatenar
 *    strings preparadas).
 *  - Whitelist explícita para nomes de coluna em ORDER BY (B-04).
 *  - Buscas por CPF/CNPJ usam `*_hash` (HMAC) — nunca decifrar todas as linhas.
 *  - Cifra é gerada via {@see SodiumCipher::encrypt()} antes de qualquer INSERT/UPDATE.
 *  - Geração de número de registro via {@see SequenceGenerator::next()} dentro do save
 *    apenas quando há transição -> deferido* sem número ainda atribuído.
 *  - Soft-delete: `deleted_at = NOW()`. Listagens excluem por padrão.
 *  - Audita via {@see AuditLogger} eventos de criação/atualização/soft-delete.
 *
 * Esta classe NÃO faz validação de transição de estado — quem é responsável
 * pela máquina de estados é a entidade {@see Agente}. Aqui apenas persistimos
 * o estado já mutado.
 */
final class WpdbAgenteRepository implements AgenteRepository
{
    /**
     * Whitelist de orderby para `listByStatus`.
     *
     * @var array<int,string>
     */
    private const ORDERBY_WHITELIST = [
        'id',
        'created_at',
        'updated_at',
        'submetido_em',
        'deferido_em',
        'numero_registro',
    ];

    /**
     * Whitelist de direções.
     *
     * @var array<int,string>
     */
    private const ORDER_WHITELIST = ['ASC', 'DESC'];

    /** @var \wpdb */
    private $wpdb;

    private SodiumCipher $cipher;

    private AuditLogger $auditLogger;

    private SequenceGenerator $sequenceGenerator;

    private AgenteHydrator $hydrator;

    private string $prefix;

    private string $tableAgentes;
    private string $tableAgentesPf;
    private string $tableAgentesOr;
    private string $tableAgentesSm;
    private string $tableRepresentantes;

    /**
     * @param \wpdb             $wpdb              Handle WP.
     * @param SodiumCipher      $cipher            Cifrador de dados sensíveis.
     * @param AuditLogger       $auditLogger       Logger append-only.
     * @param SequenceGenerator $sequenceGenerator Gerador de número de registro.
     * @param AgenteHydrator    $hydrator          Hidratador de linhas.
     */
    public function __construct(
        $wpdb,
        SodiumCipher $cipher,
        AuditLogger $auditLogger,
        SequenceGenerator $sequenceGenerator,
        AgenteHydrator $hydrator
    ) {
        $this->wpdb              = $wpdb;
        $this->cipher            = $cipher;
        $this->auditLogger       = $auditLogger;
        $this->sequenceGenerator = $sequenceGenerator;
        $this->hydrator          = $hydrator;

        $rawPrefix = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->prefix = $rawPrefix . 'pi_';

        $this->tableAgentes        = $this->prefix . 'agentes';
        $this->tableAgentesPf      = $this->prefix . 'agentes_pf';
        $this->tableAgentesOr      = $this->prefix . 'agentes_or';
        $this->tableAgentesSm      = $this->prefix . 'agentes_sm';
        $this->tableRepresentantes = $this->prefix . 'agente_representantes';
    }

    public function findById(int $id): ?Agente
    {
        if ($id <= 0) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            'SELECT * FROM `' . $this->tableAgentes . '` WHERE `id` = %d AND `deleted_at` IS NULL LIMIT 1',
            $id
        );
        if (!is_string($sql)) {
            return null;
        }
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrator->hydrateAgente($row) : null;
    }

    public function findByNumeroRegistro(string $numero): ?Agente
    {
        $numero = trim($numero);
        if ($numero === '') {
            return null;
        }
        $sql = $this->wpdb->prepare(
            'SELECT * FROM `' . $this->tableAgentes . '`'
            . ' WHERE `numero_registro` = %s AND `deleted_at` IS NULL LIMIT 1',
            $numero
        );
        if (!is_string($sql)) {
            return null;
        }
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrator->hydrateAgente($row) : null;
    }

    public function findByCpf(string $cpfPlain): ?Agente
    {
        $cpfPlain = trim($cpfPlain);
        if ($cpfPlain === '') {
            return null;
        }
        try {
            $hash = $this->cipher->searchHash($cpfPlain);
        } catch (EncryptionException $e) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            'SELECT a.* FROM `' . $this->tableAgentes . '` a'
            . ' INNER JOIN `' . $this->tableAgentesPf . '` pf ON pf.`agente_id` = a.`id`'
            . ' WHERE pf.`cpf_hash` = %s AND a.`deleted_at` IS NULL LIMIT 1',
            $hash
        );
        if (!is_string($sql)) {
            return null;
        }
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrator->hydrateAgente($row) : null;
    }

    public function findByCnpj(string $cnpjPlain): ?Agente
    {
        $cnpjPlain = trim($cnpjPlain);
        if ($cnpjPlain === '') {
            return null;
        }
        try {
            $hash = $this->cipher->searchHash($cnpjPlain);
        } catch (EncryptionException $e) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            'SELECT a.* FROM `' . $this->tableAgentes . '` a'
            . ' INNER JOIN `' . $this->tableAgentesOr . '` org ON org.`agente_id` = a.`id`'
            . ' WHERE org.`cnpj_hash` = %s AND a.`deleted_at` IS NULL LIMIT 1',
            $hash
        );
        if (!is_string($sql)) {
            return null;
        }
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrator->hydrateAgente($row) : null;
    }

    public function findByUserId(int $userId): ?Agente
    {
        if ($userId <= 0) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            'SELECT * FROM `' . $this->tableAgentes . '`'
            . ' WHERE `user_id` = %d AND `deleted_at` IS NULL LIMIT 1',
            $userId
        );
        if (!is_string($sql)) {
            return null;
        }
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrator->hydrateAgente($row) : null;
    }

    public function findByEmail(string $email): ?Agente
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        $sql = $this->wpdb->prepare(
            'SELECT * FROM `' . $this->tableAgentes . '`'
            . ' WHERE `email_principal` = %s AND `deleted_at` IS NULL LIMIT 1',
            $email
        );
        if (!is_string($sql)) {
            return null;
        }
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrator->hydrateAgente($row) : null;
    }

    /**
     * @inheritDoc
     */
    public function save(Agente $agente, object $detalhes, array $representantes = []): int
    {
        $this->guardDetalhesType($agente->getTipo(), $detalhes);

        $isNew = !$agente->isPersisted();

        // Safety-net: o caso de uso `DeferirCadastro` é responsável por gerar
        // número de registro via {@see SequenceGenerator::next()} ANTES de
        // chamar a entidade `Agente::deferir()` — porque a entidade exige o VO
        // como parâmetro. O bloco abaixo apenas valida a invariante e falha
        // explicitamente se algum fluxo de import desrespeitar a regra.
        if ($agente->getStatusCadastro()->isDeferido() && $agente->getNumeroRegistro() === null) {
            throw new \LogicException(
                'WpdbAgenteRepository::save: agente deferido sem numero_registro. '
                . 'Gere via SequenceGenerator::next() antes de chamar Agente::deferir().'
            );
        }

        $this->wpdb->query('START TRANSACTION');
        try {
            $agenteId = $isNew
                ? $this->insertAgente($agente)
                : $this->updateAgente($agente);

            $this->saveDetalhes($agenteId, $agente->getTipo(), $detalhes);
            $this->saveRepresentantes($agenteId, $representantes);

            $this->wpdb->query('COMMIT');
        } catch (DuplicateCpfException $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        } catch (DuplicateCnpjException $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }

        // Audit (sem PII — AuditLogger redige).
        $this->auditLogger->log(
            'agente',
            $agenteId,
            $isNew ? 'criar' : 'atualizar',
            null,
            [
                'tipo'              => $agente->getTipo()->value(),
                'status_cadastro'   => $agente->getStatusCadastro()->value(),
                'numero_registro'   => $agente->getNumeroRegistro() !== null
                    ? $agente->getNumeroRegistro()->value() : null,
            ]
        );

        if ($isNew) {
            $agente->assignId($agenteId);
        }

        return $agenteId;
    }

    public function softDelete(int $id): void
    {
        if ($id <= 0) {
            throw AgenteNotFound::withId($id);
        }

        $now = gmdate('Y-m-d H:i:s');
        $sql = $this->wpdb->prepare(
            'UPDATE `' . $this->tableAgentes . '` SET `deleted_at` = %s WHERE `id` = %d AND `deleted_at` IS NULL',
            $now,
            $id
        );
        if (!is_string($sql)) {
            throw new \RuntimeException('Falha ao preparar UPDATE de soft-delete.');
        }
        $affected = $this->wpdb->query($sql);

        if ($affected === false) {
            throw new \RuntimeException('Falha ao executar soft-delete do agente.');
        }
        if ($affected === 0) {
            throw AgenteNotFound::withId($id);
        }

        $this->auditLogger->log('agente', $id, 'soft_delete', null, ['deleted_at' => $now]);
    }

    /**
     * @inheritDoc
     */
    public function listByStatus(string $status, int $page = 1, int $perPage = 25): array
    {
        // Valida o status contra o enum (lança InvalidArgumentException se inválido).
        $statusVo = StatusCadastro::fromString($status);

        $page    = $page > 0 ? $page : 1;
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;
        $offset  = ($page - 1) * $perPage;

        // Whitelist de orderby/order — defesa adicional caso surja parametrização.
        $orderBy = 'created_at';
        $order   = 'DESC';
        if (!in_array($orderBy, self::ORDERBY_WHITELIST, true) || !in_array($order, self::ORDER_WHITELIST, true)) {
            // Fallback seguro caso a whitelist seja editada sem cuidado.
            $orderBy = 'id';
            $order   = 'DESC';
        }

        $sql = $this->wpdb->prepare(
            'SELECT * FROM `' . $this->tableAgentes . '`'
            . ' WHERE `status_cadastro` = %s AND `deleted_at` IS NULL'
            . ' ORDER BY `' . $orderBy . '` ' . $order
            . ' LIMIT %d OFFSET %d',
            $statusVo->value(),
            $perPage,
            $offset
        );
        $rows = is_string($sql) ? (array) $this->wpdb->get_results($sql, ARRAY_A) : [];

        $countSql = $this->wpdb->prepare(
            'SELECT COUNT(*) FROM `' . $this->tableAgentes . '`'
            . ' WHERE `status_cadastro` = %s AND `deleted_at` IS NULL',
            $statusVo->value()
        );
        $total = is_string($countSql) ? (int) $this->wpdb->get_var($countSql) : 0;

        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrator->hydrateAgente($row);
            }
        }

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    // -------- Internos --------

    /**
     * @throws InvalidArgumentException Quando o tipo de detalhes não bate com o tipo do agente.
     */
    private function guardDetalhesType(TipoAgente $tipo, object $detalhes): void
    {
        switch ($tipo->value()) {
            case TipoAgente::PF:
                if (!$detalhes instanceof AgentePF) {
                    throw new InvalidArgumentException('Detalhes incompativeis: esperado AgentePF.');
                }
                return;
            case TipoAgente::OR:
                if (!$detalhes instanceof AgenteOR) {
                    throw new InvalidArgumentException('Detalhes incompativeis: esperado AgenteOR.');
                }
                return;
            case TipoAgente::SM:
                if (!$detalhes instanceof AgenteSM) {
                    throw new InvalidArgumentException('Detalhes incompativeis: esperado AgenteSM.');
                }
                return;
        }
    }

    private function insertAgente(Agente $agente): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = [
            'tipo'             => $agente->getTipo()->value(),
            'numero_registro'  => $agente->getNumeroRegistro() !== null
                ? $agente->getNumeroRegistro()->value() : null,
            'status_cadastro'  => $agente->getStatusCadastro()->value(),
            'user_id'          => $agente->getUserId(),
            'email_principal'  => $agente->getEmailPrincipal(),
            'telefone'         => $agente->getTelefone(),
            'submetido_em'     => self::dt($agente->getSubmetidoEm()),
            'deferido_em'      => self::dt($agente->getDeferidoEm()),
            'publicado_em'     => self::dt($agente->getPublicadoEm()),
            'created_at'       => self::dt($agente->getCreatedAt()) ?? $now,
            'updated_at'       => self::dt($agente->getUpdatedAt()) ?? $now,
        ];
        $formats = ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        $ok = $this->wpdb->insert($this->tableAgentes, $row, $formats);
        if ($ok === false) {
            throw new \RuntimeException('Falha ao inserir agente: ' . (string) $this->wpdb->last_error);
        }

        return (int) $this->wpdb->insert_id;
    }

    private function updateAgente(Agente $agente): int
    {
        $id = (int) $agente->getId();
        if ($id <= 0) {
            throw new InvalidArgumentException('updateAgente: id obrigatorio.');
        }
        $row = [
            'numero_registro' => $agente->getNumeroRegistro() !== null
                ? $agente->getNumeroRegistro()->value() : null,
            'status_cadastro' => $agente->getStatusCadastro()->value(),
            'user_id'         => $agente->getUserId(),
            'email_principal' => $agente->getEmailPrincipal(),
            'telefone'        => $agente->getTelefone(),
            'submetido_em'    => self::dt($agente->getSubmetidoEm()),
            'deferido_em'     => self::dt($agente->getDeferidoEm()),
            'publicado_em'    => self::dt($agente->getPublicadoEm()),
            'updated_at'      => self::dt($agente->getUpdatedAt()) ?? gmdate('Y-m-d H:i:s'),
        ];
        $formats = ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        $ok = $this->wpdb->update($this->tableAgentes, $row, ['id' => $id], $formats, ['%d']);
        if ($ok === false) {
            throw new \RuntimeException('Falha ao atualizar agente: ' . (string) $this->wpdb->last_error);
        }

        return $id;
    }

    /**
     * @param object $detalhes Já validado em {@see guardDetalhesType()}.
     */
    private function saveDetalhes(int $agenteId, TipoAgente $tipo, object $detalhes): void
    {
        switch ($tipo->value()) {
            case TipoAgente::PF:
                /** @var AgentePF $detalhes */
                $this->saveAgentePF($agenteId, $detalhes);
                return;
            case TipoAgente::OR:
                /** @var AgenteOR $detalhes */
                $this->saveAgenteOR($agenteId, $detalhes);
                return;
            case TipoAgente::SM:
                /** @var AgenteSM $detalhes */
                $this->saveAgenteSM($agenteId, $detalhes);
                return;
        }
    }

    private function saveAgentePF(int $agenteId, AgentePF $pf): void
    {
        $cpfEnc  = $this->encryptIfPresent($pf->getCpfPlain());
        $cpfHash = $this->hashIfPresent($pf->getCpfPlain());
        $rgEnc   = $this->encryptIfPresent($pf->getRgPlain());
        $passEnc = $this->encryptIfPresent($pf->getPassaportePlain());

        // Pré-checagem de duplicidade por hash (evita pegar fail de UNIQUE só no INSERT).
        if ($cpfHash !== null) {
            $sql = $this->wpdb->prepare(
                'SELECT `agente_id` FROM `' . $this->tableAgentesPf . '` WHERE `cpf_hash` = %s AND `agente_id` <> %d LIMIT 1',
                $cpfHash,
                $agenteId
            );
            if (is_string($sql) && $this->wpdb->get_var($sql) !== null) {
                throw DuplicateCpfException::create();
            }
        }

        $row = [
            'agente_id'                => $agenteId,
            'nome_completo'            => $pf->getNomeCompleto(),
            'nome_social'              => $pf->getNomeSocial(),
            'cpf_enc'                  => $cpfEnc,
            'cpf_hash'                 => $cpfHash,
            'rg_enc'                   => $rgEnc,
            'passaporte_enc'           => $passEnc,
            'nacionalidade'            => $pf->getNacionalidade(),
            'faixa_etaria'             => $pf->getFaixaEtaria(),
            'identidade_genero'        => $pf->getIdentidadeGenero(),
            'orientacao_sexual'        => $pf->getOrientacaoSexual(),
            'raca_cor'                 => $pf->getRacaCor(),
            'pessoa_deficiencia'       => $pf->getPessoaDeficiencia(),
            'deficiencia_descricao'    => $pf->getDeficienciaDescricao(),
            'recursos_acessibilidade'  => $pf->getRecursosAcessibilidade(),
            'grau_instrucao'           => $pf->getGrauInstrucao(),
            'ocupacao'                 => $pf->getOcupacao(),
            'cidade_residencia'        => $pf->getCidadeResidencia(),
            'estado_residencia'        => $pf->getEstadoResidencia(),
            'bairro_residencia'        => $pf->getBairroResidencia(),
            'organizacao_vinculada_id' => $pf->getOrganizacaoVinculadaId(),
            'apresentacao_md'          => $pf->getApresentacaoMd(),
        ];

        $exists = $this->detailExists($this->tableAgentesPf, $agenteId);
        $this->upsertDetail($this->tableAgentesPf, $agenteId, $row, $exists);
    }

    private function saveAgenteOR(int $agenteId, AgenteOR $org): void
    {
        $cnpjEnc  = $this->encryptIfPresent($org->getCnpjPlain());
        $cnpjHash = $this->hashIfPresent($org->getCnpjPlain());

        if ($cnpjHash !== null) {
            $sql = $this->wpdb->prepare(
                'SELECT `agente_id` FROM `' . $this->tableAgentesOr . '` WHERE `cnpj_hash` = %s AND `agente_id` <> %d LIMIT 1',
                $cnpjHash,
                $agenteId
            );
            if (is_string($sql) && $this->wpdb->get_var($sql) !== null) {
                throw DuplicateCnpjException::create();
            }
        }

        $row = [
            'agente_id'               => $agenteId,
            'nome_organizacao'        => $org->getNomeOrganizacao(),
            'tem_cnpj'                => $org->getTemCnpj(),
            'cnpj_enc'                => $cnpjEnc,
            'cnpj_hash'               => $cnpjHash,
            'tipo_coletivo'           => $org->getTipoColetivo(),
            'abrangencia'             => $org->getAbrangencia(),
            'cidade_sede'             => $org->getCidadeSede(),
            'estado_sede'             => $org->getEstadoSede(),
            'bairro_sede'             => $org->getBairroSede(),
            'apresentacao_md'         => $org->getApresentacaoMd(),
            'estrutura_governanca_md' => $org->getEstruturaGovernancaMd(),
            'data_fundacao'           => $org->getDataFundacao() !== null
                ? $org->getDataFundacao()->format('Y-m-d') : null,
        ];

        $exists = $this->detailExists($this->tableAgentesOr, $agenteId);
        $this->upsertDetail($this->tableAgentesOr, $agenteId, $row, $exists);
    }

    private function saveAgenteSM(int $agenteId, AgenteSM $sm): void
    {
        $repCpfEnc  = $this->encryptIfPresent($sm->getRepresentanteCpfPlain());
        $repCpfHash = $this->hashIfPresent($sm->getRepresentanteCpfPlain());

        $row = [
            'agente_id'                 => $agenteId,
            'nome_orgao'                => $sm->getNomeOrgao(),
            'esfera'                    => $sm->getEsfera(),
            'tipo_orgao'                => $sm->getTipoOrgao(),
            'uf'                        => $sm->getUf(),
            'municipio'                 => $sm->getMunicipio(),
            'lei_instituicao'           => $sm->getLeiInstituicao(),
            'ano_lei'                   => $sm->getAnoLei(),
            'representante_legal_nome'  => $sm->getRepresentanteLegalNome(),
            'representante_legal_cargo' => $sm->getRepresentanteLegalCargo(),
            'representante_cpf_enc'     => $repCpfEnc,
            'representante_cpf_hash'    => $repCpfHash,
        ];

        $exists = $this->detailExists($this->tableAgentesSm, $agenteId);
        $this->upsertDetail($this->tableAgentesSm, $agenteId, $row, $exists);
    }

    /**
     * @param array<int,Representante> $representantes
     */
    private function saveRepresentantes(int $agenteId, array $representantes): void
    {
        if ($representantes === []) {
            return;
        }
        // Estratégia simples: apaga os existentes e reinserie.
        // Reps são lista pequena (≤ ~10) e é mais simples que diffs por id.
        $del = $this->wpdb->prepare(
            'DELETE FROM `' . $this->tableRepresentantes . '` WHERE `agente_id` = %d',
            $agenteId
        );
        if (is_string($del)) {
            $this->wpdb->query($del);
        }

        foreach ($representantes as $rep) {
            if (!$rep instanceof Representante) {
                throw new InvalidArgumentException('Representante invalido na lista.');
            }
            $cpfEnc  = $this->encryptIfPresent($rep->getCpfPlain());
            $cpfHash = $this->hashIfPresent($rep->getCpfPlain());

            $row = [
                'agente_id' => $agenteId,
                'nome'      => $rep->getNome(),
                'cpf_enc'   => $cpfEnc,
                'cpf_hash'  => $cpfHash,
                'email'     => $rep->getEmail(),
                'telefone'  => $rep->getTelefone(),
                'papel'     => $rep->getPapel(),
                'principal' => $rep->isPrincipal() ? 1 : 0,
                'ordem'     => $rep->getOrdem(),
            ];
            $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d'];

            $ok = $this->wpdb->insert($this->tableRepresentantes, $row, $formats);
            if ($ok === false) {
                throw new \RuntimeException('Falha ao inserir representante: ' . (string) $this->wpdb->last_error);
            }
        }
    }

    private function detailExists(string $table, int $agenteId): bool
    {
        $sql = $this->wpdb->prepare(
            'SELECT `agente_id` FROM `' . $table . '` WHERE `agente_id` = %d LIMIT 1',
            $agenteId
        );
        if (!is_string($sql)) {
            return false;
        }

        return $this->wpdb->get_var($sql) !== null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function upsertDetail(string $table, int $agenteId, array $row, bool $exists): void
    {
        if ($exists) {
            $update = $row;
            unset($update['agente_id']);
            $ok = $this->wpdb->update($table, $update, ['agente_id' => $agenteId]);
        } else {
            $ok = $this->wpdb->insert($table, $row);
        }
        if ($ok === false) {
            throw new \RuntimeException(sprintf(
                'Falha em upsert de detalhe (%s): %s',
                $table,
                (string) $this->wpdb->last_error
            ));
        }
    }

    private function encryptIfPresent(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return $this->cipher->encrypt($plain);
    }

    private function hashIfPresent(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return $this->cipher->searchHash($plain);
    }

    private static function dt(?DateTimeImmutable $dt): ?string
    {
        return $dt !== null ? $dt->format('Y-m-d H:i:s') : null;
    }
}
