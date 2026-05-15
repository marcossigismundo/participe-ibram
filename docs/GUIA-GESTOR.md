# Manual do Gestor

## Participe Ibram

**Operação e administração da plataforma**

<div class="lead">Este manual é para servidores do Ibram que operam o sistema:
analistas de cadastro, presidência, gestores de edital, apuradores,
encarregado de proteção de dados (DPO) e administradores. Cobre
todos os fluxos da plataforma em linguagem operacional, com passo
a passo, critérios de decisão, prazos legais e boas práticas.</div>

---

## Sumário

1. **Sobre este manual**
2. **Visão geral do sistema**
3. **Acessando a plataforma**
4. **Gestão de cadastros (Analista)**
5. **Recursos administrativos (Presidência)**
6. **Gestão de editais (Gestor de Edital)**
7. **Apuração de votações (Apurador)**
8. **Conformidade LGPD (Encarregado)**
9. **Administração geral**
10. **Auditoria e indicadores**
11. **Boas práticas e SLAs**
12. **Resolução de problemas**
13. **Termos importantes**

<div class="pagebreak"></div>

## 1. Sobre este manual

### Para quem é

Este manual atende **6 perfis** que operam dentro do Participe Ibram:

<div class="role-grid role-grid--3">

<div class="role-card role-card--pf">
<div class="role-card__badge">Analista</div>
<h3>Análise de cadastros</h3>
<p>Revisa documentos, defere ou indefere cadastros de agentes
culturais. Decide recursos de retratação.</p>
</div>

<div class="role-card role-card--admin">
<div class="role-card__badge">Presidência</div>
<h3>Recursos finais</h3>
<p>Julga em última instância os recursos contra indeferimentos
mantidos pela análise.</p>
</div>

<div class="role-card role-card--sm">
<div class="role-card__badge">Gestor</div>
<h3>Editais culturais</h3>
<p>Cria editais, define categorias, decide habilitações e atende
recursos de inabilitação.</p>
</div>

</div>

<div class="role-grid role-grid--3">

<div class="role-card role-card--citizen">
<div class="role-card__badge">Apurador</div>
<h3>Votações</h3>
<p>Verifica integridade dos votos, executa a apuração e publica o
resultado oficial.</p>
</div>

<div class="role-card role-card--or">
<div class="role-card__badge">DPO</div>
<h3>Proteção de dados</h3>
<p>Encarregado pela LGPD. Atende solicitações de titulares e
fiscaliza acessos a dados sensíveis.</p>
</div>

<div class="role-card role-card--admin">
<div class="role-card__badge">Admin</div>
<h3>Administração</h3>
<p>Configura SMTP, modelos de e-mail, vocabulários, e mantém
saúde geral do sistema.</p>
</div>

</div>

### Como usar este manual

<div class="callout callout-tip">
<strong>Dica.</strong> Cada perfil tem uma seção dedicada. Vá direto
à sua. Os fluxos de outros perfis estão ligados — leia também o
contexto de quem te entrega e recebe trabalho.
</div>

<div class="callout callout-info">
<strong>Importante.</strong> O sistema implementa a Portaria Ibram
3.230/2024, o Despacho 98/2025-DDFEM e a LGPD (Lei 13.709/2018).
Os prazos e procedimentos descritos aqui têm fundamento legal —
respeite-os.
</div>

<div class="pagebreak"></div>

## 2. Visão geral do sistema

### O que o sistema faz

O Participe Ibram digitaliza e dá transparência a 4 grandes processos
do instituto:

<div class="step-grid">

<div class="step">
<div class="step__num">1</div>
<div class="step__body">
<h4>Cadastro de agentes culturais</h4>
<p>Agentes (pessoas físicas, organizações, secretarias) se cadastram
no CNAC — Cadastro Nacional de Agentes Culturais. Equipe do Ibram
analisa e defere.</p>
</div>
</div>

<div class="step">
<div class="step__num">2</div>
<div class="step__body">
<h4>Editais culturais</h4>
<p>Gestores publicam editais com categorias de vagas. Agentes
deferidos se inscrevem. Inscrições são habilitadas formalmente.</p>
</div>
</div>

<div class="step">
<div class="step__num">3</div>
<div class="step__body">
<h4>Votações eletrônicas</h4>
<p>Após habilitações, eleitores votam de forma secreta. Apurador
roda apuração com critério determinístico.</p>
</div>
</div>

<div class="step">
<div class="step__num">4</div>
<div class="step__body">
<h4>Proteção de dados (LGPD)</h4>
<p>Cidadãos exercem direitos sobre seus dados (acesso, retificação,
anonimização, portabilidade). DPO atende em 15 dias úteis.</p>
</div>
</div>

</div>

### Fluxo macro

<div class="timeline">

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Agente envia cadastro</h4>
<p>Pelo formulário público, em 5 passos com salvamento automático.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Análise pelo Ibram</h4>
<p>Analista assume o cadastro, revisa documentos, decide. Se
deferido, gera número de registro automaticamente.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Inscrição em edital</h4>
<p>Agentes deferidos podem se inscrever em editais abertos. Gestor
habilita as inscrições.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Votação eletrônica</h4>
<p>Eleitores votam secretamente. Sistema garante anti-rastreio voto-
eleitor por design criptográfico.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot timeline__dot--success"></div>
<div class="timeline__body">
<h4>Resultado oficial</h4>
<p>Apurador publica eleitos e suplentes. Relatório oficial gerado
para auditoria pública.</p>
</div>
</div>

</div>

<div class="pagebreak"></div>

## 3. Acessando a plataforma

### Como você recebe acesso

O acesso ao back-office administrativo é concedido pelo coordenador
da sua área. O processo:

<div class="step-grid">

<div class="step">
<div class="step__num">1</div>
<div class="step__body">
<h4>Solicitação formal</h4>
<p>Seu coordenador abre solicitação ao administrador do sistema,
informando seu nome, e-mail funcional e o perfil necessário.</p>
</div>
</div>

