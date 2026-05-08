# R1 — Pesquisa: gov.br Design System (DSGov) para o plugin Participe Ibram

> **Escopo:** subsidios praticos para refatorar o plugin WordPress `crm-developer` em direcao a plataforma federal **Participe Ibram** (`cadastro.museus.gov.br`), aderente ao Padrao Digital de Governo (gov.br/ds).
> **Data da pesquisa:** maio/2026.
> **Autor:** Agente R1 (pesquisa de design system).
>
> **NOTA SOBRE O CAMINHO DESTE ARQUIVO:** o caminho originalmente solicitado
> (`C:\Users\marcos.sigismundo\.claude\projects\c--xampp82-...\refactor-spec\research\R1-dsgov.md`)
> esta bloqueado para Write neste ambiente sandbox. Este arquivo foi gravado em
> `c:\tmp\R1-dsgov.md` para permitir entrega; mover manualmente para o destino
> final ou ajustar permissoes.

---

## 1. Resumo executivo

1. O **gov.br Design System (DSGov / GOVBR-DS)** e o padrao digital federal brasileiro mantido pelo SERPRO em <https://www.gov.br/ds>. Em 2026 ele convive em **duas faixas**: a **versao 3.x estavel** (`@govbr-ds/core` 3.7.0, recomendada para producao) e a **versao 4 (beta)** publicada em `next-ds.estaleiro.serpro.gov.br`. Para o Participe Ibram a recomendacao e fixar **v3.7.x agora**, com plano de migracao para v4 quando sair do beta.
2. O DSGov e **agnostico de framework**: distribui CSS+JS puros (`@govbr-ds/core`) e wrappers para React/Vue/Angular. Para um plugin WordPress (PHP+jQuery, sem build obrigatorio), a integracao natural e **CSS+JS via npm self-host** com fallback CDN (jsDelivr).
3. **Tokens essenciais** seguem nomenclatura tipo USWDS (`--blue-warm-vivid-70`, `--gray-2`, etc.) expostos como **CSS custom properties**. Tipografia oficial: **Rawline** (corpo) + **Raleway** (fallback) + Font Awesome 6 para icones.
4. **Wizard / multi-step**: o DSGov possui o componente `Step` (`br-step`), porem ele e mais um *step indicator* visual que um wizard completo. A recomendacao e **construir um wizard customizado** seguindo o pattern WAI-ARIA (`aria-current="step"` + `<nav aria-label="Progresso do cadastro">`), reutilizando as classes visuais `br-step` do DSGov para coerencia.
5. **Modais explicativos contextuais (icone "?"):** usar o `br-modal` do DSGov como container, mas seguir o pattern WAI-ARIA Dialog (`role="dialog"`, `aria-modal="true"`, `aria-labelledby`, focus trap, ESC). Para help inline curto, preferir `br-tooltip`/popover sobre modal.
6. **Integracao com WordPress:** `wp_enqueue_style` / `wp_enqueue_script` somente nas paginas do plugin (front-end e admin do plugin), **nao** no admin global, com handles prefixados (`participe-ibram-*`). Self-host dos assets em `assets/vendor/govbr-ds/` (sem CDN externa para nao quebrar em redes governamentais com firewall restritivo).
7. **Acessibilidade:** o DSGov e construido sobre WCAG 2.1 AA e segue eMAG. Os componentes ja trazem ARIA correto, mas e responsabilidade do desenvolvedor instanciar os scripts (`new core.BRInput(...)`, `new core.BRModal(...)`, etc.) para ativar comportamento acessivel dinamico.
8. **Barra gov.br** (`barra.brasil.gov.br`) e **obrigatoria por lei** (Instrucao Normativa nº 8/2014 da SECOM) em sites do Executivo Federal — deve ser carregada antes do `</body>` e e independente do DSGov.

---

## 2. Versionamento e formas de obter o DSGov

### 2.1 Versoes em 2026

| Faixa | Pacote npm | Versao | Status | Quando usar |
|------|------------|--------|--------|-------------|
| **v3 estavel** | `@govbr-ds/core` | **3.7.0** | Producao | **Recomendado para Participe Ibram MVP** |
| v4 beta | (publicado em `next-ds.estaleiro.serpro.gov.br`) | 4.x beta | Beta | Acompanhar; migrar quando estavel |
| Wrappers oficiais | `@govbr-ds/webcomponents`, `@govbr-ds/react-components`, `@govbr-ds/webcomponents-vue`, `@govbr-ds/webcomponents-angular`, `@govbr-ds/utilities` | varia | Estaveis | N/A para WP |
| Comunidade | `tesouro/react-dsgov` (33+ componentes), `oluizcarvalho/govbr-ds-angular`, `helder-nicollas/govbr-react-components` | varia | Nao-oficiais | Apenas referencia |

**Ponto critico:** a v4 muda nomenclatura de tokens e adiciona novo design-token formal (`fundamentos/design-token`). Se o time quiser ficar mais proximo do "futuro", desenhe o CSS do plugin **isolando os tokens em um unico arquivo** (`_tokens.scss`) para facilitar a troca.

### 2.2 Formas de instalacao

#### Opcao A — npm + bundle local (recomendada)

```bash
npm install @govbr-ds/core@3.7.0
```

O pacote distribui:

```
node_modules/@govbr-ds/core/dist/
├── core.css         (CSS bundle completo)
├── core.min.css
├── core.js          (bundle JS — exporta `core.BRInput`, `core.BRModal`, etc.)
├── core.min.js
└── ...
```

No projeto WordPress, copiar ou referenciar:

```
plugins/participe-ibram/assets/vendor/govbr-ds/core.min.css
plugins/participe-ibram/assets/vendor/govbr-ds/core.min.js
plugins/participe-ibram/assets/vendor/fontawesome-6/...
plugins/participe-ibram/assets/vendor/rawline/...    (webfont)
```

Justificativa do self-host: ambientes governamentais frequentemente tem **firewall com whitelist**; depender de `cdn.jsdelivr.net` ou `unpkg.com` quebra o sistema em orgaos parceiros.

#### Opcao B — CDN (apenas para prototipos / dev)

```html
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@govbr-ds/core@3.7.0/dist/core.min.css">
<script src="https://cdn.jsdelivr.net/npm/@govbr-ds/core@3.7.0/dist/core.min.js"></script>
```

Nao usar em producao do Participe Ibram (cadastro.museus.gov.br precisa ser auto-suficiente).

#### Opcao C — SCSS source (custom build)

Permite recompilar com tokens customizados. Possui `@import` para parciais como `_colors.scss`, `_spacing.scss`, etc. Indicado se for haver tema "Ibram" sobre o DSGov base. Para o MVP, **nao recomendo** — adiciona pipeline de build sem ganho proporcional.

#### Dependencias externas obrigatorias

- **Font Awesome 6** (Free): icones referenciados nos componentes (`<i class="fas fa-user"></i>`).
- **Rawline** (font oficial): <https://font.download/font/rawline> ou Raleway do Google Fonts como fallback.
- **Raleway** (Google Fonts): <https://fonts.google.com/specimen/Raleway>.

---

## 3. Tokens DSGov essenciais (v3.7)

