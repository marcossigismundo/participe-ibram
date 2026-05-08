# R4 - Acessibilidade (eMAG + WCAG 2.1 AA) aplicada ao Participe Ibram

## 1. Resumo executivo

Pesquisa sobre **eMAG 3.1** (Modelo de Acessibilidade em Governo Eletrônico) e **WCAG 2.1 nível AA** aplicados ao formulário multi-passo (wizard) e módulo de votação do plugin Participe Ibram, plataforma federal IBRAM/MinC. Conformidade obrigatória por força do Decreto 5.296/2004 e Lei 13.146/2015 (LBI).

Pontos críticos:

1. **Wizard:** usar `<nav>` + `<ol>` com `aria-current="step"` (NÃO `role="tablist"`, que pressupõe paineis intercambiáveis arbitrariamente).
2. **Validação inline:** combinar `aria-invalid`, `aria-describedby` e live region (`role="alert"` ou `aria-live="assertive"`).
3. **Foco gerenciado:** ao avançar, foco no `<h2 tabindex="-1">` do novo passo. Ao haver erro, foco no sumário de erros (lista linkada aos campos).
4. **Modal:** `role="dialog"` + `aria-modal="true"` + `inert` no resto da página + focus trap + ESC + restauração de foco.
5. **Contraste:** 4.5:1 normal; 3:1 grande/UI; modo alto contraste 7:1.
6. **Reflow:** funcional em 320 CSS px, sem scroll horizontal.
7. **Testes obrigatórios:** ASES Web (oficial GovBR) + axe + WAVE + Lighthouse + manual com NVDA/Chrome e VoiceOver.

---

## 2. Checklist eMAG aplicável (SIM/NÃO)

### 2.1 Marcação (R1 a R7)

| # | Recomendação | Aplicável | Ação |
|---|---|---|---|
| R1 | Respeitar Padrões Web | SIM | HTML5 válido (W3C validator) |
| R2 | HTML semântico | SIM | `<form>`, `<fieldset>`, `<legend>`, `<label>`, `<nav>`, headings |
| R3 | Níveis de cabeçalho corretos | SIM | h1 página, h2 passo, h3 grupo |
| R4 | Ordem lógica de leitura | SIM | DOM = visual; sem `tabindex` positivo |
| R5 | Âncoras para blocos | SIM | Skip link "Pular para o conteúdo principal" |
| R6 | Não usar tabela para layout | SIM | Grid/Flexbox |
| R7 | Separar HTML/CSS/JS | SIM | - |

### 2.2 Comportamento (R8 a R13)

| # | Recomendação | Aplicável | Ação |
|---|---|---|---|
| R8 | Funções via teclado | SIM | Sem dependência de mouse |
| R9 | Foco visível | SIM | `:focus-visible` >= 2px, contraste 3:1 |
| R10 | Sem trap não-intencional | SIM | Trap apenas no modal |
| R11 | Sem refresh automático | NÃO usual | Auto-save com aviso via live region |
| R12 | Sem redirecionamento automático | NÃO usual | Apenas em logout/expiração |
| R13 | Alternativa para limites de tempo | SIM (sessão) | Avisar e permitir estender |

### 2.3 Conteúdo (R14 a R22)

| # | Recomendação | Aplicável | Ação |
|---|---|---|---|
| R14 | Idioma principal | SIM | `<html lang="pt-BR">` |
| R15 | Mudanças de idioma | Eventual | `lang="en"` em trechos |
| R16 | Título descritivo | SIM | `<title>Passo 2 de 5 - Dados profissionais - Cadastro IBRAM</title>` |
| R17 | Localização do usuário | SIM | Breadcrumb + indicador de passo |
| R18 | Links descritivos | SIM | "Voltar para passo anterior" (não "clique aqui") |
| R19 | Alt text em imagens | SIM | `alt="..."` ou `alt=""` |
| R20 | Imagens decorativas | SIM | `alt=""` + `aria-hidden="true"` |

### 2.4 Apresentação (R23 a R31)