<div class="step">
<div class="step__num">2</div>
<div class="step__body">
<h4>Criação do usuário</h4>
<p>Administrador cria seu login, atribui o papel correspondente
(Analista, Gestor, Apurador, DPO, etc.) e envia senha temporária
por e-mail seguro.</p>
</div>
</div>

<div class="step">
<div class="step__num">3</div>
<div class="step__body">
<h4>Primeiro acesso</h4>
<p>Você acessa a URL administrativa, faz login com a senha
temporária e é forçado a trocá-la por uma definitiva.</p>
</div>
</div>

<div class="step">
<div class="step__num">4</div>
<div class="step__body">
<h4>Treinamento</h4>
<p>Leia este manual. Em caso de dúvida, contate o administrador
ou o DPO conforme o tipo de questão.</p>
</div>
</div>

</div>

<div class="callout callout-warning">
<strong>Segurança.</strong> Senha deve ter no mínimo 12 caracteres
com letras maiúsculas, minúsculas, números e símbolos. NUNCA
compartilhe sua senha. Sessões expiram após 60 minutos de inatividade.
</div>

### Navegação

Ao entrar no back-office você verá uma **barra lateral própria** do
plugin à esquerda da página, organizada em 5 grupos lógicos. Cada
grupo só aparece se você tem permissão para acessá-lo.

<div class="role-grid">

<div class="role-card role-card--citizen">
<div class="role-card__badge">Grupo 1</div>
<h3>Análise de cadastros</h3>
<p>Fila de análise · Todos os agentes · Recursos (Retratação,
Presidência, Prazos vencendo)</p>
</div>

<div class="role-card role-card--sm">
<div class="role-card__badge">Grupo 2</div>
<h3>Editais & habilitações</h3>
<p>Editais · Novo edital · Habilitações pendentes · Recursos de
inabilitação</p>
</div>

<div class="role-card role-card--pf">
<div class="role-card__badge">Grupo 3</div>
<h3>Votações</h3>
<p>Votações · Auditoria de votação</p>
</div>

<div class="role-card role-card--or">
<div class="role-card__badge">Grupo 4</div>
<h3>Conformidade & LGPD</h3>
<p>Log de eventos · Acessos a PII · Decisões · Configuração DPO ·
Solicitações de titulares</p>
</div>

<div class="role-card role-card--admin">
<div class="role-card__badge">Grupo 5</div>
<h3>Ferramentas</h3>
<p>E-mail · Setup de teste · Ajuda · Vocabulários</p>
</div>

</div>

### O Painel principal

Ao entrar, você cai na página **Painel**, com 6 indicadores numéricos
e um painel "Próximo passo" que muda conforme seu perfil:

<div class="rights-grid">

<div class="right">
<div class="right__icon">1</div>
<h4>Cadastros pendentes</h4>
<p>Total aguardando análise.</p>
</div>

<div class="right">
<div class="right__icon">2</div>
<h4>Editais publicados</h4>
<p>Editais ativos no momento.</p>
</div>

<div class="right">
<div class="right__icon">3</div>
<h4>Recursos abertos</h4>
<p>Em retratação ou presidência.</p>
</div>

<div class="right">
<div class="right__icon">4</div>
<h4>Votações em curso</h4>
<p>Abertas neste momento.</p>
</div>

<div class="right">
<div class="right__icon">5</div>
<h4>Solicitações LGPD</h4>
<p>Pendentes de resposta do DPO.</p>
</div>

<div class="right">
<div class="right__icon">6</div>
<h4>Fila de e-mail</h4>
<p>Mensagens aguardando envio.</p>
</div>

</div>

<div class="callout callout-tip">
<strong>Dica.</strong> O painel "Próximo passo" sugere onde sua
atenção é mais urgente. Para analista, indica a fila. Para DPO,
indica solicitações vencendo. Use como bússola diária.
</div>

<div class="pagebreak"></div>

## 4. Gestão de cadastros (Analista)

### Visão geral do seu papel

Você é o ponto de entrada de qualidade do CNAC. Cada cadastro enviado
por um agente passa pelo seu crivo. Você confere:

- Se os documentos anexados são autênticos e legíveis;
- Se as informações declaradas batem com os documentos;
- Se o agente realmente se enquadra no tipo declarado (PF, OR, SM);
- Se há sinais de duplicidade ou fraude.

<div class="callout callout-info">
<strong>Importante.</strong> Você NÃO julga mérito artístico, qualidade
ou relevância. Sua análise é estritamente formal/documental.
A análise é regida pela Portaria Ibram 3.230/2024.
</div>

### Fila de análise

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Análise → Fila de análise</strong>. A lista mostra
cadastros com status "Submetido" ou "Em análise".</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Filtros disponíveis no topo: <em>tipo</em> (PF, OR, SM), <em>UF</em>,
<em>data de submissão</em>, <em>analista atribuído</em>. Use para
priorizar.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Clique em um cadastro para abrir os detalhes. Você vê: dados
declarados, documentos anexados, linha do tempo, consentimentos LGPD
aceitos.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>Para começar a analisar, clique em <strong>Assumir análise</strong>.
O cadastro fica marcado como seu — outros analistas verão que está
em curso e não duplicarão trabalho.</p>
</div>

</div>

### Critérios de deferimento (checklist mental)

Use esta lista ao revisar cada cadastro:

<div class="step-grid">

<div class="step">
<div class="step__num">A</div>
<div class="step__body">
<h4>Identificação consistente</h4>
<p>Nome no cadastro = nome no documento de identidade. CPF/CNPJ/Lei
declarado bate com o documento. Sem rasuras ou alterações suspeitas.</p>
</div>
</div>

<div class="step">
<div class="step__num">B</div>
<div class="step__body">
<h4>Documentos completos</h4>
<p>Todos os campos obrigatórios para o tipo (PF/OR/SM) estão
preenchidos. Anexos legíveis, no formato correto (PDF/JPG/PNG).</p>
</div>
</div>