> **Aviso de fonte:** as paginas oficiais (`gov.br/ds/fundamentos/*`) sao SPA e nao respondem a fetch sem JS — os valores abaixo foram **agregados a partir de:** (i) documentacao textual indexada do DSGov, (ii) pacote npm `@govbr-ds/core` 3.7.0, (iii) UI Kit Figma oficial v3.5.1 (<https://www.figma.com/community/file/1398351127929377953>), (iv) wrapper React `tesouro/react-dsgov` que expoe os tokens como CSS vars. Antes de gravar valores em codigo, **validar contra o `core.css` real** baixado de `node_modules/@govbr-ds/core/dist/core.css`.

### 3.1 Cores institucionais (paleta principal)

| Token CSS | Valor (hex) | Uso |
|-----------|-------------|-----|
| `--blue-warm-vivid-70` | `#1351B4` | **Cor primaria** (links, botoes primarios, header) |
| `--blue-warm-vivid-80` | `#0C326F` | Hover de primaria, header escuro |
| `--blue-warm-vivid-90` | `#071D41` | Texto sobre fundo claro destacado |
| `--blue-warm-vivid-60` | `#2670E8` | Estado focus/hover suave |
| `--blue-warm-20` | `#C5D4EB` | Fundo informativo claro |
| `--green-cool-vivid-50` | `#168821` | Sucesso |
| `--yellow-vivid-20` | `#FFCD07` | Aviso (cor da bandeira) |
| `--orange-vivid-40` | `#E6741B` | Aviso forte |
| `--red-vivid-50` | `#E52207` | Erro / danger |
| `--gray-90` | `#1B1B1B` | Texto principal |
| `--gray-80` | `#333333` | Texto secundario |
| `--gray-60` | `#636363` | Texto auxiliar / placeholder |
| `--gray-40` | `#9E9E9E` | Bordas |
| `--gray-20` | `#CCCCCC` | Fundos sutis / divisorias |
| `--gray-10` | `#E6E6E6` | Fundo input desabilitado |
| `--gray-2` | `#F8F8F8` | Background da pagina |
| `--pure-0` | `#FFFFFF` | Branco puro |
| `--pure-100` | `#000000` | Preto puro |

**Cores institucionais semanticas:**

```css
:root {
  --interactive: var(--blue-warm-vivid-70);          /* #1351B4 */
  --interactive-light: var(--blue-warm-20);
  --interactive-dark: var(--blue-warm-vivid-90);
  --visited: var(--violet-warm-vivid-70);
  --hover: rgba(0, 0, 0, 0.08);
  --pressed: rgba(0, 0, 0, 0.16);
  --focus-color: var(--blue-warm-vivid-70);
  --focus-color-light: var(--blue-warm-vivid-30);
  --focus-style: dashed;
  --focus-width: 4px;
  --focus-offset: 0;
}
```

### 3.2 Tipografia

| Token | Valor | Aplicacao |
|-------|-------|-----------|
| `--font-family-base` | `"Rawline", "Raleway", sans-serif` | Texto geral |
| `--font-family-secondary` | `"Raleway", sans-serif` | Titulos (em alguns layouts) |
| `--font-size-scale-base` | `100%` | Base — **nao alterar** (afeta acessibilidade) |
| `--font-size-scale-up-01` | `87.5%` (~14px) | Texto pequeno |
| `--font-size-scale-up-02` | `100%` (16px) | **Body** |
| `--font-size-scale-up-03` | `112.5%` (~18px) | Lead |
| `--font-size-scale-up-04` | `125%` (20px) | h6 |
| `--font-size-scale-up-05` | `137.5%` (~22px) | h5 |
| `--font-size-scale-up-06` | `150%` (24px) | h4 |
| `--font-size-scale-up-07` | `175%` (28px) | h3 |
| `--font-size-scale-up-08` | `200%` (32px) | h2 |
| `--font-size-scale-up-09` | `225%` (36px) | h1 |
| `--font-weight-light` | `300` | |
| `--font-weight-regular` | `400` | |
| `--font-weight-semi-bold` | `600` | |
| `--font-weight-bold` | `700` | |
| `--font-line-height-low` | `1.15` | Titulos |
| `--font-line-height-medium` | `1.45` | UI |
| `--font-line-height-high` | `1.65` | Texto longo |

Importacao dos webfonts:

```css
@font-face {
  font-family: 'Rawline';
  src: url('../vendor/rawline/rawline-400.woff2') format('woff2');
  font-weight: 400;
  font-display: swap;
}
/* idem para 300, 500, 600, 700 */
```

### 3.3 Espacamento (escala de 4px / "spacing-scale")

| Token | Valor | Equivalente px |
|-------|-------|----------------|
| `--spacing-scale-half` | `0.25rem` | 4px |
| `--spacing-scale-base` | `0.5rem` | 8px |
| `--spacing-scale-1x` | `0.5rem` | 8px |
| `--spacing-scale-2x` | `1rem` | 16px |
| `--spacing-scale-3x` | `1.5rem` | 24px |
| `--spacing-scale-4x` | `2rem` | 32px |
| `--spacing-scale-5x` | `2.5rem` | 40px |
| `--spacing-scale-6x` | `3rem` | 48px |
| `--spacing-scale-7x` | `3.5rem` | 56px |
| `--spacing-scale-8x` | `4rem` | 64px |
| `--spacing-scale-9x` | `4.5rem` | 72px |
| `--spacing-scale-10x` | `5rem` | 80px |

### 3.4 Breakpoints

| Token | Valor | Dispositivo |
|-------|-------|-------------|
| `--breakpoint-xs` | `0` | Mobile (default) |
| `--breakpoint-sm` | `576px` | Mobile landscape |
| `--breakpoint-md` | `992px` | Tablet |
| `--breakpoint-lg` | `1280px` | Desktop |
| `--breakpoint-xl` | `1600px` | Desktop grande |

**Importante:** o DSGov usa **mobile-first** com breakpoints ligeiramente diferentes do Bootstrap (e.g., `md=992px` em vez de `768px`). Wizards e cadastros precisam ser testados especificamente em 768px–991px.

### 3.5 Outros tokens

| Token | Valor |
|-------|-------|
| `--surface-rounder-sm` | `4px` |
| `--surface-rounder-md` | `8px` |
| `--surface-rounder-lg` | `16px` |
| `--surface-rounder-pill` | `100em` |
| `--surface-shadow-sm` | `0 1px 4px rgba(0,0,0,0.16)` |
| `--surface-shadow-md` | `0 3px 6px rgba(0,0,0,0.16)` |
| `--surface-shadow-lg` | `0 6px 12px rgba(0,0,0,0.16)` |
| `--z-index-layer-1` | `100` |
| `--z-index-layer-2` | `200` |
| `--z-index-layer-3` | `300` |
| `--z-index-layer-4` | `400` (modais) |

---

## 4. Componentes prioritarios para Participe Ibram

> Todos os exemplos abaixo usam classes do `@govbr-ds/core@3.7.x`. Para ativar comportamento JS de cada um, e necessario **instanciar** apos o DOM carregar:
>
> ```js
> document.addEventListener('DOMContentLoaded', () => {
>   for (const el of document.querySelectorAll('.br-input'))   new core.BRInput('.br-input', el);
>   for (const el of document.querySelectorAll('.br-select'))  new core.BRSelect('.br-select', el);
>   for (const el of document.querySelectorAll('.br-modal'))   new core.BRModal('.br-modal', el);
>   for (const el of document.querySelectorAll('.br-message')) new core.BRAlert('.br-message', el);
>   for (const el of document.querySelectorAll('.br-tooltip')) new core.BRTooltip('.br-tooltip', el);
>   for (const el of document.querySelectorAll('.br-upload'))  new core.BRUpload('.br-upload', el);
>   for (const el of document.querySelectorAll('.br-step'))    new core.BRStep('.br-step', el);
>   for (const el of document.querySelectorAll('.br-table'))   new core.BRTable('.br-table', el);
> });
> ```

### 4.1 Input (`br-input`)

```html
<div class="br-input">
  <label for="nome-completo">Nome completo</label>
  <div class="input-group">
    <div class="input-icon">
      <i class="fas fa-user" aria-hidden="true"></i>
    </div>
    <input id="nome-completo"
           type="text"
           required
           aria-required="true"
           aria-describedby="nome-completo-help"
           placeholder="Insira seu nome completo">
  </div>
  <span id="nome-completo-help" class="feedback">
    Como aparece no seu documento de identificacao.
  </span>
</div>
```

Variantes: `.br-input.input-button` (com botao a direita), `.br-input.danger` (erro), `.br-input.success` (valido), `.br-input.warning`.

Estados de erro (use junto com `aria-invalid`):

```html
<div class="br-input danger">
  <label for="cpf">CPF</label>
  <input id="cpf" type="text" aria-invalid="true" aria-describedby="cpf-error">
  <span id="cpf-error" class="feedback danger" role="alert">
    <i class="fas fa-times-circle" aria-hidden="true"></i>
    CPF invalido.
  </span>
</div>
```

### 4.2 Select (`br-select`)

```html
<div class="br-select">
  <div class="br-input">
    <label for="estado">Estado</label>
    <input id="estado" type="text" placeholder="Selecione o estado">
    <button class="br-button" type="button" aria-label="Exibir lista" tabindex="-1" data-trigger="data-trigger">
      <i class="fas fa-angle-down" aria-hidden="true"></i>
    </button>
  </div>
  <div class="br-list" tabindex="0" role="listbox">
    <div class="br-item" tabindex="-1" role="option">
      <div class="br-radio"><input type="radio" name="estado" id="estado-sp" value="SP">
        <label for="estado-sp">Sao Paulo</label></div>
    </div>
    <!-- ... mais opcoes ... -->
  </div>
</div>
```

### 4.3 Checkbox e Radio

```html
<div class="br-checkbox">
  <input id="lgpd-consent" type="checkbox" required aria-required="true">
  <label for="lgpd-consent">
    Li e concordo com a <a href="/lgpd">Politica de Privacidade (LGPD)</a>.
  </label>
</div>

<fieldset>
  <legend>Tipo de pessoa</legend>
  <div class="br-radio">
    <input id="tipo-pf" type="radio" name="tipo" value="PF" checked>
    <label for="tipo-pf">Pessoa fisica</label>
  </div>
  <div class="br-radio">
    <input id="tipo-pj" type="radio" name="tipo" value="PJ">
    <label for="tipo-pj">Pessoa juridica</label>
  </div>
</fieldset>
```

### 4.4 Button (`br-button`)

```html
<!-- Primario (acao principal) -->
<button class="br-button primary" type="submit">
  Enviar cadastro
</button>

<!-- Secundario -->
<button class="br-button secondary" type="button">
  Salvar rascunho
</button>

<!-- Terciario (texto link) -->
<button class="br-button" type="button">
  Cancelar
</button>

<!-- Botao de perigo -->
<button class="br-button danger" type="button">
  Excluir conta
</button>

<!-- Bloco em mobile, auto a partir de sm -->
<button class="br-button primary block auto-sm" type="submit">
  Proximo passo
</button>

<!-- Com icone -->
<button class="br-button primary" type="button">
  <i class="fas fa-download" aria-hidden="true"></i>
  Baixar comprovante
</button>

<!-- Loading -->
<button class="br-button primary loading" type="button" aria-busy="true">
  Enviando...
</button>
```

Modificadores: `.primary | .secondary | .danger | .success`, `.block` (largura 100%), `.auto-sm` (auto a partir de sm), `.small | .large`, `.circle` (apenas icone).

### 4.5 Alert / Message (`br-message`)

```html
<div class="br-message info" role="alert">
  <div class="icon">
    <i class="fas fa-info-circle" aria-hidden="true"></i>
  </div>
  <div class="content">
    <span class="message-title">Rascunho salvo automaticamente</span>
    <span class="message-body">
      Suas respostas foram salvas em <time datetime="2026-05-06T14:32">14:32</time>.
    </span>
  </div>
  <div class="close">
    <button class="br-button circle small" type="button" aria-label="Fechar mensagem">
      <i class="fas fa-times" aria-hidden="true"></i>
    </button>
  </div>
</div>
```

Variantes: `.info | .success | .warning | .danger`.

### 4.6 Modal (`br-modal`) — base para help contextual

Ver secao 6 para o pattern completo com focus trap.

```html
<button class="br-button primary"
        data-toggle="br-modal"
        data-target="#modal-help-cpf">
  <i class="fas fa-question-circle" aria-hidden="true"></i>
  <span class="sr-only">Ajuda sobre CPF</span>
</button>

<div class="br-modal" id="modal-help-cpf"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modal-help-cpf-title"
     aria-describedby="modal-help-cpf-body"
     hidden>
  <div class="br-modal-header">
    <div class="br-modal-title" id="modal-help-cpf-title">
      Por que pedimos seu CPF?
    </div>
  </div>
  <div class="br-modal-body" id="modal-help-cpf-body">
    <p>O CPF e utilizado exclusivamente para identificacao do agente
       cadastrado e para validacao contra a Receita Federal.
       Nao compartilhamos esse dado com terceiros (LGPD art. 7º, II).</p>
  </div>
  <div class="br-modal-footer justify-content-end">
    <button class="br-button secondary small" type="button"
            data-dismiss="br-modal">Fechar</button>
  </div>
</div>
```

### 4.7 Wizard / Step (`br-step`)

Ver secao 5 para o pattern completo com a11y. HTML minimo do indicador visual:

```html
<div class="br-step" data-initial="1" data-type="simple">
  <div class="step-progress">
    <button class="step-progress-btn" type="button" data-step="1" aria-current="step">
      <span class="step-info">Dados pessoais</span>
    </button>
    <button class="step-progress-btn" type="button" data-step="2">
      <span class="step-info">Endereco</span>
    </button>
    <button class="step-progress-btn" type="button" data-step="3">
      <span class="step-info">Documentos</span>
    </button>
    <button class="step-progress-btn" type="button" data-step="4">
      <span class="step-info">Revisao</span>
    </button>
  </div>
</div>
```

### 4.8 Breadcrumb (`br-breadcrumb`)

```html
<nav class="br-breadcrumb" aria-label="Voce esta em">
  <ol class="crumb-list">
    <li class="crumb home">
      <a href="/" aria-label="Inicio">
        <i class="icon fas fa-home" aria-hidden="true"></i>
      </a>
    </li>
    <li class="crumb">
      <i class="icon fas fa-chevron-right" aria-hidden="true"></i>
      <a href="/cadastro">Cadastro de agentes</a>
    </li>
    <li class="crumb" data-active="active">
      <i class="icon fas fa-chevron-right" aria-hidden="true"></i>
      <span aria-current="page">Novo cadastro</span>
    </li>
  </ol>
</nav>
```

### 4.9 Header (`br-header`) — esqueleto institucional

```html
<header class="br-header" id="header" data-sticky="data-sticky">
  <div class="container-lg">
    <div class="header-top">
      <div class="header-logo">
        <img src="/wp-content/plugins/participe-ibram/assets/img/ibram.png"
             alt="Instituto Brasileiro de Museus — IBRAM">
        <span class="br-divider vertical mx-half mx-sm-1"></span>
        <div class="header-sign">Participe Ibram</div>
      </div>
      <div class="header-actions">
        <div class="header-functions"></div>
        <div class="header-login">
          <button class="br-sign-in primary small" type="button" id="login-govbr">
            <i class="fas fa-user" aria-hidden="true"></i>
            <span class="d-sm-inline">Entrar com gov.br</span>
          </button>
        </div>
      </div>
    </div>
    <div class="header-bottom">
      <div class="header-menu">
        <div class="header-menu-trigger">
          <button class="br-button small circle" type="button"
                  aria-label="Menu" data-toggle="menu" data-target="#main-nav">
            <i class="fas fa-bars" aria-hidden="true"></i>
          </button>
        </div>
        <div class="header-info">
          <div class="header-title">Plataforma de participacao social</div>
          <div class="header-subtitle">Cadastro de agentes, editais e votacao eletronica</div>
        </div>
      </div>
    </div>
  </div>
</header>
```

### 4.10 Footer (`br-footer`)

```html
<footer class="br-footer" data-without-social>
  <div class="container-lg">
    <div class="logo">
      <img src="/wp-content/plugins/participe-ibram/assets/img/ibram.png"
           alt="Instituto Brasileiro de Museus">
    </div>
  </div>
</footer>
```

### 4.11 File upload (`br-upload`)

```html
<div class="br-upload">
  <button class="upload-button" type="button" aria-controls="upload-input-1">
    <i class="fas fa-upload" aria-hidden="true"></i>
    <span>Selecione arquivos (PDF, JPG, PNG, max. 10MB)</span>
  </button>
  <input id="upload-input-1" type="file" name="documentos[]"
         multiple accept=".pdf,.jpg,.jpeg,.png">
  <div class="upload-list"></div>
</div>
```

### 4.12 Card (`br-card`)

```html
<article class="br-card" tabindex="0">
  <div class="card-header">
    <div class="d-flex align-items-center">
      <div class="ml-3">
        <div class="text-weight-semi-bold text-up-02">Edital nº 03/2026</div>
        <div class="text-base">Pontos de Memoria</div>
      </div>
    </div>
  </div>
  <div class="card-content">
    Apoio a iniciativas de memoria social e museologia comunitaria.
  </div>
  <div class="card-footer">
    <div class="d-flex">
      <button class="br-button primary small">Ver detalhes</button>
      <button class="br-button secondary small ml-2">Inscrever-se</button>
    </div>
  </div>
</article>
```

### 4.13 Table (`br-table`)

```html
<div class="br-table" data-search="data-search" data-selection="data-selection">
  <table>
    <caption>Inscricoes no edital nº 03/2026</caption>
    <thead>
      <tr>
        <th scope="col">Protocolo</th>
        <th scope="col">Agente</th>
        <th scope="col">Estado</th>
        <th scope="col">Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>2026-0001</td>
        <td>Casa de Cultura X</td>
        <td>RJ</td>
        <td><span class="br-tag info">Em analise</span></td>
      </tr>
    </tbody>
  </table>
</div>
```

---

## 5. Pattern de wizard / multi-step recomendado

### 5.1 Decisao arquitetural

| Opcao | Pros | Contras | Veredito |
|------|------|---------|----------|
| (A) Usar **so `br-step` do DSGov** | Visual identico ao padrao | E so step indicator; nao gerencia estados/validacao | Insuficiente |
| (B) **Wizard customizado puro WAI-ARIA** + classes visuais DSGov | Controle total, validacao por passo, autosave | Mais codigo JS | **RECOMENDADO** |
| (C) Plugin externo (jQuery Steps, SmartWizard) | Pronto | Conflito visual com DSGov, manutencao/seguranca | Evitar |

### 5.2 Pattern WAI-ARIA: `aria-current="step"`, NAO `role="tablist"`

**Decisao de a11y critica:** o W3C ARIA APG **nao tem padrao unico de "wizard"**. Ha duas interpretacoes:

1. **Wizard como tablist** (`role="tablist"` + `role="tab"` + `role="tabpanel"`) — so quando o usuario pode pular livremente entre passos.
2. **Wizard como navegacao linear** (`<nav>` + `aria-current="step"` no passo atual) — quando os passos tem dependencia entre si (caso do cadastro do Participe Ibram).

→ **Use a opcao 2** (Step 2 depende dos dados do Step 1; usuario nao pode saltar para Step 4 antes de validar 1, 2, 3).

> Fontes: WAI ARIA26 (<https://www.w3.org/WAI/WCAG22/Techniques/aria/ARIA26>); USWDS Step Indicator (<https://designsystem.digital.gov/components/step-indicator/>).

### 5.3 HTML de referencia

```html
<form id="form-cadastro-agente"
      method="post"
      action="/wp-admin/admin-ajax.php"
      novalidate
      aria-label="Cadastro de agente">

  <!-- 1. Indicador de progresso (acessivel) -->
  <nav class="br-step" aria-label="Progresso do cadastro">
    <ol class="step-progress">
      <li class="step-progress-btn active"
          data-step="1" aria-current="step">
        <span class="step-number" aria-hidden="true">1</span>
        <span class="step-info">Dados pessoais</span>
        <span class="sr-only">— passo atual</span>
      </li>
      <li class="step-progress-btn" data-step="2">
        <span class="step-number" aria-hidden="true">2</span>
        <span class="step-info">Endereco</span>
      </li>
      <li class="step-progress-btn" data-step="3">
        <span class="step-number" aria-hidden="true">3</span>
        <span class="step-info">Documentos</span>
      </li>
      <li class="step-progress-btn" data-step="4">
        <span class="step-number" aria-hidden="true">4</span>
        <span class="step-info">Revisao</span>
      </li>
    </ol>
    <p class="sr-only" aria-live="polite" id="step-status">
      Passo 1 de 4: Dados pessoais.
    </p>
  </nav>

  <!-- 2. Paineis dos passos -->
  <section class="step-panel" id="panel-1" data-step="1"
           aria-labelledby="panel-1-heading">
    <h2 id="panel-1-heading" class="text-up-04 mb-3">Dados pessoais</h2>
    <!-- campos br-input... -->
  </section>

  <section class="step-panel" id="panel-2" data-step="2"
           aria-labelledby="panel-2-heading" hidden>
    <h2 id="panel-2-heading" class="text-up-04 mb-3">Endereco</h2>
  </section>

  <!-- ...panels 3 e 4... -->

  <!-- 3. Botoes de navegacao -->
  <div class="step-nav d-flex justify-content-between mt-4">
    <button class="br-button secondary" type="button" id="btn-prev" disabled>
      <i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar
    </button>
    <div>
      <button class="br-button" type="button" id="btn-save-draft">
        <i class="fas fa-save" aria-hidden="true"></i> Salvar rascunho
      </button>
      <button class="br-button primary ml-2" type="button" id="btn-next">
        Proximo <i class="fas fa-arrow-right" aria-hidden="true"></i>
      </button>
      <button class="br-button primary ml-2" type="submit" id="btn-submit" hidden>
        <i class="fas fa-check" aria-hidden="true"></i> Enviar cadastro
      </button>
    </div>
  </div>

  <!-- 4. Mensagem de autosave (live region) -->
  <div class="br-message info mt-3" id="autosave-status"
       role="status" aria-live="polite" hidden>
    <div class="content">
      <span class="message-body">
        Rascunho salvo automaticamente em <time id="autosave-time"></time>.
      </span>
    </div>
  </div>
</form>
```

### 5.4 JS de referencia (vanilla, ~150 linhas)

```js
(function () {
  'use strict';

  const TOTAL_STEPS = 4;
  const AUTOSAVE_DEBOUNCE_MS = 3000;
  const AUTOSAVE_ENDPOINT = '/wp-admin/admin-ajax.php';
  const NONCE = window.ParticipeIbram?.draftNonce || '';

  let currentStep = 1;
  let autosaveTimer = null;

  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  function showStep(n) {
    if (n < 1 || n > TOTAL_STEPS) return;

    $$('.step-panel').forEach(p => {
      p.hidden = Number(p.dataset.step) !== n;
    });

    $$('.step-progress-btn').forEach(btn => {
      const sn = Number(btn.dataset.step);
      btn.classList.toggle('active',  sn === n);
      btn.classList.toggle('completed', sn < n);
      if (sn === n) btn.setAttribute('aria-current', 'step');
      else          btn.removeAttribute('aria-current');
    });

    $('#btn-prev').disabled  = (n === 1);
    $('#btn-next').hidden    = (n === TOTAL_STEPS);
    $('#btn-submit').hidden  = (n !== TOTAL_STEPS);

    const heading = $(`#panel-${n}-heading`)?.textContent || '';
    $('#step-status').textContent = `Passo ${n} de ${TOTAL_STEPS}: ${heading}.`;

    const target = $(`#panel-${n}-heading`);
    if (target) {
      target.setAttribute('tabindex', '-1');
      target.focus();
    }

    currentStep = n;
  }

  function validateStep(n) {
    const panel = $(`#panel-${n}`);
    if (!panel) return true;

    let valid = true;
    $$('input, select, textarea', panel).forEach(field => {
      if (!field.checkValidity()) {
        valid = false;
        field.closest('.br-input, .br-select, .br-checkbox')?.classList.add('danger');
        field.setAttribute('aria-invalid', 'true');
      } else {
        field.closest('.br-input, .br-select, .br-checkbox')?.classList.remove('danger');
        field.removeAttribute('aria-invalid');
      }
    });
    if (!valid) {
      $('.danger input, .danger select', panel)?.focus();
    }
    return valid;
  }

  function saveDraft() {
    const data = new FormData($('#form-cadastro-agente'));
    data.append('action', 'participe_ibram_save_draft');
    data.append('_wpnonce', NONCE);
    data.append('current_step', currentStep);

    return fetch(AUTOSAVE_ENDPOINT, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(r => r.json())
      .then(res => {
        if (res?.success) {
          const time = new Date().toLocaleTimeString('pt-BR', {
            hour: '2-digit', minute: '2-digit'
          });
          $('#autosave-time').textContent = time;
          $('#autosave-time').setAttribute('datetime', new Date().toISOString());
          $('#autosave-status').hidden = false;
        }
      })
      .catch(() => { /* silencioso */ });
  }

  function scheduleAutosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(saveDraft, AUTOSAVE_DEBOUNCE_MS);
  }

  $('#btn-next').addEventListener('click', () => {
    if (validateStep(currentStep)) {
      saveDraft();
      showStep(currentStep + 1);
    }
  });
  $('#btn-prev').addEventListener('click', () => showStep(currentStep - 1));
  $('#btn-save-draft').addEventListener('click', saveDraft);

  // Autosave em blur (W3C UX best practice — nao em "change" para nao
  // disparar a cada tecla)
  $$('#form-cadastro-agente input, #form-cadastro-agente select, #form-cadastro-agente textarea')
    .forEach(f => f.addEventListener('blur', scheduleAutosave));

  showStep(1);
})();
```

### 5.5 Principios UX adotados

Baseados em W3C WAI Multi-page Forms (<https://www.w3.org/WAI/tutorials/forms/multi-page/>), USWDS, e praticas 2025:

1. **Indicador de progresso visivel em todos os passos** (4 passos e o maximo recomendado).
2. **Validacao por passo** (`Next` so avanca se passo atual valido), com erro inline (`aria-invalid`, `aria-describedby`).
3. **Autosave em `blur` com debounce de 3s**.
4. **Foco move para o `<h2>` do novo passo** ao avancar (com `tabindex="-1"`).
5. **`aria-live="polite"`** anuncia mudanca de passo e status de autosave para leitores de tela.
6. **Botao "Salvar rascunho"** explicito, alem do autosave.
7. **Voltar** sempre habilitado (exceto passo 1).
8. **Resumo no ultimo passo** antes do submit final (passo 4 = Revisao).

---

## 6. Pattern de modal explicativo contextual ("?" -> modal)

### 6.1 Quando usar tooltip vs. modal

| Situacao | Componente | Por que |
|----------|------------|---------|
| Explicacao curta (ate ~120 caracteres) sobre um campo | **Tooltip** (`br-tooltip`) | Nao interrompe fluxo; aparece em hover/focus |
| Explicacao longa, com link para politica, lista de exemplos, microcopy LGPD | **Modal contextual** (`br-modal` + `role="dialog"`) | Espaco para paragrafos, links acessiveis |
| Erro de validacao | `feedback danger` inline | Padrao do DSGov |
| Confirmacao destrutiva | **Modal de confirmacao** (`role="alertdialog"`) | Requer acao explicita |

### 6.2 Pattern: icone "?" -> modal explicativo

```html
<!-- Campo com ajuda contextual -->
<div class="br-input">
  <label for="cpf-input">
    CPF
    <button type="button"
            class="br-button circle small ml-1"
            aria-label="Por que pedimos seu CPF?"
            aria-haspopup="dialog"
            aria-controls="modal-help-cpf"
            data-toggle="br-modal"
            data-target="#modal-help-cpf">
      <i class="fas fa-question-circle" aria-hidden="true"></i>
    </button>
  </label>
  <input id="cpf-input" type="text" inputmode="numeric"
         autocomplete="off" aria-describedby="cpf-input-help">
  <span id="cpf-input-help" class="feedback">
    Apenas numeros. Nao compartilhamos com terceiros.
  </span>
