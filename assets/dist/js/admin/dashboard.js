/**
 * Participe Ibram — Admin Dashboard JS (Wave 7 / W7-C)
 *
 * Renders SVG charts from JSON data injected by DashboardController.
 * No external CDN dependency: pure vanilla JS + inline SVG.
 *
 * Charts implemented:
 *  - Pie   : #pi-chart-tipo    (PF/OR/SM)
 *  - Bar   : #pi-chart-status  (status counts)
 *  - Line  : #pi-chart-mes     (monthly series)
 *  - HBar  : #pi-chart-estados (top-10 states)
 *
 * WCAG 1.1.1: each SVG has <title> + <desc>; accessible table toggle via
 *   aria-expanded / hidden on companion tables.
 * WCAG 4.1.3: live region #pi-dash-status announces update time.
 */

(function () {
  'use strict';

  /* ── Colour palette (DSGov tokens, high-contrast AA) ────────────── */
  var COLORS = ['#1351B4', '#2C7BE5', '#57A0E8', '#A3CDED', '#C8DCEF', '#E4F0FA', '#F4C430'];
  var COLOR_STATUS = {
    rascunho: '#888888',
    submetido: '#1351B4',
    em_analise: '#F4C430',
    deferido: '#168821',
    indeferido_aguardando_recurso: '#E52207',
    indeferido_definitivo: '#9E001A',
    em_recurso: '#E06C00',
    em_recurso_presidencia: '#C45500',
  };

  /* ── Utility ─────────────────────────────────────────────────────── */
  function qs(sel) { return document.querySelector(sel); }
  function qsa(sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); }

  function svgEl(tag, attrs) {
    var el = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.keys(attrs).forEach(function (k) { el.setAttribute(k, attrs[k]); });
    return el;
  }

  function polarToXY(cx, cy, r, angleDeg) {
    var rad = (angleDeg - 90) * Math.PI / 180;
    return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) };
  }

  function describeArc(cx, cy, r, startDeg, endDeg) {
    var s = polarToXY(cx, cy, r, startDeg);
    var e = polarToXY(cx, cy, r, endDeg);
    var large = (endDeg - startDeg) > 180 ? 1 : 0;
    return [
      'M', cx, cy,
      'L', s.x.toFixed(2), s.y.toFixed(2),
      'A', r, r, 0, large, 1, e.x.toFixed(2), e.y.toFixed(2),
      'Z',
    ].join(' ');
  }

  function formatLabel(key) {
    return String(key).replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  /* ── Pie chart ───────────────────────────────────────────────────── */
  function renderPie(container, data, titleText, descText) {
    if (!container) { return; }
    var keys = Object.keys(data);
    var values = keys.map(function (k) { return Number(data[k]) || 0; });
    var total = values.reduce(function (a, b) { return a + b; }, 0);

    var W = 280, H = 280, cx = 90, cy = 140, R = 80;
    var svg = svgEl('svg', {
      viewBox: '0 0 ' + W + ' ' + H,
      role: 'img',
      'aria-labelledby': container.id + '-svgtitle ' + container.id + '-svgdesc',
      focusable: 'false',
    });

    var titleEl = svgEl('title', {}); titleEl.id = container.id + '-svgtitle'; titleEl.textContent = titleText;
    var descEl  = svgEl('desc',  {}); descEl.id  = container.id + '-svgdesc';  descEl.textContent = descText || titleText;
    svg.appendChild(titleEl);
    svg.appendChild(descEl);

    if (total === 0) {
      var nodata = svgEl('text', { x: cx, y: cy, 'text-anchor': 'middle', fill: '#666', 'font-size': '14' });
      nodata.textContent = 'Sem dados';
      svg.appendChild(nodata);
      container.appendChild(svg);
      return;
    }

    var currentAngle = 0;
    var legendY = 24;
    keys.forEach(function (key, i) {
      var pct = values[i] / total;
      var sweep = pct * 360;
      if (sweep === 0) { return; }
      var path = svgEl('path', {
        d: describeArc(cx, cy, R, currentAngle, currentAngle + sweep - 0.1),
        fill: COLORS[i % COLORS.length],
        stroke: '#fff',
        'stroke-width': '2',
      });
      var ariaLabel = formatLabel(key) + ': ' + values[i] + ' (' + Math.round(pct * 100) + '%)';
      path.setAttribute('aria-label', ariaLabel);
      svg.appendChild(path);
      currentAngle += sweep;

      // Legend
      var rect = svgEl('rect', { x: 170, y: legendY - 10, width: 14, height: 14, fill: COLORS[i % COLORS.length] });
      svg.appendChild(rect);
      var txt = svgEl('text', { x: 188, y: legendY, 'font-size': '12', fill: '#1c1c1e' });
      txt.textContent = key.toUpperCase() + ': ' + values[i];
      svg.appendChild(txt);
      legendY += 22;
    });

    container.appendChild(svg);
  }

  /* ── Bar chart ───────────────────────────────────────────────────── */
  function renderBar(container, data, titleText, descText) {
    if (!container) { return; }
    var keys = Object.keys(data);
    var values = keys.map(function (k) { return Number(data[k]) || 0; });
    var maxVal = Math.max.apply(null, values.concat([1]));

    var barH = 28, gap = 6, padLeft = 170, padRight = 30, padTop = 20, padBottom = 20;
    var barW = 240;
    var H = padTop + keys.length * (barH + gap) + padBottom;
    var W = padLeft + barW + padRight;

    var svg = svgEl('svg', {
      viewBox: '0 0 ' + W + ' ' + H,
      role: 'img',
      'aria-labelledby': container.id + '-svgtitle ' + container.id + '-svgdesc',
      focusable: 'false',
    });

    var titleEl = svgEl('title', {}); titleEl.id = container.id + '-svgtitle'; titleEl.textContent = titleText;
    var descEl  = svgEl('desc',  {}); descEl.id  = container.id + '-svgdesc';  descEl.textContent = descText || titleText;
    svg.appendChild(titleEl);
    svg.appendChild(descEl);

    if (keys.length === 0) {
      var nodata = svgEl('text', { x: W / 2, y: H / 2, 'text-anchor': 'middle', fill: '#666', 'font-size': '14' });
      nodata.textContent = 'Sem dados';
      svg.appendChild(nodata);
      container.appendChild(svg);
      return;
    }

    keys.forEach(function (key, i) {
      var y = padTop + i * (barH + gap);
      var w = Math.round((values[i] / maxVal) * barW);
      var color = COLOR_STATUS[key] || COLORS[i % COLORS.length];

      var bar = svgEl('rect', {
        x: padLeft, y: y, width: w, height: barH,
        fill: color,
        rx: '3',
        'aria-label': formatLabel(key) + ': ' + values[i],
      });
      svg.appendChild(bar);

      var lbl = svgEl('text', {
        x: padLeft - 6, y: y + barH / 2 + 4,
        'text-anchor': 'end',
        'font-size': '11',
        fill: '#1c1c1e',
      });
      lbl.textContent = formatLabel(key);
      svg.appendChild(lbl);

      var val = svgEl('text', {
        x: padLeft + w + 4, y: y + barH / 2 + 4,
        'font-size': '11',
        fill: '#1c1c1e',
      });
      val.textContent = String(values[i]);
      svg.appendChild(val);
    });

    container.appendChild(svg);
  }

  /* ── Line chart ──────────────────────────────────────────────────── */
  function renderLine(container, series, titleText, descText) {
    if (!container) { return; }
    var W = 600, H = 200, padL = 40, padR = 20, padT = 20, padB = 40;
    var plotW = W - padL - padR;
    var plotH = H - padT - padB;
    var n = series.length;

    var svg = svgEl('svg', {
      viewBox: '0 0 ' + W + ' ' + H,
      role: 'img',
      'aria-labelledby': container.id + '-svgtitle ' + container.id + '-svgdesc',
      focusable: 'false',
    });

    var titleEl = svgEl('title', {}); titleEl.id = container.id + '-svgtitle'; titleEl.textContent = titleText;
    var descEl  = svgEl('desc',  {}); descEl.id  = container.id + '-svgdesc';  descEl.textContent = descText || titleText;
    svg.appendChild(titleEl);
    svg.appendChild(descEl);

    if (n === 0) {
      var nodata = svgEl('text', { x: W / 2, y: H / 2, 'text-anchor': 'middle', fill: '#666', 'font-size': '14' });
      nodata.textContent = 'Sem dados';
      svg.appendChild(nodata);
      container.appendChild(svg);
      return;
    }

    var maxVal = Math.max.apply(null, series.map(function (d) { return Number(d.total) || 0; }).concat([1]));

    function xFor(i) { return padL + (n > 1 ? i / (n - 1) : 0.5) * plotW; }
    function yFor(v) { return padT + plotH - (v / maxVal) * plotH; }

    // Gridlines.
    for (var g = 0; g <= 4; g++) {
      var gy = padT + (g / 4) * plotH;
      var gridLine = svgEl('line', { x1: padL, y1: gy, x2: padL + plotW, y2: gy, stroke: '#e0e0e0', 'stroke-width': '1' });
      svg.appendChild(gridLine);
    }

    // Axes.
    svg.appendChild(svgEl('line', { x1: padL, y1: padT, x2: padL, y2: padT + plotH, stroke: '#888', 'stroke-width': '1' }));
    svg.appendChild(svgEl('line', { x1: padL, y1: padT + plotH, x2: padL + plotW, y2: padT + plotH, stroke: '#888', 'stroke-width': '1' }));

    // Line path.
    var pathD = series.map(function (d, i) {
      return (i === 0 ? 'M' : 'L') + xFor(i).toFixed(1) + ',' + yFor(Number(d.total) || 0).toFixed(1);
    }).join(' ');
    svg.appendChild(svgEl('path', { d: pathD, fill: 'none', stroke: '#1351B4', 'stroke-width': '2' }));

    // Points + x-labels.
    series.forEach(function (d, i) {
      var px = xFor(i), py = yFor(Number(d.total) || 0);
      var circle = svgEl('circle', {
        cx: px.toFixed(1), cy: py.toFixed(1), r: '4',
        fill: '#1351B4', stroke: '#fff', 'stroke-width': '2',
        'aria-label': d.mes + ': ' + d.total,
      });
      svg.appendChild(circle);

      if (n <= 12 || i % 3 === 0) {
        var xlbl = svgEl('text', {
          x: px.toFixed(1), y: padT + plotH + 14,
          'text-anchor': 'middle', 'font-size': '9', fill: '#555',
          transform: 'rotate(-45,' + px.toFixed(1) + ',' + (padT + plotH + 14) + ')',
        });
        xlbl.textContent = d.mes ? String(d.mes).slice(2) : '';
        svg.appendChild(xlbl);
      }
    });

    container.appendChild(svg);
  }

  /* ── Horizontal bar (top10 estados) ─────────────────────────────── */
  function renderHBar(container, data, titleText, descText) {
    renderBar(container, data, titleText, descText);
  }

  /* ── Toggle table buttons ─────────────────────────────────────────  */
  function initToggles() {
    qsa('.pi-chart-alt-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        var targetId = btn.getAttribute('aria-controls');
        var target = targetId ? document.getElementById(targetId) : null;
        if (target) {
          if (expanded) {
            target.hidden = true;
          } else {
            target.hidden = false;
          }
        }
      });
    });
  }

  /* ── AJAX refresh ────────────────────────────────────────────────── */
  function initRefresh() {
    var btn = qs('#pi-dash-refresh');
    var status = qs('#pi-dash-status');
    if (!btn) { return; }

    btn.addEventListener('click', function () {
      var nonce = btn.getAttribute('data-nonce') || '';
      var ajaxUrl = btn.getAttribute('data-ajaxurl') || '';
      if (!ajaxUrl) { return; }

      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');

      var formData = new FormData();
      formData.append('action', 'pi_admin_dashboard_metrics');
      formData.append('_wpnonce', nonce);

      fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
          if (resp && resp.success && resp.data) {
            updateKpis(resp.data);
            updateCharts(resp.data);
            if (status) {
              var now = new Date();
              var hh = String(now.getHours()).padStart(2, '0');
              var mm = String(now.getMinutes()).padStart(2, '0');
              status.textContent = 'Dados atualizados às ' + hh + ':' + mm;
            }
          }
        })
        .catch(function () {
          if (status) { status.textContent = 'Erro ao atualizar dados. Tente novamente.'; }
        })
        .finally(function () {
          btn.disabled = false;
          btn.removeAttribute('aria-busy');
        });
    });
  }

  function updateKpis(data) {
    var map = {
      cadastros_pendentes:  (data.cadastros_por_status ? ((data.cadastros_por_status.submetido || 0) + (data.cadastros_por_status.em_analise || 0)) : 0),
      cadastros_em_analise: (data.cadastros_por_status ? (data.cadastros_por_status.em_analise || 0) : 0),
      editais_ativos:       data.editais_ativos || 0,
      solicitacoes_lgpd:    data.solicitacoes_lgpd || 0,
    };
    Object.keys(map).forEach(function (k) {
      var el = document.querySelector('[data-pi-metric="' + k + '"]');
      if (el) { el.textContent = String(map[k]); }
    });
  }

  function updateCharts(data) {
    clearChart('pi-chart-tipo');
    clearChart('pi-chart-status');
    clearChart('pi-chart-mes');
    clearChart('pi-chart-estados');

    renderPie(qs('#pi-chart-tipo'), data.cadastrosTipo || data.cadastros_por_tipo || {}, 'Cadastros por tipo', 'Gráfico de pizza: distribuição de cadastros por tipologia.');
    renderBar(qs('#pi-chart-status'), data.cadastrosStatus || data.cadastros_por_status || {}, 'Cadastros por status', 'Gráfico de barras: cadastros por status de análise.');
    renderLine(qs('#pi-chart-mes'), data.cadastrosMes || data.cadastros_por_mes || [], 'Cadastros por mês', 'Gráfico de linha: evolução mensal de cadastros submetidos.');

    var estados = data.top10Estados || data.cadastros_por_estado || {};
    var top10 = {};
    var keys = Object.keys(estados).slice(0, 10);
    keys.forEach(function (k) { top10[k] = estados[k]; });
    renderHBar(qs('#pi-chart-estados'), top10, 'Top 10 estados', 'Top 10 estados com mais cadastros deferidos.');
  }

  function clearChart(id) {
    var el = document.getElementById(id);
    if (el) { el.innerHTML = ''; }
  }

  /* ── Bootstrap ───────────────────────────────────────────────────── */
  function init() {
    var dataEl = document.getElementById('pi-dashboard-data');
    if (!dataEl) { return; }

    var data = {};
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { data = {}; }

    renderPie(
      qs('#pi-chart-tipo'),
      data.cadastrosTipo || {},
      'Cadastros por tipo (PF, OR, SM)',
      'Gráfico de pizza: distribuição de cadastros por tipologia de agente cultural.'
    );

    renderBar(
      qs('#pi-chart-status'),
      data.cadastrosStatus || {},
      'Cadastros por status',
      'Gráfico de barras: contagem de cadastros agrupados por status de análise.'
    );

    renderLine(
      qs('#pi-chart-mes'),
      data.cadastrosMes || [],
      'Cadastros submetidos nos últimos 12 meses',
      'Gráfico de linha: evolução mensal de cadastros submetidos.'
    );

    var estados = data.top10Estados || {};
    renderHBar(
      qs('#pi-chart-estados'),
      estados,
      'Top 10 estados com mais cadastros deferidos',
      'Gráfico de barras horizontais: estados com maior número de cadastros deferidos.'
    );

    initToggles();
    initRefresh();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