<div class="step">
<div class="step__num">C</div>
<div class="step__body">
<h4>Enquadramento no tipo</h4>
<p>PF: pessoa física com CPF próprio. OR: organização com CNPJ
OU coletivo formalizado com carta de indicação. SM: sistema
municipal/estadual com lei de criação.</p>
</div>
</div>

<div class="step">
<div class="step__num">D</div>
<div class="step__body">
<h4>Consentimentos obrigatórios</h4>
<p>Pelo menos os consentimentos "necessários" (manutenção de cadastro
+ comunicações oficiais) precisam estar aceitos. Os opcionais ficam a
critério do agente.</p>
</div>
</div>

<div class="step">
<div class="step__num">E</div>
<div class="step__body">
<h4>Sem duplicidade</h4>
<p>Confira em "Todos os agentes" se o mesmo CPF/CNPJ já tem outro
cadastro deferido. O sistema mostra alerta automático em CPF
duplicado.</p>
</div>
</div>

</div>

### Quando deferir

Se todos os 5 critérios acima passam, clique em **Deferir**. O sistema:

<div class="step-grid">

<div class="step">
<div class="step__num">1</div>
<div class="step__body">
<h4>Gera o número de registro</h4>
<p>Formato: <code>PI-{TIPO}-{ANO}-{SEQ06}</code>. Exemplo:
<code>PI-PF-2026-000123</code>. É imutável e único.</p>
</div>
</div>

<div class="step">
<div class="step__num">2</div>
<div class="step__body">
<h4>Atualiza o status</h4>
<p>Status passa para "Deferido". O agente pode se inscrever em
editais a partir de agora.</p>
</div>
</div>

<div class="step">
<div class="step__num">3</div>
<div class="step__body">
<h4>Envia comunicação</h4>
<p>O agente recebe e-mail automático com o número de registro e
link para acesso à plataforma.</p>
</div>
</div>

<div class="step">
<div class="step__num">4</div>
<div class="step__body">
<h4>Registra em auditoria</h4>
<p>Toda análise é logada com seu ID de analista, data/hora e
decisão. A trilha é imutável.</p>
</div>
</div>

</div>

### Quando indeferir

Se algum critério não passa, clique em **Indeferir**. O sistema exige
**motivo escrito** obrigatório. Use texto claro e específico:

<div class="callout callout-success">
<strong>Bom motivo.</strong> "Documento de identidade ilegível. Por
favor reenvie a frente do RG em resolução mínima de 200dpi e a foto
nítida. Outros documentos estão em ordem."
</div>

<div class="callout callout-danger">
<strong>Motivo ruim.</strong> "Documentação incompleta." (vago — o
agente não sabe o que falta exatamente).
</div>

### Recurso de retratação

Após indeferimento, o agente tem prazo legal para protocolar **recurso
de retratação**. Esse recurso volta para você (o mesmo analista) revisar.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Análise → Recursos — Retratação</strong>. Lista
recursos abertos com prazo limite.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Leia a argumentação do agente e os documentos novos (se anexou).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Decida: <strong>Retratar</strong> (você reverte seu indeferimento e
defere) ou <strong>Manter</strong> (o indeferimento original
prevalece e o recurso sobe para a Presidência).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>Em ambos os casos, escreva fundamentação. O agente recebe
notificação automática.</p>
</div>

</div>

<div class="callout callout-warning">
<strong>Atenção ao prazo.</strong> Se você não responder o recurso
dentro do prazo legal, ele sobe automaticamente para a Presidência.
Use o filtro "Prazos vencendo" para priorizar.
</div>

### SLA do analista

<div class="rights-grid">

<div class="right">
<div class="right__icon">10</div>
<h4>Prazo análise</h4>
<p>10 dias úteis a partir da submissão.</p>
</div>

<div class="right">
<div class="right__icon">5</div>
<h4>Prazo retratação</h4>
<p>5 dias úteis a partir do recurso.</p>
</div>

<div class="right">
<div class="right__icon">24h</div>
<h4>Resposta inicial</h4>
<p>"Assumir análise" no primeiro dia útil.</p>
</div>

</div>

<div class="pagebreak"></div>

## 5. Recursos administrativos (Presidência)

### Visão geral do seu papel

Você é a **segunda instância** dos recursos. Quando um analista
mantém um indeferimento (em retratação), o agente pode levar o caso
à Presidência. Sua decisão é **definitiva** — encerra o processo
administrativo.

### Como julgar um recurso

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Análise → Recursos — Presidência</strong>. A lista
mostra recursos que subiram da retratação.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Clique para abrir o caso. Você vê toda a linha do tempo: cadastro
original, motivo do indeferimento, argumentação do agente, manutenção
do analista.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Avalie o conjunto. A decisão tem dois caminhos:</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p><strong>Defere o recurso</strong> (cadastro aprovado em definitivo):
o agente é deferido, recebe número de registro, status fica
"Deferido após recurso".</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">5</div>
<p><strong>Nega o recurso</strong>: o indeferimento se torna
definitivo. Status fica "Indeferido final". O agente não pode mais
recorrer administrativamente.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">6</div>
<p>Em ambos os casos, escreva a fundamentação detalhada. Ela é
publicada na linha do tempo e enviada por e-mail ao agente.</p>
</div>

</div>

<div class="callout callout-info">
<strong>Fundamentação é mandatória.</strong> A decisão da Presidência
pode ser questionada por via judicial ou pela ANPD. Uma fundamentação
sólida protege o instituto. Cite a norma aplicável (Portaria 3.230/2024,
artigo X).
</div>

### Padrão de decisão

<div class="step-grid">

<div class="step">
<div class="step__num">A</div>
<div class="step__body">
<h4>Confira o devido processo</h4>
<p>O analista cumpriu prazos? Motivo do indeferimento foi específico?
O agente teve oportunidade real de contestar?</p>
</div>
</div>