| # | Recomendação | Ação |
|---|---|---|
| R23 | Contraste mínimo | 4.5:1 normal; alto contraste 7:1 |
| R24 | Cor não é o único indicador | Erro = cor + ícone + texto |
| R25 | Redimensionamento | Zoom 200% sem perda |
| R26 | Foco visualmente evidente | Outline 2px + offset |
| R27 | Funções via teclado | Tab/Shift+Tab/Enter/Space/ESC |
| R28 | Skip link | Primeiro elemento focável |
| R29 | Sem flash > 3Hz | - |
| R30 | Alternativa para CAPTCHA | reCAPTCHA com áudio |
| R31 | Animações controláveis | `prefers-reduced-motion` |

### 2.5 Formulários (R38 a R45) — CRÍTICO

| # | Recomendação | Ação |
|---|---|---|
| **R38** | Alternativa em texto para botões-imagem | `<button type="submit">Enviar</button>` |
| **R39** | Etiquetas associadas aos campos | `<label for="">`; não usar só placeholder |
| **R40** | Ordem lógica de navegação | DOM = visual |
| **R41** | Sem mudança automática de contexto | Não submeter no `change` de `<select>` |
| **R42** | Instruções para entrada | `aria-describedby` com formato esperado |
| **R43** | Identificar/descrever erros | `aria-invalid` + `role="alert"` |
| **R44** | Agrupar campos | `<fieldset><legend>` |
| **R45** | CAPTCHA humano | Com alternativa acessível |

---

## 3. Checklist WCAG 2.1 AA aplicável

| Critério | Nível | Como atender |
|---|---|---|
| **1.3.1** Info and Relationships | A | `<label>`, `<fieldset>`/`<legend>`, headings, ARIA quando nativo não basta |
| **1.3.5** Identify Input Purpose | AA | `autocomplete="name"`, `email`, `tel`, `bday`, `street-address` |
| **1.4.3** Contrast (Minimum) | AA | 4.5:1 normal; 3:1 grande (>=18pt ou >=14pt bold) |
| **1.4.4** Resize text | AA | Zoom 200% sem perda |
| **1.4.10** Reflow | AA | 320 CSS px sem scroll horizontal |
| **1.4.11** Non-text Contrast | AA | 3:1 borda input, foco, ícone erro |
| **1.4.12** Text Spacing | AA | line-height 1.5, letter-spacing 0.12em sem corte |
| **1.4.13** Content on Hover or Focus | AA | Tooltip dispensável (ESC), persistente, hoverável |
| **2.1.1** Keyboard | A | Tudo operável via teclado |
| **2.1.2** No Keyboard Trap | A | ESC libera modal |
| **2.4.3** Focus Order | A | Ordem lógica = visual |
| **2.4.6** Headings and Labels | AA | Headings descritivos por passo |
| **2.4.7** Focus Visible | AA | Indicador sempre visível |
| **3.2.1** On Focus | A | Foco não muda contexto |
| **3.2.2** On Input | A | Mudança de valor não submete |
| **3.2.4** Consistent Identification | AA | Mesmo "Salvar" entre passos |
| **3.3.1** Error Identification | A | Erro descrito em texto |
| **3.3.2** Labels or Instructions | A | Toda entrada com label |
| **3.3.3** Error Suggestion | AA | Sugestão de correção |
| **3.3.4** Error Prevention (Legal) | AA | Confirmação + reversibilidade (votação) |
| **4.1.2** Name, Role, Value | A | Componentes custom expõem nome/papel/valor |
| **4.1.3** Status Messages | AA | Live regions para "Salvo", "Erro", etc. |

---

## 4. Pattern HTML+ARIA: Wizard acessível

### 4.1 Decisão: `aria-current="step"` (NÃO `role="tablist"`)

- **`role="tablist"`** = paineis intercambiáveis acionados por setas, qualquer tab ativável. Inadequado para fluxo linear validado.
- **`aria-current="step"`** = marca o passo ativo dentro de uma `<nav><ol>`, mantendo semântica linear e permitindo voltar a passos completos.

### 4.2 HTML

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Passo 2 de 5 - Dados profissionais - Cadastro IBRAM</title>
  <link rel="stylesheet" href="wizard.css">