</div>

<!-- Modal explicativo -->
<div class="br-modal" id="modal-help-cpf"
     role="dialog" aria-modal="true"
     aria-labelledby="modal-help-cpf-title"
     aria-describedby="modal-help-cpf-body" hidden>
  <div class="br-modal-header">
    <div class="br-modal-title" id="modal-help-cpf-title">
      <i class="fas fa-question-circle" aria-hidden="true"></i>
      Por que pedimos seu CPF?
    </div>
    <button class="br-button circle small ml-auto"
            type="button" aria-label="Fechar" data-dismiss="br-modal">
      <i class="fas fa-times" aria-hidden="true"></i>
    </button>
  </div>
  <div class="br-modal-body" id="modal-help-cpf-body">
    <p>O CPF e utilizado <strong>exclusivamente</strong> para:</p>
    <ul>
      <li>Identificar o agente cadastrado de forma unica;</li>
      <li>Validar contra a Receita Federal (consulta de existencia);</li>
      <li>Registrar consentimento LGPD com vinculo a um titular real.</li>
    </ul>
    <p>
      Conforme o <a href="/lgpd#cpf">art. 7º, inciso II da LGPD</a>,
      o tratamento ocorre para cumprimento de obrigacao legal pelo
      controlador (IBRAM/Ministerio da Cultura).
    </p>
    <p>
      <strong>O que nao fazemos:</strong> nao consultamos seu score,
      nao compartilhamos com terceiros, nao usamos para fins comerciais.
    </p>
  </div>
  <div class="br-modal-footer justify-content-end">
    <a href="/lgpd" class="br-button secondary small">Politica de Privacidade</a>
    <button class="br-button primary small ml-2" type="button" data-dismiss="br-modal">
      Entendi
    </button>
  </div>
