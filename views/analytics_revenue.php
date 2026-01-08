<?php
/** @var string $title */
/** @var array $templates */
/** @var array $favoritesV2 */
?>
<h1>Analýza</h1>

<style>
.v2-controls { display:grid; grid-template-columns: 1fr 320px; gap:1.2rem; align-items:start; }
.v2-output { margin-top:1rem; }
.v2-form { display:flex; flex-direction:column; gap:0.8rem; }
.v2-form label { font-weight:600; display:block; margin-bottom:0.15rem; }
.v2-row { display:flex; gap:0.6rem; flex-wrap:wrap; }
.v2-row .field { flex:1 1 0; min-width:140px; }
.v2-form input:not([type="checkbox"]), .v2-form select { width:100%; padding:0.4rem 0.5rem; }
.v2-form input[type="checkbox"] { width:auto; padding:0; }
.checkbox-label { display:flex; align-items:center; gap:0.45rem; font-weight:600; }
.notice { padding:0.6rem 0.8rem; border:1px solid #e0e0e0; border-radius:8px; background:#f7f9fc; }
.muted { color:#607d8b; }
.error { color:#c62828; font-weight:600; }
.chips { display:flex; gap:0.4rem; flex-wrap:wrap; margin-top:0.3rem; }
.chip { background:#eceff1; border-radius:999px; padding:0.25rem 0.55rem; display:inline-flex; align-items:center; gap:0.35rem; }
.chip button { border:0; background:none; cursor:pointer; font-weight:700; color:#c62828; }
.help-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #eceff1;
  color: #37474f;
  font-size: 0.75rem;
  margin-left: 0.35rem;
  cursor: help;
}
.dropdown { border:1px solid #d0d7de; border-radius:6px; padding:0.35rem 0.45rem; background:#fff; max-height:180px; overflow:auto; margin-top:0.2rem; }
.dropdown div { padding:0.2rem 0.1rem; cursor:pointer; }
.dropdown div:hover { background:#f1f5f9; }
.result-table { width:100%; border-collapse:collapse; margin-top:0.6rem; }
.result-table th, .result-table td { border:1px solid #e0e0e0; padding:0.35rem 0.4rem; text-align:left; }
.result-table tfoot td { font-weight:700; background:#f5f7fa; }
.favorite-list { list-style:none; padding:0; margin:0; }
.favorite-list li { border:1px solid #eceff1; border-radius:8px; padding:0.55rem 0.7rem; margin-bottom:0.5rem; display:flex; justify-content:space-between; gap:0.6rem; }
.favorite-title { font-weight:600; }
.favorite-actions { display:flex; align-items:center; gap:0.45rem; }
.favorite-actions button { background:none; border:0; cursor:pointer; font-size:0.95rem; color:#1e88e5; }
.favorite-actions .favorite-delete { color:#c62828; font-weight:700; margin-left:0.2rem; }
.favorite-empty { font-size:0.9rem; color:#78909c; }
.chart-box { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:0.7rem; }
</style>

<div class="v2-controls">
  <div>
    <form id="v2-form" class="v2-form" action="javascript:void(0);" style="margin-bottom:1rem;">
      <div class="field">
        <label for="template-id">Šablona</label>
        <select id="template-id" name="template_id"></select>
        <p class="muted" id="template-desc"></p>
      </div>

      <div id="param-fields"></div>

      <div class="field" id="contact-field" style="display:none;">
        <label>Kontakt (IČ / e-mail / firma)</label>
        <input type="text" id="contact-search" placeholder="Hledat..." autocomplete="off" />
        <div id="contact-dropdown" class="dropdown" style="display:none;"></div>
        <div class="chips" id="contact-chips"></div>
        <p class="muted">Vyberte 0–N kontaktů; prázdné = všechny.</p>
      </div>

      <div class="field" id="product-field" style="display:none;">
        <label>Produkty (SKU / název)</label>
        <input type="text" id="product-search" placeholder="Hledat..." autocomplete="off" />
        <div id="product-dropdown" class="dropdown" style="display:none;"></div>
        <div class="chips" id="product-chips"></div>
        <p class="muted">Vyberte 0–N produktů; prázdné = všechny.</p>
      </div>

      <div class="field" id="eshop-field" style="display:none;">
        <label for="eshop-source">E-shop</label>
        <select id="eshop-source" name="eshop_source" multiple size="6"></select>
        <p class="muted">Nezvolíte-li nic, použijí se všechny kanály.</p>
      </div>

      <button type="submit">Spustit dotaz</button>
      <div id="v2-error" class="error" style="display:none;"></div>
    </form>
  </div>

  <div>
    <div class="notice" style="margin-bottom:1rem;">
      <strong>Oblíbené nastavení</strong>
      <div class="v2-row" style="margin-top:0.4rem;">
        <div class="field">
          <label for="fav-title">Název</label>
          <input type="text" id="fav-title" placeholder="Např. Klient 123 - posledních 18M" />
        </div>
        <div class="field" style="align-self:flex-end;">
          <label><input type="checkbox" id="fav-public" checked /> Sdílet s ostatními</label>
        </div>
        <button type="button" id="fav-save">Uložit oblíbené</button>
      </div>
    </div>

    <h3>Moje oblíbené</h3>
    <ul class="favorite-list" id="favorite-mine"></ul>
    <h3>Oblíbené ostatních</h3>
    <ul class="favorite-list" id="favorite-shared"></ul>
  </div>
</div>

<div class="v2-output">
  <div class="chart-box">
    <canvas id="v2-chart" height="200"></canvas>
  </div>
  <div id="v2-result"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
  const templates = <?= json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const favoritesInit = <?= json_encode($favoritesV2, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  const form = document.getElementById('v2-form');
  const selectTpl = document.getElementById('template-id');
  const descBox = document.getElementById('template-desc');
  const paramBox = document.getElementById('param-fields');
  const contactField = document.getElementById('contact-field');
  const contactInput = document.getElementById('contact-search');
  const contactDropdown = document.getElementById('contact-dropdown');
  const contactChips = document.getElementById('contact-chips');
  const productField = document.getElementById('product-field');
  const productInput = document.getElementById('product-search');
  const productDropdown = document.getElementById('product-dropdown');
  const productChips = document.getElementById('product-chips');
  const eshopField = document.getElementById('eshop-field');
  const eshopSelect = document.getElementById('eshop-source');
  const errorBox = document.getElementById('v2-error');
  const resultBox = document.getElementById('v2-result');
  const chartCanvas = document.getElementById('v2-chart');
  const chartBox = chartCanvas?.closest('.chart-box');

  const favMine = document.getElementById('favorite-mine');
  const favShared = document.getElementById('favorite-shared');
  const favTitle = document.getElementById('fav-title');
  const favPublic = document.getElementById('fav-public');
  const favSave = document.getElementById('fav-save');

  let chart;
  const state = {
    contacts: [],
    products: [],
    favorites: favoritesInit || { mine: [], shared: [] },
    lastRows: [],
  };

  // Populate template select
  Object.entries(templates).forEach(([id, tpl]) => {
    const opt = document.createElement('option');
    opt.value = id;
    opt.textContent = tpl.title || id;
    selectTpl.appendChild(opt);
  });

  function renderParams() {
    paramBox.innerHTML = '';
    const tpl = templates[selectTpl.value];
    descBox.textContent = tpl?.description || '';
    let hasContact = false;
    let hasProduct = false;
    let hasEshop = false;
    let dateRow = null;
    let toggleRow = null;

    const addHelp = (labelEl, helpText) => {
      if (!helpText) return;
      const help = document.createElement('span');
      help.className = 'help-icon';
      help.title = helpText;
      help.textContent = '?';
      labelEl.appendChild(help);
    };

    (tpl?.params || []).forEach((p) => {
      const wrap = document.createElement('div');
      wrap.className = 'field';
      const useDateRow = p.type === 'date' && (p.name === 'start_date' || p.name === 'end_date');
      const useToggleRow = p.name === 'active_only' || p.name === 'movement_direction';
      const appendWrap = () => {
        if (useDateRow) {
          if (!dateRow) {
            dateRow = document.createElement('div');
            dateRow.className = 'v2-row';
            paramBox.appendChild(dateRow);
          }
          dateRow.appendChild(wrap);
        } else if (useToggleRow) {
          if (!toggleRow) {
            toggleRow = document.createElement('div');
            toggleRow.className = 'v2-row';
            paramBox.appendChild(toggleRow);
          }
          toggleRow.appendChild(wrap);
        } else {
          paramBox.appendChild(wrap);
        }
      };
      const label = document.createElement('label');
      label.textContent = p.label || p.name;
      addHelp(label, p.help);
      wrap.appendChild(label);
      let input;
      switch (p.type) {
        case 'date':
          input = document.createElement('input');
          input.type = 'date';
          if (p.default) input.value = p.default;
          break;
        case 'int':
          input = document.createElement('input');
          input.type = 'number';
          input.step = '1';
          if (p.default !== undefined) input.value = p.default;
          break;
        case 'string':
          input = document.createElement('input');
          input.type = 'text';
          if (p.default !== undefined) input.value = p.default;
          break;
        case 'bool':
          input = document.createElement('input');
          input.type = 'checkbox';
          input.checked = Boolean(p.default);
          input.name = p.name;
          label.className = 'checkbox-label';
          label.textContent = '';
          label.appendChild(input);
          label.appendChild(document.createTextNode(p.label || p.name));
          addHelp(label, p.help);
          appendWrap();
          return;
        case 'enum': {
          input = document.createElement('select');
          (p.values || []).forEach((val) => {
            const opt = document.createElement('option');
            if (typeof val === 'object') {
              opt.value = val.value ?? '';
              opt.textContent = val.label ?? val.value ?? '';
            } else {
              opt.value = val;
              opt.textContent = val;
            }
            input.appendChild(opt);
          });
          if (p.default !== undefined) input.value = p.default;
          break;
        }
        case 'enum_multi': {
          if (p.name === 'eshop_source') {
            hasEshop = true;
            return; // handled in separate field
          }
          input = document.createElement('select');
          input.multiple = true;
          (p.values || []).forEach((val) => {
            const opt = document.createElement('option');
            if (typeof val === 'object') {
              opt.value = val.value ?? '';
              opt.textContent = val.label ?? val.value ?? '';
            } else {
              opt.value = val;
              opt.textContent = val === 'vse' ? 'Vše' : val;
            }
            input.appendChild(opt);
          });
          break;
        }
        case 'contact_multi':
          hasContact = true;
          return; // handled separately
        case 'product_multi':
          hasProduct = true;
          return; // handled separately
        default:
          input = document.createElement('input');
          input.type = 'text';
      }
      input.name = p.name;
      wrap.appendChild(input);
      appendWrap();
    });

    contactField.style.display = hasContact ? 'block' : 'none';
    productField.style.display = hasProduct ? 'block' : 'none';
    eshopField.style.display = hasEshop ? 'block' : 'none';
    if (!hasEshop) {
      eshopSelect.innerHTML = '';
    } else {
      // populate eshop options from template param definition
      const eshopParam = tpl.params.find(pr => pr.name === 'eshop_source');
      eshopSelect.innerHTML = '';
      (eshopParam?.values || []).forEach(val => {
      const opt = document.createElement('option');
      const value = (typeof val === 'object') ? (val.value ?? '') : val;
      const labelText = (typeof val === 'object') ? (val.label ?? val.value ?? '') : val;
      opt.value = value;
      opt.textContent = value === 'vsechny' ? 'Všechny (součet všech)' : (value === 'vse' ? 'Vše' : labelText);
      eshopSelect.appendChild(opt);
    });
  }
  }

  function toParams() {
    const tpl = templates[selectTpl.value];
    const params = {};
    (tpl?.params || []).forEach((p) => {
      if (p.type === 'contact_multi') {
        params[p.name] = state.contacts.map(c => c.id);
        return;
      }
      if (p.type === 'product_multi') {
        params[p.name] = state.products.map(item => item.sku);
        return;
      }
      if (p.type === 'bool') {
        const input = paramBox.querySelector(`[name="${p.name}"]`);
        params[p.name] = input && input.checked ? 1 : 0;
        return;
      }
      if (p.type === 'enum_multi') {
        if (p.name === 'eshop_source') {
          params[p.name] = Array.from(eshopSelect.selectedOptions || []).map(o => o.value);
        } else {
          const select = paramBox.querySelector(`[name="${p.name}"]`);
          params[p.name] = Array.from(select?.selectedOptions || []).map(o => o.value);
        }
        return;
      }
      const input = paramBox.querySelector(`[name="${p.name}"]`);
      params[p.name] = input ? input.value : '';
    });
    return params;
  }

  function renderFavorites() {
    const renderList = (items, node) => {
      node.innerHTML = '';
      if (!items || !items.length) {
        const li = document.createElement('li');
        li.className = 'favorite-empty';
        li.textContent = 'Zatím nic uloženo.';
        node.appendChild(li);
        return;
      }
      items.forEach((fav) => {
        const li = document.createElement('li');
        const left = document.createElement('div');
        const title = document.createElement('a');
        title.className = 'favorite-title';
        title.href = '#';
        title.textContent = fav.title;
        title.onclick = (e) => { e.preventDefault(); loadFavorite(fav, true); };
        left.appendChild(title);
        const actions = document.createElement('div');
        actions.className = 'favorite-actions';
        if (node === favMine) {
          const btnDel = document.createElement('button');
          btnDel.type = 'button';
          btnDel.className = 'favorite-delete';
          btnDel.textContent = '×';
          btnDel.title = 'Smazat';
          btnDel.onclick = () => deleteFavorite(fav.id);
          actions.appendChild(btnDel);
        }
        li.appendChild(left);
        li.appendChild(actions);
        node.appendChild(li);
      });
    };
    renderList(state.favorites.mine, favMine);
    renderList(state.favorites.shared, favShared);
  }

  function renderTable(rows, tplId) {
    if (!rows || !rows.length) {
      resultBox.innerHTML = '<p class="muted">Žádná data.</p>';
      return;
    }
    const isProducts = tplId === 'products';
    const cols = Object.keys(rows[0])
      .filter((c) => !['serie_key','serie_label','qty'].includes(c))
      .filter((c) => !(isProducts && c === 'mj'));
    const table = document.createElement('table');
    table.className = 'result-table';
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');
    cols.forEach((c) => {
      const th = document.createElement('th');
      th.textContent = c;
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    const totals = {};
    const productUnits = isProducts ? new Set(rows.map(r => r.mj).filter(Boolean)) : null;
    rows.forEach((r) => {
      const tr = document.createElement('tr');
      cols.forEach((c) => {
        const td = document.createElement('td');
        if (isProducts && c === 'mnozstvi') {
          const valNum = Number(r[c]);
          const rounded = Number.isNaN(valNum) ? r[c] : Math.round(valNum);
          const unit = r.mj || '';
          td.textContent = unit ? `${rounded} ${unit}` : String(rounded);
        } else {
          td.textContent = r[c] ?? '';
        }
        tr.appendChild(td);
        const val = Number(r[c]);
        if (!Number.isNaN(val)) {
          totals[c] = (totals[c] || 0) + val;
        }
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    if (tplId !== 'stock_value_by_month') {
      const tfoot = document.createElement('tfoot');
      const trf = document.createElement('tr');
      cols.forEach((c, idx) => {
        const td = document.createElement('td');
        if (idx === 0) {
          td.textContent = 'Celkem';
        } else if (totals[c] !== undefined) {
          if (isProducts && c === 'mnozstvi') {
            const rounded = Math.round(totals[c]);
            const unit = (productUnits && productUnits.size === 1) ? Array.from(productUnits)[0] : '';
            td.textContent = unit ? `${rounded} ${unit}` : String(rounded);
          } else {
            td.textContent = Math.round(totals[c]);
          }
        }
        trf.appendChild(td);
      });
      tfoot.appendChild(trf);
      table.appendChild(tfoot);
    }
    resultBox.innerHTML = '';
    resultBox.appendChild(table);
  }

  function renderChart(rows) {
    if (!rows || !rows.length || !window.Chart) {
      if (chart) chart.destroy();
      chart = null;
      return;
    }
    const sample = rows[0] || {};
    const xKey = 'mesic' in sample ? 'mesic' : ('stav_ke_dni' in sample ? 'stav_ke_dni' : Object.keys(sample)[0]);
    const exclude = new Set([xKey, 'serie_key', 'serie_label']);
    const keys = Object.keys(sample);
    let yKey = keys.find(k => k.toLowerCase() === 'trzby') || keys.find(k => k.toLowerCase() === 'hodnota_czk') || null;
    if (!yKey) {
      for (const k of keys) {
        if (exclude.has(k)) continue;
        if (typeof sample[k] === 'number' || !isNaN(Number(sample[k]))) {
          yKey = k; break;
        }
      }
    }
    if (!yKey) yKey = keys.find(k => !exclude.has(k)) || xKey;

    const parseX = (raw) => {
      const str = String(raw).trim();
      const iso = str.length === 7 ? `${str}-01` : str;
      const t = Date.parse(iso);
      return Number.isNaN(t) ? null : t;
    };
    const bySeries = new Map();
    rows.forEach((r) => {
      const key = r.serie_key || r.serie_label || 'all';
      const label = (r.serie_label && String(r.serie_label).trim() !== '') ? r.serie_label : 'Celkem';
      const xVal = parseX(r[xKey]);
      if (xVal === null) return;
      if (!bySeries.has(key)) bySeries.set(key, { label, points: [] });
      bySeries.get(key).points.push({ x: xVal, y: Number(r[yKey] || 0) });
    });
    const datasets = Array.from(bySeries.values()).map((s, idx) => {
      s.points.sort((a,b) => a.x - b.x);
      const color = palette(idx);
      return {
        label: s.label,
        data: s.points,
        borderColor: color,
        backgroundColor: color + '33',
        tension: 0.2,
      };
    });
    if (chart) chart.destroy();
    chart = new Chart(chartCanvas.getContext('2d'), {
      type: 'line',
      data: { datasets },
      options: {
        parsing: { xAxisKey: 'x', yAxisKey: 'y' },
        scales: {
          x: { 
            type: 'linear',
            title: { display: true, text: xKey },
            ticks: {
              callback: (val) => {
                const d = new Date(val);
                return Number.isNaN(d.getTime()) ? val : d.toISOString().slice(0,7);
              }
            }
          },
          y: { title: { display: true, text: yKey }, beginAtZero: true },
        },
        plugins: { 
          legend: { display: true, position: 'bottom' },
          tooltip: {
            callbacks: {
              title: (items) => {
                if (!items.length) return '';
                const d = new Date(items[0].parsed.x);
                return Number.isNaN(d.getTime()) ? '' : d.toISOString().slice(0,7);
              },
              label: (ctx) => {
                const name = ctx.dataset?.label || '';
                const val = ctx.parsed?.y ?? 0;
                return `${name}: ${Number(val).toLocaleString('cs-CZ')}`;
              }
            }
          }
        },
      },
    });
  }

  function updateChartVisibility() {
    const tpl = templates[selectTpl.value];
    const hideChart = Boolean(tpl?.hide_chart);
    if (!chartBox) return;
    if (hideChart) {
      chartBox.style.display = 'none';
      if (chart) chart.destroy();
      chart = null;
    } else {
      chartBox.style.display = '';
    }
  }

  function palette(i) {
    const colors = ['#1565c0','#ef6c00','#2e7d32','#8e24aa','#c62828','#00838f','#6d4c41','#5d4037','#283593','#ad1457'];
    return colors[i % colors.length];
  }

  async function toJsonSafe(res) {
    const text = await res.text();
    let data = null;
    try { data = text ? JSON.parse(text) : null; } catch (_) {
      const snippet = text ? text.slice(0, 200) : '(prázdná odpověď)';
      throw new Error('Neplatná JSON odpověď: ' + snippet);
    }
    if (!res.ok) {
      const message = (data && data.error) ? data.error : (text || `HTTP ${res.status}`);
      throw new Error(message);
    }
    if (!data) {
      const snippet = text ? text.slice(0, 200) : '(prázdná odpověď)';
      throw new Error('Neplatná odpověď: ' + snippet);
    }
    return data;
  }

  async function runQuery() {
    errorBox.style.display = 'none';
    const tplId = selectTpl.value;
    const res = await fetch('/analytics/run', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ template_id: tplId, params: toParams() })
    });
    const data = await toJsonSafe(res);
    if (!data.ok) throw new Error(data.error || 'Dotaz selhal.');
    state.lastRows = data.rows || [];
    updateChartVisibility();
    if (!templates[tplId]?.hide_chart) {
      renderChart(state.lastRows);
    }
    renderTable(state.lastRows, tplId);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await runQuery();
    } catch (err) {
      errorBox.style.display = 'block';
      errorBox.textContent = err.message || 'Dotaz selhal.';
    }
  });

  async function loadFavorite(fav, run = false) {
    if (!fav || !fav.template_id) return;
    if (!templates[fav.template_id]) {
      alert('Šablona už neexistuje.');
      return;
    }
    selectTpl.value = fav.template_id;
    onTemplateChange();
    const p = fav.params || {};
    // set params
    const tplParams = templates[fav.template_id].params || [];
    const contactIds = [];
    let productSkus = [];
    tplParams.forEach((param) => {
      if (param.type === 'contact_multi') {
        (p[param.name] || []).forEach(id => contactIds.push(id));
      } else if (param.type === 'product_multi') {
        productSkus = Array.isArray(p[param.name]) ? p[param.name] : [];
      } else if (param.type === 'bool') {
        const input = paramBox.querySelector(`[name="${param.name}"]`);
        if (input && Object.prototype.hasOwnProperty.call(p, param.name)) {
          input.checked = Boolean(p[param.name]);
        }
      } else if (param.type === 'enum_multi') {
        const select = paramBox.querySelector(`[name="${param.name}"]`) || eshopSelect;
        Array.from(select?.options || []).forEach(opt => {
          opt.selected = Array.isArray(p[param.name]) && p[param.name].includes(opt.value);
        });
      } else {
        const input = paramBox.querySelector(`[name="${param.name}"]`);
        if (input) input.value = p[param.name] || '';
      }
    });
    state.products = (productSkus || []).map(sku => ({ sku, nazev: '' }));
    if (contactIds.length) {
      try {
        const params = new URLSearchParams();
        contactIds.forEach(id => params.append('ids[]', id));
        const res = await fetch('/analytics/contacts/by-id?' + params.toString());
        const data = await toJsonSafe(res);
        if (data.ok) {
          state.contacts = data.items || [];
        }
      } catch (e) {
        // ignore, fallback to empty labels
      }
    } else {
      state.contacts = [];
    }
    renderChips();
    if (run) {
      try { await runQuery(); } catch (err) { errorBox.style.display='block'; errorBox.textContent = err.message || 'Dotaz selhal.'; }
    }
  }

  async function deleteFavorite(id) {
    const res = await fetch('/analytics/favorite/delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await toJsonSafe(res);
    if (data.ok) {
      state.favorites = data.favorites || { mine: [], shared: [] };
      renderFavorites();
    }
  }

  favSave.addEventListener('click', async () => {
    const title = favTitle.value.trim();
    if (!title) {
      alert('Zadejte název.');
      return;
    }
    const res = await fetch('/analytics/favorite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title,
        template_id: selectTpl.value,
        params: toParams(),
        is_public: favPublic.checked ? 1 : 0,
      }),
    });
    const data = await toJsonSafe(res);
    if (!data.ok) {
      alert(data.error || 'Uložení selhalo.');
      return;
    }
    state.favorites = data.favorites || { mine: [], shared: [] };
    renderFavorites();
  });

  async function refreshFavorites() {
    const res = await fetch('/analytics/favorite/list');
    const data = await toJsonSafe(res);
    if (data.ok) {
      state.favorites = data.favorites || { mine: [], shared: [] };
      renderFavorites();
    }
  }

  function renderChips() {
    contactChips.innerHTML = '';
    state.contacts.forEach((c) => {
      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.textContent = c.label || (`Kontakt #${c.id}`);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = 'x';
      btn.onclick = () => {
        state.contacts = state.contacts.filter(item => item.id !== c.id);
        renderChips();
      };
      chip.appendChild(btn);
      contactChips.appendChild(chip);
    });
    productChips.innerHTML = '';
    state.products.forEach((p) => {
      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.textContent = p.nazev ? `${p.sku} - ${p.nazev}` : p.sku;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = 'x';
      btn.onclick = () => {
        state.products = state.products.filter(item => item.sku !== p.sku);
        renderChips();
      };
      chip.appendChild(btn);
      productChips.appendChild(chip);
    });
  }

  let suggestTimeout;
  contactInput.addEventListener('input', () => {
    const q = contactInput.value.trim();
    if (suggestTimeout) clearTimeout(suggestTimeout);
    if (q.length < 2) {
      contactDropdown.style.display = 'none';
      return;
    }
    suggestTimeout = setTimeout(async () => {
      const res = await fetch('/analytics/contacts?q=' + encodeURIComponent(q));
      const data = await toJsonSafe(res);
      const items = data.items || [];
      contactDropdown.innerHTML = '';
      if (!items.length) {
        contactDropdown.innerHTML = '<div class="muted">Nic nenalezeno</div>';
      } else {
        items.forEach((it) => {
          const div = document.createElement('div');
          div.textContent = it.label;
          div.onclick = () => {
            if (!state.contacts.some(c => c.id === it.id)) {
              state.contacts.push(it);
              renderChips();
            }
            contactDropdown.style.display = 'none';
            contactInput.value = '';
          };
          contactDropdown.appendChild(div);
        });
      }
      contactDropdown.style.display = 'block';
    }, 250);
  });

  let productSuggestTimeout;
  productInput.addEventListener('input', () => {
    const q = productInput.value.trim();
    if (productSuggestTimeout) clearTimeout(productSuggestTimeout);
    if (q.length < 2) {
      productDropdown.style.display = 'none';
      return;
    }
    productSuggestTimeout = setTimeout(async () => {
      const res = await fetch('/products/search?q=' + encodeURIComponent(q));
      const data = await toJsonSafe(res);
      const items = data.items || [];
      productDropdown.innerHTML = '';
      if (!items.length) {
        productDropdown.innerHTML = '<div class="muted">Nic nenalezeno</div>';
      } else {
        items.forEach((it) => {
          const div = document.createElement('div');
          div.textContent = it.nazev ? `${it.sku} - ${it.nazev}` : it.sku;
          div.onclick = () => {
            if (!state.products.some(p => p.sku === it.sku)) {
              state.products.push({ sku: it.sku, nazev: it.nazev || '' });
              renderChips();
            }
            productDropdown.style.display = 'none';
            productInput.value = '';
          };
          productDropdown.appendChild(div);
        });
      }
      productDropdown.style.display = 'block';
    }, 250);
  });

  document.addEventListener('click', (e) => {
    if (!contactDropdown.contains(e.target) && e.target !== contactInput) {
      contactDropdown.style.display = 'none';
    }
    if (!productDropdown.contains(e.target) && e.target !== productInput) {
      productDropdown.style.display = 'none';
    }
  });

  function onTemplateChange() {
    renderParams();
    renderChips(); // reset display
    updateChartVisibility();
  }

  selectTpl.addEventListener('change', onTemplateChange);
  onTemplateChange();
  renderFavorites();
  refreshFavorites();
})();
</script>
