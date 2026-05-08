<?php
/**
 * Fallback seeder de vocabulários (defensive — produção usa V002/V003).
 *
 * @package Ibram\ParticipeIbram\Application\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Vocabulario;

use Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\VocabularioRepository;

/**
 * Popula `wp_pi_vocabularios` quando estiver vazia.
 *
 * Em produção a migração V002 já popula via SQL; esta classe é fallback para:
 *  - testes de integração (PHPUnit + DB efêmero)
 *  - dev environments que rodam apenas V001 sem os seeders
 *  - reset de ambiente após truncate
 *
 * Espelha integralmente os valores do V002 (defensive programming — qualquer
 * divergência aqui implica desvio entre seed SQL e seed PHP).
 */
final class SeederVocabularios
{
    private VocabularioRepository $repository;

    public function __construct(VocabularioRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Executa o seed apenas se nenhum dos tipos canônicos tiver itens.
     *
     * Critério de "vazia": verificamos `tipos_coletivo` e `abrangencias` —
     * baratíssimo e suficiente (V002 popula tudo de uma vez).
     */
    public function runIfEmpty(): void
    {
        $tiposColetivo = $this->repository->listByTipo(TipoVocabulario::TIPOS_COLETIVO, false);
        $abrangencias  = $this->repository->listByTipo(TipoVocabulario::ABRANGENCIAS, false);
        if (count($tiposColetivo) > 0 || count($abrangencias) > 0) {
            return;
        }

        $this->run();
    }

    /**
     * Força a execução do seed (idempotente: save() faz upsert por (tipo,valor)).
     */
    public function run(): void
    {
        foreach ($this->payload() as $row) {
            $this->repository->save(new ItemVocabulario(
                null,
                $row['tipo'],
                $row['valor'],
                $row['rotulo'],
                null,
                $row['ordem'],
                true,
                $row['metadata'] ?? null
            ));
        }
    }

    /**
     * Espelho do V002__seed_vocabularios.sql.
     *
     * @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int,metadata?:array<string,mixed>}>
     */
    private function payload(): array
    {
        return array_merge(
            $this->tiposColetivo(),
            $this->abrangencias(),
            $this->nacionalidades(),
            $this->faixasEtarias(),
            $this->identidadesGenero(),
            $this->orientacoesSexuais(),
            $this->racasCor(),
            $this->povosComunidadesTradicionais(),
            $this->grausInstrucao(),
            $this->ocupacoes(),
            $this->areasTematicas(),
            $this->instanciasParticipacao()
        );
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function tiposColetivo(): array
    {
        return $this->makeRows(TipoVocabulario::TIPOS_COLETIVO, [
            ['rede', 'Rede'],
            ['ponto_memoria', 'Ponto de Memória'],
            ['ponto_cultura', 'Ponto de Cultura'],
            ['ponto_leitura', 'Ponto de Leitura'],
            ['associacao', 'Associação'],
            ['movimento_social', 'Movimento social'],
            ['museu_comunitario', 'Museu comunitário'],
            ['ecomuseu', 'Ecomuseu'],
            ['sistema_museu_privado', 'Sistema de museu (privado)'],
            ['ong', 'ONG'],
            ['sociedade', 'Sociedade'],
            ['federacao', 'Federação'],
            ['sindicato', 'Sindicato'],
            ['outro', 'Outro'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function abrangencias(): array
    {
        return $this->makeRows(TipoVocabulario::ABRANGENCIAS, [
            ['local', 'Local'],
            ['municipal', 'Municipal'],
            ['estadual', 'Estadual'],
            ['regional', 'Regional'],
            ['nacional', 'Nacional'],
            ['internacional', 'Internacional'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function nacionalidades(): array
    {
        return $this->makeRows(TipoVocabulario::NACIONALIDADES, [
            ['brasileira', 'Brasileira'],
            ['brasileira_nacionalizada', 'Brasileira nacionalizada'],
            ['estrangeira', 'Estrangeira'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function faixasEtarias(): array
    {
        return $this->makeRows(TipoVocabulario::FAIXAS_ETARIAS, [
            ['10_19', '10 a 19 anos'],
            ['20_29', '20 a 29 anos'],
            ['30_39', '30 a 39 anos'],
            ['40_49', '40 a 49 anos'],
            ['50_59', '50 a 59 anos'],
            ['60_69', '60 a 69 anos'],
            ['70_79', '70 a 79 anos'],
            ['80_mais', '80 anos ou mais'],
            ['prefiro_nao_informar', 'Prefiro não informar'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function identidadesGenero(): array
    {
        return $this->makeRows(TipoVocabulario::IDENTIDADES_GENERO, [
            ['homem_cis', 'Homem cisgênero'],
            ['homem_trans', 'Homem transgênero'],
            ['mulher_cis', 'Mulher cisgênero'],
            ['mulher_trans', 'Mulher transgênero'],
            ['nao_binarie', 'Não-binárie'],
            ['outro', 'Outro'],
            ['prefiro_nao_informar', 'Prefiro não informar'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function orientacoesSexuais(): array
    {
        return $this->makeRows(TipoVocabulario::ORIENTACOES_SEXUAIS, [
            ['bissexual', 'Bissexual'],
            ['homossexual', 'Homossexual'],
            ['heterossexual', 'Heterossexual'],
            ['pansexual', 'Pansexual'],
            ['outras', 'Outras'],
            ['prefiro_nao_informar', 'Prefiro não informar'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function racasCor(): array
    {
        return $this->makeRows(TipoVocabulario::RACAS_COR, [
            ['amarela', 'Amarela'],
            ['branca', 'Branca'],
            ['indigena', 'Indígena'],
            ['negra_pretos', 'Negra (Pretos)'],
            ['negra_pardos', 'Negra (Pardos)'],
            ['outra', 'Outra'],
            ['prefiro_nao_informar', 'Prefiro não informar'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function povosComunidadesTradicionais(): array
    {
        return $this->makeRows(TipoVocabulario::POVOS_COMUNIDADES_TRADICIONAIS, [
            ['povos_indigenas', 'Povos indígenas'],
            ['quilombolas', 'Comunidades quilombolas'],
            ['terreiro_matriz_africana', 'Povos e comunidades de terreiro / matriz africana'],
            ['povos_ciganos', 'Povos ciganos'],
            ['pescadores_artesanais', 'Pescadores artesanais'],
            ['extrativistas', 'Extrativistas'],
            ['extrativistas_costeiros_marinhos', 'Extrativistas costeiros e marinhos'],
            ['caicaras', 'Caiçaras'],
            ['faxinalenses', 'Faxinalenses'],
            ['benzedeiros', 'Benzedeiros'],
            ['ilheus', 'Ilhéus'],
            ['raizeiros', 'Raizeiros'],
            ['geraizeiros', 'Geraizeiros'],
            ['caatingueiros', 'Caatingueiros'],
            ['vazanteiros', 'Vazanteiros'],
            ['veredeiros', 'Veredeiros'],
            ['apanhadores_flores_sempre_vivas', 'Apanhadores de flores sempre vivas'],
            ['pantaneiros', 'Pantaneiros'],
            ['morroquianos', 'Morroquianos'],
            ['povo_pomerano', 'Povo pomerano'],
            ['catadores_mangaba', 'Catadores de mangaba'],
            ['quebradeiras_coco_babacu', 'Quebradeiras de coco babaçu'],
            ['retireiros_araguaia', 'Retireiros do Araguaia'],
            ['fundos_fechos_pasto', 'Comunidades de fundos e fechos de pasto'],
            ['ribeirinhos', 'Ribeirinhos'],
            ['cipozeiros', 'Cipozeiros'],
            ['andirobeiros', 'Andirobeiros'],
            ['caboclos', 'Caboclos'],
            ['juventude_pct', 'Juventude de povos e comunidades tradicionais'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function grausInstrucao(): array
    {
        return $this->makeRows(TipoVocabulario::GRAUS_INSTRUCAO, [
            ['fundamental_incompleto', 'Ensino Fundamental incompleto'],
            ['fundamental_completo', 'Ensino Fundamental completo'],
            ['medio_incompleto', 'Ensino Médio incompleto'],
            ['medio_completo', 'Ensino Médio completo'],
            ['superior_incompleto', 'Ensino Superior incompleto'],
            ['superior_completo', 'Ensino Superior completo'],
            ['especializacao_incompleta', 'Especialização incompleta'],
            ['especializacao_completa', 'Especialização completa'],
            ['mestrado_incompleto', 'Mestrado incompleto'],
            ['mestrado_completo', 'Mestrado completo'],
            ['doutorado_incompleto', 'Doutorado incompleto'],
            ['doutorado_completo', 'Doutorado completo'],
            ['outro', 'Outro'],
            ['prefiro_nao_informar', 'Prefiro não informar'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function ocupacoes(): array
    {
        return $this->makeRows(TipoVocabulario::OCUPACOES, [
            ['profissional_museu_ponto_memoria', 'Profissional atuando em museu ou ponto de memória'],
            ['representante_ponto_memoria', 'Representante de ponto de memória'],
            ['educador_museal', 'Educador museal'],
            ['professor_superior', 'Professor de ensino superior'],
            ['professor_medio_fundamental', 'Professor de ensino médio ou fundamental'],
            ['estudante_museologia', 'Estudante de Museologia'],
            ['estudante_superior_outros', 'Estudante de ensino superior (exceto Museologia)'],
            ['economia_criativa', 'Integrante de segmento da economia criativa'],
            ['servidor_publico_cultural', 'Servidor público da área cultural'],
            ['profissional_apoio_museus', 'Profissional de instituição com ação de apoio a museus ou cultura'],
            ['outra', 'Outra'],
            ['prefiro_nao_informar', 'Prefiro não informar'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}> */
    private function areasTematicas(): array
    {
        return $this->makeRows(TipoVocabulario::AREAS_TEMATICAS, [
            ['gestao_acervos_documentacao', 'Gestão de Acervos e Documentação Museológica'],
            ['conservacao_restauracao', 'Conservação e Restauração'],
            ['comunicacao_curadoria', 'Comunicação e Curadoria'],
            ['pesquisa_museus', 'Pesquisa em Museus'],
            ['educacao_museal', 'Educação Museal'],
            ['acessibilidade_museal', 'Acessibilidade Museal'],
            ['tecnologia_inovacao_museal', 'Tecnologia e Inovação Museal'],
            ['patrimonio_imaterial_museus', 'Patrimônio Imaterial em Museus'],
            ['museologia_social', 'Museologia Social'],
            ['museus_comunitarios_ecomuseus', 'Museus Comunitários e Ecomuseus'],
            ['pontos_memoria', 'Pontos de Memória'],
            ['patrimonio_direitos_humanos', 'Patrimônio e Direitos Humanos'],
            ['economia_cultura_museus', 'Economia da Cultura e dos Museus'],
            ['difusao_fomento_museal', 'Difusão e Fomento Museal'],
            ['sistemas_redes_museus', 'Sistemas e Redes de Museus'],
            ['formacao_profissional_museologia', 'Formação Profissional em Museologia'],
            ['museus_universitarios', 'Museus Universitários'],
            ['museus_indigenas', 'Museus Indígenas'],
            ['museus_memoria_lgbtqiapn', 'Museus de Memória LGBTQIAPN+'],
            ['museus_mulheres', 'Museus de Mulheres'],
            ['memoria_pct', 'Memória de Povos e Comunidades Tradicionais'],
            ['outras', 'Outras (campo aberto na inscrição)'],
        ]);
    }

    /** @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int,metadata:array<string,mixed>}> */
    private function instanciasParticipacao(): array
    {
        $tipo = TipoVocabulario::INSTANCIAS_PARTICIPACAO;
        $rows = [
            ['ccpm', 'CCPM — Conselho Consultivo do Patrimônio Museológico', ['recorrente' => true, 'tipo' => 'permanente']],
            ['cgsbm', 'CGSBM — Comitê Gestor do Sistema Brasileiro de Museus', ['recorrente' => true, 'tipo' => 'permanente']],
            ['comite_pontos_memoria', 'Comitê Consultivo do Programa Pontos de Memória', ['recorrente' => true, 'tipo' => 'permanente']],
            ['ccdem', 'CCDEM — Comitê Consultivo de Desenvolvimento Econômico Museal', ['recorrente' => true, 'tipo' => 'permanente']],
            ['forum_nacional_museus', 'Fórum Nacional de Museus', ['recorrente' => false, 'tipo' => 'evento']],
            ['encontro_educacao_museal', 'Encontro Nacional de Educação Museal', ['recorrente' => false, 'tipo' => 'evento']],
            ['teia_memoria', 'Teia da Memória', ['recorrente' => false, 'tipo' => 'evento']],
            ['outras', 'Outras instâncias (campo livre na inscrição)', ['recorrente' => false, 'tipo' => 'evento']],
        ];
        $out = [];
        foreach ($rows as $i => [$valor, $rotulo, $metadata]) {
            $out[] = [
                'tipo'     => $tipo,
                'valor'    => $valor,
                'rotulo'   => $rotulo,
                'ordem'    => $i + 1,
                'metadata' => $metadata,
            ];
        }

        return $out;
    }

    /**
     * Wrap helper.
     *
     * @param array<int, array{0:string,1:string}> $pairs
     *
     * @return array<int, array{tipo:string,valor:string,rotulo:string,ordem:int}>
     */
    private function makeRows(string $tipo, array $pairs): array
    {
        $out = [];
        foreach ($pairs as $i => [$valor, $rotulo]) {
            $out[] = [
                'tipo'   => $tipo,
                'valor'  => $valor,
                'rotulo' => $rotulo,
                'ordem'  => $i + 1,
            ];
        }

        return $out;
    }
}
