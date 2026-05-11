/**
 * Participe Ibram — Admin Ajuda JS (Wave 7 / W7-C)
 *
 * Tabs ARIA acessíveis seguindo W3C ARIA APG "Tabs with Manual Activation":
 *   https://www.w3.org/WAI/ARIA/apg/patterns/tabs/
 *
 * WCAG 2.1 AA critérios atendidos:
 *  - 2.1.1  Keyboard: Tab/Shift+Tab, Setas Left/Right, Home/End, Enter/Space
 *  - 2.4.3  Focus Order: tabindex gerenciado (active=0, demais=-1)
 *  - 4.1.2  Name, Role, Value: aria-selected, aria-controls, tabindex
 *  - 1.4.3  Contraste: não altera valores de cor (gerenciado pelo SCSS)
 *
 * Filtro de glossário:
 *  - Query param ?letra=A foca primeiro <dt> cuja inicial é A
 *  - Live region #pi-glossario-live anuncia resultado do filtro
 *
 * ES2018+, sem dependências externas, sem innerHTML com dados externos.
 */

(function () {
  'use strict';

  // ─────────────────────────────────────────────────────────────────────────
  // Tabs ARIA — Manual Activation (W3C APG)
  // ─────────────────────────────────────────────────────────────────────────

  function initTabs(tablistEl) {
    var tabs    = Array.from(tablistEl.querySelectorAll('[role="tab"]'));
    var panels  = tabs.map(function (tab) {
      var panelId = tab.getAttribute('aria-controls');
      return panelId ? document.getElementById(panelId) : null;
    });

    if (!tabs.length) return;

    /**
     * Ativa uma tab pelo índice (manual activation).
     * @param {number} idx
     * @param {boolean} [moveFocus=true]
     */
    function activateTab(idx, moveFocus) {
      tabs.forEach(function (tab, i) {
        var isActive = (i === idx);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        tab.setAttribute('tabindex',      isActive ? '0'    : '-1');
        tab.classList.toggle('pi-tabs__tab--active', isActive);
      });

      panels.forEach(function (panel, i) {
        if (!panel) return;
        if (i === idx) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      });

      if (moveFocus !== false) {
        tabs[idx].focus();
      }

      // Atualiza o hash da URL para deep-link (sem disparar scroll)
      var tabId = tabs[idx].id;
      if (tabId && window.history && window.history.replaceState) {
        window.history.replaceState(null, '', '#' + tabId);
      }
    }

    /**
     * Retorna o índice da tab atualmente ativa.
     * @returns {number}
     */
    function activeIndex() {
      return tabs.findIndex(function (t) {
        return t.getAttribute('aria-selected') === 'true';
      });
    }

    // Clique: ativa a tab clicada (manual)
    tabs.forEach(function (tab, idx) {
      tab.addEventListener('click', function () {
        activateTab(idx);
      });
    });

    // Teclado: setas, Home, End para navegar; Enter/Space para ativar
    tablistEl.addEventListener('keydown', function (e) {
      var current = activeIndex();
      var focused = tabs.indexOf(document.activeElement);
      var target  = focused >= 0 ? focused : current;
      var next    = target;

      switch (e.key) {
        case 'ArrowRight':
          next = (target + 1) % tabs.length;
          e.preventDefault();
          // Move foco mas NÃO ativa (manual activation)
          tabs[next].focus();
          tabs.forEach(function (t, i) {
            t.setAttribute('tabindex', i === next ? '0' : '-1');
          });
          break;

        case 'ArrowLeft':
          next = (target - 1 + tabs.length) % tabs.length;
          e.preventDefault();
          tabs[next].focus();
          tabs.forEach(function (t, i) {
            t.setAttribute('tabindex', i === next ? '0' : '-1');
          });
          break;

        case 'Home':
          e.preventDefault();
          tabs[0].focus();
          tabs.forEach(function (t, i) {
            t.setAttribute('tabindex', i === 0 ? '0' : '-1');
          });
          break;

        case 'End':
          e.preventDefault();
          tabs[tabs.length - 1].focus();
          tabs.forEach(function (t, i) {
            t.setAttribute('tabindex', i === (tabs.length - 1) ? '0' : '-1');
          });
          break;

        case 'Enter':
        case ' ':
          // Ativa a tab que está com foco
          if (focused >= 0) {
            e.preventDefault();
            activateTab(focused);
          }
          break;
      }
    });

    // Restaura tab a partir do hash inicial (#pi-tab-*)
    (function restoreFromHash() {
      var hash = window.location.hash.replace('#', '');
      if (!hash) return;

      var idx = tabs.findIndex(function (t) { return t.id === hash; });
      if (idx >= 0) {
        activateTab(idx, false);
      }
    }());
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Filtro de glossário por letra
  // ─────────────────────────────────────────────────────────────────────────

  function initGlossario() {
    var navEl    = document.querySelector('[data-pi-glossario-nav]');
    var liveEl   = document.getElementById('pi-glossario-live');
    var glossEl  = document.querySelector('.pi-glossario');

    if (!navEl || !glossEl) return;

    // Lê parâmetro ?letra= da URL atual
    function getLetraParam() {
      var params = new URLSearchParams(window.location.search);
      return (params.get('letra') || '').trim().toUpperCase();
    }

    /**
     * Foca o primeiro <dt> cujo texto começa com `letra`.
     * @param {string} letra
     */
    function focusPrimeiroDtComLetra(letra) {
      if (!letra) return;

      var dts     = Array.from(glossEl.querySelectorAll('dt.pi-glossario__term'));
      var target  = dts.find(function (dt) {
        return dt.textContent.trim().toUpperCase().startsWith(letra);
      });

      if (target) {
        // Garante que o elemento pode receber foco
        if (!target.getAttribute('tabindex')) {
          target.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: false });

        if (liveEl) {
          liveEl.textContent = '';
          // Força re-render da live region
          window.requestAnimationFrame(function () {
            liveEl.textContent =
              target.textContent.trim() + ': ' +
              (target.nextElementSibling ? target.nextElementSibling.textContent.trim().substring(0, 60) + '…' : '');
          });
        }
      } else if (liveEl) {
        liveEl.textContent = '';
        window.requestAnimationFrame(function () {
          liveEl.textContent = 'Nenhum termo encontrado para a letra ' + letra + '.';
        });
      }
    }

    // Cliques nos links de letra
    navEl.addEventListener('click', function (e) {
      var link = e.target.closest('[data-letra]');
      if (!link) return;

      e.preventDefault();
      var letra = link.getAttribute('data-letra');
      focusPrimeiroDtComLetra(letra);

      // Atualiza URL sem recarregar
      if (window.history && window.history.pushState) {
        var url = new URL(window.location.href);
        url.searchParams.set('letra', letra);
        window.history.pushState(null, '', url.toString());
      }
    });

    // Processa query param inicial
    var letraInicial = getLetraParam();
    if (letraInicial) {
      focusPrimeiroDtComLetra(letraInicial);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Init
  // ─────────────────────────────────────────────────────────────────────────

  function init() {
    // Inicializa todos os tablists na página
    var tablists = document.querySelectorAll('[role="tablist"]');
    tablists.forEach(function (tablist) {
      initTabs(tablist);
    });

    // Inicializa glossário
    initGlossario();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