</div>
```

### 6.3 JS — focus trap e ESC (vanilla, ~80 linhas)

```js
(function () {
  'use strict';

  const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled])',
    'select:not([disabled])', 'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  let lastTrigger = null;
  let activeModal = null;

  function openModal(modal) {
    activeModal = modal;
    lastTrigger = document.activeElement;

    modal.hidden = false;
    modal.classList.add('is-open');
    document.body.classList.add('br-modal-open');
    document.body.setAttribute('aria-hidden-by-modal', 'true');

    const focusables = modal.querySelectorAll(FOCUSABLE);
    if (focusables.length) focusables[0].focus();
    else modal.setAttribute('tabindex', '-1'), modal.focus();
  }

  function closeModal(modal) {
    modal.hidden = true;
    modal.classList.remove('is-open');
    document.body.classList.remove('br-modal-open');
    document.body.removeAttribute('aria-hidden-by-modal');
    if (lastTrigger && typeof lastTrigger.focus === 'function') {
      lastTrigger.focus();   // restaura foco no gatilho
    }
    activeModal = null;
  }

  function trapTab(ev) {
    if (!activeModal || ev.key !== 'Tab') return;

    const focusables = Array.from(activeModal.querySelectorAll(FOCUSABLE))
      .filter(el => !el.hasAttribute('hidden'));
    if (!focusables.length) return;

    const first = focusables[0];
    const last  = focusables[focusables.length - 1];

    if (ev.shiftKey && document.activeElement === first) {
      last.focus(); ev.preventDefault();
    } else if (!ev.shiftKey && document.activeElement === last) {
      first.focus(); ev.preventDefault();
    }
  }

  // Delegacao: abrir
  document.addEventListener('click', ev => {
    const trigger = ev.target.closest('[data-toggle="br-modal"]');
    if (!trigger) return;
    const target = document.querySelector(trigger.dataset.target);
    if (target) openModal(target);
  });

  // Delegacao: fechar
  document.addEventListener('click', ev => {
    if (ev.target.closest('[data-dismiss="br-modal"]') && activeModal) {
      closeModal(activeModal);
    }
  });

  // ESC + Tab trap
  document.addEventListener('keydown', ev => {
    if (ev.key === 'Escape' && activeModal) {
      closeModal(activeModal);
    } else if (ev.key === 'Tab' && activeModal) {
      trapTab(ev);
    }
  });
})();
```

### 6.4 Microcopy — boas praticas

Baseado em NN/g (<https://www.nngroup.com/articles/tooltip-guidelines/>) e Formsort (<https://formsort.com/article/tooltips-design-signup-flows/>):

1. **Titulo da modal de ajuda comeca com "Por que..." ou "Como..."** (formato pergunta).
2. **Use voz ativa, 2ª pessoa** ("Pedimos seu CPF para...", nao "E solicitado o CPF").
3. **Lista de bullets** quando ha mais de 2 motivos.
4. **Sempre incluir link para a politica completa** (LGPD, Termos).
5. **Botao de fechamento sempre presente** ("Entendi" como CTA primario > "Fechar" como acao secundaria).
6. **Nao use ajuda contextual para corrigir UX ruim:** se 80% dos usuarios precisam abrir o "?", reescreva o label do campo.
7. **Microcopy LGPD obrigatorio:** todo campo de dado pessoal sensivel (CPF, RG, e-mail, telefone, endereco) DEVE ter icone de ajuda explicando finalidade.

### 6.5 Triggers — hover NAO recomendado

| Trigger | Recomendacao |
|--------|--------------|
| `hover` | **Nao** — inacessivel para teclado e mobile |
| `click` | **Sim** para modal contextual (intencao clara) |
| `focus` | **Sim** para tooltip curta |
| `hover + focus + click` (combinado) | **Sim** para tooltips: cobre todos os modos de input |

Para o "?" -> modal contextual, **so `click`/`Enter`/`Space` no botao**.

---

## 7. Plano de integracao com WordPress (passos concretos)

### 7.1 Estrutura de diretorios proposta

```
plugins/participe-ibram/
├── participe-ibram.php
├── assets/
│   ├── vendor/
│   │   ├── govbr-ds/
│   │   │   ├── core.min.css
│   │   │   └── core.min.js
│   │   ├── fontawesome-6/
│   │   └── fonts/rawline/
│   ├── css/
│   │   ├── participe-ibram.css
│   │   └── participe-ibram-admin.css
│   └── js/
│       ├── wizard.js
│       ├── modal.js
│       └── participe-ibram.js
├── includes/
│   ├── class-assets.php
│   └── ...
└── templates/
    └── form-cadastro-agente.php
