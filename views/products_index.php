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
/* Zaoblená šipka (Lucide chevron-down) místo textového trojúhelníku; po otevření se otočí */
.collapsible summary::after {
  content: '';
  width: 18px;
  height: 18px;
  margin-left: 0.5rem;
  background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235f5e5a' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat center / contain;
  transition: transform .15s;
}
.collapsible[open] summary::after { transform: rotate(180deg); }
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
  background:#f6f5f2;
  color:#111111;
  font-size:0.75rem;
  cursor:help;
}
.min-stock-cell {
  position:relative;
}
.min-stock-cell[data-stock-mode="auto"] {
  color:#5f5e5a;
  cursor:not-allowed;
}
.min-stock-cell[data-stock-mode="auto"]::after {
  content:'auto';
  font-size:0.7rem;
  margin-left:0.4rem;
  padding:0.05rem 0.4rem;
  border-radius:999px;
  background:#fbeee6;
  color:#c14a00;
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
.muted { color:#5f5e5a; }

.products-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.products-table th,
.products-table td { border:1px solid #ddd; padding:0.45rem 0.55rem; vertical-align:top; }
.products-table th { background:#f3f6f9; }
.del-btn { border:none; background:transparent; color:#c62828; cursor:pointer; font-size:16px; line-height:1; padding:2px 6px; border-radius:4px; }
.del-btn:hover { background:#ffebee; }
.del-disabled { color:#d6d4cf; }
.product-create-form { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px 14px; align-items:end; margin-top:0.6rem; }
.product-create-form .fld { display:flex; flex-direction:column; gap:3px; min-width:0; }
.product-create-form .fld label { font-size:12px; color:#555; font-weight:600; }
.product-create-form .fld input, .product-create-form .fld select, .product-create-form .fld textarea { width:100%; box-sizing:border-box; }
.product-create-form .fld-wide { grid-column:span 2; }
.product-create-form .form-actions { grid-column:1 / -1; margin-top:0.2rem; }
@media (max-width:760px){ .product-create-form { grid-template-columns:repeat(2, minmax(0,1fr)); } .product-create-form .fld-wide { grid-column:span 2; } }
.inactive-sku { text-decoration: line-through; }
.sku-cell {
  cursor: pointer;
  font-weight: 600;
  white-space:nowrap;
  padding:0.45rem 0.55rem;
}
.sku-toggle {
  display:inline-block;
  margin-right:0.25rem;
  font-size:0.9rem;
  color:#5f5e5a;
  width:1rem;
  text-align:center;
}
.inline-input { width:100%; box-sizing:border-box; }
.bom-tree-row td { background:#fdfdfd; padding:0.65rem; border-top:none; }
.bom-tree-table { width:100%; border-collapse:collapse; font-family:"Fira Mono","Consolas",monospace; font-size:0.9rem; }
.bom-tree-table th,
.bom-tree-table td { border:1px solid #e5e5e5; padding:0.35rem 0.5rem; vertical-align:top; }
.bom-tree-table th { background:#f7f9fb; text-align:left; font-weight:600; }
.bom-tree-cell { white-space:nowrap; display:flex; align-items:flex-start; gap:0.2rem; }
.bom-tree-prefix { display:inline-block; color:#8a8985; white-space:pre; font-family:"Fira Mono","Consolas",monospace; }
.bom-tree-label { font-weight:600; display:inline-flex; flex-wrap:wrap; }
.bom-root-label { font-weight:700; }
.bom-tree-note { margin-left:0.5rem; font-size:0.8rem; color:#b00020; }
.bom-tree-actions { text-align:right; white-space:nowrap; }
.bom-action-btn {
  border: 1px solid #d6d4cf;
  background: #fff;
  color: #111111;
  font-size: 0.85rem;
  line-height: 1;
  padding: 0.2rem 0.45rem;
  margin-left: 0.2rem;
  border-radius: 4px;
  cursor: pointer;
}
.bom-action-btn:hover { background:#f6f5f2; }
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
  border:1px solid #e5e5e5;
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
  color:#8a8985;
}
.search-actions {
  align-self:flex-end;
  display:flex;
  align-items:center;
  gap:0.4rem;
}
.search-result-pill {
  font-size:0.9rem;
  color:#5f5e5a;
}
.search-reset {
  text-decoration:none;
  font-size:1.2rem;
  color:#b00020;
  padding:0 0.2rem;
  line-height:1;
}
.search-reset:hover { color:#d32f2f; }
button.search-reset { border:none; background:transparent; cursor:pointer; }
#product-results .search-result-pill { margin:0.2rem 0 0; }
/* Výběr sloupců – poslední sloupec tabulky */
.col-picker-th { position:relative; width:1%; text-align:center; padding:0.2rem 0.35rem !important; }
.col-picker-btn { border:1px solid var(--c-border-strong); background:#fff; color:var(--c-text-secondary); border-radius:4px; padding:0.2rem 0.35rem; cursor:pointer; line-height:0; }
.col-picker-btn:hover, .col-picker-btn[aria-expanded="true"] { color:var(--c-primary-text); border-color:var(--c-primary); }
.col-picker-menu { position:absolute; right:0; top:100%; z-index:20; margin-top:4px; background:#fff; border:1px solid var(--c-border-strong); border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,.12); padding:0.5rem 0.7rem; text-align:left; font-weight:600; white-space:nowrap; min-width:200px; }
.col-picker-menu[hidden] { display:none; }
.col-picker-title { font-size:0.8rem; color:var(--c-text-muted); text-transform:uppercase; letter-spacing:.03em; margin-bottom:0.3rem; }
.col-picker-menu label { display:flex; align-items:center; gap:0.45rem; padding:0.15rem 0; cursor:pointer; font-weight:600; }
.col-picker-menu label.is-required { cursor:default; color:var(--c-text-secondary); }
/* Aktivní: fajfka / křížek, po kliknutí přepínač */
.editable[data-field="aktivni"] { text-align:center; cursor:pointer; }
.active-mark { display:inline-flex; line-height:0; }
.active-yes { color:var(--c-ok); }
.active-no { color:var(--c-danger); }
.active-toggle { display:inline-flex; border:1px solid var(--c-border-strong); border-radius:4px; overflow:hidden; }
.active-toggle button { border:none; border-radius:0; background:#fff; padding:0.2rem 0.4rem; line-height:0; cursor:pointer; }
.active-toggle button + button { border-left:1px solid var(--c-border-strong); }
.active-toggle button.on { color:var(--c-ok); }
.active-toggle button.off { color:var(--c-danger); }
.active-toggle button:hover { background:var(--c-surface); }
.active-toggle button.on.is-sel { background:#e8f5e9; }
.active-toggle button.off.is-sel { background:#ffebee; }
.col-picker-td { width:1%; }
/* Přetahování sloupců: úchyt a ruka po najetí, při tažení průhledný sloupec a oranžová čára v místě dopadu */
.products-table th[draggable="true"] { cursor:grab; user-select:none; white-space:nowrap; position:relative; }
.products-table th[draggable="true"]:active { cursor:grabbing; }
.col-grip { display:inline-flex; vertical-align:-2px; margin-right:0.2rem; color:var(--c-text-muted); opacity:0; transition:opacity .12s; }
.products-table th[draggable="true"]:hover .col-grip { opacity:1; }
.products-table th.is-dragging { opacity:0.45; }
.products-table th.drop-before { box-shadow:inset 3px 0 0 var(--c-primary); }
.products-table th.drop-after { box-shadow:inset -3px 0 0 var(--c-primary); }
.col-order-reset { margin-top:0.5rem; padding-top:0.45rem; border:none; border-top:1px solid var(--c-border); border-radius:0; background:transparent; color:var(--c-primary-text); cursor:pointer; width:100%; text-align:left; font-weight:600; display:flex; align-items:center; gap:0.35rem; }
/* Výrobní sloupce (převzato z Výroby) */
.products-table td.needs-production { background:#fffdf7; }
.products-table td.is-blocked { background:#fff3f0; }
.qty-cell { white-space:nowrap; font-variant-numeric:tabular-nums; }
.sku-vyroba-cell { cursor:pointer; font-weight:600; white-space:nowrap; }
.sku-vyroba-toggle, .available-toggle, .demand-toggle { display:inline-flex; color:var(--c-text-secondary); width:1rem; }
.sku-availability { display:flex; flex-direction:column; gap:0.25rem; }
.sku-availability-content { display:flex; align-items:center; gap:0.35rem; }
.sku-availability-bar, .ratio-bar { width:100%; height:6px; border-radius:999px; background:var(--c-border); overflow:hidden; }
.sku-availability-bar { max-width:100px; }
.sku-availability-bar span, .ratio-bar span { display:block; height:100%; background:#66bb6a; }
.sku-availability-bar span[data-state="warn"], .ratio-bar span[data-state="warn"] { background:#ffa726; }
.sku-availability-bar span[data-state="critical"], .ratio-bar span[data-state="critical"] { background:#ff7043; }
.deficit-cell { font-weight:600; }
.deficit-with-bar { display:flex; flex-direction:column; gap:0.25rem; }
.available-cell, .demand-cell { display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer; }
.bom-node-critical { color:#b00020; font-weight:600; }
.bom-node-warning { color:#ef6c00; font-weight:600; }
.bom-root-row td { font-weight:700; }
.movement-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
.movement-table th, .movement-table td { border:1px solid var(--c-border); padding:0.35rem 0.45rem; vertical-align:top; }
.movement-table th { background:var(--c-surface-2); text-align:left; }
.movement-table .qty-cell, .movement-table .stock-cell { text-align:right; font-variant-numeric:tabular-nums; }
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
      active: [{value:'1',label:'Ano'},{value:'0',label:'Ne'}],
      stockModes: [{value:'auto',label:'Automaticky'},{value:'manual',label:'Manuálně'}]
    };

    // Delegace na obal výsledků – tabulka se při živém hledání vyměňuje
    const table = document.getElementById('product-results');
    if (!table) return;

    const updateUrl = '/products/update';
    const bomUrl = '/products/bom-tree';
    const bomAddUrl = '/products/bom/add';
    const bomDeleteUrl = '/products/bom/delete';
    const productSearchUrl = '/products/search';
    let bomState = { row: null, detail: null };
    let bomAddState = { row: null };
    table.addEventListener('products:replaced', () => {
      bomState = { row: null, detail: null };
      bomAddState = { row: null };
    });

    table.addEventListener('click', (event) => {
      // Aktivní: jedno kliknutí otevře přepínač fajfka / křížek
      const activeCell = event.target.closest('.editable[data-field="aktivni"]');
      if (activeCell && table.contains(activeCell)) {
        if (activeCell.dataset.editing !== '1') startActiveToggle(activeCell);
        return;
      }
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
      if (!cell || cell.dataset.editing === '1' || cell.dataset.field === 'aktivni') return;
      const row = cell.closest('tr');
      const sku = row?.dataset.sku;
      if (!sku) return;
      if (cell.dataset.lock === 'auto' && (row?.dataset.stockMode || '').toLowerCase() === 'auto') {
        alert(cell.dataset.lockMessage || 'Pole nastavuje automatický výpočet stavů.');
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
      if (toggle) toggle.innerHTML = LUCIDE['chevron-down'];
      row.classList.add('bom-open');
      const detailRow = document.createElement('tr');
      detailRow.className = 'bom-tree-row';
      const detailCell = document.createElement('td');
      detailCell.colSpan = Array.from(row.children).filter((c) => getComputedStyle(c).display !== 'none').length; // bez skrytých sloupců
      detailCell.textContent = 'Načítám…';
      detailRow.appendChild(detailCell);
      row.parentNode.insertBefore(detailRow, row.nextSibling);
      bomState = { row, detail: detailRow };
      loadBomTree(cell.dataset.sku || row.dataset.sku, detailCell);
    }

    function closeBomRow() {
      if (!bomState.row) return;
      const toggle = bomState.row.querySelector('.sku-toggle');
      if (toggle) toggle.innerHTML = LUCIDE['chevron-right'];
      bomState.row.classList.remove('bom-open');
      if (bomState.detail) bomState.detail.remove();
      bomState = { row: null, detail: null };
      closeBomAddForm();
    }

    async function loadBomTree(sku, container) {
      if (!sku) {
        container.textContent = 'Chybí SKU.';
        return;
      }
      try {
        const response = await fetch(`${bomUrl}?sku=${encodeURIComponent(sku)}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (!data.ok) throw new Error(data.error || 'Nepodařilo se načíst BOM strom.');
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
      table.innerHTML = '<thead><tr><th>Strom vazeb</th><th>Koeficient</th><th>MJ</th><th>Typ položky</th><th>Akce</th></tr></thead>';
      const body = document.createElement('tbody');
      const rows = flattenBomTree(tree);
      rows.forEach((rowData) => {
        const tr = document.createElement('tr');
        if (rowData.node.is_root) {
          tr.className = 'bom-root-row';
        }

      const first = document.createElement('td');
      first.className = 'bom-tree-cell';
      const prefix = document.createElement('span');
      prefix.className = 'bom-tree-prefix';
      prefix.textContent = buildBranchPrefix(rowData.guides);
      if (!prefix.textContent.trim()) prefix.style.display = 'none';
      const label = document.createElement('span');
      label.className = 'bom-tree-label';
      label.textContent = formatNodeLabel(rowData.node);
      if (rowData.node.is_root) {
        label.classList.add('bom-root-label');
      }
      first.appendChild(prefix);
      first.appendChild(label);
        if (rowData.node.cycle) {
          const badge = document.createElement('span');
          badge.className = 'bom-tree-note';
          badge.textContent = '⟳ cyklus';
          first.appendChild(badge);
        }
        tr.appendChild(first);

        const edge = rowData.node.edge || {};
        tr.appendChild(createValueCell(formatNumber(edge.koeficient)));
        tr.appendChild(createValueCell(displayValue(edge.merna_jednotka || rowData.node.merna_jednotka)));
        tr.appendChild(createValueCell(displayValue(rowData.node.typ)));

        const actions = document.createElement('td');
        actions.className = 'bom-tree-actions';
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'bom-action-btn';
        addBtn.textContent = '+';
        addBtn.title = 'Přidat potomka';
        addBtn.addEventListener('click', () => openBomAddForm(tr, rowData, refresh));
        actions.appendChild(addBtn);
        if (rowData.parentSku) {
          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'bom-action-btn bom-action-btn--danger';
          delBtn.innerHTML = LUCIDE.x;
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
      out += guides[i] ? '   ' : '│  ';
    }
    out += guides[guides.length - 1] ? '└─ ' : '├─ ';
    return out;
  }

    function formatNodeLabel(node) {
      const sku = node.sku || '(bez SKU)';
      return node.nazev ? `${sku} – ${node.nazev}` : sku;
    }

    function createValueCell(value) {
      const td = document.createElement('td');
      td.textContent = value === undefined || value === null || value === '' ? '' : value;
      return td;
    }

    function displayValue(value) {
      if (value === undefined || value === null || value === '') return '';
      return String(value);
    }

    function formatNumber(value) {
      if (value === undefined || value === null || value === '') return '';
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
            alert(data.error || 'Smazání se nezdařilo.');
            return;
          }
          refresh();
        })
        .catch(() => alert('Smazání se nezdařilo.'));
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
            <input type="text" class="bom-child-search" placeholder="SKU, název, EAN" autocomplete="off" />
            <input type="hidden" class="bom-child-sku" />
            <div class="bom-search-results"></div>
          </div>
          <div class="field">
            <label>Koeficient*</label>
            <input type="number" step="0.001" min="0" class="bom-input-koef" required />
          </div>
        </div>
        <div class="bom-add-actions">
          <strong>Rodič:</strong> <span>${rowData.node.sku || ''}</span>
          <span class="bom-add-error"></span>
          <button type="submit">Uložit</button>
          <button type="button" class="bom-add-cancel">Zrušit</button>
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
      const coefInput = form.querySelector('.bom-input-koef');
      const errorBox = form.querySelector('.bom-add-error');
      const cancelBtn = form.querySelector('.bom-add-cancel');
      const parentSku = (rowData && rowData.node && rowData.node.sku) ? String(rowData.node.sku) : '';

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
          .catch(() => { resultsBox.innerHTML = '<span class="bom-search-empty">Chyba vyhledávání</span>'; });
      }

      function renderSearchResults(items) {
        resultsBox.innerHTML = '';
        // nenabízet potomka, který je totožný s rodičem (self-reference)
        if (parentSku) items = items.filter((it) => String(it.sku) !== parentSku);
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
          const skuSpan = document.createElement('span');
          skuSpan.textContent = item.sku;
          if (Number(item.aktivni) === 0) skuSpan.className = 'inactive-sku';
          btn.appendChild(skuSpan);
          btn.appendChild(document.createTextNode(` – ${item.nazev}`));
          btn.addEventListener('click', () => {
            skuInput.value = item.sku;
            searchInput.value = `${item.sku} – ${item.nazev}`;
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
          errorBox.textContent = 'Koeficient musí být kladné číslo.';
          return;
        }
        const payload = {
          parent: rowData.node.sku,
          child: childSku,
          koeficient: coef,
        };
        fetch(bomAddUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify(payload),
        })
          .then((r) => r.json())
          .then((data) => {
            if (!data.ok) {
              errorBox.textContent = data.error || 'Uložení se nezdařilo.';
              return;
            }
            closeBomAddForm();
            refresh();
          })
          .catch(() => { errorBox.textContent = 'Uložení se nezdařilo.'; });
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
          setDisplay(cell, field, currentValue);
          cell.dataset.value = currentValue;
          return;
        }
        const newValue = input.value.trim();
        if (newValue === currentValue) {
          setDisplay(cell, field, currentValue);
          return;
        }
        saveChange(sku, field, newValue)
          .then((ok) => {
            const valueToShow = ok ? newValue : currentValue;
            if (ok) cell.dataset.value = newValue;
            setDisplay(cell, field, valueToShow);
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

    function setDisplay(cell, field, value) {
      if (field === 'aktivni') { renderActive(cell, value); return; }
      cell.textContent = formatDisplay(field, value);
    }

    function renderActive(cell, value) {
      cell.innerHTML = value === '1'
        ? `<span class="active-mark active-yes" title="Aktivní – kliknutím změníte">${LUCIDE.check}</span>`
        : (value === '0' ? `<span class="active-mark active-no" title="Neaktivní – kliknutím změníte">${LUCIDE.x}</span>` : '');
      const skuLabel = cell.closest('tr')?.querySelector('.sku-cell > span:not(.sku-toggle)');
      if (skuLabel) skuLabel.classList.toggle('inactive-sku', value === '0');
    }

    // Přepínač Aktivní: dvě tlačítka (fajfka / křížek), volba se hned uloží; Esc nebo klik mimo = beze změny
    function startActiveToggle(cell) {
      const sku = cell.closest('tr')?.dataset.sku;
      if (!sku) return;
      const current = cell.dataset.value;
      cell.dataset.editing = '1';
      cell.innerHTML = `<span class="active-toggle" role="group" aria-label="Aktivní">`
        + `<button type="button" data-v="1" class="on${current === '1' ? ' is-sel' : ''}" title="Aktivní">${LUCIDE.check}</button>`
        + `<button type="button" data-v="0" class="off${current === '0' ? ' is-sel' : ''}" title="Neaktivní">${LUCIDE.x}</button>`
        + `</span>`;
      const close = (value) => {
        document.removeEventListener('click', onOutside, true);
        document.removeEventListener('keydown', onKey);
        cell.dataset.editing = '0';
        renderActive(cell, value);
      };
      const onOutside = (e) => { if (!cell.contains(e.target)) close(current); };
      const onKey = (e) => { if (e.key === 'Escape') close(current); };
      cell.querySelector('.active-toggle').addEventListener('click', async (e) => {
        e.stopPropagation();
        const btn = e.target.closest('button[data-v]');
        if (!btn) return;
        const value = btn.dataset.v;
        if (value === current) { close(current); return; }
        cell.querySelectorAll('button').forEach((b) => { b.disabled = true; });
        const ok = await saveChange(sku, 'aktivni', value);
        if (ok) cell.dataset.value = value;
        close(ok ? value : current);
      });
      setTimeout(() => document.addEventListener('click', onOutside, true)); // až po aktuálním kliknutí
      document.addEventListener('keydown', onKey);
    }

    function appendOptions(select, options) {
      select.innerHTML = '';
      select.appendChild(new Option('', ''));
      options.forEach((opt) => select.appendChild(new Option(opt.label, opt.value)));
    }

    function formatDisplay(field, value) {
      if (value === undefined || value === null || value === '') return '';
      if (field === 'aktivni') return value === '1' ? 'Ano' : (value === '0' ? 'Ne' : '');
      if (field === 'znacka_id') return lookupLabel(meta.brands, value);
      if (field === 'skupina_id') return lookupLabel(meta.groups, value);
      return value;
    }

    function lookupLabel(list, value) {
      const found = list.find((item) => item.value === String(value));
      return found ? found.label : '';
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
          alert(data.error || 'Uložení se nezdařilo.');
          return false;
        }
        return true;
      } catch (err) {
        alert('Chyba při ukládání.');
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
  <summary>Přidat produkt</summary>
  <div class="collapsible-body">
    <section class="collapsible-block">
      <h3 class="collapsible-heading">Nápověda – CSV a pole produktu</h3>
      <p><strong>Popis sloupců CSV (oddělovač středník):</strong></p>
      <ul>
        <li><code>sku</code> – povinný interní kód produktu.</li>
        <li><code>alt_sku</code> – volitelný alternativní kód (unikátní, nesmí být shodné se SKU).</li>
        <li><code>ean</code> – volitelný EAN / čárový kód.</li>
        <li><code>značka</code> / <code>skupina</code> – názvy definované v Nastavení.</li>
        <li><code>typ</code> – jedna z hodnot <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>balení</code>, <code>karton</code>.</li>
        <li><code>měrná_jednotka</code> – kód jednotky z Nastavení (např. <code>ks</code>, <code>kg</code>).</li>
        <li><code>název</code> – povinný název položky.</li>
        <li><code>min_zásoba</code> – bezpečná zásoba; plánování ji má držet alespoň na této hodnotě.</li>
        <li><code>min_dávka</code> – minimální výrobní dávka. Menší množství se nevyrábí.</li>
        <li><code>krok_výroby</code> – o kolik lze dávku navyšovat nad minimum (např. krok 50 ⇒ 200, 250, 300…).</li>
        <li><code>výrobní_doba_dnů</code> – délka výroby v kalendářních dnech.</li>
        <li><code>skl_hodnota</code> - jednotkova skladova hodnota (napr. nakladova cena za MJ) pro oceneni zasob.</li>
        <li><code>aktivní</code> – 1 = aktivní, 0 = skrytý produkt.</li>
        <li><code>poznámka</code> – libovolný text.</li>
      </ul>
      <p>Desetinné hodnoty pište s tečkou. CSV musí být v UTF-8.</p>
    </section>

    <section class="collapsible-block">
      <h3 class="collapsible-heading">Nový produkt</h3>
      <form method="post" action="/products/create" class="product-create-form">
        <!-- 1. řádek: identifikace -->
        <div class="fld"><label>SKU*</label><input type="text" name="sku" value="<?= htmlspecialchars((string)($formOld['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?>" required /></div>
        <div class="fld"><label>Alt SKU</label><input type="text" name="alt_sku" value="<?= htmlspecialchars((string)($formOld['alt_sku'] ?? ''),ENT_QUOTES,'UTF-8') ?>" /></div>
        <div class="fld"><label>EAN</label><input type="text" name="ean" value="<?= htmlspecialchars((string)($formOld['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?>" /></div>
        <div class="fld"><label>Název*</label><input type="text" name="nazev" value="<?= htmlspecialchars((string)($formOld['nazev'] ?? ''),ENT_QUOTES,'UTF-8') ?>" required /></div>
        <!-- 2. řádek: zařazení -->
        <div class="fld"><label>Značka</label>
          <select name="znacka_id">
            <option value=""<?= empty($formOld['znacka_id'] ?? 0) ? ' selected' : '' ?>>Všechny</option>
            <?php foreach (($brands ?? []) as $b): $id=(int)$b['id']; ?>
              <option value="<?= $id ?>"<?= (int)($formOld['znacka_id'] ?? 0) === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fld"><label>Skupina</label>
          <select name="skupina_id">
            <option value=""<?= empty($formOld['skupina_id'] ?? 0) ? ' selected' : '' ?>>Všechny</option>
            <?php foreach (($groups ?? []) as $g): $gid=(int)$g['id']; ?>
              <option value="<?= $gid ?>"<?= (int)($formOld['skupina_id'] ?? 0) === $gid ? ' selected' : '' ?>><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fld"><label>Typ*</label>
          <select name="typ" required>
            <?php foreach (($types ?? []) as $t): $selected = ((string)($formOld['typ'] ?? '') === (string)$t) ? ' selected' : ''; ?>
              <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"<?= $selected ?>><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fld"><label>Měrná jednotka*</label>
          <select name="merna_jednotka" required>
            <?php foreach (($units ?? []) as $u): $code = (string)$u['kod']; ?>
              <option value="<?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?>"<?= ((string)($formOld['merna_jednotka'] ?? '') === $code) ? ' selected' : '' ?>><?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- 3. řádek: výrobní parametry -->
        <div class="fld"><label>Min. zásoba</label><input type="number" step="0.001" name="min_zasoba" value="<?= htmlspecialchars((string)($formOld['min_zasoba'] ?? ''),ENT_QUOTES,'UTF-8') ?>" /></div>
        <div class="fld"><label>Min. dávka</label><input type="number" step="0.001" name="min_davka" value="<?= htmlspecialchars((string)($formOld['min_davka'] ?? ''),ENT_QUOTES,'UTF-8') ?>" /></div>
        <div class="fld"><label>Krok výroby</label><input type="number" step="0.001" name="krok_vyroby" value="<?= htmlspecialchars((string)($formOld['krok_vyroby'] ?? ''),ENT_QUOTES,'UTF-8') ?>" /></div>
        <div class="fld"><label>Výrobní doba (dny)</label><input type="number" step="1" name="vyrobni_doba_dni" value="<?= htmlspecialchars((string)($formOld['vyrobni_doba_dni'] ?? ''),ENT_QUOTES,'UTF-8') ?>" /></div>
        <!-- 4. řádek: ostatní -->
        <div class="fld"><label>Skladová hodnota</label><input type="number" step="0.01" name="skl_hodnota" value="<?= htmlspecialchars((string)($formOld['skl_hodnota'] ?? ''),ENT_QUOTES,'UTF-8') ?>" /></div>
        <div class="fld"><label>Aktivní*</label>
          <select name="aktivni">
            <option value="1"<?= (string)($formOld['aktivni'] ?? '1') === '1' ? ' selected' : '' ?>>Aktivní</option>
            <option value="0"<?= (string)($formOld['aktivni'] ?? '1') === '0' ? ' selected' : '' ?>>Skryto</option>
          </select>
        </div>
        <div class="fld fld-wide"><label>Poznámka</label><textarea name="poznamka" rows="2"><?= htmlspecialchars((string)($formOld['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></textarea></div>
        <div class="form-actions"><button type="submit">Uložit produkt</button></div>
      </form>
    </section>

    <section class="collapsible-block" id="product-import">
      <h3 class="collapsible-heading">Import a úprava produktů</h3>
      <?php if (!empty($importMessage) || !empty($importStats) || !empty($importErrors)): ?>
        <?php $importHasErrors = !empty($importErrors); ?>
        <div class="notice <?= $importHasErrors ? 'notice-error' : 'notice-success' ?> import-result">
          <?php if (!empty($importMessage)): ?>
            <strong><?= htmlspecialchars((string)$importMessage,ENT_QUOTES,'UTF-8') ?></strong>
          <?php endif; ?>
          <?php if (!empty($importStats)): ?>
            <ul class="import-stats">
              <li>Nové: <strong><?= (int)($importStats['created'] ?? 0) ?></strong></li>
              <li>Aktualizované: <strong><?= (int)($importStats['updated'] ?? 0) ?></strong></li>
              <li>Beze změny: <strong><?= (int)($importStats['unchanged'] ?? 0) ?></strong></li>
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
      <p><a href="/products/export">Stáhnout CSV (aktuální)</a></p>
      <form method="post" action="/products/import" enctype="multipart/form-data">
        <label>Nahrát CSV</label><br>
        <input type="file" name="csv" accept=".csv" required />
        <br>
        <button type="submit">Importovat</button>
        <span class="muted">Používejte UTF-8 a středník jako oddělovač.</span>
      </form>
    </section>
  </div>
</details>

<details class="collapsible" id="bom-import-panel">
  <summary>Import BOM (karton / sada)</summary>
  <div class="collapsible-body">
    <section class="collapsible-block" id="bom-import">
      <h3 class="collapsible-heading">Nápověda – BOM import</h3>
      <p><strong>Popis sloupců (oddělovač středník):</strong></p>
      <ul>
        <li><code>rodic_sku</code> – finální produkt nebo karton, pro který skládáte recepturu.</li>
        <li><code>potomek_sku</code> – komponenta, která do rodiče vstupuje.</li>
        <li><code>koeficient</code> – množství potomka na 1 jednotku rodiče (ve stejné MJ jako má potomek).</li>
        <li><code>merna_jednotka_potomka</code> – volitelné; prázdné pole převezme MJ potomka z kmenových produktů.</li>
      </ul>
      <p>Desetinné hodnoty zadávejte s tečkou. Každou vazbu lze nahrát kdykoliv – rodič i potomek musí existovat v tabulce produktů.</p>
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
            <li>Nové: <strong><?= (int)($bomStats['created'] ?? 0) ?></strong></li>
            <li>Aktualizované: <strong><?= (int)($bomStats['updated'] ?? 0) ?></strong></li>
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
        <strong>Nepřiřazené vazby (chybí produkt):</strong>
        <ul style="margin:0.4rem 0 0 1rem;">
          <?php foreach ($bomOrphans as $orphan): ?>
            <li>
              <?= htmlspecialchars($orphan['rodic_sku'],ENT_QUOTES,'UTF-8') ?>
              <?= ikona('arrow-right', 14, 'ikona-muted') ?> <?= htmlspecialchars($orphan['potomek_sku'],ENT_QUOTES,'UTF-8') ?>
              (<?= $orphan['missing_parent'] ? 'chybí rodič' : '' ?><?= ($orphan['missing_parent'] && $orphan['missing_child']) ? ', ' : '' ?><?= $orphan['missing_child'] ? 'chybí potomek' : '' ?>)
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <p class="muted-note">Celkem vazeb v tabulce BOM: <strong><?= number_format((int)($bomTotal ?? 0), 0, ',', ' ') ?></strong></p>
    <p><a href="/bom/export">Stáhnout CSV (aktuální)</a></p>
    <form method="post" action="/bom/import" enctype="multipart/form-data">
      <label>Nahrát CSV</label><br>
      <input type="file" name="csv" accept=".csv" required />
      <br>
      <button type="submit">Importovat</button>
      <span class="muted">Tip: používejte UTF-8; oddělovač je středník.</span>
    </form>
  </div>
</details>


<div class="product-search-panel">
  <div class="section-title">Vyhledej produkt</div>
  <form method="get" action="/products" class="product-filter-form" id="product-search" autocomplete="off">
    <label>
      <span>Značka</span>
      <select name="znacka_id">
        <option value="">Všechny</option>
        <?php foreach (($brands ?? []) as $b): $id = (int)$b['id']; ?>
          <option value="<?= $id ?>"<?= $filterBrand === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Skupina</span>
      <select name="skupina_id">
        <option value="">Všechny</option>
        <?php foreach (($groups ?? []) as $g): $id = (int)$g['id']; ?>
          <option value="<?= $id ?>"<?= $filterGroup === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Typ</span>
      <select name="typ">
        <option value="">Všechny</option>
        <?php foreach (($types ?? []) as $t): ?>
          <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"<?= $filterType === $t ? ' selected' : '' ?>><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Hledat</span>
      <input type="text" name="q" value="<?= htmlspecialchars($filterSearch,ENT_QUOTES,'UTF-8') ?>" placeholder="SKU / název / EAN – hledá se průběžně" />
    </label>
    <div class="search-actions">
      <button type="button" class="search-reset" id="product-search-reset" title="Vymazat hledání" aria-label="Vymazat hledání"><?= ikona('x', 14) ?></button>
    </div>
  </form>
</div>

<div id="product-results">
<?php require __DIR__ . '/products_results.php'; ?>
</div>

<script>
// Živé hledání produktů (vzor: hledání v Pohybech) + výběr zobrazených sloupců
(function () {
  const form = document.getElementById('product-search');
  const box = document.getElementById('product-results');
  if (!form || !box) return;

  // --- Výběr sloupců: skryté sloupce si pamatuje prohlížeč (localStorage), SKU a Název jsou povinné
  const HIDE_KEY = 'gworm.produkty.skryteSloupce';
  const REQUIRED = ['sku', 'nazev', '_picker'];
  let hidden = [];
  try {
    const saved = JSON.parse(localStorage.getItem(HIDE_KEY) || '[]');
    if (Array.isArray(saved)) hidden = saved.filter((k) => !REQUIRED.includes(k));
  } catch (_) { hidden = []; }
  const colStyle = document.createElement('style');
  document.head.appendChild(colStyle);

  // --- Pořadí sloupců: přetažením hlavičky, ukládá se per prohlížeč (localStorage); prázdné = výchozí pořadí
  const ORDER_KEY = 'gworm.produkty.poradiSloupcu';
  let order = [];
  try {
    const saved = JSON.parse(localStorage.getItem(ORDER_KEY) || '[]');
    if (Array.isArray(saved)) order = saved;
  } catch (_) { order = []; }
  function saveOrder() {
    try {
      if (order.length) localStorage.setItem(ORDER_KEY, JSON.stringify(order));
      else localStorage.removeItem(ORDER_KEY);
    } catch (_) { /* jen pohodlí */ }
  }
  // Uložené pořadí + sloupce přidané později (vloží se za svého výchozího předchůdce); výběr sloupců vždy poslední
  function mergedOrder(defaults) {
    const result = order.filter((k) => defaults.includes(k) && k !== '_picker');
    defaults.forEach((k, i) => {
      if (k === '_picker' || result.includes(k)) return;
      let pos = 0;
      for (let j = i - 1; j >= 0; j--) {
        const p = result.indexOf(defaults[j]);
        if (p >= 0) { pos = p + 1; break; }
      }
      result.splice(pos, 0, k);
    });
    if (defaults.includes('_picker')) result.push('_picker');
    return result;
  }
  function applyOrder() {
    const tbl = box.querySelector('.products-table');
    if (!tbl || !tbl.rows.length) return;
    const head = tbl.rows[0];
    const current = Array.from(head.cells).map((th) => th.dataset.col);
    if (!tbl.dataset.defaultOrder) tbl.dataset.defaultOrder = current.join(','); // server vykresluje výchozí pořadí
    const target = mergedOrder(tbl.dataset.defaultOrder.split(','));
    if (target.join(',') === current.join(',')) return;
    const perm = target.map((k) => current.indexOf(k));
    Array.from(tbl.rows).forEach((tr) => {
      if (tr.cells.length !== current.length) return; // rozbalené detaily (jedna buňka přes celou šířku)
      const cells = Array.from(tr.cells);
      perm.forEach((idx) => tr.appendChild(cells[idx]));
    });
  }

  let dragKey = null;
  function clearDropMarks() {
    box.querySelectorAll('.drop-before, .drop-after').forEach((th) => th.classList.remove('drop-before', 'drop-after'));
  }
  function dropTarget(ev) {
    const th = ev.target.closest('.products-table th[data-col]');
    if (!th || !dragKey || th.dataset.col === '_picker' || th.dataset.col === dragKey) return null;
    const rect = th.getBoundingClientRect();
    return { th, after: ev.clientX > rect.left + rect.width / 2 };
  }
  box.addEventListener('dragstart', (ev) => {
    const th = ev.target.closest && ev.target.closest('.products-table th[draggable="true"]');
    if (!th) return;
    dragKey = th.dataset.col;
    th.classList.add('is-dragging');
    ev.dataTransfer.effectAllowed = 'move';
    ev.dataTransfer.setData('text/plain', dragKey); // Firefox bez dat tažení nespustí
  });
  box.addEventListener('dragover', (ev) => {
    const t = dropTarget(ev);
    clearDropMarks();
    if (!t) return;
    ev.preventDefault();
    ev.dataTransfer.dropEffect = 'move';
    t.th.classList.add(t.after ? 'drop-after' : 'drop-before');
  });
  box.addEventListener('drop', (ev) => {
    const t = dropTarget(ev);
    clearDropMarks();
    if (!t) return;
    ev.preventDefault();
    const keys = Array.from(t.th.parentNode.cells).map((th) => th.dataset.col).filter((k) => k !== '_picker' && k !== dragKey);
    keys.splice(keys.indexOf(t.th.dataset.col) + (t.after ? 1 : 0), 0, dragKey);
    order = keys;
    saveOrder();
    applyOrder();
    applyColumns();
  });
  box.addEventListener('dragend', () => {
    clearDropMarks();
    box.querySelectorAll('.is-dragging').forEach((th) => th.classList.remove('is-dragging'));
    dragKey = null;
  });

  function applyColumns() {
    // Skrytí přes nth-child podle pořadí hlavičky – buňky řádků nepotřebují vlastní značku
    const rules = [];
    box.querySelectorAll('.products-table th[data-col]').forEach((th) => {
      if (hidden.includes(th.dataset.col)) {
        rules.push(`.products-table > tbody > tr > :nth-child(${th.cellIndex + 1})`);
      }
    });
    colStyle.textContent = rules.length ? rules.join(',\n') + ' { display:none; }' : '';
    box.querySelectorAll('.col-picker-menu input[type=checkbox]').forEach((cb) => {
      cb.checked = !hidden.includes(cb.value);
    });
  }
  function saveColumns() {
    try { localStorage.setItem(HIDE_KEY, JSON.stringify(hidden)); } catch (_) { /* jen pohodlí, bez uložení to funguje */ }
  }
  function closePicker() {
    box.querySelectorAll('.col-picker-menu').forEach((m) => { m.hidden = true; });
    box.querySelectorAll('.col-picker-btn').forEach((b) => b.setAttribute('aria-expanded', 'false'));
  }

  box.addEventListener('click', (ev) => {
    if (ev.target.closest('.col-order-reset')) {
      order = [];
      saveOrder();
      applyOrder();
      applyColumns();
      return;
    }
    const btn = ev.target.closest('.col-picker-btn');
    if (btn) {
      const menu = btn.parentNode.querySelector('.col-picker-menu');
      const open = menu.hidden;
      closePicker();
      menu.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  });
  box.addEventListener('change', (ev) => {
    const cb = ev.target.closest('.col-picker-menu input[type=checkbox]');
    if (!cb || REQUIRED.includes(cb.value)) return;
    hidden = hidden.filter((k) => k !== cb.value);
    if (!cb.checked) hidden.push(cb.value);
    applyColumns();
    saveColumns();
  });
  document.addEventListener('click', (ev) => {
    if (!ev.target.closest('.col-picker-th')) closePicker();
  });
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') closePicker(); });
  applyOrder();
  applyColumns();

  // --- Živé hledání: debounce 250 ms u psaní, změna selectu hned, výsledky jako HTML fragment
  let timer = null;
  let seq = 0;
  async function runSearch() {
    const params = new URLSearchParams();
    new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') params.set(k, String(v)); });
    const hasAny = Array.from(params.keys()).length > 0;
    if (hasAny) params.set('search', '1');
    // URL drží aktuální filtr – reload i návrat po uložení/smazání ukáže stejný výpis
    history.replaceState(null, '', '/products' + (hasAny ? '?' + params.toString() : ''));
    params.set('partial', '1');
    const mySeq = ++seq;
    try {
      const res = await fetch('/products?' + params.toString(), { headers: { 'Accept': 'text/html' } });
      if (res.redirected || !res.ok) { window.location.reload(); return; } // např. vypršelé přihlášení
      const html = await res.text();
      if (mySeq !== seq) return; // mezitím přišel novější dotaz
      box.innerHTML = html;
      box.dispatchEvent(new CustomEvent('products:replaced'));
      applyOrder();
      applyColumns();
    } catch (_) {
      if (mySeq === seq) box.innerHTML = '<p class="text-error">Vyhledávání selhalo.</p>';
    }
  }
  form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(runSearch, 250); });
  form.addEventListener('change', (ev) => {
    if (ev.target.tagName !== 'SELECT') return;
    clearTimeout(timer);
    runSearch();
  });
  form.addEventListener('submit', (ev) => { ev.preventDefault(); clearTimeout(timer); runSearch(); });
  const reset = document.getElementById('product-search-reset');
  if (reset) {
    reset.addEventListener('click', () => {
      form.querySelectorAll('select').forEach((s) => { s.value = ''; });
      form.querySelectorAll('input[type=text]').forEach((i) => { i.value = ''; });
      clearTimeout(timer);
      runSearch();
      const q = form.querySelector('input[name=q]');
      if (q) q.focus();
    });
  }
})();
</script>

<script>
// Výrobní sloupce převzaté z Výroby: rozbalení stromu potomků s potřebou (SKU výroba),
// pohybů položky (Dostupné) a stromu poptávky (Dovyrobit). Každý detail má vlastní stav.
(function () {
  const box = document.getElementById('product-results');
  if (!box) return;

  const states = { tree: null, movement: null, demand: null };
  box.addEventListener('products:replaced', () => { states.tree = states.movement = states.demand = null; });

  box.addEventListener('click', (event) => {
    const movementCell = event.target.closest('.available-cell');
    if (movementCell) { event.preventDefault(); toggleDetail('movement', movementCell, '.available-toggle', 'Načítám pohyby…', loadMovementList); return; }
    const demandCell = event.target.closest('.demand-cell');
    if (demandCell) { event.preventDefault(); toggleDetail('demand', demandCell, '.demand-toggle', 'Načítám zdroje poptávky…', loadDemandTree); return; }
    const treeCell = event.target.closest('.sku-vyroba-cell');
    if (treeCell) { event.preventDefault(); toggleDetail('tree', treeCell, '.sku-vyroba-toggle', 'Načítám strom vazeb…', loadNeedTree); }
  });

  function toggleDetail(kind, cell, toggleSel, loadingText, loader) {
    const row = cell.closest('tr');
    if (!row) return;
    const wasOpen = states[kind] && states[kind].row === row;
    closeDetail(kind);
    if (wasOpen) return;
    const toggle = cell.querySelector(toggleSel);
    if (toggle) toggle.innerHTML = LUCIDE['chevron-down'];
    const detailRow = document.createElement('tr');
    detailRow.className = 'bom-tree-row';
    const detailCell = document.createElement('td');
    detailCell.colSpan = Array.from(row.children).filter((c) => getComputedStyle(c).display !== 'none').length; // bez skrytých sloupců
    detailCell.textContent = loadingText;
    detailRow.appendChild(detailCell);
    row.parentNode.insertBefore(detailRow, row.nextSibling);
    states[kind] = { row, detail: detailRow, toggle };
    loader(cell, detailCell);
  }

  function closeDetail(kind) {
    const st = states[kind];
    if (!st) return;
    if (st.toggle) st.toggle.innerHTML = LUCIDE['chevron-right'];
    if (st.detail) st.detail.remove();
    states[kind] = null;
  }

  async function fetchJson(url) {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  // --- Strom potomků s potřebou (stejné jako SKU ve Výrobě)
  async function loadNeedTree(cell, container) {
    const sku = cell.dataset.sku;
    const required = parseFloat(cell.dataset.deficit || '0');
    try {
      const requiredParam = Number.isFinite(required) ? `&required=${encodeURIComponent(required)}` : '';
      const data = await fetchJson(`/products/bom-tree?sku=${encodeURIComponent(sku)}${requiredParam}`);
      if (!data.ok) throw new Error(data.error || 'Nepodařilo se načíst strom.');
      container.innerHTML = '';
      container.appendChild(buildNeedTable(data.tree));
    } catch (err) {
      container.textContent = `Chyba: ${err.message || err}`;
    }
  }

  function buildNeedTable(tree) {
    if (!tree || !Array.isArray(tree.children) || tree.children.length === 0) {
      const wrap = document.createElement('div');
      wrap.textContent = 'Produkt nemá navázané potomky.';
      return wrap;
    }
    const table = document.createElement('table');
    table.className = 'bom-tree-table';
    table.innerHTML = '<thead><tr><th>Strom vazeb</th><th>Koeficient</th><th>MJ</th><th>Typ položky</th><th>Dostupné</th><th>Cílový stav</th><th>Chybí</th></tr></thead>';
    const body = document.createElement('tbody');
    flattenTree(tree).forEach((row) => {
      const tr = document.createElement('tr');
      if (row.node.is_root) tr.classList.add('bom-root-row');
      const label = treeLabelCell(row);
      const status = row.node.status || null;
      if (!row.node.is_root && status && (status.deficit || 0) > 0.0005) {
        label.text.classList.add('bom-node-critical');
      } else if (!row.node.is_root && status && (status.ratio || 0) > 0.4) {
        label.text.classList.add('bom-node-warning');
      }
      tr.appendChild(label.td);
      const edge = row.node.edge || {};
      tr.appendChild(createCell(edge.koeficient));
      tr.appendChild(createCell(edge.merna_jednotka || row.node.merna_jednotka));
      tr.appendChild(createCell(row.node.typ));
      tr.appendChild(createCell(formatInteger(status ? status.available : null)));
      tr.appendChild(createCell(formatInteger(status ? status.target : null)));
      tr.appendChild(createCell(formatInteger(status ? status.deficit : null)));
      body.appendChild(tr);
    });
    table.appendChild(body);
    return table;
  }

  // --- Pohyby položky (stejné jako Dostupné ve Výrobě)
  async function loadMovementList(cell, container) {
    try {
      const data = await fetchJson(`/production/movements?sku=${encodeURIComponent(cell.dataset.sku)}`);
      if (!data.ok) throw new Error(data.error || 'Chyba načtení pohybů.');
      const rows = data.movements || [];
      if (!rows.length) { container.textContent = 'Žádné pohyby.'; return; }
      container.textContent = '';
      container.appendChild(buildMovementTable(rows));
    } catch (err) {
      container.textContent = err && err.message ? err.message : 'Chyba načtení pohybů.';
    }
  }

  function buildMovementTable(rows) {
    const table = document.createElement('table');
    table.className = 'movement-table';
    table.innerHTML = '<thead><tr><th>Datum</th><th>E-shop</th><th>Faktura</th><th>SKU</th><th>počet</th><th>Aktuální sklad</th><th>název položky</th></tr></thead>';
    const body = document.createElement('tbody');
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.appendChild(createCell(row.datum ?? ''));
      tr.appendChild(createCell(row.eshop ?? ''));
      tr.appendChild(createCell(row.faktura ?? ''));
      tr.appendChild(createCell(row.sku ?? ''));
      const qty = createCell(row.pocet ?? '');
      qty.className = 'qty-cell';
      tr.appendChild(qty);
      const stock = createCell(row.sklad ?? '');
      stock.className = 'stock-cell';
      tr.appendChild(stock);
      tr.appendChild(createCell(row.nazev ?? ''));
      body.appendChild(tr);
    });
    table.appendChild(body);
    return table;
  }

  // --- Strom poptávky (stejné jako Dovyrobit ve Výrobě)
  async function loadDemandTree(cell, container) {
    try {
      const data = await fetchJson(`/production/demand-tree?sku=${encodeURIComponent(cell.dataset.sku)}`);
      if (!data.ok) throw new Error(data.error || 'Nepodařilo se načíst zdroje poptávky.');
      if (!data.tree) { container.textContent = 'Nenalezeny žádné zdroje poptávky.'; return; }
      container.innerHTML = '';
      container.appendChild(buildDemandTable(data.tree));
      if (!data.tree.children || !data.tree.children.length) {
        const note = document.createElement('p');
        note.className = 'muted';
        note.textContent = 'Poptávka vzniká přímo na této položce (rezervace nebo minimální zásoba).';
        container.appendChild(note);
      }
    } catch (err) {
      container.textContent = err.message || 'Nepodařilo se načíst zdroje poptávky.';
    }
  }

  function buildDemandTable(tree) {
    const table = document.createElement('table');
    table.className = 'bom-tree-table demand-tree-table';
    const rootUnit = tree.merna_jednotka || '';
    const head = document.createElement('thead');
    const headRow = document.createElement('tr');
    [
      ['Strom poptávky', ''],
      ['Dovyrobit', 'Hodnota „dovyrobit" pro tento uzel v jeho měrné jednotce.'],
      [`Požadavek na ${tree.sku}`, `Příspěvek všech rodičů přepočtený do měrné jednotky kořene (${rootUnit || '—'}).`],
      ['Koeficient', ''],
      ['Režim', ''],
    ].forEach(([text, tip]) => {
      const th = document.createElement('th');
      th.textContent = text;
      if (tip) {
        const icon = document.createElement('span');
        icon.className = 'info-icon';
        icon.title = tip;
        icon.innerHTML = LUCIDE.info;
        th.appendChild(icon);
      }
      headRow.appendChild(th);
    });
    head.appendChild(headRow);
    table.appendChild(head);
    const body = document.createElement('tbody');
    flattenTree(tree).forEach((row) => {
      if (row.node.is_nonstock) return; // neskladové uzly se v poptávce nezobrazují
      const tr = document.createElement('tr');
      if (row.node.is_root) tr.classList.add('bom-root-row');
      tr.appendChild(treeLabelCell(row).td);
      const unit = row.node.merna_jednotka || '';
      tr.appendChild(createCell(`${formatNumber(row.node.needed, 0)} ${unit}`.trim()));
      tr.appendChild(createCell(`${formatNumber(row.node.contribution, 0)} ${rootUnit}`.trim()));
      tr.appendChild(createCell(formatDemandEdge(row.node.edge)));
      tr.appendChild(createCell(row.node.status && row.node.status.mode ? row.node.status.mode : '—'));
      body.appendChild(tr);
    });
    table.appendChild(body);
    return table;
  }

  function formatDemandEdge(edge) {
    if (!edge || !edge.koeficient) return '—';
    return formatNumber(edge.koeficient) + (edge.merna_jednotka ? ` ${edge.merna_jednotka}` : '');
  }

  // --- Společné pomocníky stromů
  function flattenTree(node, guides = []) {
    const rows = [{ node, guides }];
    const children = Array.isArray(node.children) ? node.children : [];
    children.forEach((child, index) => {
      rows.push(...flattenTree(child, guides.concat([index === children.length - 1])));
    });
    return rows;
  }

  function buildPrefix(guides) {
    let prefix = '';
    guides.forEach((isLast, idx) => {
      if (idx === guides.length - 1) prefix += isLast ? '└── ' : '├── ';
      else prefix += isLast ? '    ' : '│   ';
    });
    return prefix;
  }

  function treeLabelCell(row) {
    const td = document.createElement('td');
    td.className = 'bom-tree-cell';
    const prefix = document.createElement('span');
    prefix.className = 'bom-tree-prefix';
    prefix.textContent = buildPrefix(row.guides);
    if (!prefix.textContent.trim()) prefix.style.display = 'none';
    const text = document.createElement('span');
    text.className = 'bom-tree-label';
    text.textContent = `${row.node.sku}${row.node.nazev ? ` – ${row.node.nazev}` : ''}`.trim();
    if (row.node.is_root) text.classList.add('bom-root-label');
    td.appendChild(prefix);
    td.appendChild(text);
    return { td, text };
  }

  function createCell(value) {
    const td = document.createElement('td');
    td.textContent = value ?? '—';
    return td;
  }

  function formatNumber(value, decimals = 3) {
    if (value === null || value === undefined || value === '') return '—';
    const num = Number(value);
    if (!Number.isFinite(num)) return '—';
    const fixed = num.toFixed(decimals);
    // koncové nuly jen za desetinnou čárkou (jinak by se ze 100 stalo 1)
    return fixed.includes('.') ? fixed.replace(/\.?0+$/, '') : fixed;
  }

  function formatInteger(value) {
    if (value === null || value === undefined || isNaN(value)) return '—';
    return String(Math.round(Number(value)));
  }
})();
</script>
