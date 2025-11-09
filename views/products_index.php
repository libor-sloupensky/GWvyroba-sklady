<?php
  $activeFilters = $filters ?? ['brand'=>0,'group'=>0,'type'=>'','search'=>''];
  $filterBrand = (int)($activeFilters['brand'] ?? 0);
  $filterGroup = (int)($activeFilters['group'] ?? 0);
  $filterType  = (string)($activeFilters['type'] ?? '');
  $filterSearch= (string)($activeFilters['search'] ?? '');
  $hasSearchActive = (bool)($hasSearch ?? false);
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
  gap:0.35rem;
  white-space:nowrap;
}
.sku-toggle { font-size:0.9rem; color:#455a64; }
.inline-input { width:100%; box-sizing:border-box; }
.bom-tree-row td { background:#fdfdfd; padding:0.65rem; border-top:none; }
.bom-tree-table { width:100%; border-collapse:collapse; font-family:"Fira Mono","Consolas",monospace; font-size:0.9rem; }
.bom-tree-table th,
.bom-tree-table td { border:1px solid #e0e0e0; padding:0.35rem 0.5rem; vertical-align:top; }
.bom-tree-table th { background:#f7f9fb; text-align:left; font-weight:600; }
.bom-tree-cell { white-space:nowrap; }
.bom-tree-prefix { display:inline-block; min-width:1.8rem; color:#90a4ae; }
.bom-tree-label { font-weight:600; }
.bom-tree-note { margin-left:0.5rem; font-size:0.8rem; color:#b00020; }
</style>

<?php if (!empty($error)): ?>
  <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;">
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
    active: [{value:'1',label:'✓'},{value:'0',label:'–'}]
  };

  const table = document.querySelector('.products-table');
  if (!table) return;

  const updateUrl = '/products/update';
  const bomUrl = '/products/bom-tree';
  let bomState = { row: null, detail: null };

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
    startEdit(cell, sku);
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
    if (toggle) toggle.textContent = '▾';
    row.classList.add('bom-open');
    const detailRow = document.createElement('tr');
    detailRow.className = 'bom-tree-row';
    const detailCell = document.createElement('td');
    detailCell.colSpan = row.children.length;
    detailCell.textContent = 'Načítám…';
    detailRow.appendChild(detailCell);
    row.parentNode.insertBefore(detailRow, row.nextSibling);
    bomState = { row, detail: detailRow };
    loadBomTree(cell.dataset.sku || row.dataset.sku, detailCell);
  }

  function closeBomRow() {
    if (!bomState.row) return;
    const toggle = bomState.row.querySelector('.sku-toggle');
    if (toggle) toggle.textContent = '▸';
    bomState.row.classList.remove('bom-open');
    if (bomState.detail) bomState.detail.remove();
    bomState = { row: null, detail: null };
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
      container.appendChild(buildBomTable(data.tree));
    } catch (err) {
      container.textContent = `Chyba: ${err.message || err}`;
    }
  }

  function buildBomTable(tree) {
    const table = document.createElement('table');
    table.className = 'bom-tree-table';
    table.innerHTML = '<thead><tr><th>Strom vazeb</th><th>Koeficient</th><th>MJ</th><th>Druh vazby</th><th>Typ položky</th></tr></thead>';
    const body = document.createElement('tbody');
    flattenBomTree(tree).forEach(({ node, guides }) => {
      const tr = document.createElement('tr');

      const first = document.createElement('td');
      first.className = 'bom-tree-cell';
      const prefix = document.createElement('span');
      prefix.className = 'bom-tree-prefix';
      prefix.textContent = buildBranchPrefix(guides);
      const label = document.createElement('span');
      label.className = 'bom-tree-label';
      label.textContent = formatNodeLabel(node);
      first.appendChild(prefix);
      first.appendChild(label);
      if (node.cycle) {
        const badge = document.createElement('span');
        badge.className = 'bom-tree-note';
        badge.textContent = '⟳ cyklus';
        first.appendChild(badge);
      }
      tr.appendChild(first);

      const edge = node.edge || {};
      tr.appendChild(createValueCell(formatNumber(edge.koeficient)));
      tr.appendChild(createValueCell(displayValue(edge.merna_jednotka || node.merna_jednotka)));
      tr.appendChild(createValueCell(displayValue(edge.druh_vazby)));
      tr.appendChild(createValueCell(displayValue(node.typ)));
      body.appendChild(tr);
    });
    table.appendChild(body);
    return table;
  }

  function flattenBomTree(root) {
    const rows = [];
    const walk = (node, guides = []) => {
      rows.push({ node, guides });
      const children = Array.isArray(node.children) ? node.children : [];
      children.forEach((child, index) => {
        walk(child, guides.concat(index === children.length - 1));
      });
    };
    walk(root, []);
    return rows;
  }

  function buildBranchPrefix(guides) {
    if (!guides.length) return '';
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
    td.textContent = value === undefined || value === null || value === '' ? '–' : value;
    return td;
  }

  function displayValue(value) {
    if (value === undefined || value === null || value === '') return '–';
    return String(value);
  }

  function formatNumber(value) {
    if (value === undefined || value === null || value === '') return '–';
    const num = Number(value);
    if (Number.isNaN(num)) return String(value);
    return Number.isInteger(num) ? String(num) : num.toString().replace('.', ',');
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
    select.appendChild(new Option('–', ''));
    options.forEach((opt) => select.appendChild(new Option(opt.label, opt.value)));
  }

  function formatDisplay(field, value) {
    if (!value) return '–';
    if (field === 'aktivni') return value === '1' ? '✓' : '–';
    if (field === 'znacka_id') return lookupLabel(meta.brands, value);
    if (field === 'skupina_id') return lookupLabel(meta.groups, value);
    return value;
  }

  function lookupLabel(list, value) {
    const found = list.find((item) => item.value === String(value));
    return found ? found.label : '–';
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
  <div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;">
    <?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?>
  </div>
<?php endif; ?>

<details class="collapsible" id="products-help">
  <summary>Nápověda – CSV a pole produktu</summary>
  <div class="collapsible-body">
    <p><strong>Popis sloupců CSV (oddělovač ;):</strong></p>
    <ul>
      <li><code>sku</code> – povinný interní kód produktu.</li>
      <li><code>alt_sku</code> – volitelný alternativní kód (unikátní, nesmí být shodný se SKU).</li>
      <li><code>ean</code> – volitelný EAN / čárový kód.</li>
      <li><code>značka</code> / <code>skupina</code> – názvy definované v Nastavení.</li>
      <li><code>typ</code> – jedna z hodnot <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>balení</code>, <code>karton</code>.</li>
      <li><code>měrná_jednotka</code> – kód jednotky z Nastavení (např. <code>ks</code>, <code>kg</code>).</li>
      <li><code>název</code> – povinný název položky.</li>
      <li><code>min_zásoba</code> – bezpečná zásoba; plánování se má držet alespoň této hodnoty.</li>
      <li><code>min_dávka</code> – minimální vyráběná dávka. Menší množství výroba nespustí.</li>
      <li><code>krok_výroby</code> – o kolik lze dávku navyšovat nad minimum (např. krok 50 ⇒ 200, 250, 300 …).</li>
      <li><code>výrobní_doba_dní</code> – délka výroby v kalendářních dnech.</li>
      <li><code>aktivní</code> – 1 = aktivní, 0 = skrytý produkt.</li>
      <li><code>poznámka</code> – libovolný text.</li>
    </ul>
    <p>Desetinné hodnoty pište s tečkou (např. <code>0.25</code>). CSV musí být v UTF‑8.</p>
  </div>
</details>

<details class="collapsible" id="product-create-panel">
  <summary>Přidat produkt</summary>
  <div class="collapsible-body">
    <form method="post" action="/products/create" class="product-create-form">
      <label>SKU*</label><input type="text" name="sku" required />
      <label>Alt SKU</label><input type="text" name="alt_sku" />
      <label>EAN</label><input type="text" name="ean" />
      <label>Značka</label>
      <select name="znacka_id">
        <option value="">Všechny</option>
        <?php foreach (($brands ?? []) as $b): ?>
          <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <label>Skupina</label>
      <select name="skupina_id">
        <option value="">Všechny</option>
        <?php foreach (($groups ?? []) as $g): ?>
          <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <label>Typ*</label>
      <select name="typ" required>
        <?php foreach (($types ?? []) as $t): ?>
          <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <label>Měrná jednotka*</label>
      <select name="merna_jednotka" required>
        <?php foreach (($units ?? []) as $u): ?>
          <option value="<?= htmlspecialchars((string)$u['kod'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$u['kod'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <label>Název*</label><input type="text" name="nazev" required />
      <label>Min. zásoba</label><input type="number" step="0.001" name="min_zasoba" />
      <label>Min. dávka</label><input type="number" step="0.001" name="min_davka" />
      <label>Krok výroby</label><input type="number" step="0.001" name="krok_vyroby" />
      <label>Výrobní doba (dny)</label><input type="number" step="1" name="vyrobni_doba_dni" />
      <label>Aktivní*</label>
      <select name="aktivni">
        <option value="1">Aktivní</option>
        <option value="0">Skryto</option>
      </select>
      <label>Poznámka</label><textarea name="poznamka" rows="2"></textarea>
      <button type="submit">Uložit produkt</button>
    </form>
  </div>
</details>

<details class="collapsible" id="product-import-panel">
  <summary>Import a úprava produktů</summary>
  <div class="collapsible-body">
    <p><a href="/products/export">Stáhnout CSV (aktuální)</a></p>
    <?php if (!empty($errors)): ?>
      <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;">
        <strong>Chyby importu:</strong>
        <ul style="margin:0.4rem 0 0 1rem;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <form method="post" action="/products/import" enctype="multipart/form-data">
      <label>Nahrát CSV</label><br>
      <input type="file" name="csv" accept=".csv" required />
      <br>
      <button type="submit">Importovat</button>
      <span class="muted">Používejte UTF‑8 a středník jako oddělovač.</span>
    </form>
  </div>
</details>

<div class="product-search-panel">
  <div class="section-title">Vyhledej produkt</div>
  <form method="get" action="/products" class="product-filter-form">
    <input type="hidden" name="search" value="1" />
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
      <input type="text" name="q" value="<?= htmlspecialchars($filterSearch,ENT_QUOTES,'UTF-8') ?>" placeholder="SKU / název / EAN" />
    </label>
    <div style="align-self:flex-end;display:flex;gap:0.5rem;">
      <button type="submit">Vyhledat</button>
      <a href="/products" style="align-self:center;">Zrušit filtr</a>
    </div>
  </form>
</div>

<?php if (!$hasSearchActive): ?>
  <p class="muted">Zadejte parametry vyhledávání a potvrďte tlačítkem „Vyhledat“. Seznam produktů se zobrazí až po vyhledání.</p>
<?php elseif (empty($items)): ?>
  <p class="muted">Žádné produkty neodpovídají zadaným filtrům.</p>
<?php else: ?>
<table class="products-table">
  <tr>
    <th>SKU</th>
    <th>Alt SKU</th>
    <th>EAN</th>
    <th>Značka</th>
    <th>Skupina</th>
    <th>Typ</th>
    <th>MJ</th>
    <th>Název</th>
    <th>Min. zásoba</th>
    <th>Min. dávka</th>
    <th>Krok výroby</th>
    <th>Výrobní doba</th>
    <th>Aktivní</th>
    <th>Poznámka</th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>">
    <td class="sku-cell" data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>">
      <span class="sku-toggle">▸</span>
      <span><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></span>
    </td>
    <td class="editable" data-field="alt_sku" data-type="text" data-value="<?= htmlspecialchars((string)($it['alt_sku'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
      <?= isset($it['alt_sku']) && $it['alt_sku'] !== '' ? htmlspecialchars((string)$it['alt_sku'],ENT_QUOTES,'UTF-8') : '–' ?>
    </td>
    <td class="editable" data-field="ean" data-type="text" data-value="<?= htmlspecialchars((string)($it['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
      <?= isset($it['ean']) && $it['ean'] !== '' ? htmlspecialchars((string)$it['ean'],ENT_QUOTES,'UTF-8') : '–' ?>
    </td>
    <td class="editable" data-field="znacka_id" data-type="select" data-options="brands" data-value="<?= (int)($it['znacka_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['znacka'] ?? '–'),ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="skupina_id" data-type="select" data-options="groups" data-value="<?= (int)($it['skupina_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['skupina'] ?? '–'),ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="typ" data-type="select" data-options="types" data-value="<?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="merna_jednotka" data-type="select" data-options="units" data-value="<?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="nazev" data-type="text" data-value="<?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="min_zasoba" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['min_zasoba'] ?></td>
    <td class="editable" data-field="min_davka" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['min_davka'] ?></td>
    <td class="editable" data-field="krok_vyroby" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['krok_vyroby'] ?></td>
    <td class="editable" data-field="vyrobni_doba_dni" data-type="number" data-step="1" data-value="<?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="aktivni" data-type="select" data-options="active" data-value="<?= (int)$it['aktivni'] ?>"><?= (int)$it['aktivni'] ? '✓' : '–' ?></td>
    <td class="editable" data-field="poznamka" data-type="textarea" data-value="<?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