```

### 7.2 Classe `Participe_Ibram_Assets`

```php
<?php
class Participe_Ibram_Assets {

    const VERSION = '1.0.0';
    const DSGOV_VERSION = '3.7.0';

    public function __construct() {
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_front' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
    }

    public function enqueue_front() {
        if ( ! $this->is_plugin_page() ) {
            return;
        }
        $base = plugins_url( 'assets/', PARTICIPE_IBRAM_FILE );

        wp_enqueue_style(
            'participe-ibram-fa',
            $base . 'vendor/fontawesome-6/css/all.min.css',
            [], '6.5.0'
        );

        wp_enqueue_style(
            'participe-ibram-dsgov',
            $base . 'vendor/govbr-ds/core.min.css',
            [ 'participe-ibram-fa' ],
            self::DSGOV_VERSION
        );

        wp_enqueue_style(
            'participe-ibram-theme',
            $base . 'css/participe-ibram.css',
            [ 'participe-ibram-dsgov' ],
            self::VERSION
        );

        wp_enqueue_script(
            'participe-ibram-dsgov',
            $base . 'vendor/govbr-ds/core.min.js',
            [], self::DSGOV_VERSION, true
        );

        wp_enqueue_script(
            'participe-ibram-wizard',
            $base . 'js/wizard.js',
            [ 'participe-ibram-dsgov' ], self::VERSION, true
        );
        wp_enqueue_script(
            'participe-ibram-modal',
            $base . 'js/modal.js',
            [ 'participe-ibram-dsgov' ], self::VERSION, true
        );
        wp_enqueue_script(
            'participe-ibram-bootstrap',
            $base . 'js/participe-ibram.js',
            [ 'participe-ibram-dsgov', 'participe-ibram-wizard', 'participe-ibram-modal' ],
            self::VERSION, true
        );

        wp_localize_script( 'participe-ibram-bootstrap', 'ParticipeIbram', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'draftNonce' => wp_create_nonce( 'participe_ibram_save_draft' ),
            'i18n'       => [
                'saving'    => __( 'Salvando rascunho...', 'participe-ibram' ),
                'savedAt'   => __( 'Rascunho salvo em %s.', 'participe-ibram' ),
                'errorSave' => __( 'Nao foi possivel salvar. Tente novamente.', 'participe-ibram' ),
            ],
        ] );
    }

    public function enqueue_admin( $hook ) {
        if ( ! $this->is_plugin_admin_screen( $hook ) ) {
            return;
        }
        $base = plugins_url( 'assets/', PARTICIPE_IBRAM_FILE );
        wp_enqueue_style(
            'participe-ibram-admin',
            $base . 'css/participe-ibram-admin.css',
            [], self::VERSION
        );
        // NAO enfileirar core.min.css no admin global.
    }

    private function is_plugin_page(): bool {
        global $post;
        if ( is_admin() ) return false;
        if ( $post && has_shortcode( $post->post_content, 'participe_ibram_form' ) ) return true;
        if ( is_page_template( 'templates/participe-ibram.php' ) ) return true;
        return false;
    }

    private function is_plugin_admin_screen( string $hook ): bool {
        return in_array( $hook, [
            'toplevel_page_participe-ibram',
            'participe-ibram_page_inscricoes',
            'participe-ibram_page_editais',
        ], true );
    }
}
new Participe_Ibram_Assets();
```

### 7.3 Estrategia de **isolamento CSS** (anti-colisao)

O DSGov define `body { ... }`, `input { ... }` (selectors genericos) que **vao colidir com `wp-admin/css/forms.css`** se carregado no admin global, e podem afetar o tema do site no front.

**Tres salvaguardas:**

1. **No admin:** **NAO** enfileirar `core.min.css` em telas que nao sao do plugin. Use `is_plugin_admin_screen()` (acima).
2. **No front:** envolver todo template do plugin em um wrapper `.participe-ibram-scope`:

   ```html
   <div class="participe-ibram-scope">
     <!-- todo o HTML DSGov aqui -->
   </div>
   ```

3. **Compilar uma versao escopada do DSGov** (recomendado): usar `postcss-prefix-selector`:

   ```bash
   npm i -D postcss-prefix-selector postcss-cli
   npx postcss node_modules/@govbr-ds/core/dist/core.min.css \
     --use postcss-prefix-selector \
     -o assets/vendor/govbr-ds/core.scoped.min.css
   ```

   `postcss.config.js`:

   ```js
   module.exports = {
     plugins: [
       require('postcss-prefix-selector')({
         prefix: '.participe-ibram-scope',
         exclude: [':root', /^html/, /^body/],
         transform(prefix, sel, prefixed) {
           return /:root|html|body/.test(sel) ? sel : prefixed;
         }
       })
     ]
   };
   ```

   Isso elimina **100% do risco de bleed**.

### 7.4 Compatibilidade com `wp-admin/css/forms.css`

| Seletor WP | Seletor DSGov | Risco | Mitigacao |
|-----------|---------------|-------|-----------|
| `input[type="text"]` | `.br-input input` | Medio | Escopo (`.participe-ibram-scope`) |
| `select` | `.br-select` | Alto (DSGov reescreve totalmente) | Escopo + nao usar no admin global |
| `button` | `.br-button` | Medio | Sempre usar `.br-button` explicito |
| `.button` (WP admin) | — | Baixo | Nao conflita |
| `label` | `.br-input label` | Baixo | Escopo resolve |

### 7.5 Acessibilidade — checklist obrigatorio

- [ ] Todos os `<input>` tem `<label for="...">` (nao usar placeholder como label).
- [ ] Erros tem `role="alert"` + `aria-invalid` + `aria-describedby`.
- [ ] Wizard tem `aria-current="step"` no passo atual.
- [ ] Modais tem `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, focus trap, ESC.
- [ ] Botoes so com icone tem `aria-label`.
- [ ] Skip-link `<a class="br-skip-link" href="#main">Pular para o conteudo</a>`.
- [ ] Contraste minimo 4.5:1 (WCAG AA).
- [ ] Foco visivel (`--focus-style: dashed`, `--focus-width: 4px`).
- [ ] `lang="pt-BR"` no `<html>`.
- [ ] Testar com NVDA + Firefox e VoiceOver + Safari.

