-- =====================================================================
-- Participe Ibram - Seed: vocabularios (V002)
-- =====================================================================
-- Reference: refactor-spec/VOCABULARIES.md sections 1..12
-- Idempotent via INSERT IGNORE (avoids B-10 anti-pattern: silent dup errors).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. tipos_coletivo
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('tipos_coletivo','rede','Rede',1,1),
('tipos_coletivo','ponto_memoria','Ponto de Memória',2,1),
('tipos_coletivo','ponto_cultura','Ponto de Cultura',3,1),
('tipos_coletivo','ponto_leitura','Ponto de Leitura',4,1),
('tipos_coletivo','associacao','Associação',5,1),
('tipos_coletivo','movimento_social','Movimento social',6,1),
('tipos_coletivo','museu_comunitario','Museu comunitário',7,1),
('tipos_coletivo','ecomuseu','Ecomuseu',8,1),
('tipos_coletivo','sistema_museu_privado','Sistema de museu (privado)',9,1),
('tipos_coletivo','ong','ONG',10,1),
('tipos_coletivo','sociedade','Sociedade',11,1),
('tipos_coletivo','federacao','Federação',12,1),
('tipos_coletivo','sindicato','Sindicato',13,1),
('tipos_coletivo','outro','Outro',14,1);

-- ---------------------------------------------------------------------
-- 2. abrangencias
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('abrangencias','local','Local',1,1),
('abrangencias','municipal','Municipal',2,1),
('abrangencias','estadual','Estadual',3,1),
('abrangencias','regional','Regional',4,1),
('abrangencias','nacional','Nacional',5,1),
('abrangencias','internacional','Internacional',6,1);

-- ---------------------------------------------------------------------
-- 3. nacionalidades
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('nacionalidades','brasileira','Brasileira',1,1),
('nacionalidades','brasileira_nacionalizada','Brasileira nacionalizada',2,1),
('nacionalidades','estrangeira','Estrangeira',3,1);

-- ---------------------------------------------------------------------
-- 4. faixas_etarias
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('faixas_etarias','10_19','10 a 19 anos',1,1),
('faixas_etarias','20_29','20 a 29 anos',2,1),
('faixas_etarias','30_39','30 a 39 anos',3,1),
('faixas_etarias','40_49','40 a 49 anos',4,1),
('faixas_etarias','50_59','50 a 59 anos',5,1),
('faixas_etarias','60_69','60 a 69 anos',6,1),
('faixas_etarias','70_79','70 a 79 anos',7,1),
('faixas_etarias','80_mais','80 anos ou mais',8,1),
('faixas_etarias','prefiro_nao_informar','Prefiro não informar',9,1);

-- ---------------------------------------------------------------------
-- 5. identidades_genero
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('identidades_genero','homem_cis','Homem cisgênero',1,1),
('identidades_genero','homem_trans','Homem transgênero',2,1),
('identidades_genero','mulher_cis','Mulher cisgênero',3,1),
('identidades_genero','mulher_trans','Mulher transgênero',4,1),
('identidades_genero','nao_binarie','Não-binárie',5,1),
('identidades_genero','outro','Outro',6,1),
('identidades_genero','prefiro_nao_informar','Prefiro não informar',7,1);

-- ---------------------------------------------------------------------
-- 6. orientacoes_sexuais
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('orientacoes_sexuais','bissexual','Bissexual',1,1),
('orientacoes_sexuais','homossexual','Homossexual',2,1),
('orientacoes_sexuais','heterossexual','Heterossexual',3,1),
('orientacoes_sexuais','pansexual','Pansexual',4,1),
('orientacoes_sexuais','outras','Outras',5,1),
('orientacoes_sexuais','prefiro_nao_informar','Prefiro não informar',6,1);

-- ---------------------------------------------------------------------
-- 7. racas_cor
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('racas_cor','amarela','Amarela',1,1),
('racas_cor','branca','Branca',2,1),
('racas_cor','indigena','Indígena',3,1),
('racas_cor','negra_pretos','Negra (Pretos)',4,1),
('racas_cor','negra_pardos','Negra (Pardos)',5,1),
('racas_cor','outra','Outra',6,1),
('racas_cor','prefiro_nao_informar','Prefiro não informar',7,1);

