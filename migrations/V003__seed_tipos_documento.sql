-- =====================================================================
-- Participe Ibram - Seed: tipos_documento (V003)
-- =====================================================================
-- Reference: refactor-spec/VOCABULARIES.md section 13
-- Idempotent via INSERT IGNORE.
-- =====================================================================

INSERT IGNORE INTO `{prefix}tipos_documento`
  (`codigo`,`nome`,`descricao`,`obrigatorio_para`,`mime_permitidos`,`tamanho_max_kb`,`ativo`,`ordem`)
VALUES
('cpf','Comprovante de inscrição no CPF','Documento oficial de inscrição no CPF do titular.','PF,SM','application/pdf,image/jpeg,image/png',5120,1,10),
('rg','RG ou documento de identidade','RG ou outro documento oficial de identidade com foto.','PF,SM','application/pdf,image/jpeg,image/png',5120,1,20),
('passaporte','Passaporte (estrangeiros)','Passaporte para titulares estrangeiros (alternativo ao RG).',NULL,'application/pdf,image/jpeg,image/png',5120,1,30),
('cnpj','Comprovante de inscrição no CNPJ','Comprovante de inscrição no CNPJ. Obrigatório para OR com CNPJ.','OR','application/pdf',2048,1,40),
('estatuto','Estatuto social ou documento equivalente','Estatuto social registrado. Obrigatório para OR com CNPJ.','OR','application/pdf',10240,1,50),
('ata_posse','Ata de posse da diretoria/representante','Ata de posse vigente da diretoria. Obrigatória para OR com CNPJ.','OR','application/pdf',10240,1,60),
('carta_apresentacao','Carta de apresentação e intenções','Carta de apresentação e intenções (modelo gerado pelo sistema disponível).','PF','application/pdf',5120,1,70),
('carta_indicacao_coletivo','Carta de indicação de representante (coletivo sem CNPJ)','Carta de indicação assinada por mínimo 5 integrantes do coletivo sem CNPJ.','OR','application/pdf',10240,1,80),
('lei_instituicao','Lei de instituição (sistema/secretaria)','Lei de instituição do órgão (Sistema/Secretaria).','SM','application/pdf',10240,1,90),
('oficio_indicacao','Ofício de indicação de representante legal','Ofício formal de indicação do representante legal do órgão.','SM','application/pdf',5120,1,100),
('documentos_coletivo','Outros documentos do coletivo','Atas, regimentos, manifestos, anais e demais documentos do coletivo.',NULL,'application/pdf,image/jpeg,image/png',10240,1,110);