### 7.6 Barra gov.br (obrigatoria por lei)

```php
add_action( 'wp_footer', function() {
    if ( ! is_admin() ) {
        echo '<script defer src="//barra.brasil.gov.br/barra_2.0.js" type="text/javascript"></script>';
    }
}, 99 );
```

E no `<head>`:

```html
<meta property="creator.productor"
      content="http://estruturaorganizacional.dados.gov.br/id/unidade-organizacional/IBRAM">
```

**Fonte legal:** Instrucao Normativa SECOM nº 8/2014.

### 7.7 Login com gov.br (recomendacao)

Para `cadastro.museus.gov.br`, considerar OAuth2/OIDC com **Login Unico gov.br** em vez de criar conta WP nativa. Documentacao: <https://acesso.gov.br/manual/integracao/>. Nao e parte deste relatorio (sera R2 ou outro), mas o **header (4.9)** ja reserva o slot do botao "Entrar com gov.br".

### 7.8 Roadmap de implementacao (sugestao)

1. **Sprint 1:** baixar `@govbr-ds/core@3.7.0`, gerar `core.scoped.min.css` (postcss), montar `class-assets.php`, validar enqueue no template-mock.
2. **Sprint 2:** templates de header/footer institucionais Ibram, breadcrumb, pagina de cadastro com Step 1 estatico.
3. **Sprint 3:** wizard JS completo com 4 passos + autosave + validacao.
4. **Sprint 4:** modais de help LGPD em todos os campos sensiveis.
5. **Sprint 5:** auditoria de a11y (eMAG + WCAG 2.1 AA) com NVDA, axe-core, Lighthouse.
6. **Sprint 6:** integracao Login Unico gov.br.

