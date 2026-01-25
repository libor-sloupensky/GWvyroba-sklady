<?php
/** @var string $title */
/** @var array $templates */
/** @var array $favoritesV2 */
// Margins template with sortable columns
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
.toggle-switch {
  display: inline-flex;
  border: 1px solid #cfd8dc;
  border-radius: 999px;
  overflow: hidden;
  background: #f5f7fa;
}
.toggle-switch button {
  border: 0;
  background: transparent;
  padding: 0.2rem 0.65rem;
  font-weight: 600;
  font-size: 0.85rem;
  color: #455a64;
  cursor: pointer;
}
.toggle-switch button.active {
  background: #1e88e5;
  color: #fff;
}
.toggle-switch button:focus {
  outline: 1px solid #1e88e5;
  outline-offset: -1px;
}
.toggle-switch-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: nowrap;
}
.dropdown { border:1px solid #d0d7de; border-radius:6px; padding:0.35rem 0.45rem; background:#fff; max-height:180px; overflow:auto; margin-top:0.2rem; }
.dropdown div { padding:0.2rem 0.1rem; cursor:pointer; }
.dropdown div:hover { background:#f1f5f9; }
.result-table { width:100%; border-collapse:collapse; margin-top:0.6rem; }
.result-table th, .result-table td { border:1px solid #e0e0e0; padding:0.35rem 0.4rem; text-align:left; }
.result-table tfoot td { font-weight:700; background:#f5f7fa; }
.inactive-sku { text-decoration: line-through; }
.favorite-list { list-style:none; padding:0; margin:0; }
.favorite-list li { border:1px solid #eceff1; border-radius:8px; padding:0.55rem 0.7rem; margin-bottom:0.5rem; display:flex; justify-content:space-between; gap:0.6rem; }
.favorite-title { font-weight:600; }
.favorite-actions { display:flex; align-items:center; gap:0.45rem; }
.favorite-actions button { background:none; border:0; cursor:pointer; font-size:0.95rem; color:#1e88e5; }
.favorite-actions .favorite-delete { color:#c62828; font-weight:700; margin-left:0.2rem; }
.favorite-empty { font-size:0.9rem; color:#78909c; }
.chart-box { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:0.7rem; }
/* Margins table styling */
.margins-table { width:100%; border-collapse:collapse; margin-top:0.6rem; }
.margins-table th, .margins-table td { border:1px solid #e0e0e0; padding:0.35rem 0.5rem; text-align:left; }
.margins-table th { background:#f1f5f9; font-weight:600; }
.margins-table th.sortable { cursor:pointer; user-select:none; position:relative; padding-right:1.5rem; }
.margins-table th.sortable:hover { background:#e3f2fd; }
.margins-table th.sortable::after { content:'⇅'; position:absolute; right:0.4rem; color:#90a4ae; font-size:0.85em; }
.margins-table th.sortable.asc::after { content:'▲'; color:#1565c0; }
.margins-table th.sortable.desc::after { content:'▼'; color:#1565c0; }
.margins-table tfoot td { font-weight:700; background:#f5f7fa; }
.margins-table .num { text-align:right; font-variant-numeric:tabular-nums; }
.margins-table .positive { color:#2e7d32; }
.margins-table .negative { color:#c62828; }
.margins-row-parent { cursor:pointer; }
.margins-row-parent:hover { background:#f8fafc; }
.margins-row-detail { display:none; }
.margins-row-detail.open { display:table-row; }
.margins-row-detail td { background:#fafafa; padding:0.5rem 0.7rem; }
.margins-detail-table { width:100%; border-collapse:collapse; font-size:0.9em; }
.margins-detail-table th, .margins-detail-table td { border:1px solid #e8e8e8; padding:0.25rem 0.4rem; }
.margins-detail-table th { background:#f0f4f8; }
.margins-loading { color:#78909c; font-style:italic; }
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
    marginSort: { key: null, dir: null }, // key: column key, dir: 'asc' or 'desc'
    marginMode: null,
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
      const useToggleRow = p.name === 'active_only' || p.name === 'movement_direction' || p.name === 'nonzero_only';
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
      const isMovementToggle = p.name === 'movement_direction';
      if (!isMovementToggle) {
        wrap.appendChild(label);
      }
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
          if (p.name === 'movement_direction') {
            const toggle = document.createElement('div');
            toggle.className = 'toggle-switch';
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = p.name;
            const values = (p.values || []).map((val) => {
              if (typeof val === 'object') {
                return { value: String(val.value ?? ''), label: String(val.label ?? val.value ?? '') };
              }
              return { value: String(val), label: String(val) };
            });
            const labelFor = (value, fallback) => {
              const found = values.find(v => v.value === value);
              return found?.label || fallback;
            };
            const normalize = (value) => (value === 'prijem' ? 'prijem' : 'vydej');
            const leftBtn = document.createElement('button');
            leftBtn.type = 'button';
            leftBtn.dataset.value = 'vydej';
            leftBtn.textContent = labelFor('vydej', 'Výdej');
            const rightBtn = document.createElement('button');
            rightBtn.type = 'button';
            rightBtn.dataset.value = 'prijem';
            rightBtn.textContent = labelFor('prijem', 'Příjem');
            const setToggle = (value, notify = true) => {
              const normalized = normalize(value);
              hidden.value = normalized;
              leftBtn.classList.toggle('active', normalized === 'vydej');
              rightBtn.classList.toggle('active', normalized === 'prijem');
              if (notify) {
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
              }
            };
            leftBtn.addEventListener('click', () => setToggle('vydej'));
            rightBtn.addEventListener('click', () => setToggle('prijem'));
            hidden.addEventListener('change', () => setToggle(hidden.value, false));
            toggle.appendChild(leftBtn);
            toggle.appendChild(rightBtn);
            const initial = normalize(p.default ?? 'vydej');
            setToggle(initial, false);
            const toggleRow = document.createElement('div');
            toggleRow.className = 'toggle-switch-row';
            toggleRow.appendChild(toggle);
            if (p.help) {
              const help = document.createElement('span');
              help.className = 'help-icon';
              help.title = p.help;
              help.textContent = '?';
              toggleRow.appendChild(help);
            }
            wrap.appendChild(hidden);
            wrap.appendChild(toggleRow);
            appendWrap();
            return;
          }
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
          let hasVseOption = false;
          (p.values || []).forEach((val) => {
            const opt = document.createElement('option');
            if (typeof val === 'object') {
              opt.value = val.value ?? '';
              opt.textContent = val.label ?? val.value ?? '';
              if (opt.value === 'vse') hasVseOption = true;
            } else {
              opt.value = val;
              opt.textContent = val === 'vse' ? 'Vše' : val;
              if (val === 'vse') hasVseOption = true;
            }
            input.appendChild(opt);
          });
          // Defaultně zaškrtnout "Vše" pokud není nastaven default a existuje option "vse"
          if (hasVseOption && (!p.default || (Array.isArray(p.default) && p.default.length === 0))) {
            setTimeout(() => {
              const vseOpt = input.querySelector('option[value="vse"]');
              if (vseOpt) vseOpt.selected = true;
            }, 0);
          }
          // Přidat normalizaci výběru - pokud je vybráno "Vše" + něco jiného, nechá jen "Vše"
          input.addEventListener('change', () => {
            const selected = Array.from(input.selectedOptions || []).map(opt => opt.value);
            if (selected.includes('vse') && selected.length > 1) {
              Array.from(input.options || []).forEach(opt => {
                opt.selected = opt.value === 'vse';
              });
            } else if (selected.length === 0 && hasVseOption) {
              // Pokud není nic vybráno, zaškrtnout "Vše"
              const vseOpt = input.querySelector('option[value="vse"]');
              if (vseOpt) vseOpt.selected = true;
            }
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

    const movementSelect = paramBox.querySelector('[name="movement_direction"]');
    const eshopFilterSelect = hasEshop ? eshopSelect : null;
    if (eshopFilterSelect) {
      const selectAllValue = eshopFilterSelect.querySelector('option[value="vse"]')
        ? 'vse'
        : (eshopFilterSelect.querySelector('option[value="vsechny"]') ? 'vsechny' : null);
      const setOnlyAllSelected = () => {
        if (!selectAllValue) return;
        Array.from(eshopFilterSelect.options || []).forEach(opt => {
          opt.selected = opt.value === selectAllValue;
        });
      };
      const normalizeEshopSelection = () => {
        if (!selectAllValue) return;
        const selected = Array.from(eshopFilterSelect.selectedOptions || []).map(opt => opt.value);
        if (selected.includes(selectAllValue) && selected.length > 1) {
          setOnlyAllSelected();
        } else if (selected.length === 0) {
          // Pokud není nic vybráno, zaškrtnout "Vše"
          setOnlyAllSelected();
        }
      };
      const updateEshopFilterState = () => {
        const isVydej = !movementSelect || movementSelect.value === 'vydej';
        eshopFilterSelect.disabled = !isVydej;
        if (!isVydej) {
          setOnlyAllSelected();
        }
      };
      eshopFilterSelect.addEventListener('change', normalizeEshopSelection);
      if (movementSelect) {
        movementSelect.addEventListener('change', updateEshopFilterState);
      }
      normalizeEshopSelection();
      updateEshopFilterState();
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
    const isInactiveProduct = (row) => isProducts && Number(row?.aktivni ?? 0) === 0;
    const cols = Object.keys(rows[0])
      .filter((c) => !['serie_key','serie_label','qty','aktivni'].includes(c))
      .filter((c) => !(isProducts && c === 'mj'));
    const table = document.createElement('table');
    table.className = 'result-table';
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');
    cols.forEach((c) => {
      const th = document.createElement('th');
      th.textContent = c;
      if (isProducts && c === 'hodnota skladu') {
        const help = document.createElement('span');
        help.className = 'help-icon';
        help.title = 'Skladová hodnota položky × počet kusů.';
        help.textContent = '?';
        th.appendChild(help);
      }
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    const totals = {};
    const productUnits = isProducts ? new Set(rows.map(r => r.mj).filter(Boolean)) : null;
    rows.forEach((r) => {
      const tr = document.createElement('tr');
      const inactiveRow = isInactiveProduct(r);
      cols.forEach((c) => {
        const td = document.createElement('td');
        if (isProducts && (c === 'množství' || c === 'mnozstvi')) {
          const valNum = Number(r[c]);
          const rounded = Number.isNaN(valNum) ? r[c] : Math.round(valNum);
          const unit = r.mj || '';
          td.textContent = unit ? `${rounded} ${unit}` : String(rounded);
        } else {
          td.textContent = r[c] ?? '';
        }
        if (inactiveRow && String(c).toLowerCase() === 'sku') {
          td.classList.add('inactive-sku');
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
          if (isProducts && (c === 'množství' || c === 'mnozstvi')) {
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

  function renderMarginsTable(rows, mode) {
    if (!rows || !rows.length) {
      resultBox.innerHTML = '<p class="muted">Žádná data.</p>';
      return;
    }

    // Store mode for re-rendering after sort
    state.marginMode = mode;

    const formatNum = (val, decimals = 0) => {
      const num = Number(val);
      if (isNaN(num)) return val;
      return num.toLocaleString('cs-CZ', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    };

    const formatPct = (val) => {
      const num = Number(val);
      if (isNaN(num)) return val;
      return num.toFixed(1) + ' %';
    };

    const profitClass = (val) => {
      const num = Number(val);
      if (isNaN(num)) return '';
      return num >= 0 ? 'positive' : 'negative';
    };

    const sortRows = (key, dir) => {
      const sorted = [...rows].sort((a, b) => {
        let valA = a[key];
        let valB = b[key];
        // Convert to numbers if possible
        const numA = Number(valA);
        const numB = Number(valB);
        if (!isNaN(numA) && !isNaN(numB)) {
          valA = numA;
          valB = numB;
        }
        // Compare
        if (valA < valB) return dir === 'asc' ? -1 : 1;
        if (valA > valB) return dir === 'asc' ? 1 : -1;
        return 0;
      });
      return sorted;
    };

    const table = document.createElement('table');
    table.className = 'margins-table';
    const thead = document.createElement('thead');
    const tbody = document.createElement('tbody');
    const tfoot = document.createElement('tfoot');

    let cols = [];
    let totals = { trzby: 0, naklady: 0, zisk: 0 };

    if (mode === 'invoices') {
      cols = [
        { key: 'toggle', label: '', width: '30px' },
        { key: 'eshop_source', label: 'E-shop' },
        { key: 'cislo_dokladu', label: 'Číslo dokladu' },
        { key: 'datum', label: 'Datum' },
        { key: 'kontakt', label: 'Kontakt' },
        { key: 'trzby', label: 'Tržby (CZK)', numeric: true },
        { key: 'naklady', label: 'Náklady (CZK)', numeric: true },
        { key: 'zisk', label: 'Zisk (CZK)', numeric: true },
        { key: 'zisk_pct', label: 'Zisk %', numeric: true },
      ];
    } else if (mode === 'contacts') {
      cols = [
        { key: 'kontakt', label: 'Kontakt' },
        { key: 'pocet_faktur', label: 'Počet faktur', numeric: true },
        { key: 'trzby', label: 'Tržby (CZK)', numeric: true },
        { key: 'naklady', label: 'Náklady (CZK)', numeric: true },
        { key: 'zisk', label: 'Zisk (CZK)', numeric: true },
        { key: 'zisk_pct', label: 'Průměrné zisk %', numeric: true },
      ];
    } else if (mode === 'products') {
      cols = [
        { key: 'sku', label: 'SKU' },
        { key: 'nazev', label: 'Název' },
        { key: 'mnozstvi', label: 'Prodané množství', numeric: true },
        { key: 'trzby', label: 'Tržby (CZK)', numeric: true },
        { key: 'naklady', label: 'Náklady (CZK)', numeric: true },
        { key: 'zisk', label: 'Zisk (CZK)', numeric: true },
        { key: 'zisk_pct', label: 'Zisk %', numeric: true },
      ];
    }

    // Header
    const trh = document.createElement('tr');
    cols.forEach(col => {
      const th = document.createElement('th');
      th.textContent = col.label;
      if (col.width) th.style.width = col.width;

      // Make sortable (except toggle column)
      if (col.key !== 'toggle') {
        th.className = 'sortable';
        // Highlight active sort
        if (state.marginSort.key === col.key) {
          th.classList.add(state.marginSort.dir);
        }
        // Add click handler
        th.addEventListener('click', () => {
          let newDir = 'desc'; // default to descending
          if (state.marginSort.key === col.key) {
            // Toggle direction
            newDir = state.marginSort.dir === 'desc' ? 'asc' : 'desc';
          }
          state.marginSort = { key: col.key, dir: newDir };
          const sortedRows = sortRows(col.key, newDir);
          renderMarginsTable(sortedRows, mode);
        });
      }

      trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);

    // Body
    rows.forEach((row, idx) => {
      const tr = document.createElement('tr');
      if (mode === 'invoices') {
        tr.className = 'margins-row-parent';
        tr.dataset.eshop = row.eshop_source || '';
        tr.dataset.doklad = row.cislo_dokladu || '';
      }

      cols.forEach(col => {
        const td = document.createElement('td');
        if (col.numeric) td.className = 'num';

        if (col.key === 'toggle' && mode === 'invoices') {
          const toggle = document.createElement('span');
          toggle.className = 'row-toggle';
          toggle.textContent = '▶';
          td.appendChild(toggle);
        } else if (col.key === 'trzby' || col.key === 'naklady' || col.key === 'zisk') {
          td.textContent = formatNum(row[col.key]);
          if (col.key === 'zisk') {
            td.classList.add(profitClass(row[col.key]));
          }
        } else if (col.key === 'zisk_pct') {
          td.textContent = formatPct(row[col.key]);
          td.classList.add(profitClass(row.zisk));
        } else if (col.key === 'pocet_faktur' || col.key === 'mnozstvi') {
          td.textContent = formatNum(row[col.key]);
        } else {
          td.textContent = row[col.key] ?? '';
        }
        tr.appendChild(td);
      });

      tbody.appendChild(tr);

      // Add detail row for invoices mode
      if (mode === 'invoices') {
        const detailTr = document.createElement('tr');
        detailTr.className = 'margins-row-detail';
        detailTr.id = `margin-detail-${idx}`;
        const detailTd = document.createElement('td');
        detailTd.colSpan = cols.length;
        detailTd.innerHTML = '<span class="margins-loading">Načítám položky...</span>';
        detailTr.appendChild(detailTd);
        tbody.appendChild(detailTr);

        tr.addEventListener('click', () => {
          const isOpen = detailTr.classList.contains('open');
          const toggle = tr.querySelector('.row-toggle');
          if (isOpen) {
            detailTr.classList.remove('open');
            if (toggle) toggle.textContent = '▶';
          } else {
            detailTr.classList.add('open');
            if (toggle) toggle.textContent = '▼';
            // Load detail if not already loaded
            if (detailTd.dataset.loaded !== '1') {
              loadMarginDetail(row.eshop_source, row.cislo_dokladu, detailTd);
            }
          }
        });
      }

      // Sum totals
      totals.trzby += Number(row.trzby) || 0;
      totals.naklady += Number(row.naklady) || 0;
      totals.zisk += Number(row.zisk) || 0;
    });

    table.appendChild(tbody);

    // Footer with totals
    const trf = document.createElement('tr');
    cols.forEach((col, i) => {
      const td = document.createElement('td');
      if (i === 0) {
        td.textContent = 'Celkem';
      } else if (col.key === 'trzby') {
        td.textContent = formatNum(totals.trzby);
        td.className = 'num';
      } else if (col.key === 'naklady') {
        td.textContent = formatNum(totals.naklady);
        td.className = 'num';
      } else if (col.key === 'zisk') {
        td.textContent = formatNum(totals.zisk);
        td.className = 'num ' + profitClass(totals.zisk);
      } else if (col.key === 'zisk_pct' && totals.trzby > 0) {
        const avgPct = (totals.zisk / totals.trzby) * 100;
        td.textContent = formatPct(avgPct);
        td.className = 'num ' + profitClass(totals.zisk);
      } else if (col.key === 'pocet_faktur') {
        td.textContent = formatNum(rows.length);
        td.className = 'num';
      }
      trf.appendChild(td);
    });
    tfoot.appendChild(trf);
    table.appendChild(tfoot);

    resultBox.innerHTML = '';
    resultBox.appendChild(table);
  }

  async function loadMarginDetail(eshop, doklad, td) {
    try {
      const res = await fetch('/analytics/invoice-items?' + new URLSearchParams({ eshop_source: eshop, cislo_dokladu: doklad }));
      const data = await toJsonSafe(res);
      if (!data.ok) throw new Error(data.error || 'Chyba při načítání');

      const items = data.items || [];
      if (!items.length) {
        td.innerHTML = '<span class="muted">Žádné položky.</span>';
        td.dataset.loaded = '1';
        return;
      }

      const formatNum = (val, dec = 0) => {
        const n = Number(val);
        return isNaN(n) ? val : n.toLocaleString('cs-CZ', { minimumFractionDigits: dec, maximumFractionDigits: dec });
      };

      let html = '<table class="margins-detail-table"><thead><tr>' +
        '<th>SKU</th><th>Název</th><th>Množství</th><th>Tržba (CZK)</th><th>Náklad (CZK)</th><th>Zisk (CZK)</th><th>Zisk %</th>' +
        '</tr></thead><tbody>';

      items.forEach(item => {
        const profitClass = (Number(item.zisk) >= 0) ? 'positive' : 'negative';
        html += `<tr>
          <td>${item.sku || ''}</td>
          <td>${item.nazev || ''}</td>
          <td class="num">${formatNum(item.mnozstvi)}</td>
          <td class="num">${formatNum(item.trzba)}</td>
          <td class="num">${formatNum(item.naklad)}</td>
          <td class="num ${profitClass}">${formatNum(item.zisk)}</td>
          <td class="num ${profitClass}">${Number(item.zisk_pct).toFixed(1)} %</td>
        </tr>`;
      });

      html += '</tbody></table>';
      td.innerHTML = html;
      td.dataset.loaded = '1';
    } catch (err) {
      td.innerHTML = '<span class="error">Chyba: ' + (err.message || 'Neznámá chyba') + '</span>';
    }
  }

  function renderChart(rows) {
    if (!rows || !rows.length || !window.Chart) {
      if (chart) chart.destroy();
      chart = null;
      return;
    }
    const sample = rows[0] || {};
    const xKey = 'měsíc' in sample ? 'měsíc' : ('mesic' in sample ? 'mesic' : ('stav_ke_dni' in sample ? 'stav_ke_dni' : Object.keys(sample)[0]));
    const exclude = new Set([xKey, 'serie_key', 'serie_label']);
    const keys = Object.keys(sample);
    let yKey = keys.find(k => k.toLowerCase() === 'tržby') || keys.find(k => k.toLowerCase() === 'trzby') || keys.find(k => k.toLowerCase() === 'hodnota_czk') || null;
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
    // Special rendering for margins template
    if (tplId === 'margins' && data.mode) {
      renderMarginsTable(state.lastRows, data.mode);
    } else {
      renderTable(state.lastRows, tplId);
    }
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
        // Pokud není nic vybráno, zaškrtnout "Vše"
        const selectedCount = Array.from(select?.selectedOptions || []).length;
        if (selectedCount === 0) {
          const vseOpt = select?.querySelector('option[value="vse"], option[value="vsechny"]');
          if (vseOpt) vseOpt.selected = true;
        }
      } else {
        const input = paramBox.querySelector(`[name="${param.name}"]`);
        if (input) {
          input.value = p[param.name] || '';
          if (param.name === 'movement_direction') {
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
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