-- ---------------------------------------------------------------------
-- 8. povos_comunidades_tradicionais (Decreto 8.750/2016, art. 4o §2o)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('povos_comunidades_tradicionais','povos_indigenas','Povos indígenas',1,1),
('povos_comunidades_tradicionais','quilombolas','Comunidades quilombolas',2,1),
('povos_comunidades_tradicionais','terreiro_matriz_africana','Povos e comunidades de terreiro / matriz africana',3,1),
('povos_comunidades_tradicionais','povos_ciganos','Povos ciganos',4,1),
('povos_comunidades_tradicionais','pescadores_artesanais','Pescadores artesanais',5,1),
('povos_comunidades_tradicionais','extrativistas','Extrativistas',6,1),
('povos_comunidades_tradicionais','extrativistas_costeiros_marinhos','Extrativistas costeiros e marinhos',7,1),
('povos_comunidades_tradicionais','caicaras','Caiçaras',8,1),
('povos_comunidades_tradicionais','faxinalenses','Faxinalenses',9,1),
('povos_comunidades_tradicionais','benzedeiros','Benzedeiros',10,1),
('povos_comunidades_tradicionais','ilheus','Ilhéus',11,1),
('povos_comunidades_tradicionais','raizeiros','Raizeiros',12,1),
('povos_comunidades_tradicionais','geraizeiros','Geraizeiros',13,1),
('povos_comunidades_tradicionais','caatingueiros','Caatingueiros',14,1),
('povos_comunidades_tradicionais','vazanteiros','Vazanteiros',15,1),
('povos_comunidades_tradicionais','veredeiros','Veredeiros',16,1),
('povos_comunidades_tradicionais','apanhadores_flores_sempre_vivas','Apanhadores de flores sempre vivas',17,1),
('povos_comunidades_tradicionais','pantaneiros','Pantaneiros',18,1),
('povos_comunidades_tradicionais','morroquianos','Morroquianos',19,1),
('povos_comunidades_tradicionais','povo_pomerano','Povo pomerano',20,1),
('povos_comunidades_tradicionais','catadores_mangaba','Catadores de mangaba',21,1),
('povos_comunidades_tradicionais','quebradeiras_coco_babacu','Quebradeiras de coco babaçu',22,1),
('povos_comunidades_tradicionais','retireiros_araguaia','Retireiros do Araguaia',23,1),
('povos_comunidades_tradicionais','fundos_fechos_pasto','Comunidades de fundos e fechos de pasto',24,1),
('povos_comunidades_tradicionais','ribeirinhos','Ribeirinhos',25,1),
('povos_comunidades_tradicionais','cipozeiros','Cipozeiros',26,1),
('povos_comunidades_tradicionais','andirobeiros','Andirobeiros',27,1),
('povos_comunidades_tradicionais','caboclos','Caboclos',28,1),
('povos_comunidades_tradicionais','juventude_pct','Juventude de povos e comunidades tradicionais',29,1);

-- ---------------------------------------------------------------------
-- 9. graus_instrucao
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('graus_instrucao','fundamental_incompleto','Ensino Fundamental incompleto',1,1),
('graus_instrucao','fundamental_completo','Ensino Fundamental completo',2,1),
('graus_instrucao','medio_incompleto','Ensino Médio incompleto',3,1),
('graus_instrucao','medio_completo','Ensino Médio completo',4,1),
('graus_instrucao','superior_incompleto','Ensino Superior incompleto',5,1),
('graus_instrucao','superior_completo','Ensino Superior completo',6,1),
('graus_instrucao','especializacao_incompleta','Especialização incompleta',7,1),
('graus_instrucao','especializacao_completa','Especialização completa',8,1),
('graus_instrucao','mestrado_incompleto','Mestrado incompleto',9,1),
('graus_instrucao','mestrado_completo','Mestrado completo',10,1),
('graus_instrucao','doutorado_incompleto','Doutorado incompleto',11,1),
('graus_instrucao','doutorado_completo','Doutorado completo',12,1),
('graus_instrucao','outro','Outro',13,1),
('graus_instrucao','prefiro_nao_informar','Prefiro não informar',14,1);