<div class="step">
<div class="step__num">B</div>
<div class="step__body">
<h4>Verifique novos elementos</h4>
<p>O agente trouxe documentos ou argumentos não considerados antes?
Em caso afirmativo, eles superam o motivo original?</p>
</div>
</div>

<div class="step">
<div class="step__num">C</div>
<div class="step__body">
<h4>Equilibre interesse público</h4>
<p>Em casos limítrofes, considere a função social do cadastro:
ampliar participação cultural é a finalidade da Portaria 3.230/2024.</p>
</div>
</div>

</div>

### SLA da presidência

<div class="callout callout-warning">
<strong>Prazo.</strong> 15 dias úteis para decidir o recurso, contados
da data em que o caso subiu da retratação. Pode haver prorrogação
única e justificada por mais 5 dias úteis.
</div>

<div class="pagebreak"></div>

## 6. Gestão de editais (Gestor de Edital)

### Visão geral do seu papel

Você cria os instrumentos de participação social do Ibram. Um edital
bem desenhado garante transparência, evita questionamentos e amplia
representatividade. Você opera 5 momentos do edital:

<div class="timeline">

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Criação (rascunho)</h4>
<p>Define objeto, datas, categorias e vagas.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Publicação</h4>
<p>Abre o edital para inscrições.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Habilitação</h4>
<p>Aceita ou recusa inscrições.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Recursos de inabilitação</h4>
<p>Atende contestações de inabilitados.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot timeline__dot--success"></div>
<div class="timeline__body">
<h4>Encerramento</h4>
<p>Passa para votação (a cargo do apurador).</p>
</div>
</div>

</div>

### Criando um novo edital

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Editais → Novo edital</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Preencha o <strong>cabeçalho</strong>: título público, descrição
(suporta formatação Markdown — você pode usar listas, negrito,
links).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Configure as <strong>datas-chave</strong> em ordem cronológica
estrita: abertura → encerramento de inscrições → publicação da
habilitação → prazo recurso → abertura votação → encerramento
votação → publicação do resultado.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>Clique <strong>Salvar rascunho</strong>. Edital existe mas não
está visível para os agentes ainda.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">5</div>
<p>Acesse a aba <strong>Categorias</strong> e adicione as vagas
(cargos a serem eleitos). Cada categoria tem: nome, tipos elegíveis
(PF/OR/SM), número de vagas titulares, número de suplentes.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">6</div>
<p>Revise tudo. Quando estiver pronto, clique <strong>Publicar</strong>.
O edital aparece publicamente e as inscrições abrem.</p>
</div>

</div>

<div class="callout callout-warning">
<strong>Cuidado com as datas.</strong> Depois de publicado, alterar
datas exige justificativa formal documentada e nova publicação. As
datas vinculam todos os candidatos.
</div>

<div class="callout callout-tip">
<strong>Dica.</strong> Use o <strong>timeline visual</strong> na
sidebar do formulário para confirmar que a sequência cronológica
está correta antes de publicar.
</div>

### Habilitação de inscrições

Após o prazo de inscrição encerrar, você precisa **habilitar ou
inabilitar** cada inscrição. Habilitação significa que a inscrição
está apta a concorrer na votação.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Editais & habilitações → Habilitações pendentes</strong>.
Lista todas as inscrições aguardando decisão.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Clique em uma inscrição. Veja dados do agente (deferido, com
número de registro), carta de apresentação, vínculos com a
categoria.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Decida: <strong>Habilitar</strong> (a inscrição vai para a fase
de votação) ou <strong>Inabilitar</strong> com motivo escrito.</p>
</div>

</div>

### Critérios de habilitação

<div class="step-grid">

<div class="step">
<div class="step__num">A</div>
<div class="step__body">
<h4>Cadastro deferido</h4>
<p>O agente precisa ter cadastro com status "Deferido". O sistema
bloqueia inscrições de agentes não-deferidos automaticamente.</p>
</div>
</div>

<div class="step">
<div class="step__num">B</div>
<div class="step__body">
<h4>Tipo compatível</h4>
<p>O tipo do agente (PF/OR/SM) deve estar nos "tipos aceitos" da
categoria.</p>
</div>
</div>

<div class="step">
<div class="step__num">C</div>
<div class="step__body">
<h4>Sem conflito de impedimento</h4>
<p>Conforme o edital, agentes em situações específicas (servidor
do Ibram, vínculo com fornecedor, etc.) podem ser impedidos.</p>
</div>
</div>

<div class="step">
<div class="step__num">D</div>
<div class="step__body">
<h4>Carta de apresentação completa</h4>
<p>Os campos obrigatórios da inscrição (carta, declaração) estão
preenchidos.</p>
</div>
</div>

</div>

### Recursos de inabilitação

Inscrições inabilitadas podem ser contestadas pelo agente. Como o
gestor do edital, você decide esses recursos.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Habilitações — Recursos de inabilitação</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Cada recurso mostra o motivo da inabilitação e a argumentação do
agente.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Decida em até 5 dias úteis: <strong>Habilitar</strong> (reverte
sua decisão original) ou <strong>Manter inabilitação</strong> com
nova fundamentação. Não há segunda instância para esse recurso.</p>
</div>

</div>

### SLAs do gestor de edital

<div class="rights-grid">

<div class="right">
<div class="right__icon">3d</div>
<h4>Antecedência publicação</h4>
<p>3 dias úteis antes da abertura prevista.</p>
</div>

<div class="right">
<div class="right__icon">7d</div>
<h4>Habilitação</h4>
<p>Até 7 dias úteis após encerramento das inscrições.</p>
</div>

<div class="right">
<div class="right__icon">5d</div>
<h4>Recurso inabilitação</h4>
<p>5 dias úteis a partir do protocolo.</p>
</div>

</div>

<div class="pagebreak"></div>

## 7. Apuração de votações (Apurador)

### Visão geral do seu papel

Você garante a **integridade do resultado** das eleições. O sistema
implementa voto secreto com técnicas criptográficas, mas o ato de
apurar e publicar exige um humano responsável — você. Você opera 4
momentos:

<div class="step-grid">

