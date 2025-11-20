<?php
  $activeFilters = $filters ?? ['brand'=>0,'group'=>0,'type'=>'','search'=>''];
  $filterBrand = (int)($activeFilters['brand'] ?? 0);
  $filterGroup = (int)($activeFilters['group'] ?? 0);
  $filterType  = (string)($activeFilters['type'] ?? '');
  $filterSearch= (string)($activeFilters['search'] ?? '');
  $hasSearchActive = (bool)($hasSearch ?? false);
  $resultCount = (int)($resultCount ?? 0);
  $formOld = $formOld ?? [];
  if (!array_key_exists('aktivni', $formOld)) {
      $formOld['aktivni'] = '1';
  }
  if (!array_key_exists('nast_zasob', $formOld)) {
      $formOld['nast_zasob'] = 'auto';
  }
?>

<h1>Produkty</h1>
<style>
.collapsible {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 0.65rem 0.9rem;
  margin-bottom: 1rem;
}
.collapsible summary {
  cursor: pointer;
  font-weight: 600;
  list-style: none;
  display: flex;
  align-items: center;
}
.collapsible summary::-webkit-details-marker { display:none; }
.collapsible summary::after {
  content: '\25BC';
  font-size: 1.3rem;
  margin-left: 0.5rem;
  color: #455a64;
}
.collapsible[open] summary::after { content: '\25B2'; }
.collapsible-body { margin-top: 0.75rem; }
.collapsible-block { margin-bottom: 1.25rem; }
.collapsible-heading { font-size: 1.05rem; font-weight: 600; margin: 0 0 0.4rem; }
.notice-success { border-color:#c8e6c9; background:#f1f8f1; color:#2e7d32; }
.notice-error { border-color:#ffbdbd; background:#fff5f5; color:#b00020; }
.notice-warning { border-color:#ffe082; background:#fff8e1; color:#8d6e63; }
.import-result { margin-bottom: 0.8rem; }
.import-stats {
  list-style: none;
  padding: 0.3rem 0 0;
  margin: 0.3rem 0 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.8rem;
  font-size: 0.9rem;
}
.text-success { color:#2e7d32; }
.text-error { color:#b00020; }
.info-icon {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:18px;
  height:18px;
  margin-left:0.35rem;
  border-radius:50%;
  background:#eceff1;
  color:#37474f;
  font-size:0.75rem;
  cursor:help;
}
.min-stock-cell {
  position:relative;
}
.min-stock-cell[data-stock-mode="auto"] {
  color:#607d8b;
  cursor:not-allowed;
}
.min-stock-cell[data-stock-mode="auto"]::after {
  content:'auto';
  font-size:0.7rem;
  margin-left:0.4rem;
  padding:0.05rem 0.4rem;
  border-radius:999px;
  background:#e3f2fd;
  color:#1565c0;
  text-transform:uppercase;
}

.product-filter-form {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 0.9rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1rem;
  background: #fafafa;
}
.product-filter-form label {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-weight: 600;
  min-width: 200px;
}
.section-title { font-size: 1.1rem; font-weight: 600; margin: 1rem 0 0.4rem; }
.muted { color:#607d8b; }

.products-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.products-table th,
.products-table td { border:1px solid #ddd; padding:0.45rem 0.55rem; vertical-align:top; }
.products-table th { background:#f3f6f9; }
.sku-cell {
  cursor: pointer;
  font-weight: 600;
  display:flex;
  align-items:center;
  gap:0.25rem;
  white-space:nowrap;
  padding:0.45rem 0.55rem;
}
.sku-toggle {
  font-size:0.9rem;
  color:#455a64;
  width:1rem;
  text-align:center;
  flex-shrink:0;
}
.inline-input { width:100%; box-sizing:border-box; }
.bom-tree-row td { background:#fdfdfd; padding:0.65rem; border-top:none; }
.bom-tree-table { width:100%; border-collapse:collapse; font-family:"Fira Mono","Consolas",monospace; font-size:0.9rem; }
.bom-tree-table th,
.bom-tree-table td { border:1px solid #e0e0e0; padding:0.35rem 0.5rem; vertical-align:top; }
.bom-tree-table th { background:#f7f9fb; text-align:left; font-weight:600; }
.bom-tree-cell { white-space:nowrap; display:flex; align-items:flex-start; gap:0.2rem; }
.bom-tree-prefix { display:inline-block; color:#90a4ae; white-space:pre; font-family:"Fira Mono","Consolas",monospace; }
.bom-tree-label { font-weight:600; display:inline-flex; flex-wrap:wrap; }
.bom-tree-note { margin-left:0.5rem; font-size:0.8rem; color:#b00020; }
.bom-tree-actions { text-align:right; white-space:nowrap; }
.bom-action-btn {
  border: 1px solid #cfd8dc;
  background: #fff;
  color: #37474f;
  font-size: 0.85rem;
  line-height: 1;
  padding: 0.2rem 0.45rem;
  margin-left: 0.2rem;
  border-radius: 4px;
  cursor: pointer;
}
.bom-action-btn:hover { background:#eceff1; }
.bom-action-btn--danger { color:#b00020; border-color:#f8bbd0; }
.bom-action-btn--danger:hover { background:#ffe5ec; }
.bom-add-row td { background:#f4fbff; }
.bom-add-form { display:flex; flex-direction:column; gap:0.5rem; }
.bom-add-fields {
  display:flex;
  flex-wrap:wrap;
  gap:0.75rem;
}
.bom-add-fields label { font-weight:600; display:block; margin-bottom:0.2rem; }
.bom-add-fields .field { flex:1 1 220px; }
.bom-add-fields input,
.bom-add-fields select {
  width:100%;
  box-sizing:border-box;
  padding:0.35rem 0.45rem;
}
.bom-add-actions {
  display:flex;
  align-items:center;
  gap:0.5rem;
}
.bom-add-error { color:#b00020; font-size:0.9rem; flex:1; }
.bom-search-results {
  border:1px solid #e0e0e0;
  border-radius:4px;
  margin-top:0.3rem;
  max-height:180px;
  overflow:auto;
  background:#fff;
}
.bom-search-option {
  display:block;
  width:100%;
  text-align:left;
  border:none;
  background:none;
  padding:0.35rem 0.5rem;
  cursor:pointer;
}
.bom-search-option:hover { background:#f1f8ff; }
.bom-search-empty {
  display:block;
  padding:0.35rem 0.5rem;
  color:#90a4ae;
}
.search-actions {
  align-self:flex-end;
  display:flex;
  align-items:center;
  gap:0.4rem;
}
.search-result-pill {
  font-size:0.9rem;
  color:#607d8b;
}
.search-reset {
  text-decoration:none;
  font-size:1.2rem;
  color:#b00020;
  padding:0 0.2rem;
  line-height:1;
}
.search-reset:hover { color:#d32f2f; }
</style>

<?php if (!empty($error)): ?>
  <div class="notice notice-error">
    <?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  (function () {
    const meta = {
      brands: <?= json_encode(array_map(fn($b) => ['value'=>(string)$b['id'],'label'=>$b['nazev']], $brands ?? []), JSON_UNESCAPED_UNICODE) ?>,
      groups: <?= json_encode(array_map(fn($g) => ['value'=>(string)$g['id'],'label'=>$g['nazev']], $groups ?? []), JSON_UNESCAPED_UNICODE) ?>,
      units:  <?= json_encode(array_map(fn($u) => ['value'=>$u['kod'],'label'=>$u['kod']], $units ?? []), JSON_UNESCAPED_UNICODE) ?>,
      types:  <?= json_encode(array_map(fn($t) => ['value'=>$t,'label'=>$t], $types ?? []), JSON_UNESCAPED_UNICODE) ?>,
      active: [{value:'1',label:'âś“'},{value:'0',label:'â€“'}],
      stockModes: [{value:'auto',label:'Automaticky'},{value:'manual',label:'ManuĂˇlnÄ›'}]
    };

    const table = document.querySelector('.products-table');
    if (!table) return;

    const updateUrl = '/products/update';
    const bomUrl = '/products/bom-tree';
    const bomAddUrl = '/products/bom/add';
    const bomDeleteUrl = '/products/bom/delete';
    const productSearchUrl = '/products/search';
    let bomState = { row: null, detail: null };
    let bomAddState = { row: null };

    table.addEventListener('click', (event) => {
      const cell = event.target.closest('.sku-cell');
      if (!cell || !table.contains(cell) || event.detail > 1) return;
      event.preventDefault();
      toggleBomRow(cell);
    });

    table.addEventListener('dblclick', (event) => {
      const skuCell = event.target.closest('.sku-cell');
      if (skuCell && table.contains(skuCell)) {
        event.preventDefault();
        return;
      }
      const cell = event.target.closest('.editable');
      if (!cell || cell.dataset.editing === '1') return;
      const row = cell.closest('tr');
      const sku = row?.dataset.sku;
      if (!sku) return;
      if (cell.dataset.lock === 'auto' && (row?.dataset.stockMode || '').toLowerCase() === 'auto') {
        alert(cell.dataset.lockMessage || 'Pole nastavuje automatickĂ˝ vĂ˝poÄŤet stavĹŻ.');
        return;
      }
      startEdit(cell, sku, row);
    });

    table.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && bomState.row) {
        closeBomRow();
      }
    });

    function toggleBomRow(cell) {
      const row = cell.closest('tr');
      if (!row) return;
      if (bomState.row === row) {
        closeBomRow();
        return;
      }
      closeBomRow();
      const toggle = cell.querySelector('.sku-toggle');
      if (toggle) toggle.textContent = 'â–ľ';
      row.classList.add('bom-open');
      const detailRow = document.createElement('tr');
      detailRow.className = 'bom-tree-row';
      const detailCell = document.createElement('td');
      detailCell.colSpan = row.children.length;
      detailCell.textContent = 'NaÄŤĂ­tĂˇmâ€¦';
      detailRow.appendChild(detailCell);
      row.parentNode.insertBefore(detailRow, row.nextSibling);
      bomState = { row, detail: detailRow };
      loadBomTree(cell.dataset.sku || row.dataset.sku, detailCell);
    }

    function closeBomRow() {
      if (!bomState.row) return;
      const toggle = bomState.row.querySelector('.sku-toggle');
      if (toggle) toggle.textContent = 'â–¸';
      bomState.row.classList.remove('bom-open');
      if (bomState.detail) bomState.detail.remove();
      bomState = { row: null, detail: null };
      closeBomAddForm();
    }

    async function loadBomTree(sku, container) {
      if (!sku) {
        container.textContent = 'ChybĂ­ SKU.';
        return;
      }
      try {
        const response = await fetch(`${bomUrl}?sku=${encodeURIComponent(sku)}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (!data.ok) throw new Error(data.error || 'NepodaĹ™ilo se naÄŤĂ­st BOM strom.');
        container.innerHTML = '';
        const refresh = () => loadBomTree(sku, container);
        container.appendChild(buildBomTable(data.tree, refresh));
      } catch (err) {
        container.textContent = `Chyba: ${err.message || err}`;
      }
    }

    function buildBomTable(tree, refresh) {
      const table = document.createElement('table');
      table.className = 'bom-tree-table';
      table.innerHTML = '<thead><tr><th>Strom vazeb</th><th>Koeficient</th><th>MJ</th><th>Druh vazby</th><th>Typ poloĹľky</th><th>Akce</th></tr></thead>';
      const body = document.createElement('tbody');
      const rows = flattenBomTree(tree);
      rows.forEach((rowData) => {
        const tr = document.createElement('tr');

      const first = document.createElement('td');
      first.className = 'bom-tree-cell';
      const prefix = document.createElement('span');
      prefix.className = 'bom-tree-prefix';
      prefix.textContent = buildBranchPrefix(rowData.guides);
      if (!prefix.textContent.trim()) prefix.style.display = 'none';
      const label = document.createElement('span');
      label.className = 'bom-tree-label';
      label.textContent = formatNodeLabel(rowData.node);
      first.appendChild(prefix);
      first.appendChild(label);
        if (rowData.node.cycle) {
          const badge = document.createElement('span');
          badge.className = 'bom-tree-note';
          badge.textContent = 'âźł cyklus';
          first.appendChild(badge);
        }
        tr.appendChild(first);

        const edge = rowData.node.edge || {};
        tr.appendChild(createValueCell(formatNumber(edge.koeficient)));
        tr.appendChild(createValueCell(displayValue(edge.merna_jednotka || rowData.node.merna_jednotka)));
        tr.appendChild(createValueCell(displayValue(edge.druh_vazby)));
        tr.appendChild(createValueCell(displayValue(rowData.node.typ)));

        const actions = document.createElement('td');
        actions.className = 'bom-tree-actions';
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'bom-action-btn';
        addBtn.textContent = '+';
        addBtn.title = 'PĹ™idat potomka';
        addBtn.addEventListener('click', () => openBomAddForm(tr, rowData, refresh));
        actions.appendChild(addBtn);
        if (rowData.parentSku) {
          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'bom-action-btn bom-action-btn--danger';
          delBtn.textContent = 'Ă—';
          delBtn.title = 'Smazat vazbu';
          delBtn.addEventListener('click', () => deleteBomLink(rowData.parentSku, rowData.node.sku, refresh));
          actions.appendChild(delBtn);
        }
        tr.appendChild(actions);

        body.appendChild(tr);
      });
      table.appendChild(body);
      return table;
    }

    function flattenBomTree(root) {
      const rows = [];
      const walk = (node, guides = [], depth = 0, parentSku = null) => {
        rows.push({ node, guides, depth, parentSku });
        const children = Array.isArray(node.children) ? node.children : [];
        children.forEach((child, index) => {
          walk(child, guides.concat(index === children.length - 1), depth + 1, node.sku || parentSku);
        });
      };
      walk(root, [], 0, null);
      return rows;
    }

  function buildBranchPrefix(guides) {
    if (!Array.isArray(guides) || !guides.length) return '';
    let out = '';
    for (let i = 0; i < guides.length - 1; i++) {
      out += guides[i] ? '   ' : 'â”‚  ';
    }
    out += guides[guides.length - 1] ? 'â””â”€ ' : 'â”śâ”€ ';
    return out;
  }

    function formatNodeLabel(node) {
      const sku = node.sku || '(bez SKU)';
      return node.nazev ? `${sku} â€“ ${node.nazev}` : sku;
    }

    function createValueCell(value) {
      const td = document.createElement('td');
      td.textContent = value === undefined || value === null || value === '' ? 'â€“' : value;
      return td;
    }

    function displayValue(value) {
      if (value === undefined || value === null || value === '') return 'â€“';
      return String(value);
    }

    function formatNumber(value) {
      if (value === undefined || value === null || value === '') return 'â€“';
      const num = Number(value);
      if (Number.isNaN(num)) return String(value);
      return Number.isInteger(num) ? String(num) : num.toString().replace('.', ',');
    }

    function deleteBomLink(parentSku, childSku, refresh) {
      if (!confirm('Opravdu odstranit vazbu?')) return;
      fetch(bomDeleteUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({parent: parentSku, child: childSku})
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data.ok) {
            alert(data.error || 'SmazĂˇnĂ­ se nezdaĹ™ilo.');
            return;
          }
          refresh();
        })
        .catch(() => alert('SmazĂˇnĂ­ se nezdaĹ™ilo.'));
    }

    function openBomAddForm(targetRow, rowData, refresh) {
      closeBomAddForm();
      const formRow = document.createElement('tr');
      formRow.className = 'bom-add-row';
      const cell = document.createElement('td');
      cell.colSpan = targetRow.children.length;
      const form = document.createElement('form');
      form.className = 'bom-add-form';
      form.innerHTML = `
        <div class="bom-add-fields">
          <div class="field">
            <label>Potomek*</label>
            <input type="text" class="bom-child-search" placeholder="SKU, nĂˇzev, EAN" autocomplete="off" />
            <input type="hidden" class="bom-child-sku" />
            <div class="bom-search-results"></div>
          </div>
          <div class="field">
            <label>Koeficient*</label>
            <input type="number" step="0.001" min="0" class="bom-input-koef" required />
          </div>
          <div class="field">
            <label>MJ potomka</label>
            <input type="text" class="bom-input-unit" />
          </div>
          <div class="field">
            <label>Druh vazby</label>
            <select class="bom-input-bond">
              <option value="">(automaticky)</option>
              <option value="sada">sada</option>
              <option value="karton">karton</option>
            </select>
          </div>
        </div>
        <div class="bom-add-actions">
          <strong>RodiÄŤ:</strong> <span>${rowData.node.sku || ''}</span>
          <span class="bom-add-error"></span>
          <button type="submit">UloĹľit</button>
          <button type="button" class="bom-add-cancel">ZruĹˇit</button>
        </div>
      `;
      cell.appendChild(form);
      formRow.appendChild(cell);
      targetRow.parentNode.insertBefore(formRow, targetRow.nextSibling);
      bomAddState = { row: formRow };
      setupAddForm(form, rowData, refresh);
    }

    function closeBomAddForm() {
      if (bomAddState.row) {
        bomAddState.row.remove();
        bomAddState = { row: null };
      }
    }

    function setupAddForm(form, rowData, refresh) {
      const searchInput = form.querySelector('.bom-child-search');
      const skuInput = form.querySelector('.bom-child-sku');
      const resultsBox = form.querySelector('.bom-search-results');
      const unitInput = form.querySelector('.bom-input-unit');
      const bondSelect = form.querySelector('.bom-input-bond');
      const coefInput = form.querySelector('.bom-input-koef');
      const errorBox = form.querySelector('.bom-add-error');
      const cancelBtn = form.querySelector('.bom-add-cancel');

      bondSelect.value = rowData.node.typ === 'karton' ? 'karton' : '';

      let searchTimer = null;
      searchInput.addEventListener('input', () => {
        skuInput.value = '';
        if (searchTimer) clearTimeout(searchTimer);
        const term = searchInput.value.trim();
        if (term.length < 2) {
          resultsBox.innerHTML = '';
          return;
        }
        searchTimer = setTimeout(() => runProductSearch(term), 250);
      });

      function runProductSearch(term) {
        fetch(`${productSearchUrl}?q=${encodeURIComponent(term)}`)
          .then((r) => r.json())
          .then((data) => renderSearchResults(data.items || []))
          .catch(() => { resultsBox.innerHTML = '<span class="bom-search-empty">Chyba vyhledĂˇvĂˇnĂ­</span>'; });
      }

      function renderSearchResults(items) {
        resultsBox.innerHTML = '';
        if (!items.length) {
          const empty = document.createElement('span');
          empty.className = 'bom-search-empty';
          empty.textContent = 'Nenalezeno';
          resultsBox.appendChild(empty);
          return;
        }
        items.forEach((item) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'bom-search-option';
          btn.textContent = `${item.sku} â€“ ${item.nazev}`;
          btn.addEventListener('click', () => {
            skuInput.value = item.sku;
            searchInput.value = `${item.sku} â€“ ${item.nazev}`;
            unitInput.value = item.merna_jednotka || '';
            resultsBox.innerHTML = '';
          });
          resultsBox.appendChild(btn);
        });
      }

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        errorBox.textContent = '';
        const childSku = (skuInput.value || searchInput.value || '').trim();
        const coef = parseFloat(String(coefInput.value).replace(',', '.'));
        if (!childSku) {
          errorBox.textContent = 'Vyberte potomka.';
          return;
        }
        if (!Number.isFinite(coef) || coef <= 0) {
          errorBox.textContent = 'Koeficient musĂ­ bĂ˝t kladnĂ© ÄŤĂ­slo.';
          return;
        }
        const payload = {
          parent: rowData.node.sku,
          child: childSku,
          koeficient: coef,
          merna_jednotka_potomka: unitInput.value.trim(),
          druh_vazby: bondSelect.value,
        };
        fetch(bomAddUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify(payload),
        })
          .then((r) => r.json())
          .then((data) => {
            if (!data.ok) {
              errorBox.textContent = data.error || 'UloĹľenĂ­ se nezdaĹ™ilo.';
              return;
            }
            closeBomAddForm();
            refresh();
          })
          .catch(() => { errorBox.textContent = 'UloĹľenĂ­ se nezdaĹ™ilo.'; });
      });

      cancelBtn.addEventListener('click', (event) => {
        event.preventDefault();
        closeBomAddForm();
      });
    }

    function startEdit(cell, sku) {
      cell.dataset.editing = '1';
      const field = cell.dataset.field;
      const type = cell.dataset.type || 'text';
      const currentValue = cell.dataset.value ?? cell.textContent.trim();
      let input;
      if (type === 'select') {
        const optionsKey = cell.dataset.options;
        input = document.createElement('select');
        appendOptions(input, meta[optionsKey] ?? []);
        input.value = currentValue;
      } else if (type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = 3;
        input.value = currentValue;
      } else {
        input = document.createElement('input');
        input.type = type === 'number' ? 'number' : 'text';
        if (type === 'number' && cell.dataset.step) {
          input.step = cell.dataset.step;
        }
        input.value = currentValue;
      }
      input.className = 'inline-input';
      cell.innerHTML = '';
      cell.appendChild(input);
      input.focus();
      if (input.select) input.select();

      const finish = (commit) => {
        cell.dataset.editing = '0';
        input.removeEventListener('blur', onBlur);
        input.removeEventListener('keydown', onKey);
        if (!commit) {
          cell.textContent = formatDisplay(field, currentValue);
          cell.dataset.value = currentValue;
          return;
        }
        const newValue = input.value.trim();
        if (newValue === currentValue) {
          cell.textContent = formatDisplay(field, currentValue);
          return;
        }
        saveChange(sku, field, newValue)
          .then((ok) => {
            const valueToShow = ok ? newValue : currentValue;
            if (ok) cell.dataset.value = newValue;
            cell.textContent = formatDisplay(field, valueToShow);
          });
      };

      const onBlur = () => finish(true);
      const onKey = (e) => {
        if (e.key === 'Enter' && type !== 'textarea') {
          e.preventDefault();
          finish(true);
        } else if (e.key === 'Escape') {
          e.preventDefault();
          finish(false);
        }
      };
      input.addEventListener('blur', onBlur);
      input.addEventListener('keydown', onKey);
    }

    function appendOptions(select, options) {
      select.innerHTML = '';
      select.appendChild(new Option('â€“', ''));
      options.forEach((opt) => select.appendChild(new Option(opt.label, opt.value)));
    }

    function formatDisplay(field, value) {
      if (!value) return 'â€“';
      if (field === 'aktivni') return value === '1' ? 'âś“' : 'â€“';
      if (field === 'znacka_id') return lookupLabel(meta.brands, value);
      if (field === 'skupina_id') return lookupLabel(meta.groups, value);
      return value;
    }

    function lookupLabel(list, value) {
      const found = list.find((item) => item.value === String(value));
      return found ? found.label : 'â€“';
    }

    async function saveChange(sku, field, value) {
      try {
        const response = await fetch(updateUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({sku, field, value})
        });
        const data = await response.json();
        if (!data.ok) {
          alert(data.error || 'UloĹľenĂ­ se nezdaĹ™ilo.');
          return false;
        }
        return true;
      } catch (err) {
        alert('Chyba pĹ™i uklĂˇdĂˇnĂ­.');
        return false;
      }
    }
  })();
});
</script>
<?php if (!empty($message)): ?>
  <div class="notice notice-success">
    <?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?>
  </div>
<?php endif; ?>

<details class="collapsible" id="product-create-panel">
  <summary>PĹ™idat produkt</summary>
  <div class="collapsible-body">
    <section class="collapsible-block">
      <h3 class="collapsible-heading">NĂˇpovÄ›da â€“ CSV a pole produktu</h3>
      <p><strong>Popis sloupcĹŻ CSV (oddÄ›lovaÄŤ stĹ™ednĂ­k):</strong></p>
      <ul>
        <li><code>sku</code> â€“ povinnĂ˝ internĂ­ kĂłd produktu.</li>
        <li><code>alt_sku</code> â€“ volitelnĂ˝ alternativnĂ­ kĂłd (unikĂˇtnĂ­, nesmĂ­ bĂ˝t shodnĂ© se SKU).</li>
        <li><code>ean</code> â€“ volitelnĂ˝ EAN / ÄŤĂˇrovĂ˝ kĂłd.</li>
        <li><code>znaÄŤka</code> / <code>skupina</code> â€“ nĂˇzvy definovanĂ© v NastavenĂ­.</li>
        <li><code>typ</code> â€“ jedna z hodnot <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>balenĂ­</code>, <code>karton</code>.</li>
        <li><code>mÄ›rnĂˇ_jednotka</code> â€“ kĂłd jednotky z NastavenĂ­ (napĹ™. <code>ks</code>, <code>kg</code>).</li>
        <li><code>nĂˇzev</code> â€“ povinnĂ˝ nĂˇzev poloĹľky.</li>
        <li><code>min_zĂˇsoba</code> â€“ bezpeÄŤnĂˇ zĂˇsoba; plĂˇnovĂˇnĂ­ ji mĂˇ drĹľet alespoĹ na tĂ©to hodnotÄ›.</li>
        <li><code>min_dĂˇvka</code> â€“ minimĂˇlnĂ­ vĂ˝robnĂ­ dĂˇvka. MenĹˇĂ­ mnoĹľstvĂ­ se nevyrĂˇbĂ­.</li>
        <li><code>krok_vĂ˝roby</code> â€“ o kolik lze dĂˇvku navyĹˇovat nad minimum (napĹ™. krok 50 â‡’ 200, 250, 300â€¦).</li>
        <li><code>vĂ˝robnĂ­_doba_dnĹŻ</code> â€“ dĂ©lka vĂ˝roby v kalendĂˇĹ™nĂ­ch dnech.</li>
        <li><code>aktivnĂ­</code> â€“ 1 = aktivnĂ­, 0 = skrytĂ˝ produkt.</li>
        <li><code>poznĂˇmka</code> â€“ libovolnĂ˝ text.</li>
      </ul>
      <p>DesetinnĂ© hodnoty piĹˇte s teÄŤkou. CSV musĂ­ bĂ˝t v UTF-8.</p>
    </section>

    <section class="collapsible-block">
      <h3 class="collapsible-heading">NovĂ˝ produkt</h3>
      <form method="post" action="/products/create" class="product-create-form">
        <label>SKU*</label><input type="text" name="sku" value="<?= htmlspecialchars((string)($formOld['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?>" required />
        <label>Alt SKU</label><input type="text" name="alt_sku" value="<?= htmlspecialchars((string)($formOld['alt_sku'] ?? ''),ENT_QUOTES,'UTF-8') ?>" />
        <label>EAN</label><input type="text" name="ean" value="<?= htmlspecialchars((string)($formOld['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?>" />
        <label>ZnaÄŤka</label>
        <select name="znacka_id">
          <option value=""<?= empty($formOld['znacka_id'] ?? 0) ? ' selected' : '' ?>>VĹˇechny</option>
          <?php foreach (($brands ?? []) as $b): $id=(int)$b['id']; ?>
            <option value="<?= $id ?>"<?= (int)($formOld['znacka_id'] ?? 0) === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <label>Skupina</label>
        <select name="skupina_id">
          <option value=""<?= empty($formOld['skupina_id'] ?? 0) ? ' selected' : '' ?>>VĹˇechny</option>
          <?php foreach (($groups ?? []) as $g): $gid=(int)$g['id']; ?>
            <option value="<?= $gid ?>"<?= (int)($formOld['skupina_id'] ?? 0) === $gid ? ' selected' : '' ?>><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <label>Typ*</label>
        <select name="typ" required>
          <?php foreach (($types ?? []) as $t): $selected = ((string)($formOld['typ'] ?? '') === (string)$t) ? ' selected' : ''; ?>
            <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"<?= $selected ?>><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <label>MÄ›rnĂˇ jednotka*</label>
        <select name="merna_jednotka" required>
          <?php foreach (($units ?? []) as $u): $code = (string)$u['kod']; ?>
            <option value="<?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?>"<?= ((string)($formOld['merna_jednotka'] ?? '') === $code) ? ' selected' : '' ?>><?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <label>NĂˇzev*</label><input type="text" name="nazev" value="<?= htmlspecialchars((string)($formOld['nazev'] ?? ''),ENT_QUOTES,'UTF-8') ?>" required />
        <label>Min. zĂˇsoba</label><input type="number" step="0.001" name="min_zasoba" value="<?= htmlspecialchars((string)($formOld['min_zasoba'] ?? ''),ENT_QUOTES,'UTF-8') ?>" />
        <label>Min. dĂˇvka</label><input type="number" step="0.001" name="min_davka" value="<?= htmlspecialchars((string)($formOld['min_davka'] ?? ''),ENT_QUOTES,'UTF-8') ?>" />
        <label>Krok vĂ˝roby</label><input type="number" step="0.001" name="krok_vyroby" value="<?= htmlspecialchars((string)($formOld['krok_vyroby'] ?? ''),ENT_QUOTES,'UTF-8') ?>" />
        <label>VĂ˝robnĂ­ doba (dny)</label><input type="number" step="1" name="vyrobni_doba_dni" value="<?= htmlspecialchars((string)($formOld['vyrobni_doba_dni'] ?? ''),ENT_QUOTES,'UTF-8') ?>" />
        <label>AktivnĂ­*</label>
        <select name="aktivni">
          <option value="1"<?= (string)($formOld['aktivni'] ?? '1') === '1' ? ' selected' : '' ?>>AktivnĂ­</option>
          <option value="0"<?= (string)($formOld['aktivni'] ?? '1') === '0' ? ' selected' : '' ?>>Skryto</option>
        </select>
        <label>PoznĂˇmka</label><textarea name="poznamka" rows="2"><?= htmlspecialchars((string)($formOld['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></textarea>
        <button type="submit">UloĹľit produkt</button>
      </form>
    </section>

    <section class="collapsible-block" id="product-import">
      <h3 class="collapsible-heading">Import a Ăşprava produktĹŻ</h3>
      <?php if (!empty($importMessage) || !empty($importStats) || !empty($importErrors)): ?>
        <?php $importHasErrors = !empty($importErrors); ?>
        <div class="notice <?= $importHasErrors ? 'notice-error' : 'notice-success' ?> import-result">
          <?php if (!empty($importMessage)): ?>
            <strong><?= htmlspecialchars((string)$importMessage,ENT_QUOTES,'UTF-8') ?></strong>
          <?php endif; ?>
          <?php if (!empty($importStats)): ?>
            <ul class="import-stats">
              <li>NovĂ©: <strong><?= (int)($importStats['created'] ?? 0) ?></strong></li>
              <li>AktualizovanĂ©: <strong><?= (int)($importStats['updated'] ?? 0) ?></strong></li>
              <li>Beze zmÄ›ny: <strong><?= (int)($importStats['unchanged'] ?? 0) ?></strong></li>
              <li class="<?= ((int)($importStats['errors'] ?? 0)) === 0 ? 'text-success' : 'text-error' ?>">
                Chyby: <strong><?= (int)($importStats['errors'] ?? 0) ?></strong>
              </li>
            </ul>
          <?php endif; ?>
          <?php if ($importHasErrors): ?>
            <div>Chyby importu:</div>
            <ul style="margin:0.4rem 0 0 1rem;">
              <?php foreach ($importErrors as $e): ?>
                <li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li>
              <?php endforeach; ?>
            </ul>
          <?php elseif (empty($importStats)): ?>
            <div class="text-success">Chyby: 0</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <p><a href="/products/export">StĂˇhnout CSV (aktuĂˇlnĂ­)</a></p>
      <form method="post" action="/products/import" enctype="multipart/form-data">
        <label>NahrĂˇt CSV</label><br>
        <input type="file" name="csv" accept=".csv" required />
        <br>
        <button type="submit">Importovat</button>
        <span class="muted">PouĹľĂ­vejte UTF-8 a stĹ™ednĂ­k jako oddÄ›lovaÄŤ.</span>
      </form>
    </section>
  </div>
</details>

<details class="collapsible" id="bom-import-panel">
  <summary>Import BOM (karton / sada)</summary>
  <div class="collapsible-body">
    <section class="collapsible-block" id="bom-import">
      <h3 class="collapsible-heading">NĂˇpovÄ›da â€“ BOM import</h3>
      <p><strong>Popis sloupcĹŻ (oddÄ›lovaÄŤ stĹ™ednĂ­k):</strong></p>
      <ul>
        <li><code>rodic_sku</code> â€“ finĂˇlnĂ­ produkt nebo karton, pro kterĂ˝ sklĂˇdĂˇte recepturu.</li>
        <li><code>potomek_sku</code> â€“ komponenta, kterĂˇ do rodiÄŤe vstupuje.</li>
        <li><code>koeficient</code> â€“ mnoĹľstvĂ­ potomka na 1 jednotku rodiÄŤe (ve stejnĂ© MJ jako mĂˇ potomek).</li>
        <li><code>merna_jednotka_potomka</code> â€“ volitelnĂ©; prĂˇzdnĂ© pole pĹ™evezme MJ potomka z kmenovĂ˝ch produktĹŻ.</li>
        <li><code>druh_vazby</code> â€“ <code>karton</code> pouze pro rodiÄŤe typu karton; ve vĹˇech ostatnĂ­ch pĹ™Ă­padech zvolte <em>sada</em>. PrĂˇzdnĂ© pole systĂ©m dopoÄŤĂ­tĂˇ stejnÄ›.</li>
      </ul>
      <p>DesetinnĂ© hodnoty zadĂˇvejte s teÄŤkou. KaĹľdou vazbu lze nahrĂˇt kdykoliv â€“ rodiÄŤ i potomek musĂ­ existovat v tabulce produktĹŻ.</p>
    </section>
    <?php
      $bomHasErrors = !empty($bomErrors);
      $bomNotice = $bomError ?? $bomMessage ?? null;
    ?>
    <?php if ($bomNotice || $bomHasErrors || !empty($bomStats)): ?>
      <div class="notice <?= $bomError ? 'notice-error' : 'notice-success' ?> import-result">
        <?php if ($bomNotice): ?>
          <strong><?= htmlspecialchars((string)$bomNotice,ENT_QUOTES,'UTF-8') ?></strong>
        <?php endif; ?>
        <?php if (!empty($bomStats)): ?>
          <ul class="import-stats">
            <li>NovĂ©: <strong><?= (int)($bomStats['created'] ?? 0) ?></strong></li>
            <li>AktualizovanĂ©: <strong><?= (int)($bomStats['updated'] ?? 0) ?></strong></li>
            <li class="<?= ((int)($bomStats['errors'] ?? 0)) === 0 ? 'text-success' : 'text-error' ?>">
              Chyby: <strong><?= (int)($bomStats['errors'] ?? 0) ?></strong>
            </li>
          </ul>
        <?php endif; ?>
        <?php if ($bomHasErrors): ?>
          <div>Chyby importu:</div>
          <ul style="margin:0.4rem 0 0 1rem;">
            <?php foreach ($bomErrors as $e): ?>
              <li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        <?php elseif (empty($bomStats)): ?>
          <div class="text-success">Chyby: 0</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($bomOrphans)): ?>
      <div class="notice notice-warning">
        <strong>NepĹ™iĹ™azenĂ© vazby (chybĂ­ produkt):</strong>
        <ul style="margin:0.4rem 0 0 1rem;">
          <?php foreach ($bomOrphans as $orphan): ?>
            <li>
              <?= htmlspecialchars($orphan['rodic_sku'],ENT_QUOTES,'UTF-8') ?>
              â†’ <?= htmlspecialchars($orphan['potomek_sku'],ENT_QUOTES,'UTF-8') ?>
              (<?= $orphan['missing_parent'] ? 'chybĂ­ rodiÄŤ' : '' ?><?= ($orphan['missing_parent'] && $orphan['missing_child']) ? ', ' : '' ?><?= $orphan['missing_child'] ? 'chybĂ­ potomek' : '' ?>)
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <p class="muted-note">Celkem vazeb v tabulce BOM: <strong><?= number_format((int)($bomTotal ?? 0), 0, ',', ' ') ?></strong></p>
    <p><a href="/bom/export">StĂˇhnout CSV (aktuĂˇlnĂ­)</a></p>
    <form method="post" action="/bom/import" enctype="multipart/form-data">
      <label>NahrĂˇt CSV</label><br>
      <input type="file" name="csv" accept=".csv" required />
      <br>
      <button type="submit">Importovat</button>
      <span class="muted">Tip: pouĹľĂ­vejte UTF-8; oddÄ›lovaÄŤ je stĹ™ednĂ­k.</span>
    </form>
  </div>
</details>


<div class="product-search-panel">
  <div class="section-title">Vyhledej produkt</div>
  <form method="get" action="/products" class="product-filter-form">
    <input type="hidden" name="search" value="1" />
    <label>
      <span>ZnaÄŤka</span>
      <select name="znacka_id">
        <option value="">VĹˇechny</option>
        <?php foreach (($brands ?? []) as $b): $id = (int)$b['id']; ?>
          <option value="<?= $id ?>"<?= $filterBrand === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Skupina</span>
      <select name="skupina_id">
        <option value="">VĹˇechny</option>
        <?php foreach (($groups ?? []) as $g): $id = (int)$g['id']; ?>
          <option value="<?= $id ?>"<?= $filterGroup === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Typ</span>
      <select name="typ">
        <option value="">VĹˇechny</option>
        <?php foreach (($types ?? []) as $t): ?>
          <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"<?= $filterType === $t ? ' selected' : '' ?>><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Hledat</span>
      <input type="text" name="q" value="<?= htmlspecialchars($filterSearch,ENT_QUOTES,'UTF-8') ?>" placeholder="SKU / nĂˇzev / EAN" />
    </label>
    <div class="search-actions">
      <button type="submit">Vyhledat</button>
      <?php if ($hasSearchActive): ?>
        <span class="search-result-pill">Zobrazeno <?= $resultCount ?></span>
        <a href="/products" class="search-reset" title="ZruĹˇit filtr" aria-label="ZruĹˇit filtr">Ă—</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if (!$hasSearchActive): ?>
  <p class="muted">Zadejte parametry vyhledĂˇvĂˇnĂ­ a potvrÄŹte tlaÄŤĂ­tkem â€žVyhledatâ€ś. Seznam produktĹŻ se zobrazĂ­ aĹľ po vyhledĂˇnĂ­.</p>
<?php elseif (empty($items)): ?>
  <p class="muted">Ĺ˝ĂˇdnĂ© produkty neodpovĂ­dajĂ­ zadanĂ˝m filtrĹŻm.</p>
<?php else: ?>
<table class="products-table">
  <tr>
    <th>SKU</th>
    <th>Alt SKU</th>
    <th>EAN</th>
    <th>ZnaÄŤka</th>
    <th>Skupina</th>
    <th>Typ</th>
    <th>MJ</th>
    <th>NĂˇzev</th>
    <th>Min. zĂˇsoba</th>
    <th>Min. dĂˇvka</th>
    <th>Krok vĂ˝roby</th>
    <th>VĂ˝robnĂ­ doba</th>
    <th>AktivnĂ­</th>
    <th>PoznĂˇmka</th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>">
    <td class="sku-cell" data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>">
      <span class="sku-toggle">â–¸</span>
      <span><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></span>
    </td>
    <td class="editable" data-field="alt_sku" data-type="text" data-value="<?= htmlspecialchars((string)($it['alt_sku'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
      <?= isset($it['alt_sku']) && $it['alt_sku'] !== '' ? htmlspecialchars((string)$it['alt_sku'],ENT_QUOTES,'UTF-8') : 'â€“' ?>
    </td>
    <td class="editable" data-field="ean" data-type="text" data-value="<?= htmlspecialchars((string)($it['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
      <?= isset($it['ean']) && $it['ean'] !== '' ? htmlspecialchars((string)$it['ean'],ENT_QUOTES,'UTF-8') : 'â€“' ?>
    </td>
    <td class="editable" data-field="znacka_id" data-type="select" data-options="brands" data-value="<?= (int)($it['znacka_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['znacka'] ?? 'â€“'),ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="skupina_id" data-type="select" data-options="groups" data-value="<?= (int)($it['skupina_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['skupina'] ?? 'â€“'),ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="typ" data-type="select" data-options="types" data-value="<?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="merna_jednotka" data-type="select" data-options="units" data-value="<?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="nazev" data-type="text" data-value="<?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="min_zasoba" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['min_zasoba'] ?></td>
    <td class="editable" data-field="min_davka" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['min_davka'] ?></td>
    <td class="editable" data-field="krok_vyroby" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['krok_vyroby'] ?></td>
    <td class="editable" data-field="vyrobni_doba_dni" data-type="number" data-step="1" data-value="<?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="aktivni" data-type="select" data-options="active" data-value="<?= (int)$it['aktivni'] ?>"><?= (int)$it['aktivni'] ? 'âś“' : 'â€“' ?></td>
    <td class="editable" data-field="poznamka" data-type="textarea" data-value="<?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
