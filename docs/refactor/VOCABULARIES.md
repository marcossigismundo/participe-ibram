# Participe Ibram — Vocabulários iniciais (v1.0)

> Listas de valores para popular `wp_pi_vocabularios` no seed. Editáveis pelo admin.

## 1. `tipos_coletivo` (do caderno .docx)
1. Rede
2. Ponto de Memória
3. Ponto de Cultura
4. Ponto de Leitura
5. Associação
6. Movimento social
7. Museu comunitário
8. Ecomuseu
9. Sistema de museu (privado)
10. ONG
11. Sociedade
12. Federação
13. Sindicato
14. Outro

## 2. `abrangencias`
1. Local
2. Municipal
3. Estadual
4. Regional
5. Nacional
6. Internacional

## 3. `nacionalidades`
1. Brasileira
2. Brasileira nacionalizada
3. Estrangeira

## 4. `faixas_etarias`
1. 10 a 19 anos
2. 20 a 29 anos
3. 30 a 39 anos
4. 40 a 49 anos
5. 50 a 59 anos
6. 60 a 69 anos
7. 70 a 79 anos
8. 80 anos ou mais
9. Prefiro não informar

## 5. `identidades_genero`
1. Homem cisgênero
2. Homem transgênero
3. Mulher cisgênero
4. Mulher transgênero
5. Não-binárie
6. Outro
7. Prefiro não informar

## 6. `orientacoes_sexuais`
1. Bissexual
2. Homossexual
3. Heterossexual
4. Pansexual
5. Outras
6. Prefiro não informar

## 7. `racas_cor` (IBGE + opções operacionais)
1. Amarela
2. Branca
3. Indígena
4. Negra (Pretos)
5. Negra (Pardos)
6. Outra
7. Prefiro não informar

## 8. `povos_comunidades_tradicionais` (Decreto 8.750/2016, art. 4º, §2º)
1. Povos indígenas
2. Comunidades quilombolas
3. Povos e comunidades de terreiro / matriz africana
4. Povos ciganos
5. Pescadores artesanais
6. Extrativistas
7. Extrativistas costeiros e marinhos
8. Caiçaras
9. Faxinalenses
10. Benzedeiros
11. Ilhéus
12. Raizeiros
13. Geraizeiros
14. Caatingueiros
15. Vazanteiros
16. Veredeiros
17. Apanhadores de flores sempre vivas
18. Pantaneiros
19. Morroquianos
20. Povo pomerano
21. Catadores de mangaba
22. Quebradeiras de coco babaçu
23. Retireiros do Araguaia
24. Comunidades de fundos e fechos de pasto
25. Ribeirinhos
26. Cipozeiros
27. Andirobeiros
28. Caboclos
29. Juventude de povos e comunidades tradicionais

## 9. `graus_instrucao`
1. Ensino Fundamental incompleto
2. Ensino Fundamental completo
3. Ensino Médio incompleto
4. Ensino Médio completo
5. Ensino Superior incompleto
6. Ensino Superior completo
7. Especialização incompleta
8. Especialização completa
9. Mestrado incompleto
10. Mestrado completo
11. Doutorado incompleto
12. Doutorado completo
13. Outro
14. Prefiro não informar

## 10. `ocupacoes` (do caderno .docx)
1. Profissional atuando em museu ou ponto de memória
2. Representante de ponto de memória
3. Educador museal
4. Professor de ensino superior
5. Professor de ensino médio ou fundamental
6. Estudante de Museologia
7. Estudante de ensino superior (exceto Museologia)
8. Integrante de segmento da economia criativa
9. Servidor público da área cultural
10. Profissional de instituição com ação de apoio a museus ou cultura
11. Outra
12. Prefiro não informar

## 11. `areas_tematicas` — SUGESTÃO (Rafaela pediu ajuste)

> Lista a v1 a partir do caderno + áreas reais do Ibram para validação com a CGSIM. Cobrir as três frentes: gestão técnica de museus, museologia social/comunitária, e economia/política museal.

### Eixo: Museologia técnica
1. Gestão de Acervos e Documentação Museológica
2. Conservação e Restauração
3. Comunicação e Curadoria
4. Pesquisa em Museus
5. Educação Museal
6. Acessibilidade Museal
7. Tecnologia e Inovação Museal
8. Patrimônio Imaterial em Museus

### Eixo: Museologia social
9. Museologia Social
10. Museus Comunitários e Ecomuseus
11. Pontos de Memória
12. Patrimônio e Direitos Humanos