</head>
<body>
  <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>

  <header role="banner">
    <h1>Cadastro de agente museal - IBRAM</h1>
  </header>

  <main id="conteudo-principal" tabindex="-1">

    <nav aria-label="Progresso do cadastro" class="wizard-nav">
      <ol class="wizard-steps">
        <li class="wizard-step is-complete">
          <a href="#passo-1" aria-label="Passo 1: Identificação - completo">
            <span class="step-num" aria-hidden="true">1</span>
            <span class="step-label">Identificação</span>
          </a>
        </li>
        <li class="wizard-step is-current" aria-current="step">
          <a href="#passo-2" aria-label="Passo 2: Dados profissionais - atual">
            <span class="step-num" aria-hidden="true">2</span>
            <span class="step-label">Dados profissionais</span>
          </a>
        </li>
        <li class="wizard-step">
          <span class="step-num" aria-hidden="true">3</span>
          <span class="step-label">Endereço</span>
        </li>
      </ol>
    </nav>

    <div id="wizard-live" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

    <form id="cadastro" novalidate action="/cadastro" method="post">
      <section id="passo-2" class="wizard-panel" aria-labelledby="passo-2-titulo">
        <h2 id="passo-2-titulo" tabindex="-1">Passo 2 de 5: Dados profissionais</h2>

        <p class="passo-instrucoes">
          Os campos marcados com <span aria-hidden="true">*</span>
          <span class="sr-only">asterisco</span> são obrigatórios.
        </p>

        <div id="erros-passo-2" class="erros-sumario" role="alert" aria-live="assertive" hidden>
          <h3>Existem erros neste passo</h3>
          <ul></ul>
        </div>

        <fieldset>
          <legend>Vínculo institucional</legend>

          <div class="campo">
            <label for="instituicao">Instituição <span aria-hidden="true">*</span></label>
            <input type="text" id="instituicao" name="instituicao" required
                   autocomplete="organization" aria-required="true"
                   aria-describedby="instituicao-dica instituicao-erro">
            <p id="instituicao-dica" class="dica">Ex.: Museu Nacional, MAR, Pinacoteca.</p>
            <p id="instituicao-erro" class="erro" hidden></p>
          </div>
        </fieldset>

        <nav class="wizard-acoes" aria-label="Navegação do formulário">
          <button type="button" class="btn-secundario" data-acao="voltar">
            <span aria-hidden="true">&larr;</span> Voltar
          </button>
          <button type="button" class="btn-secundario" data-acao="salvar">Salvar rascunho</button>
          <button type="submit" class="btn-primario" data-acao="avancar">
            Avançar <span aria-hidden="true">&rarr;</span>
          </button>
        </nav>
      </section>
    </form>
  </main>

  <script src="wizard.js"></script>
