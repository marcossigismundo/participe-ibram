<?php
/**
 * Handler do SalvarRascunhoCommand (TD-04).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\Representante;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Vocabulario\VocabularioRepository;
use InvalidArgumentException;

/**
 * Salva (cria ou atualiza) o rascunho do agente conforme dados parciais do
 * wizard.
 *
 *  1. Se `agenteId` é null: cria novo {@see Agente} em status `rascunho`.
 *  2. Caso contrário, carrega o agente existente — só permite update se ele
 *     ainda estiver em `rascunho` (qualquer outro status indica que o cadastro
 *     já foi submetido e o caminho passa a ser via análise/recurso).
 *  3. Constrói a sub-entidade tipológica (PF/OR/SM) a partir de `dadosTipologia`.
 *  4. Valida vocabulários multi-select contra {@see VocabularioRepository}.
 *  5. Constrói a lista de Representantes (apenas OR/SM).
 *  6. Persiste via {@see AgenteRepository::save()}.
 *  7. Audita evento `rascunho_salvo` (sem PII no payload — apenas IDs e contagens).
 */
final class SalvarRascunhoHandler
{
    private AgenteRepository $agentes;
    private VocabularioRepository $vocabularios;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        VocabularioRepository $vocabularios,
        AuditLogger $audit
    ) {
        $this->agentes      = $agentes;
        $this->vocabularios = $vocabularios;
        $this->audit        = $audit;
    }

    /**
     * @return int Identificador interno do agente (rascunho).
     *
     * @throws DomainException        Quando o agente já está fora de rascunho.
     * @throws InvalidArgumentException Quando dados são inconsistentes.
     */
    public function handle(SalvarRascunhoCommand $command): int
    {
        $now   = new DateTimeImmutable('now');
        $tipo  = TipoAgente::fromString($command->tipoAgente());
        $email = self::sanitizeString((string) ($command->dadosBasicos()['email_principal'] ?? ''));
        if ($email === '') {
            throw new InvalidArgumentException('SalvarRascunho: email_principal e obrigatorio.');
        }
        $telefone = isset($command->dadosBasicos()['telefone'])
            ? self::sanitizeString((string) $command->dadosBasicos()['telefone'])
            : null;

        if ($command->agenteId() === null) {
            $agente = Agente::novo($tipo, $email, $command->userId(), $telefone, $now);
        } else {
            $agente = $this->agentes->findById($command->agenteId());
            if ($agente === null) {
                throw new DomainException(sprintf(
                    'Agente id=%d nao encontrado.',
                    $command->agenteId()
                ));
            }
            if (!$agente->getStatusCadastro()->equals(StatusCadastro::rascunho())) {
                throw new DomainException(
                    'Apenas rascunhos podem ser editados via SalvarRascunho.'
                );
            }
            if ($agente->getTipo()->value() !== $tipo->value()) {
                throw new DomainException(
                    'Tipo de agente nao pode ser alterado apos criacao do rascunho.'
                );
            }
            // Permitido: atualizar email/telefone via novo agregado raiz.
            $agente = new Agente(
                $agente->getId(),
                $agente->getTipo(),
                $agente->getNumeroRegistro(),
                $agente->getStatusCadastro(),
                $agente->getUserId() ?? $command->userId(),
                $email,
                $telefone,
                $agente->getSubmetidoEm(),
                $agente->getDeferidoEm(),
                $agente->getPublicadoEm(),
                $agente->getCreatedAt(),
                $now,
                $agente->getDeletedAt()
            );
        }

        // Vocabulários multi-select.
        $this->validarVocabularios($command->vocabulariosMultiSelect());

        // Sub-entidade tipológica (placeholder agenteId=1 para novos; será
        // ajustado pelo repositório após o INSERT do agente).
        $placeholderAgenteId = $agente->getId() ?? 1;
        $detalhes            = $this->montarDetalhes($tipo, $placeholderAgenteId, $command->dadosTipologia());

        // Representantes.
        $representantes = $this->montarRepresentantes(
            $placeholderAgenteId,
            $command->representantes(),
            $tipo
        );

        $id = $this->agentes->save($agente, $detalhes, $representantes);

        $this->audit->log(
            'agente',
            $id,
            'rascunho_salvo',
            null,
            [
                'tipo'                       => $tipo->value(),
                'user_id'                    => $command->userId(),
                'representantes_count'       => count($representantes),
                'vocabularios_multi_count'   => array_sum(array_map('count', $command->vocabulariosMultiSelect())),
                'created'                    => $command->agenteId() === null,
            ],
            $command->userId()
        );

        return $id;
    }

    /**
     * @param array<string,array<int,string>> $vocabulariosMulti
     */
    private function validarVocabularios(array $vocabulariosMulti): void
    {
        foreach ($vocabulariosMulti as $tipoVocab => $valores) {
            if (!is_string($tipoVocab) || !is_array($valores)) {
                continue;
            }
            foreach ($valores as $valor) {
                if (!is_string($valor) || $valor === '') {
                    continue;
                }
                if (!$this->vocabularios->validar($tipoVocab, $valor)) {
                    throw new DomainException(sprintf(
                        'Valor de vocabulario invalido: %s = %s',
                        $tipoVocab,
                        $valor
                    ));
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $d
     *
     * @return AgentePF|AgenteOR|AgenteSM
     */
    private function montarDetalhes(TipoAgente $tipo, int $agenteId, array $d): object
    {
        switch ($tipo->value()) {
            case TipoAgente::PF:
                return new AgentePF(
                    $agenteId,
                    self::sanitizeString((string) ($d['nome_completo'] ?? '')),
                    isset($d['nome_social']) ? self::sanitizeString((string) $d['nome_social']) : null,
                    isset($d['cpf']) ? self::sanitizeString((string) $d['cpf']) : null,
                    isset($d['rg']) ? self::sanitizeString((string) $d['rg']) : null,
                    isset($d['passaporte']) ? self::sanitizeString((string) $d['passaporte']) : null,
                    isset($d['nacionalidade']) ? self::sanitizeString((string) $d['nacionalidade']) : null,
                    isset($d['faixa_etaria']) ? self::sanitizeString((string) $d['faixa_etaria']) : null,
                    isset($d['identidade_genero']) ? self::sanitizeString((string) $d['identidade_genero']) : null,
                    isset($d['orientacao_sexual']) ? self::sanitizeString((string) $d['orientacao_sexual']) : null,
                    isset($d['raca_cor']) ? self::sanitizeString((string) $d['raca_cor']) : null,
                    isset($d['pessoa_deficiencia'])
                        ? self::sanitizeString((string) $d['pessoa_deficiencia'])
                        : AgentePF::PESSOA_DEFICIENCIA_PREFIRO_NAO_INFORMAR,
                    isset($d['deficiencia_descricao']) ? (string) $d['deficiencia_descricao'] : null,
                    isset($d['recursos_acessibilidade']) ? (string) $d['recursos_acessibilidade'] : null,
                    isset($d['grau_instrucao']) ? self::sanitizeString((string) $d['grau_instrucao']) : null,
                    isset($d['ocupacao']) ? self::sanitizeString((string) $d['ocupacao']) : null,
                    isset($d['cidade_residencia']) ? self::sanitizeString((string) $d['cidade_residencia']) : null,
                    isset($d['estado_residencia']) ? strtoupper(self::sanitizeString((string) $d['estado_residencia'])) : null,
                    isset($d['bairro_residencia']) ? self::sanitizeString((string) $d['bairro_residencia']) : null,
                    isset($d['organizacao_vinculada_id']) ? (int) $d['organizacao_vinculada_id'] : null,
                    isset($d['apresentacao_md']) ? (string) $d['apresentacao_md'] : null
                );

            case TipoAgente::OR:
                $temCnpj = isset($d['tem_cnpj']) ? self::sanitizeString((string) $d['tem_cnpj']) : AgenteOR::TEM_CNPJ_NAO;
                $dataFundacao = null;
                if (isset($d['data_fundacao']) && is_string($d['data_fundacao']) && $d['data_fundacao'] !== '') {
                    try {
                        $dataFundacao = new DateTimeImmutable((string) $d['data_fundacao']);
                    } catch (\Throwable $e) {
                        $dataFundacao = null;
                    }
                }

                return new AgenteOR(
                    $agenteId,
                    self::sanitizeString((string) ($d['nome_organizacao'] ?? '')),
                    $temCnpj,
                    isset($d['cnpj']) ? self::sanitizeString((string) $d['cnpj']) : null,
                    isset($d['tipo_coletivo']) ? self::sanitizeString((string) $d['tipo_coletivo']) : null,
                    isset($d['abrangencia']) ? self::sanitizeString((string) $d['abrangencia']) : null,
                    isset($d['cidade_sede']) ? self::sanitizeString((string) $d['cidade_sede']) : null,
                    isset($d['estado_sede']) ? strtoupper(self::sanitizeString((string) $d['estado_sede'])) : null,
                    isset($d['bairro_sede']) ? self::sanitizeString((string) $d['bairro_sede']) : null,
                    isset($d['apresentacao_md']) ? (string) $d['apresentacao_md'] : null,
                    isset($d['estrutura_governanca_md']) ? (string) $d['estrutura_governanca_md'] : null,
                    $dataFundacao
                );

            case TipoAgente::SM:
                return new AgenteSM(
                    $agenteId,
                    self::sanitizeString((string) ($d['nome_orgao'] ?? '')),
                    self::sanitizeString((string) ($d['esfera'] ?? '')),
                    self::sanitizeString((string) ($d['tipo_orgao'] ?? '')),
                    self::sanitizeString((string) ($d['representante_legal_nome'] ?? '')),
                    isset($d['uf']) ? strtoupper(self::sanitizeString((string) $d['uf'])) : null,
                    isset($d['municipio']) ? self::sanitizeString((string) $d['municipio']) : null,
                    isset($d['lei_instituicao']) ? self::sanitizeString((string) $d['lei_instituicao']) : null,
                    isset($d['ano_lei']) ? (int) $d['ano_lei'] : null,
                    isset($d['representante_legal_cargo']) ? self::sanitizeString((string) $d['representante_legal_cargo']) : null,
                    isset($d['representante_cpf']) ? self::sanitizeString((string) $d['representante_cpf']) : null
                );

            default:
                throw new InvalidArgumentException(sprintf(
                    'Tipo de agente nao suportado: %s',
                    $tipo->value()
                ));
        }
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     *
     * @return array<int,Representante>
     */
    private function montarRepresentantes(int $agenteId, array $rows, TipoAgente $tipo): array
    {
        // PF não tem representantes.
        if ($tipo->value() === TipoAgente::PF) {
            return [];
        }

        $out = [];
        $idx = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nome = self::sanitizeString((string) ($row['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $out[] = new Representante(
                isset($row['id']) ? (int) $row['id'] : null,
                $agenteId,
                $nome,
                isset($row['cpf']) ? self::sanitizeString((string) $row['cpf']) : null,
                isset($row['email']) ? self::sanitizeString((string) $row['email']) : null,
                isset($row['telefone']) ? self::sanitizeString((string) $row['telefone']) : null,
                isset($row['papel']) ? self::sanitizeString((string) $row['papel']) : null,
                !empty($row['principal']),
                isset($row['ordem']) ? (int) $row['ordem'] : $idx
            );
            $idx++;
        }

        return $out;
    }

    private static function sanitizeString(string $value): string
    {
        $clean = trim($value);
        // Remove control chars (sem depender de WP — WP-side sanitiza fora).
        $clean = preg_replace('/[\x00-\x1F\x7F]+/', '', $clean) ?? '';

        return $clean;
    }
}
