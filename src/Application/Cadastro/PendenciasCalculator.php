<?php
/**
 * Calcula próximos passos e pendências do agente para o dashboard "Minha conta".
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumentoRepository;

/**
 * Serviço puro de apresentação (Application layer): traduz o estado do agente
 * em listas legíveis de "próximos passos" e "pendências" para o dashboard.
 *
 * Decisão de design:
 *  - Nada de I/O além das leituras delegadas aos repositórios.
 *  - Strings em pt_BR via `__()` text-domain `participe-ibram`.
 *  - Não conhece HTML: devolve arrays de strings — o template renderiza.
 *
 * Próximos passos: array<int,array{titulo:string, descricao:string, concluido:bool}>
 * Pendências:      array<int,array{tipo:string, mensagem:string, link?:string}>
 */
final class PendenciasCalculator
{
    private DocumentoRepository $documentos;
    private TipoDocumentoRepository $tiposDocumento;

    public function __construct(
        DocumentoRepository $documentos,
        TipoDocumentoRepository $tiposDocumento
    ) {
        $this->documentos     = $documentos;
        $this->tiposDocumento = $tiposDocumento;
    }

    /**
     * @return array{
     *   proximos_passos: array<int,array{titulo:string,descricao:string,concluido:bool}>,
     *   pendencias: array<int,array{tipo:string,mensagem:string,link?:string}>,
     *   prazo_atual: ?DateTimeImmutable,
     * }
     */
    public function paraAgente(Agente $agente): array
    {
        $status     = $agente->getStatusCadastro()->value();
        $proximos   = [];
        $pendencias = [];
        $prazo      = null;

        switch ($status) {
            case StatusCadastro::RASCUNHO:
                $proximos[] = self::passo(
                    self::t('Complete o cadastro'),
                    self::t('Termine de preencher os dados e anexe os documentos exigidos para submeter à análise.'),
                    false
                );
                $proximos[] = self::passo(
                    self::t('Submeter para análise'),
                    self::t('Após preenchimento completo, clique em "Submeter" no wizard.'),
                    false
                );
                $faltam = $this->documentosFaltantes($agente);
                foreach ($faltam as $nome) {
                    $pendencias[] = [
                        'tipo'     => 'documento_faltante',
                        'mensagem' => sprintf(
                            self::t('Documento obrigatório pendente: %s.'),
                            $nome
                        ),
                    ];
                }
                break;

            case StatusCadastro::SUBMETIDO:
                $proximos[] = self::passo(
                    self::t('Cadastro enviado'),
                    self::t('Seu cadastro foi recebido pela equipe Ibram.'),
                    true
                );
                $proximos[] = self::passo(
                    self::t('Aguardando análise técnica'),
                    self::t('Prazo médio: 30 dias úteis. Você será notificado(a) por e-mail quando houver decisão.'),
                    false
                );
                break;

            case StatusCadastro::EM_ANALISE:
                $proximos[] = self::passo(
                    self::t('Cadastro enviado'),
                    self::t('Recebido pela equipe Ibram.'),
                    true
                );
                $proximos[] = self::passo(
                    self::t('Em análise técnica'),
                    self::t('Um analista já está revisando seu cadastro. Acompanhe esta página.'),
                    false
                );
                break;

            case StatusCadastro::DEFERIDO:
            case StatusCadastro::DEFERIDO_EM_RETRATACAO:
            case StatusCadastro::DEFERIDO_EM_RECURSO:
                $proximos[] = self::passo(
                    self::t('Cadastro deferido'),
                    self::t('Parabéns! Você está cadastrado(a) como Agente do Sistema Brasileiro de Museus.'),
                    true
                );
                $proximos[] = self::passo(
                    self::t('Participe de editais'),
                    self::t('Consulte editais abertos e inscreva-se nas oportunidades disponíveis.'),
                    false
                );
                $proximos[] = self::passo(
                    self::t('Mantenha seus dados atualizados'),
                    self::t('Você pode atualizar contato e endereço a qualquer momento na aba "Meus dados".'),
                    false
                );
                break;

            case StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO:
                $proximos[] = self::passo(
                    self::t('Cadastro indeferido'),
                    self::t('Você tem o direito de recorrer no prazo de 10 dias.'),
                    false
                );
                $pendencias[] = [
                    'tipo'     => 'recurso_disponivel',
                    'mensagem' => self::t('Protocolar recurso de retratação (prazo: 10 dias da publicação do indeferimento).'),
                ];
                break;

            case StatusCadastro::EM_RETRATACAO:
                $proximos[] = self::passo(
                    self::t('Recurso em análise'),
                    self::t('Sua retratação está sob revisão do analista. Aguarde nova decisão.'),
                    false
                );
                break;

            case StatusCadastro::EM_RECURSO_PRESIDENCIA:
                $proximos[] = self::passo(
                    self::t('Recurso de presidência em análise'),
                    self::t('Seu caso foi escalado à presidência. Aguarde decisão final.'),
                    false
                );
                break;

            case StatusCadastro::INDEFERIDO_FINAL:
                $proximos[] = self::passo(
                    self::t('Cadastro indeferido em definitivo'),
                    self::t('Todos os recursos foram esgotados. Para tentar novamente, será necessário novo cadastro futuramente.'),
                    false
                );
                break;
        }

        return [
            'proximos_passos' => $proximos,
            'pendencias'      => $pendencias,
            'prazo_atual'     => $prazo,
        ];
    }

    /**
     * @return array<int,string> Nomes legíveis dos tipos de documento faltantes.
     */
    private function documentosFaltantes(Agente $agente): array
    {
        $id = $agente->getId();
        if ($id === null || $id <= 0) {
            return [];
        }
        try {
            $obrigatorios = $this->tiposDocumento->findObrigatoriosPara($agente->getTipo()->value());
        } catch (\Throwable $e) {
            return [];
        }
        if ($obrigatorios === []) {
            return [];
        }

        try {
            $enviados = $this->documentos->findByAgente($id);
        } catch (\Throwable $e) {
            $enviados = [];
        }
        $enviadosIds = [];
        foreach ($enviados as $doc) {
            $enviadosIds[$doc->tipoDocumentoId()] = true;
        }

        $faltantes = [];
        foreach ($obrigatorios as $tipo) {
            $tipoId = $tipo->id();
            if ($tipoId === null) {
                continue;
            }
            if (!isset($enviadosIds[$tipoId])) {
                $faltantes[] = $tipo->nome();
            }
        }

        return $faltantes;
    }

    /**
     * @return array{titulo:string,descricao:string,concluido:bool}
     */
    private static function passo(string $titulo, string $descricao, bool $concluido): array
    {
        return ['titulo' => $titulo, 'descricao' => $descricao, 'concluido' => $concluido];
    }

    private static function t(string $msg): string
    {
        return function_exists('__') ? \__($msg, 'participe-ibram') : $msg;
    }
}