<div class="step">
<div class="step__num">1</div>
<div class="step__body">
<h4>Antes da votação</h4>
<p>Conferir que a votação tem candidatos habilitados, datas
configuradas e elegíveis identificados.</p>
</div>
</div>

<div class="step">
<div class="step__num">2</div>
<div class="step__body">
<h4>Durante a votação</h4>
<p>Acompanhar a participação (números agregados, sem identificar
votos individuais). Não há decisões a tomar — só observar.</p>
</div>
</div>

<div class="step">
<div class="step__num">3</div>
<div class="step__body">
<h4>No encerramento</h4>
<p>Verificar integridade dos votos. Apurar com critério
determinístico. Revisar resultado por categoria.</p>
</div>
</div>

<div class="step">
<div class="step__num">4</div>
<div class="step__body">
<h4>Após apuração</h4>
<p>Publicar resultado. Sistema gera pacote oficial (CSV + ata em PDF)
para auditoria pública.</p>
</div>
</div>

</div>

### Como verificar integridade

Antes de apurar, o sistema permite recalcular um **"selo"
criptográfico** dos votos. Se o selo atual bate com o registrado no
momento do encerramento, está tudo certo. Se diverge, houve adulteração
(ou bug grave) — não apure.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Votações</strong> e clique numa votação com status
"Encerrada".</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Na aba <strong>Auditoria</strong>, clique em <strong>Verificar
integridade</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>O sistema calcula o selo atual e compara com o do encerramento.
Resultado:</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p><strong>"Integridade verificada: hashes idênticos"</strong> ✓ —
pode apurar com segurança.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">5</div>
<p><strong>"ATENÇÃO: hashes divergem"</strong> ✗ — NÃO APURE.
Comunique imediatamente o DPO e o administrador. Pode ser incidente
de segurança.</p>
</div>

</div>

<div class="callout callout-danger">
<strong>Importante.</strong> Se a integridade falhar, não tente
"consertar" ou apurar mesmo assim. Pare imediatamente e abra um
incidente seguindo o procedimento do DPO. O resultado é o último
elemento — proteja a confiança no processo.
</div>

### Apuração

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Com integridade OK, clique em <strong>Apurar</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>O sistema aplica o critério de desempate determinístico:
<strong>mais votos vence</strong>; em empate, <strong>quem se
inscreveu primeiro vence</strong>; em empate persistente, o menor
ID de inscrição.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Tela de revisão mostra eleitos titulares e suplentes por
categoria. Confira que faz sentido (números, ranking).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>Se tudo OK, clique <strong>Publicar resultado</strong>. Sistema
gera o relatório oficial em ZIP (CSV + ata PDF + JSON-LD para
indexação pública).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">5</div>
<p>O resultado fica disponível publicamente em
<em>/transparencia/votacoes</em>. Os eleitos recebem comunicação
oficial automática.</p>
</div>

</div>

<div class="callout callout-success">
<strong>Pronto.</strong> Você acabou de fechar o ciclo democrático
de um processo participativo federal. O ZIP gerado é a evidência
auditável — guarde com cuidado e disponibilize quando solicitado.
</div>

### SLAs do apurador

<div class="rights-grid">

<div class="right">
<div class="right__icon">2d</div>
<h4>Apuração</h4>
<p>Até 2 dias úteis após encerramento.</p>
</div>

<div class="right">
<div class="right__icon">3d</div>
<h4>Publicação</h4>
<p>Até 3 dias úteis após apuração.</p>
</div>

<div class="right">
<div class="right__icon">24h</div>
<h4>Incidente</h4>
<p>Acionar DPO em até 24h se hash divergir.</p>
</div>

</div>

<div class="pagebreak"></div>

## 8. Conformidade LGPD (Encarregado/DPO)

### Visão geral do seu papel

Você é o **encarregado pelo tratamento de dados pessoais** previsto
no Art. 41 da LGPD. Sua função tem 4 frentes:

<div class="role-grid">

<div class="role-card role-card--or">
<div class="role-card__badge">Frente 1</div>
<h3>Solicitações de titulares</h3>
<p>Responder pedidos de acesso, retificação, anonimização ou
portabilidade de dados pessoais em até 15 dias úteis.</p>
</div>

<div class="role-card role-card--or">
<div class="role-card__badge">Frente 2</div>
<h3>Fiscalização interna</h3>
<p>Auditar acessos a PII (dados pessoais identificáveis). Identificar
acessos anômalos ou sem justificativa.</p>
</div>

<div class="role-card role-card--or">
<div class="role-card__badge">Frente 3</div>
<h3>Incidentes</h3>
<p>Notificar a ANPD em até 3 dias úteis se houver vazamento ou acesso
indevido confirmado (Resolução CD/ANPD 15/2024).</p>
</div>

<div class="role-card role-card--or">
<div class="role-card__badge">Frente 4</div>
<h3>Transparência</h3>
<p>Manter atualizados os contatos públicos do encarregado e os
relatórios de impacto disponíveis ao cidadão.</p>
</div>

</div>

### Configurando seu perfil público

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Conformidade & LGPD → Configuração DPO</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Preencha: nome completo, cargo, e-mail funcional, telefone,
horário de atendimento.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Salve. As informações ficam visíveis em <em>/transparencia/dpo</em>
para qualquer cidadão consultar.</p>
</div>

</div>

### Atendendo solicitações

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Conformidade & LGPD → Solicitações de titulares</strong>
diariamente. Cada solicitação tem prazo de <strong>15 dias úteis</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Cada solicitação mostra: tipo (acesso/retificação/anonimização/
portabilidade), data do protocolo, prazo restante, dados do titular.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Analise: o pedido é legítimo? O titular foi corretamente
identificado (passou por reauth)? Há base legal para negar?</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>Decida: <strong>Atender</strong> (sistema executa a ação
automaticamente para os tipos suportados) ou <strong>Negar</strong>
com motivo legal escrito (apenas situações excepcionais).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">5</div>
<p>Em ambos os casos, o titular é notificado por e-mail com a
decisão e os passos seguintes.</p>
</div>