</body>
</html>
```

### 4.3 CSS

```css
/* Skip link */
.skip-link { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
.skip-link:focus {
  position:fixed; left:1rem; top:1rem; width:auto; height:auto;
  padding:.75rem 1rem; background:#003366; color:#fff; z-index:9999;
  outline:3px solid #ffcc00;
}

/* Texto só para leitor de tela */
.sr-only {
  position:absolute !important; width:1px; height:1px;
  padding:0; margin:-1px; overflow:hidden;
  clip:rect(0,0,0,0); white-space:nowrap; border:0;
}

/* Foco visível - 2.4.7 + 1.4.11 */
:where(a, button, input, select, textarea, [tabindex]):focus-visible {
  outline:3px solid #ffbf47;
  outline-offset:2px;
  border-radius:2px;
}

/* Stepper */
.wizard-steps {
  display:flex; gap:1rem; list-style:none; padding:0; flex-wrap:wrap;
}
.wizard-step {
  flex:1 1 8rem; min-width:8rem; padding:.5rem;
  border:2px solid #767676; border-radius:6px; text-align:center;
}
.wizard-step.is-complete { border-color:#006633; background:#e6f4ea; }
.wizard-step.is-current  { border-color:#003366; background:#003366; color:#fff; font-weight:700; }

/* Erro: cor + ícone + texto */
.erro { color:#b10000; font-weight:700; margin-top:.25rem; }
.erro::before { content:"\26A0\FE0F  "; margin-right:.25rem; }
input[aria-invalid="true"], select[aria-invalid="true"], textarea[aria-invalid="true"] {
  border:2px solid #b10000; background:#fff5f5;
}

/* Reflow 320px */
@media (max-width:480px) {
  .wizard-steps { flex-direction:column; }
  .wizard-acoes { flex-direction:column; gap:.5rem; }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration:.001ms !important; transition-duration:.001ms !important; }
}
```

### 4.4 JavaScript do wizard

```javascript
class WizardAcessivel {
  constructor(form) {
    this.form = form;
    this.live = document.getElementById('wizard-live');
    this.passos = [...form.querySelectorAll('.wizard-panel')];
    this.indice = 0;
    this.bind();
  }

  bind() {
    this.form.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-acao]');
      if (!btn) return;
      const acao = btn.dataset.acao;
      if (acao === 'avancar') { e.preventDefault(); this.avancar(); }
      if (acao === 'voltar')  { e.preventDefault(); this.voltar(); }
      if (acao === 'salvar')  { e.preventDefault(); this.salvar(); }
    });

    this.form.addEventListener('blur', (e) => {
      if (e.target.matches('input, select, textarea')) this.validarCampo(e.target);
    }, true);
  }

  validarCampo(campo) {
    const ids = (campo.getAttribute('aria-describedby') || '').split(' ');
    const erroEl = ids.map(id => document.getElementById(id))
                      .find(el => el && el.classList.contains('erro'));
    if (!erroEl) return true;

    let msg = '';
    if (campo.validity.valueMissing)      msg = `O campo "${this.labelDe(campo)}" é obrigatório.`;
    else if (campo.validity.typeMismatch) msg = `Informe um valor válido para "${this.labelDe(campo)}".`;
    else if (campo.validity.patternMismatch) msg = campo.dataset.erroPattern || 'Formato inválido.';

    if (msg) {
      campo.setAttribute('aria-invalid', 'true');
      erroEl.textContent = msg;
      erroEl.hidden = false;
      return false;
    }
    campo.removeAttribute('aria-invalid');
    erroEl.textContent = '';
    erroEl.hidden = true;
    return true;
  }

  labelDe(campo) {
    const lbl = this.form.querySelector(`label[for="${campo.id}"]`);
    return lbl ? lbl.textContent.replace('*','').trim() : campo.name;
  }

  validarPasso(passo) {
    const campos = [...passo.querySelectorAll('input, select, textarea')];
    const invalidos = campos.filter(c => !this.validarCampo(c));
    return { ok: invalidos.length === 0, invalidos };
  }

  avancar() {
    const atual = this.passos[this.indice];
    const { ok, invalidos } = this.validarPasso(atual);
    const sumario = atual.querySelector('.erros-sumario');

    if (!ok) {
      const ul = sumario.querySelector('ul');
      ul.innerHTML = invalidos.map(c => {
        const ids = (c.getAttribute('aria-describedby') || '').split(' ');
        const erro = ids.map(i => document.getElementById(i))
                        .find(el => el && el.classList.contains('erro') && !el.hidden);
        const msg = erro ? erro.textContent : '';
        return `<li><a href="#${c.id}">${this.labelDe(c)}: ${msg}</a></li>`;
      }).join('');
      sumario.hidden = false;
      sumario.setAttribute('tabindex', '-1');
      sumario.focus();
      this.live.textContent = `${invalidos.length} erro(s) encontrados no passo.`;
      return;
    }

    sumario.hidden = true;
    if (this.indice < this.passos.length - 1) {
      this.passos[this.indice].hidden = true;
      this.indice++;
      this.mostrarPasso();
    } else {
      this.form.submit();
    }
  }

  voltar() {
    if (this.indice === 0) return;
    this.passos[this.indice].hidden = true;
    this.indice--;
    this.mostrarPasso();
  }

  mostrarPasso() {
    const passo = this.passos[this.indice];
    passo.hidden = false;
    const titulo = passo.querySelector('h2[tabindex="-1"]');
    titulo.focus();
    this.live.textContent = `Passo ${this.indice + 1} de ${this.passos.length}: ${titulo.textContent}`;
    this.atualizarStepper();
  }

  atualizarStepper() {
    document.querySelectorAll('.wizard-step').forEach((li, i) => {
      li.classList.remove('is-current', 'is-complete');
      li.removeAttribute('aria-current');
      if (i < this.indice) li.classList.add('is-complete');
      if (i === this.indice) {
        li.classList.add('is-current');
        li.setAttribute('aria-current', 'step');
      }
    });
  }

  async salvar() {
    try {
      await fetch('/api/rascunho', { method:'POST', body: new FormData(this.form) });
      this.live.textContent = 'Rascunho salvo com sucesso.';
    } catch {
      this.live.textContent = 'Erro ao salvar rascunho. Tente novamente.';
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const f = document.getElementById('cadastro');
  if (f) new WizardAcessivel(f);
});
```

---

## 5. Pattern: Validação inline acessível

```html
<div class="campo">
  <label for="cpf">CPF <span aria-hidden="true">*</span></label>
  <input
    type="text" id="cpf" name="cpf" required
    inputmode="numeric" autocomplete="off"
    pattern="\d{3}\.?\d{3}\.?\d{3}-?\d{2}"
    aria-required="true"
    aria-describedby="cpf-dica cpf-erro"
    data-erro-pattern="Informe um CPF no formato 000.000.000-00.">
  <p id="cpf-dica" class="dica">Formato: 000.000.000-00.</p>
  <p id="cpf-erro" class="erro" role="alert" hidden></p>
</div>
```

**Regras:**
1. `aria-describedby` aponta para **dica** + **erro**.
2. `aria-invalid="true"` só após blur/submit (evita ruído durante digitação).
3. `role="alert"` anuncia imediatamente.
4. Erro tem cor + ícone + texto (não depende só de cor).
5. Em submit, foco vai para sumário com lista de links para cada campo inválido.

---

## 6. Pattern: Modal acessível com focus trap

### 6.1 HTML

```html
<button type="button" id="abrir-confirmacao" data-modal-open="modal-voto">
  Confirmar voto
</button>

<div id="modal-voto" class="modal" role="dialog" aria-modal="true"
     aria-labelledby="modal-voto-titulo" aria-describedby="modal-voto-desc" hidden>
  <div class="modal__overlay" data-modal-close></div>
  <div class="modal__dialog" tabindex="-1">
    <h2 id="modal-voto-titulo">Confirmar voto</h2>
    <p id="modal-voto-desc">
      Você votou em <strong>Museu Imperial</strong>. Esta ação é final
      e não pode ser desfeita.
    </p>
    <div class="modal__acoes">
      <button type="button" data-modal-close>Cancelar</button>
      <button type="button" id="confirmar-voto" class="btn-primario">Confirmar voto</button>
    </div>
    <button type="button" class="modal__fechar" aria-label="Fechar diálogo" data-modal-close>&times;</button>
  </div>
</div>
```

### 6.2 JavaScript (focus trap + ESC + restauração)

```javascript
class ModalAcessivel {
  static FOCUSABLE = [
    'a[href]','button:not([disabled])','input:not([disabled])',
    'select:not([disabled])','textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  constructor(modal) {
    this.modal = modal;
    this.dialog = modal.querySelector('.modal__dialog');
    this.triggerAnterior = null;
    this.onKey = this.onKey.bind(this);
    this.bind();
  }

  bind() {
    this.modal.addEventListener('click', (e) => {
      if (e.target.closest('[data-modal-close]')) this.fechar();
    });
  }

  abrir(trigger) {
    this.triggerAnterior = trigger || document.activeElement;
    this.modal.hidden = false;
    document.querySelectorAll('body > *:not(.modal)').forEach(el => el.inert = true);
    document.addEventListener('keydown', this.onKey);
    const primeiro = this.modal.querySelector(ModalAcessivel.FOCUSABLE);
    (primeiro || this.dialog).focus();
  }

  fechar() {
    this.modal.hidden = true;
    document.querySelectorAll('body > *:not(.modal)').forEach(el => el.inert = false);
    document.removeEventListener('keydown', this.onKey);
    if (this.triggerAnterior) this.triggerAnterior.focus();
  }

  onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); this.fechar(); return; }
    if (e.key !== 'Tab') return;

    const focaveis = [...this.modal.querySelectorAll(ModalAcessivel.FOCUSABLE)]
      .filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);
    if (focaveis.length === 0) { e.preventDefault(); return; }

    const primeiro = focaveis[0];
    const ultimo = focaveis[focaveis.length - 1];
    if (e.shiftKey && document.activeElement === primeiro) {
      e.preventDefault(); ultimo.focus();
    } else if (!e.shiftKey && document.activeElement === ultimo) {
      e.preventDefault(); primeiro.focus();
    }
  }
}