-- ---------------------------------------------------------------------
-- 10. ocupacoes
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('ocupacoes','profissional_museu_ponto_memoria','Profissional atuando em museu ou ponto de memória',1,1),
('ocupacoes','representante_ponto_memoria','Representante de ponto de memória',2,1),
('ocupacoes','educador_museal','Educador museal',3,1),
('ocupacoes','professor_superior','Professor de ensino superior',4,1),
('ocupacoes','professor_medio_fundamental','Professor de ensino médio ou fundamental',5,1),
('ocupacoes','estudante_museologia','Estudante de Museologia',6,1),
('ocupacoes','estudante_superior_outros','Estudante de ensino superior (exceto Museologia)',7,1),
('ocupacoes','economia_criativa','Integrante de segmento da economia criativa',8,1),
('ocupacoes','servidor_publico_cultural','Servidor público da área cultural',9,1),
('ocupacoes','profissional_apoio_museus','Profissional de instituição com ação de apoio a museus ou cultura',10,1),
('ocupacoes','outra','Outra',11,1),
('ocupacoes','prefiro_nao_informar','Prefiro não informar',12,1);

-- ---------------------------------------------------------------------
-- 11. areas_tematicas
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`) VALUES
('areas_tematicas','gestao_acervos_documentacao','Gestão de Acervos e Documentação Museológica',1,1),
('areas_tematicas','conservacao_restauracao','Conservação e Restauração',2,1),
('areas_tematicas','comunicacao_curadoria','Comunicação e Curadoria',3,1),
('areas_tematicas','pesquisa_museus','Pesquisa em Museus',4,1),
('areas_tematicas','educacao_museal','Educação Museal',5,1),
('areas_tematicas','acessibilidade_museal','Acessibilidade Museal',6,1),
('areas_tematicas','tecnologia_inovacao_museal','Tecnologia e Inovação Museal',7,1),
('areas_tematicas','patrimonio_imaterial_museus','Patrimônio Imaterial em Museus',8,1),
('areas_tematicas','museologia_social','Museologia Social',9,1),
('areas_tematicas','museus_comunitarios_ecomuseus','Museus Comunitários e Ecomuseus',10,1),
('areas_tematicas','pontos_memoria','Pontos de Memória',11,1),
('areas_tematicas','patrimonio_direitos_humanos','Patrimônio e Direitos Humanos',12,1),
('areas_tematicas','economia_cultura_museus','Economia da Cultura e dos Museus',13,1),
('areas_tematicas','difusao_fomento_museal','Difusão e Fomento Museal',14,1),
('areas_tematicas','sistemas_redes_museus','Sistemas e Redes de Museus',15,1),
('areas_tematicas','formacao_profissional_museologia','Formação Profissional em Museologia',16,1),
('areas_tematicas','museus_universitarios','Museus Universitários',17,1),
('areas_tematicas','museus_indigenas','Museus Indígenas',18,1),
('areas_tematicas','museus_memoria_lgbtqiapn','Museus de Memória LGBTQIAPN+',19,1),
('areas_tematicas','museus_mulheres','Museus de Mulheres',20,1),
('areas_tematicas','memoria_pct','Memória de Povos e Comunidades Tradicionais',21,1),
('areas_tematicas','outras','Outras (campo aberto na inscrição)',22,1);

-- ---------------------------------------------------------------------
-- 12. instancias_participacao  (com metadata JSON: recorrente, tipo)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}vocabularios` (`tipo`,`valor`,`rotulo`,`ordem`,`ativo`,`metadata`) VALUES
('instancias_participacao','ccpm','CCPM — Conselho Consultivo do Patrimônio Museológico',1,1,'{"recorrente": true, "tipo": "permanente"}'),
('instancias_participacao','cgsbm','CGSBM — Comitê Gestor do Sistema Brasileiro de Museus',2,1,'{"recorrente": true, "tipo": "permanente"}'),
('instancias_participacao','comite_pontos_memoria','Comitê Consultivo do Programa Pontos de Memória',3,1,'{"recorrente": true, "tipo": "permanente"}'),
('instancias_participacao','ccdem','CCDEM — Comitê Consultivo de Desenvolvimento Econômico Museal',4,1,'{"recorrente": true, "tipo": "permanente"}'),
('instancias_participacao','forum_nacional_museus','Fórum Nacional de Museus',5,1,'{"recorrente": false, "tipo": "evento"}'),
('instancias_participacao','encontro_educacao_museal','Encontro Nacional de Educação Museal',6,1,'{"recorrente": false, "tipo": "evento"}'),
('instancias_participacao','teia_memoria','Teia da Memória',7,1,'{"recorrente": false, "tipo": "evento"}'),
('instancias_participacao','outras','Outras instâncias (campo livre na inscrição)',8,1,'{"recorrente": false, "tipo": "evento"}');