</div>

### Tipos de solicitação e como cada uma funciona

<div class="step-grid">

<div class="step">
<div class="step__num">A</div>
<div class="step__body">
<h4>Acesso (Art. 18, II)</h4>
<p>Titular pede uma cópia de todos os dados que o Ibram tem sobre
ele. Sistema gera um pacote estruturado automaticamente; você
revisa antes de liberar o download.</p>
</div>
</div>

<div class="step">
<div class="step__num">B</div>
<div class="step__body">
<h4>Retificação (Art. 18, III)</h4>
<p>Titular pede correção de dado errado. Você confere os documentos
de prova e aprova ou nega a correção.</p>
</div>
</div>

<div class="step">
<div class="step__num">C</div>
<div class="step__body">
<h4>Anonimização (Art. 18, IV)</h4>
<p>Titular pede que seus dados pessoais sejam apagados. Sistema
substitui campos identificáveis por tokens anônimos, mantendo
estatísticas agregadas. Irreversível.</p>
</div>
</div>

<div class="step">
<div class="step__num">D</div>
<div class="step__body">
<h4>Portabilidade (Art. 18, V)</h4>
<p>Titular pede os dados em formato estruturado (JSON-LD + CSV +
schema). Sistema gera ZIP assinado com TTL de 24h.</p>
</div>
</div>

</div>

<div class="callout callout-warning">
<strong>Cuidado com anonimização.</strong> Uma vez executada, é
irreversível. Confirme que o titular entendeu antes de atender. Se
houver dúvida, ligue para o titular antes de processar.
</div>

### Fiscalizando acessos a PII

Diariamente (ou conforme política institucional), revise os acessos
a dados sensíveis para identificar abusos.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Conformidade & LGPD → Acessos a PII</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Lista mostra cada vez que um servidor visualizou CPF, RG, CNPJ,
endereço ou outro dado pessoal de um agente.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Filtros: por servidor, por data, por agente acessado. Use para
investigar padrões.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>Identifique anomalias: um analista acessando muitos cadastros
fora do horário, acessos repetidos a um mesmo agente sem motivo,
servidor de outra área olhando dados sem necessidade.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">5</div>
<p>Em caso de suspeita, contate o servidor diretamente e/ou abra
processo disciplinar. Registre a investigação em
<strong>Decisões</strong>.</p>
</div>

</div>

### Procedimento de incidente

Se houver vazamento, acesso indevido ou falha de segurança grave:

<div class="timeline">

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Hora zero — Contenção</h4>
<p>Em coordenação com TI, isole o problema (desativar conta
comprometida, restaurar backup, etc.).</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Até 24h — Avaliação</h4>
<p>Determine escopo: quantos titulares afetados? Que dados? Risco
de dano?</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Até 72h — Notificação ANPD</h4>
<p>Resolução CD/ANPD 15/2024 obriga notificação em até 3 dias úteis.
Use o formulário oficial em www.gov.br/anpd.</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot"></div>
<div class="timeline__body">
<h4>Comunicação aos titulares</h4>
<p>Se houver risco aos direitos, comunicar individualmente os
titulares afetados (Art. 48 LGPD).</p>
</div>
</div>

<div class="timeline__item">
<div class="timeline__dot timeline__dot--success"></div>
<div class="timeline__body">
<h4>Relatório final</h4>
<p>Documente o incidente, ações tomadas, medidas preventivas.
Registre em "Decisões" para trilha auditável.</p>
</div>
</div>

</div>

<div class="pagebreak"></div>

## 9. Administração geral

### Configurando SMTP

Para o sistema enviar e-mails (deferimentos, recursos, votações,
LGPD), o SMTP precisa estar funcional.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Ferramentas → E-mail → Configuração SMTP</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Preencha: servidor SMTP, porta (geralmente 587 com TLS),
usuário, senha, endereço de remetente, nome de remetente.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Use o botão <strong>Enviar e-mail de teste</strong> para validar
antes de salvar em produção.</p>
</div>

</div>

### Templates de e-mail

O sistema tem ~20 templates pré-definidos (deferimento, indeferimento,
prazo vencendo, recurso decidido, etc.). Você pode editar o conteúdo
sem mudar a lógica.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Ferramentas → E-mail → Preview de templates</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Selecione um evento no menu. O preview mostra como o e-mail
chegará ao destinatário, com placeholders reais.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Edite o texto se necessário (apenas conteúdo; tags
<code>{nome}</code>, <code>{numero_registro}</code> etc. precisam ser
preservadas).</p>
</div>

</div>

### Site Health (saúde do sistema)

WordPress traz uma ferramenta nativa em <strong>Ferramentas → Saúde
do Site</strong>. O plugin adiciona checks específicos:

<div class="step-grid">

<div class="step">
<div class="step__num">1</div>
<div class="step__body">
<h4>Constantes de criptografia</h4>
<p>Confirma que as 6 chaves criptográficas estão configuradas.</p>
</div>
</div>

<div class="step">
<div class="step__num">2</div>
<div class="step__body">
<h4>Cron jobs ativos</h4>
<p>4 tarefas automáticas devem estar agendadas (fila e-mail, prazos,
limpeza, dados abertos).</p>
</div>
</div>

<div class="step">
<div class="step__num">3</div>
<div class="step__body">
<h4>Diretório privado</h4>
<p>Pasta de uploads privada existe com proteção .htaccess + web.config.</p>
</div>
</div>

<div class="step">
<div class="step__num">4</div>
<div class="step__body">
<h4>DPO configurado</h4>
<p>Nome e e-mail do encarregado estão preenchidos.</p>
</div>
</div>

</div>

### Setup de teste (ambiente de desenvolvimento)

<div class="callout callout-danger">
<strong>NÃO USE EM PRODUÇÃO.</strong> A página "Setup de Teste" cria
9 usuários e dezenas de cadastros, editais, votações fictícios. Em
produção, mantenha essa página inacessível ou apague depois do uso.
</div>