### Eixo: Política e economia
13. Economia da Cultura e dos Museus
14. Difusão e Fomento Museal
15. Sistemas e Redes de Museus
16. Formação Profissional em Museologia

### Eixo: Específicos
17. Museus Universitários
18. Museus Indígenas
19. Museus de Memória LGBTQIAPN+
20. Museus de Mulheres
21. Memória de Povos e Comunidades Tradicionais
22. Outras (campo aberto na inscrição)

**Recomendação à CGSIM:** consolidar para 12–15 categorias antes do go-live. As marcadas em "Eixo: Específicos" podem virar **tags secundárias** em vez de áreas principais — evita lista enorme.

## 12. `instancias_participacao` (flexível)

Estrutura por linha em `wp_pi_vocabularios`:
- `valor` (ex.: `ccpm`)
- `rotulo` (ex.: "Conselho Consultivo do Patrimônio Museológico")
- `metadata` JSON: `{"recorrente": true, "tipo": "permanente"|"evento", "site": "..."}`

### Permanentes (recorrentes)
1. **CCPM** — Conselho Consultivo do Patrimônio Museológico
2. **CGSBM** — Comitê Gestor do Sistema Brasileiro de Museus
3. **Comitê Consultivo do Programa Pontos de Memória**
4. **CCDEM** — Comitê Consultivo de Desenvolvimento Econômico Museal (Despacho 98/2025)

### Eventuais (não recorrentes — abrir conforme houver edição)
5. Fórum Nacional de Museus (próxima edição com data e local; admin habilita quando editar)
6. Encontro Nacional de Educação Museal
7. Teia da Memória

### Outros (livre)
8. Outras instâncias (campo livre na inscrição)

## 13. `tipos_documento` (seed inicial)

| codigo | nome | obrigatorio_para | mime_permitidos | tamanho_max_kb |
|---|---|---|---|---|
| `cpf` | Comprovante de inscrição no CPF | PF,SM | application/pdf,image/jpeg,image/png | 5120 |
| `rg` | RG ou documento de identidade | PF,SM | application/pdf,image/jpeg,image/png | 5120 |
| `passaporte` | Passaporte (estrangeiros) | — | application/pdf,image/jpeg,image/png | 5120 |
| `cnpj` | Comprovante de inscrição no CNPJ | OR* | application/pdf | 2048 |
| `estatuto` | Estatuto social ou documento equivalente | OR* | application/pdf | 10240 |
| `ata_posse` | Ata de posse da diretoria/representante | OR* | application/pdf | 10240 |
| `carta_apresentacao` | Carta de apresentação e intenções | PF | application/pdf | 5120 |
| `carta_indicacao_coletivo` | Carta de indicação de representante (coletivo sem CNPJ, mín. 5 assinaturas) | OR (sem CNPJ) | application/pdf | 10240 |
| `lei_instituicao` | Lei de instituição (sistema/secretaria) | SM | application/pdf | 10240 |
| `oficio_indicacao` | Ofício de indicação de representante legal | SM | application/pdf | 5120 |
| `documentos_coletivo` | Outros documentos do coletivo (atas, regimentos, manifestos, anais) | — | application/pdf,image/jpeg,image/png | 10240 |

`OR*` = se OR tem CNPJ exige cnpj+estatuto+ata_posse; se OR sem CNPJ exige carta_indicacao_coletivo.

## 14. Modelos de documento (gerados pelo sistema)

Para reduzir fricção de quem não tem o documento pronto, o sistema **gera** o modelo:

### Carta de apresentação e intenções (PF)
Gerada em `templates/documents/carta_apresentacao_pf.tpl.docx`. Campos preenchidos automaticamente: nome, atuação no setor (do wizard), interesse declarado.

### Carta de indicação de representante (Coletivo sem CNPJ)
Gerada com tabela para 5+ assinaturas, dados de contato, CPFs. Modelo em `templates/documents/carta_indicacao_coletivo.tpl.docx`.

### Ofício de indicação (Sistema/Secretaria)
Modelo formal padrão SEI/AGU, com campos de autoridade, indicado, fundamentação. Em `templates/documents/oficio_indicacao_sm.tpl.docx`.

Botão no wizard: "Não tem o documento? Baixe o modelo preenchido aqui."

Geração via PHPWord ou template engine simples (recomendo phpoffice/phpword).