document.querySelectorAll('[data-modal-open]').forEach(btn => {
  const modalEl = document.getElementById(btn.dataset.modalOpen);
  if (!modalEl) return;
  const modal = new ModalAcessivel(modalEl);
  btn.addEventListener('click', () => modal.abrir(btn));
});
```

**Por que `inert`:** mais robusto que `aria-hidden` + `tabindex` manual; bloqueia foco, click e leitor de tela em tudo fora do modal. Suporte: todos os browsers modernos (2023+).

---

## 7. Pattern: File upload acessível

```html
<div class="campo campo--upload">
  <label for="rg-arquivo">
    Documento de identidade (RG ou CNH) <span aria-hidden="true">*</span>
  </label>
  <input
    type="file" id="rg-arquivo" name="rg_arquivo"
    accept=".pdf,.png,.jpg,.jpeg" required aria-required="true"
    aria-describedby="rg-dica rg-status rg-erro">
  <p id="rg-dica" class="dica">Formatos aceitos: PDF, PNG, JPG. Tamanho máximo: 5 MB.</p>
  <p id="rg-status" class="status" aria-live="polite" aria-atomic="true"></p>
  <p id="rg-erro" class="erro" role="alert" hidden></p>
</div>
```

```javascript
const input  = document.getElementById('rg-arquivo');
const status = document.getElementById('rg-status');
const erro   = document.getElementById('rg-erro');
const MAX    = 5 * 1024 * 1024;
const TIPOS  = ['application/pdf','image/png','image/jpeg'];