Em ambientes de dev/homologação:

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Ferramentas → Setup de teste</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>"Criar 9 usuários de teste" — gera um para cada perfil. Senhas
mostradas na página (anote).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>"Popular dados de teste" — cria cenário realista: 8 agentes em
estados variados, 3 editais (inscrições, votação, encerrado), 25
inscrições, 40 votos, 21 eventos de auditoria.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>"Remover dados de teste" — limpa tudo. Confirma digitando
"CONFIRMAR".</p>
</div>

</div>

<div class="pagebreak"></div>

## 10. Auditoria e indicadores

### Log de eventos

Todas as ações sensíveis são registradas. Use para investigar
incidentes ou comprovar processos perante a auditoria interna.

<div class="workflow">

<div class="workflow__step">
<div class="workflow__step-num">1</div>
<p>Acesse <strong>Conformidade & LGPD → Log de eventos</strong>.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">2</div>
<p>Filtros: por entidade (cadastro, edital, votação...), por ação
(criar, atualizar, deferir, indeferir, exportar), por data, por ID do
servidor.</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">3</div>
<p>Cada linha mostra: data/hora, ator (mascarado), ação, alvo, IP em
hash (não-reversível).</p>
</div>

<div class="workflow__step">
<div class="workflow__step-num">4</div>
<p>Clique em uma linha para ver o detalhe completo (dados antes/
depois da ação, em formato técnico).</p>
</div>

</div>

### Decisões administrativas

Subconjunto do log com decisões formais (deferimentos, indeferimentos,
recursos). Útil para relatórios oficiais.

### Indicadores no painel

O Painel principal mostra contadores em tempo real (atualizados a cada
visita). Use como dashboard executivo.

<div class="callout callout-info">
<strong>Sobre exportações.</strong> Cada lista de auditoria pode ser
exportada em CSV. A exportação é registrada como ação auditável —
quem exportou, quando, o que.
</div>

<div class="pagebreak"></div>

## 11. Boas práticas e SLAs

### Frequência recomendada

<div class="rights-grid">

<div class="right">
<div class="right__icon">D</div>
<h4>Diário</h4>
<p>Fila de análise (analista), Solicitações LGPD (DPO).</p>
</div>

<div class="right">
<div class="right__icon">S</div>
<h4>Semanal</h4>
<p>Recursos pendentes, Acessos a PII (DPO), Fila de e-mail.</p>
</div>

<div class="right">
<div class="right__icon">M</div>
<h4>Mensal</h4>
<p>Relatório executivo, conferência de cron jobs, exportação de
estatísticas.</p>
</div>

</div>

### Comunicação com agentes

<div class="callout callout-tip">
<strong>Linguagem clara.</strong> Em fundamentações de indeferimento
ou inabilitação, escreva em linguagem simples. Cite o documento
faltante específico ("RG ilegível", não "documentação inadequada").
</div>

<div class="callout callout-tip">
<strong>Tom respeitoso.</strong> Os agentes culturais são parceiros do
Ibram, não adversários. Mesmo em uma negativa, mantenha o tom
respeitoso e ofereça caminhos (recurso, reenvio, contato).
</div>

### Sigilo profissional

<div class="callout callout-danger">
<strong>NUNCA compartilhe dados pessoais</strong> de agentes em
WhatsApp, e-mail pessoal, planilhas locais ou outros canais. Use
sempre a plataforma. Qualquer compartilhamento externo configura
incidente LGPD.
</div>

### Documentação de decisões

Toda decisão administrativa (deferimento, indeferimento, recurso,
habilitação, inabilitação) precisa de **fundamentação escrita**. O
sistema obriga isso em campos próprios — não pule.

<div class="pagebreak"></div>

## 12. Resolução de problemas

<div class="faq">

<div class="faq__item">
<h4>Um agente diz que enviou o cadastro mas não recebeu e-mail de confirmação</h4>
<p>Cheque <strong>Ferramentas → E-mail → Fila pendente</strong>. Se
houver mensagens não enviadas há mais de 1 hora, o SMTP pode estar
com problema. Cheque também a aba "Logs" para erros de envio.</p>
</div>

<div class="faq__item">
<h4>O número de registro foi gerado mas o agente não consegue se inscrever em edital</h4>
<p>Cheque: o tipo do agente (PF/OR/SM) é aceito pela categoria do
edital? O edital está no período de inscrições aberto? O cadastro
do agente tem status "Deferido" (não "Em análise")?</p>
</div>

<div class="faq__item">
<h4>Hash de integridade da votação diverge</h4>
<p>Pare imediatamente. Não apure. Documente o que viu, contate o DPO
e o administrador. Pode ser bug, mas trate como incidente de
segurança até confirmação contrária.</p>
</div>

<div class="faq__item">
<h4>Agente reclama de demora na análise</h4>
<p>Verifique no painel quantos cadastros estão na fila e quantos
estão em análise. Se a fila está grande, considere distribuir entre
analistas ou solicitar reforço temporário.</p>
</div>

<div class="faq__item">
<h4>Solicitação LGPD está vencendo o prazo e o titular não responde</h4>
<p>O prazo de 15 dias úteis é improrrogável (Art. 19 LGPD). Se o
titular não fornece informação adicional para identificação, atenda
ao que conseguir documentar e responda formalmente explicando a
limitação.</p>
</div>

<div class="faq__item">
<h4>Indeferimento foi questionado judicialmente</h4>
<p>Contate imediatamente a Procuradoria Federal junto ao Ibram. Não
edite ou apague registros de auditoria — o sistema impede isso, mas a
trilha existente é prova material.</p>
</div>

<div class="faq__item">
<h4>Servidor de outra área pede acesso para "ajudar"</h4>
<p>Negue. Acessos só via solicitação formal ao administrador, com
justificativa documentada e papel oficial. Compartilhar credenciais
é falta funcional.</p>
</div>