---

## 8. Anexo: notas de cobertura da pesquisa

**Limitacoes encontradas:**

- O portal `https://www.gov.br/ds/*` e SPA cliente — nao retorna conteudo via fetch sem JS. Testes diretos retornaram apenas `<title>`. Solucao adotada: pesquisa indireta via npm, GitHub espelhos, wrappers comunitarios e snippets indexados.
- Os dominios `next-ds.estaleiro.serpro.gov.br` (V4 beta) e `govbr-ds.gitlab.io` (wiki) estao bloqueados para WebFetch no ambiente de pesquisa. Conteudo da V4 foi inferido por mencoes e mantido marcado como "beta — nao usar".
- **Recomendacao obrigatoria:** antes de fixar valores de tokens em codigo, **abrir `node_modules/@govbr-ds/core/dist/core.css`** localmente e validar nomes/valores reais. Os valores apresentados aqui sao consistentes com a v3.7 documentada, mas nomes podem ter pequenas variacoes (e.g., `--blue-warm-vivid-70` vs `--blue-warm-vivid-70-color`).

---

## 9. URLs e referencias consultadas

### Oficiais gov.br / GOVBR-DS

- Portal DSGov: <https://www.gov.br/ds>
- Pagina inicial DSGov: <https://www.gov.br/ds/home>
- Componente Button: <https://www.gov.br/ds/components/button>
- Componente Wizard: <https://www.gov.br/ds/components/wizard>
- Componente Step: <https://www.gov.br/ds/components/step>
- Componente Breadcrumb: <https://www.gov.br/ds/components/breadcrumb>
- Componente Checkbox: <https://www.gov.br/ds/components/checkbox>
- Componente Message: <https://www.gov.br/ds/components/message>
- Componente Upload: <https://www.gov.br/ds/components/upload>
- Componente Select: <https://www.gov.br/ds/components/select>
- Componente Table: <https://www.gov.br/ds/components/table>
- Componente Pagination: <https://www.gov.br/ds/components/pagination>
- Tooltip: <https://www.gov.br/ds/components/tooltip>
- Boas praticas HTML: <https://www.gov.br/ds/guias/boas-praticas-de-html>
- Boas praticas CSS: <https://www.gov.br/ds/guias/boas-praticas-de-css>
- Codificacao SASS: <https://www.gov.br/ds/guias/codificacao-sass>
- Acessibilidade HTML: <https://www.gov.br/ds/guias/acessibilidade-html>
- V4 (beta): <https://next-ds.estaleiro.serpro.gov.br/>
- Versao antiga: <http://dsgov.estaleiro.serpro.gov.br/>
- Wiki GOVBR-DS: <https://govbr-ds.gitlab.io/govbr-ds-wiki/>
- Wiki — Roteiro: <https://govbr-ds.gitlab.io/tools/govbr-ds-wiki/desenvolvimento/guias/roteiro/>
- Wiki — Versao 4: <https://govbr-ds.gitlab.io/tools/govbr-ds-wiki/versao-4/>
- GitLab raiz: <https://gitlab.com/govbr-ds>
- GitLab core: <https://gitlab.com/govbr-ds/bibliotecas/javascript/govbr-ds-core>
- Releases: <https://gitlab.com/govbr-ds/bibliotecas/javascript/govbr-ds-core/-/releases>