input.addEventListener('change', () => {
  erro.hidden = true; erro.textContent = '';
  input.removeAttribute('aria-invalid');

  const f = input.files[0];
  if (!f) { status.textContent = 'Nenhum arquivo selecionado.'; return; }

  if (!TIPOS.includes(f.type)) {
    erro.textContent = `Tipo de arquivo inválido: ${f.type}. Use PDF, PNG ou JPG.`;
    erro.hidden = false;
    input.setAttribute('aria-invalid', 'true');
    status.textContent = ''; input.value = '';
    return;
  }
  if (f.size > MAX) {
    erro.textContent = `Arquivo excede 5 MB (${(f.size/1024/1024).toFixed(1)} MB).`;
    erro.hidden = false;
    input.setAttribute('aria-invalid', 'true');
    status.textContent = ''; input.value = '';
    return;
  }
  status.textContent = `Arquivo selecionado: ${f.name} (${(f.size/1024).toFixed(0)} KB).`;
});
```

---

## 8. Erros comuns a evitar

1. `placeholder` no lugar de `<label>` — some quando se digita.
2. `tabindex` positivo (ex.: `tabindex="3"`) — quebra ordem natural.
3. Remover `outline` sem substituto — viola 2.4.7.
4. Cor como único indicador de erro — viola 1.4.1.
5. `aria-hidden="true"` em elemento focável — confunde leitor.
6. `<div onclick>` no lugar de `<button>` — perde teclado/papel/nome.
7. Modal sem focus trap.
8. Modal sem retorno de foco ao trigger.
9. `<select>` que dispara `submit` no `change` — viola 3.2.2.
10. Live region criada após o evento — leitor não anuncia.
11. `role="alert"` em algo persistente — só para mensagens transientes.
12. Auto-foco no primeiro campo do passo — focar no `<h2>` do passo.
13. Múltiplos `<h1>` ou pular níveis (h1->h3) — viola 1.3.1.
14. `required` sem `aria-required="true"` — manter ambos por segurança.
15. CAPTCHA só visual — viola R45.
16. Auto-save silencioso — viola 4.1.3.
17. Mensagem genérica ("Erro!") — viola 3.3.1/3.3.3.
18. `<fieldset>` sem `<legend>` — viola R44.
19. Botão só com ícone sem `aria-label` — viola 4.1.2.
20. `display:none` em conteúdo que devia ser sr-only.

---

## 9. Plano de testes

### 9.1 Ferramentas automatizadas

| Ferramenta | Tipo | Quando | Cobertura |
|---|---|---|---|
| **ASES Web** (`asesweb.governoeletronico.gov.br`) | Online, oficial | Cada release; entrega contratual | eMAG 3.1, % conformidade |
| **axe DevTools** | Extensão Chrome/FF | Cada PR | WCAG 2.1 A/AA (~57% dos issues) |
| **WAVE** | Online + extensão | Revisão visual | WCAG 2.1, contraste, ARIA |
| **Lighthouse** | DevTools/CI | Build CI | WCAG 2.1 (subset) + perf |
| **W3C HTML Validator** | Online | Antes de release | HTML válido (R1) |
| **Color Contrast Analyser** (TPGi) | Desktop | Design review | 1.4.3, 1.4.11 |
| **pa11y** | CLI Node | Pipeline CI/CD | WCAG 2.1 headless |

### 9.2 Cenários manuais

**C1 - Teclado puro:** Tab do início ao fim. Skip link primeiro. Foco visível. Ordem = visual. Sem trap acidental. Enter/Space ativam botões. Setas em radios. ESC fecha modal.

**C2 - NVDA + Chrome:** "Pular para o conteúdo principal, link"; "Cadastro de agente museal IBRAM, cabeçalho nível 1"; "Progresso do cadastro, navegação"; "Passo 2 de 5: Dados profissionais, atual"; "Instituição, obrigatório, edição"; após erro: "O campo Instituição é obrigatório, alerta"; após avançar: "Passo 3 de 5: Endereço".

**C3 - VoiceOver + Safari (macOS/iOS):** Mesmas verificações via `VO+Setas`, rotor: forms.

**C4 - Zoom 200% + Reflow 320px:** Sem corte, sem scroll horizontal. Stepper colapsa, botões empilham.

**C5 - Alto contraste do Windows:** Campos, bordas, foco e botões visíveis. Nada essencial em `background-image` puro.

**C6 - Validação + sumário:** Submeter com 3 campos inválidos. Foco no sumário. "3 erros encontrados". Cada link leva ao campo. `aria-invalid="true"` anunciado.

**C7 - Modal de voto:** Abrir; foco no primeiro elemento. Tab fica preso. ESC fecha. Foco volta para o trigger. Fundo inerte (Tab não alcança).

**C8 - File upload:** PDF válido -> "Arquivo selecionado: nome.pdf, 234 KB". `.exe` ou >5MB -> erro via `role="alert"`, `aria-invalid="true"`, campo limpo.

**C9 - ASES Web (entrega contratual):** Submeter URL homologação. Aceite mínimo: 95% conformidade, zero erros críticos. Anexar relatório ao build.

---

## 10. URLs consultadas

**Fontes oficiais Brasil:**
- https://emag.governoeletronico.gov.br/
- https://emag.governoeletronico.gov.br/emag-3.pdf
- https://www.gov.br/governodigital/pt-br/acessibilidade-e-usuario/acessibilidade-digital/modelo-de-acessibilidade
- https://asesweb.governoeletronico.gov.br/
- https://softwarepublico.gov.br/social/ases

**W3C / WAI:**
- https://www.w3.org/TR/WCAG21/
- https://www.w3.org/WAI/WCAG21/quickref/
- https://www.w3.org/WAI/ARIA/apg/
- https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/examples/dialog/

**Comunidade especializada:**
- https://webaim.org/standards/wcag/checklist
- https://webaim.org/techniques/formvalidation/
- https://www.smashingmagazine.com/2023/02/guide-accessible-form-validation/
- https://hidde.blog/how-to-make-inline-error-messages-accessible/
- https://www.a11y-collective.com/blog/modal-accessibility/
- https://www.a11y-collective.com/blog/aria-current/

**Marco legal Brasil:**
- http://www.planalto.gov.br/ccivil_03/_ato2015-2018/2015/lei/l13146.htm
- http://www.planalto.gov.br/ccivil_03/_ato2004-2006/2004/decreto/d5296.htm