<div class="faq__item">
<h4>O painel mostra alertas em vermelho que eu não entendo</h4>
<p>Acesse <strong>Ferramentas → Saúde do Site</strong> para detalhes.
Se persistir, contate o administrador do sistema. Não ignore alertas —
podem indicar falha de criptografia ou cron parado.</p>
</div>

</div>

<div class="pagebreak"></div>

## 13. Termos importantes

<div class="glossary">

<div class="glossary__item">
<dt>CNAC</dt>
<dd>Cadastro Nacional de Agentes Culturais. A base de dados que o
sistema mantém.</dd>
</div>

<div class="glossary__item">
<dt>Deferimento / Indeferimento</dt>
<dd>Decisão administrativa de aprovar (deferir) ou recusar
(indeferir) um cadastro ou recurso.</dd>
</div>

<div class="glossary__item">
<dt>Recurso de retratação</dt>
<dd>Primeira instância de recurso. O analista que indeferiu pode
reverter sua decisão.</dd>
</div>

<div class="glossary__item">
<dt>Recurso de presidência</dt>
<dd>Segunda instância. Quando o analista mantém o indeferimento, o
caso sobe à Presidência.</dd>
</div>

<div class="glossary__item">
<dt>Número de registro</dt>
<dd>Identificador único do cadastro deferido, formato
PI-TIPO-ANO-SEQ06.</dd>
</div>

<div class="glossary__item">
<dt>Habilitação</dt>
<dd>Aceitação formal de uma inscrição em edital. Só inscrições
habilitadas concorrem na votação.</dd>
</div>

<div class="glossary__item">
<dt>Categoria</dt>
<dd>Vaga ou cargo dentro de um edital. Cada categoria tem critérios e
número de vagas titulares + suplentes.</dd>
</div>

<div class="glossary__item">
<dt>Apuração</dt>
<dd>Contagem oficial dos votos após o encerramento. Aplica critério
de desempate determinístico.</dd>
</div>

<div class="glossary__item">
<dt>Hash de integridade</dt>
<dd>"Selo" criptográfico dos votos. Permite verificar que ninguém
adulterou nada entre o encerramento e a apuração.</dd>
</div>

<div class="glossary__item">
<dt>Anti-rastreio voto-eleitor</dt>
<dd>Técnica criptográfica que impede saber em quem cada eleitor
votou — mesmo para quem tem acesso ao banco de dados.</dd>
</div>

<div class="glossary__item">
<dt>PII</dt>
<dd>Personally Identifiable Information — dados pessoais que
identificam uma pessoa (CPF, RG, endereço, telefone, etc.).</dd>
</div>

<div class="glossary__item">
<dt>Anonimização</dt>
<dd>Apagamento de dados pessoais mantendo estatísticas agregadas.
Diferente de exclusão total — preserva o histórico institucional.</dd>
</div>

<div class="glossary__item">
<dt>Portabilidade</dt>
<dd>Direito do titular de receber seus dados em formato
estruturado para uso em outro sistema (Art. 18 V LGPD).</dd>
</div>

<div class="glossary__item">
<dt>Consentimento granular</dt>
<dd>Cada finalidade de uso dos dados é aceita ou recusada
separadamente — não é "tudo ou nada".</dd>
</div>

<div class="glossary__item">
<dt>SLA</dt>
<dd>Service Level Agreement — prazo de resposta acordado para um
tipo de demanda.</dd>
</div>

<div class="glossary__item">
<dt>Reauth</dt>
<dd>Re-autenticação. O sistema pede senha novamente antes de ações
sensíveis (anonimização, portabilidade), mesmo se você já está logado.</dd>
</div>

<div class="glossary__item">
<dt>ANPD</dt>
<dd>Autoridade Nacional de Proteção de Dados. Órgão regulador da
LGPD. Recebe notificações de incidente.</dd>
</div>

<div class="glossary__item">
<dt>Trilha de auditoria</dt>
<dd>Registro imutável de todas as ações sensíveis. Cada ato fica
gravado com ator, data, alvo. Não é possível apagar.</dd>
</div>

</div>

<div class="pagebreak"></div>

## Contatos importantes

<div class="contact-grid">

<div class="contact-card">
<h4>Coordenação da sua área</h4>
<p>Para questões operacionais do dia a dia, dúvidas sobre decisões
em casos complexos.</p>
<p class="contact-card__note">Use o canal interno do Ibram.</p>
</div>

<div class="contact-card">
<h4>Administrador do sistema</h4>
<p>Para criar/desativar usuários, mudar permissões, configurar SMTP
ou investigar falha técnica.</p>
<p class="contact-card__note">Contato via TI do Ibram.</p>
</div>

<div class="contact-card">
<h4>Encarregado (DPO)</h4>
<p>Para questões sobre dados pessoais, incidentes, solicitações de
titulares.</p>
<p class="contact-card__note">Contato disponível em
<em>/transparencia/dpo</em>.</p>
</div>

<div class="contact-card">
<h4>Procuradoria Federal — Ibram</h4>
<p>Para questionamentos judiciais sobre decisões administrativas,
revisão de processos.</p>
<p class="contact-card__note">Use o canal jurídico interno.</p>
</div>

<div class="contact-card">
<h4>ANPD</h4>
<p>Autoridade Nacional de Proteção de Dados. Notificação obrigatória
de incidentes graves.</p>
<p class="contact-card__note"><strong>www.gov.br/anpd</strong></p>
</div>

<div class="contact-card">
<h4>Repositório técnico</h4>
<p>Para dúvidas sobre versão atual, novidades, ou reportar bugs.</p>
<p class="contact-card__note"><strong>github.com/marcossigismundo/participe-ibram</strong></p>
</div>

</div>

---

<div class="footer-note">
Manual do Gestor — versão 1.0, elaborado para a versão 0.1 do
Participe Ibram. Linguagem operacional, sem termos de
desenvolvimento. Para a versão técnica completa, consulte
MANUAL.md no repositório. Conteúdo licenciado em GPL-2.0-or-later.
</div>