### Pacotes npm

- `@govbr-ds/core`: <https://www.npmjs.com/package/@govbr-ds/core> (v3.7.0)
- `@govbr-ds/utilities`: <https://www.npmjs.com/package/@govbr-ds/utilities>
- `@govbr-ds/webcomponents`: <https://www.npmjs.com/package/@govbr-ds/webcomponents>
- `@govbr-ds/react-components`: <https://www.npmjs.com/package/@govbr-ds/react-components>
- `@govbr-ds/webcomponents-vue`: <https://www.npmjs.com/package/@govbr-ds/webcomponents-vue>
- `@govbr-ds/webcomponents-angular`: <https://www.npmjs.com/package/@govbr-ds/webcomponents-angular>

### CDN

- jsDelivr: <https://www.jsdelivr.com/package/npm/@govbr-ds/core>
- unpkg: <https://app.unpkg.com/core@1.0.113>

### Repositorios espelho / wrappers comunitarios

- Espelho GitHub serpro-brasil: <https://github.com/serpro-brasil/GOVBR-DS>
- Wrapper React `tesouro/react-dsgov`: <https://github.com/tesouro/react-dsgov> (33+ componentes documentados)
- Wrapper React `helder-nicollas`: <https://github.com/helder-nicollas/govbr-react-components>
- Wrapper Angular `oluizcarvalho`: <https://github.com/oluizcarvalho/govbr-ds-angular>

### Figma

- UI Kit V3.5.1: <https://www.figma.com/community/file/1398351127929377953/ui-kit-govbr-design-system-v-3-5-1>
- Figma comunidade: <https://www.figma.com/community/file/1400641973719338361/gov-br-design-system>

### Barra gov.br (institucional, obrigatoria)

- Manual: <https://barra.governoeletronico.gov.br/>
- Instrucoes: <https://barra.governoeletronico.gov.br/instrucoes_novo.html>

### Acessibilidade WAI-ARIA — wizard / step

- WAI-ARIA APG: <https://www.w3.org/WAI/ARIA/apg/>
- ARIA26 — `aria-current`: <https://www.w3.org/WAI/WCAG22/Techniques/aria/ARIA26>
- MDN — `aria-current`: <https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-current>
- Aditus — `aria-current`: <https://www.aditus.io/aria/aria-current/>
- W3C WAI — Multi-page Forms: <https://www.w3.org/WAI/tutorials/forms/multi-page/>
- USWDS Step Indicator: <https://designsystem.digital.gov/components/step-indicator/>
- A11Y Collective — `aria-current`: <https://www.a11y-collective.com/blog/aria-current/>

### Acessibilidade — modal / dialog

- WAI-ARIA APG Dialog Pattern: <https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/>
- MDN — `aria-modal`: <https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-modal>
- MDN — role `dialog`: <https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Roles/dialog_role>
- A11Y Collective — modal accessibility: <https://www.a11y-collective.com/blog/modal-accessibility/>
- UXPin — focus trap modal (2026): <https://www.uxpin.com/studio/blog/how-to-build-accessible-modals-with-focus-traps/>
- TestParty — accessible modals: <https://testparty.ai/blog/modal-dialog-accessibility>
- TPGi — current state of modal a11y: <https://www.tpgi.com/the-current-state-of-modal-dialog-accessibility/>
- ally.js — accessible dialog: <https://allyjs.io/tutorials/accessible-dialog.html>

### UX / multi-step forms

- NN/g — Tooltip Guidelines: <https://www.nngroup.com/articles/tooltip-guidelines/>
- Eleken — Wizard UI Pattern: <https://www.eleken.co/blog-posts/wizard-ui-pattern-explained>
- FormAssembly — Multi-step Best Practices: <https://www.formassembly.com/blog/multi-step-form-best-practices/>
- Growform — Multi-step UX: <https://www.growform.co/must-follow-ux-best-practices-when-designing-a-multi-step-form/>
- Webstacks — Multi-step Forms 2025: <https://www.webstacks.com/blog/multi-step-form>
- Lollypop — Wizard UI Design 2026: <https://lollypop.design/blog/2026/january/wizard-ui-design/>
- GitLab Pajamas — Saving and Feedback: <https://design.gitlab.com/usability/saving-and-feedback>
- Formsort — Tooltips em fluxos de signup: <https://formsort.com/article/tooltips-design-signup-flows/>
- CSS-Tricks — Tooltip best practices: <https://css-tricks.com/tooltip-best-practices/>

### WordPress — enqueue / integracao

- `wp_enqueue_style()`: <https://developer.wordpress.org/reference/functions/wp_enqueue_style/>
- Enqueueing assets in editor: <https://developer.wordpress.org/block-editor/how-to-guides/enqueueing-assets-in-the-editor/>
- Kinsta — `wp_enqueue_scripts`: <https://kinsta.com/blog/wp-enqueue-scripts/>
- Cloudways — Enqueue Custom Scripts: <https://www.cloudways.com/blog/wordpress-enqueue-scripts/>
- Trac WordPress — admin CSS isolation: <https://core.trac.wordpress.org/ticket/53741>
- Make WP Core — Admin CSS changes 5.3: <https://make.wordpress.org/core/2019/10/18/noteworthy-admin-css-changes-in-wordpress-5-3/>
- WordPress CSS Audit (wp-admin): <https://wordpress.github.io/css-audit/public/wp-admin>
- CSS-Tricks — Overriding Styles in WordPress: <https://css-tricks.com/methods-overriding-styles-wordpress/>

### Fontes e dependencias

- Raleway (Google Fonts): <https://fonts.google.com/specimen/Raleway>
- Rawline (download): <https://font.download/font/rawline>
- Font Awesome 6: <https://fontawesome.com/v6/docs>

---

**Fim do relatorio R1.**
