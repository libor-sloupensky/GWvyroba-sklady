<?php
/** @var string $title */
/** @var array $templates */
/** @var array $favoritesV2 */
?>
<h1>Analýza v2 (katalog dotazů)</h1>

<p>Vyberte šablonu, nastavte filtry a spusťte. Výsledek se zobrazí v grafu i tabulce. Nastavení si můžete uložit do oblíbených.</p>

<style>
.v2-grid { display:grid; grid-template-columns: 360px 1fr; gap:1.2rem; align-items:start; }
.v2-form { display:flex; flex-direction:column; gap:0.8rem; }
.v2-form label { font-weight:600; display:block; margin-bottom:0.15rem; }
.v2-row { display:flex; gap:0.6rem; flex-wrap:wrap; }
.v2-row .field { flex:1 1 0; min-width:140px; }
.v2-form input, .v2-form select { width:100%; padding:0.4rem 0.5rem; }
.notice { padding:0.6rem 0.8rem; border:1px solid #e0e0e0; border-radius:8px; background:#f7f9fc; }
.muted { color:#607d8b; }
.error { color:#c62828; font-weight:600; }
.chips { display:flex; gap:0.4rem; flex-wrap:wrap; margin-top:0.3rem; }
.chip { background:#eceff1; border-radius:999px; padding:0.25rem 0.55rem; display:inline-flex; align-items:center; gap:0.35rem; }
.chip button { border:0; background:none; cursor:pointer; font-weight:700; color:#c62828; }
.dropdown { border:1px solid #d0d7de; border-radius:6px; padding:0.35rem 0.45rem; background:#fff; max-height:180px; overflow:auto; margin-top:0.2rem; }
.dropdown div { padding:0.2rem 0.1rem; cursor:pointer; }
.dropdown div:hover { background:#f1f5f9; }
.result-wrap { margin-top:1rem; }
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

<div class="v2-grid">
  <div>
    <form id="v2-form" class="v2-form" action="javascript:void(0);">
      <div class="field">
        <label for="template-id">Šablona</label>
        <select id="template-id" name="template_id">
          <?php foreach ($templates as $id => $tpl): ?>
            <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($tpl['title'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="muted" id="template-desc"></p>
      </div>

      <div class="v2-row">
        <div class="field">
          <label for="start-date">Od</label>
          <input type="date" id="start-date" name="start_date" />
        </div>
        <div class="field">
          <label for="end-date">Do</label>
          <input type="date" id="end-date" name="end_date" />
        </div>
      </div>

      <div class="field">
        <label>Kontakt (IČ / e-mail / firma)</label>
        <input type="text" id="contact-search" placeholder="Hledat..." autocomplete="off" />
        <div id="contact-dropdown" class="dropdown" style="display:none;"></div>
        <div class="chips" id="contact-chips"></div>
        <p class="muted">Vyberte 0–N kontaktů; prázdné = všechny.</p>
      </div>

      <div class="field">
        <label for="eshop-source">E-shop</label>
        <select id="eshop-source" name="eshop_source" multiple size="6"></select>
        <p class="muted">Nezvolíte-li nic, použijí se všechny kanály.</p>
      </div>

      <button type="submit">Spustit dotaz</button>
      <div id="v2-error" class="error" style="display:none;"></div>
    </form>

    <div class="notice" style="margin-top:1rem;">
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

  <div class="result-wrap">
    <div class="chart-box">
      <canvas id="v2-chart" height="200"></canvas>
    </div>
    <div id="v2-result"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
  const templates = <?= json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const favoritesInit = <?= json_encode($favoritesV2, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  const form = document.getElementById('v2-form');
  const selectTpl = document.getElementById('template-id');
  const descBox = document.getElementById('template-desc');
  const startDate = document.getElementById('start-date');
  const endDate = document.getElementById('end-date');
  const eshopSelect = document.getElementById('eshop-source');
  const errorBox = document.getElementById('v2-error');
  const resultBox = document.getElementById('v2-result');
  const chartCanvas = document.getElementById('v2-chart');

  const contactInput = document.getElementById('contact-search');
  const contactDropdown = document.getElementById('contact-dropdown');
  const contactChips = document.getElementById('contact-chips');

  const favMine = document.getElementById('favorite-mine');
  const favShared = document.getElementById('favorite-shared');
  const favTitle = document.getElementById('fav-title');
  const favPublic = document.getElementById('fav-public');
  const favSave = document.getElementById('fav-save');

  let chart;
  const state = {
    contacts: [],
    favorites: favoritesInit || { mine: [], shared: [] },
    lastRows: [],
  };

  function initEshops() {
    const tpl = templates[selectTpl.value];
    const eshopParam = tpl?.params?.find(p => p.name === 'eshop_source');
    eshopSelect.innerHTML = '';
    if (eshopParam && Array.isArray(eshopParam.values)) {
      eshopParam.values.forEach(val => {
        const opt = document.createElement('option');
        opt.value = val;
        opt.textContent = val;
        eshopSelect.appendChild(opt);
      });
    }
  }

  function setDefaultDates() {
    const tpl = templates[selectTpl.value];
    const pStart = tpl?.params?.find(p => p.name === 'start_date');
    const pEnd = tpl?.params?.find(p => p.name === 'end_date');
    if (pStart?.default) startDate.value = pStart.default;
    if (pEnd?.default) endDate.value = pEnd.default;
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
        const title = document.createElement('div');
        title.className = 'favorite-title';
        title.textContent = fav.title;
        const meta = document.createElement('div');
        meta.className = 'muted';
        meta.textContent = `${fav.template_id || ''}`;
        left.appendChild(title);
        left.appendChild(meta);
        const actions = document.createElement('div');
        actions.className = 'favorite-actions';
        const btnLoad = document.createElement('button');
        btnLoad.type = 'button';
        btnLoad.textContent = 'Načíst';
        btnLoad.onclick = () => loadFavorite(fav);
        actions.appendChild(btnLoad);
        if (node === favMine) { // only moje -> allow delete
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

  function renderChips() {
    contactChips.innerHTML = '';
    state.contacts.forEach((c) => {
      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.textContent = c.label || (`Kontakt #${c.id}`);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = '×';
      btn.onclick = () => removeContact(c.id);
      chip.appendChild(btn);
      contactChips.appendChild(chip);
    });
  }

  function removeContact(id) {
    state.contacts = state.contacts.filter(c => c.id !== id);
    renderChips();
  }

  function addContact(item) {
    if (state.contacts.some(c => c.id === item.id)) return;
    state.contacts.push(item);
    renderChips();
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
      const res = await fetch('/analytics/v2/contacts?q=' + encodeURIComponent(q));
      const data = await res.json();
      const items = data.items || [];
      contactDropdown.innerHTML = '';
      if (!items.length) {
        contactDropdown.innerHTML = '<div class="muted">Nic nenalezeno</div>';
      } else {
        items.forEach((it) => {
          const div = document.createElement('div');
          div.textContent = it.label;
          div.onclick = () => {
            addContact(it);
            contactDropdown.style.display = 'none';
            contactInput.value = '';
          };
          contactDropdown.appendChild(div);
        });
      }
      contactDropdown.style.display = 'block';
    }, 250);
  });

  document.addEventListener('click', (e) => {
    if (!contactDropdown.contains(e.target) && e.target !== contactInput) {
      contactDropdown.style.display = 'none';
    }
  });

  function toParams() {
    const params = {
      start_date: startDate.value || '',
      end_date: endDate.value || '',
      contact_ids: state.contacts.map(c => c.id),
      eshop_source: Array.from(eshopSelect.selectedOptions).map(o => o.value),
    };
    return params;
  }

  function renderTable(rows) {
    if (!rows || !rows.length) {
      resultBox.innerHTML = '<p class="muted">Žádná data.</p>';
      return;
    }
    const cols = Object.keys(rows[0]);
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
    let totals = {};
    rows.forEach((r) => {
      const tr = document.createElement('tr');
      cols.forEach((c) => {
        const td = document.createElement('td');
        td.textContent = r[c] ?? '';
        tr.appendChild(td);
        const val = Number(r[c]);
        if (!Number.isNaN(val)) {
          totals[c] = (totals[c] || 0) + val;
        }
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);

    const tfoot = document.createElement('tfoot');
    const trf = document.createElement('tr');
    cols.forEach((c, idx) => {
      const td = document.createElement('td');
      if (idx === 0) {
        td.textContent = 'Celkem';
      } else if (totals[c] !== undefined) {
        td.textContent = Math.round(totals[c]);
      } else {
        td.textContent = '';
      }
      trf.appendChild(td);
    });
    tfoot.appendChild(trf);
    table.appendChild(tfoot);
    resultBox.innerHTML = '';
    resultBox.appendChild(table);
  }

  function renderChart(rows) {
    if (!rows || !rows.length || !window.Chart) {
      if (chart) chart.destroy();
      chart = null;
      return;
    }
    const bySeries = new Map();
    rows.forEach(r => {
      const label = r.serie_label || 'Celkem';
      const key = r.serie_key || label;
      if (!bySeries.has(key)) bySeries.set(key, { label, points: [] });
      bySeries.get(key).points.push({ x: r.mesic, y: Number(r.trzby || 0) });
    });
    const datasets = Array.from(bySeries.values()).map((s, idx) => {
      s.points.sort((a,b) => a.x.localeCompare(b.x));
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
          x: { title: { display: true, text: 'Měsíc' } },
          y: { title: { display: true, text: 'Tržby (CZK)' }, beginAtZero: true },
        },
        plugins: { legend: { display: true, position: 'bottom' } },
      },
    });
  }

  function palette(i) {
    const colors = ['#1565c0','#ef6c00','#2e7d32','#8e24aa','#c62828','#00838f','#6d4c41','#5d4037','#283593','#ad1457'];
    return colors[i % colors.length];
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';
    const tplId = selectTpl.value;
    try {
      const res = await fetch('/analytics/v2/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ template_id: tplId, params: toParams() })
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Dotaz selhal.');
      state.lastRows = data.rows || [];
      renderChart(state.lastRows);
      renderTable(state.lastRows);
    } catch (err) {
      errorBox.style.display = 'block';
      errorBox.textContent = err.message || 'Dotaz selhal.';
    }
  });

  function loadFavorite(fav) {
    if (!fav || !fav.template_id) return;
    if (!templates[fav.template_id]) {
      alert('Šablona už neexistuje.');
      return;
    }
    selectTpl.value = fav.template_id;
    onTemplateChange();
    const p = fav.params || {};
    startDate.value = p.start_date || '';
    endDate.value = p.end_date || '';
    state.contacts = (p.contact_ids || []).map(id => ({ id, label: 'Kontakt #' + id }));
    renderChips();
    Array.from(eshopSelect.options).forEach(opt => {
      opt.selected = Array.isArray(p.eshop_source) && p.eshop_source.includes(opt.value);
    });
  }

  async function deleteFavorite(id) {
    const res = await fetch('/analytics/v2/favorite/delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
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
    const res = await fetch('/analytics/v2/favorite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title,
        template_id: selectTpl.value,
        params: toParams(),
        is_public: favPublic.checked ? 1 : 0,
      }),
    });
    const data = await res.json();
    if (!data.ok) {
      alert(data.error || 'Uložení selhalo.');
      return;
    }
    state.favorites = data.favorites || { mine: [], shared: [] };
    renderFavorites();
  });

  async function refreshFavorites() {
    const res = await fetch('/analytics/v2/favorite/list');
    const data = await res.json();
    if (data.ok) {
      state.favorites = data.favorites || { mine: [], shared: [] };
      renderFavorites();
    }
  }

  function onTemplateChange() {
    const tpl = templates[selectTpl.value];
    descBox.textContent = tpl?.description || '';
    initEshops();
    setDefaultDates();
  }

  selectTpl.addEventListener('change', onTemplateChange);
  onTemplateChange();
  renderFavorites();
  refreshFavorites();
})();
</script>
