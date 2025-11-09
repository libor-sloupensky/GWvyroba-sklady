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
.collapsible summary::-webkit-details-marker {
  display: none;
}
.collapsible summary::after {
  content: '\25BC';
  font-size: 1.4rem;
  margin-left: 0.5rem;
  color: #455a64;
}
.collapsible[open] summary::after {
  content: '\25B2';
}
.collapsible-body {
  margin-top: 0.75rem;
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
.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 1rem 0 0.4rem;
}
.muted {
  color: #607d8b;
}
.products-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}
.products-table th,
.products-table td {
  border: 1px solid #ddd;
  padding: 0.4rem 0.5rem;
  vertical-align: top;
}
.products-table th {
  background: #f3f6f9;
}
.sku-cell {
  cursor: pointer;
  font-weight: 600;
}
.inline-input {
  width: 100%;
  box-sizing: border-box;
}
.bom-tree-panel {
  border: 1px solid #cfd8dc;
  border-radius: 4px;
  padding: 0.75rem;
  background: #f6fbff;
  margin-bottom: 1rem;
}
.bom-tree-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.4rem;
}
.bom-tree-panel button {
  border: 1px solid #90a4ae;
  background: #fff;
  padding: 0.15rem 0.6rem;
  cursor: pointer;
}
#bom-tree-content {
  max-height: 320px;
  overflow-y: auto;
  background: #fff;
  padding: 0.5rem;
}
</style>

<?php if (!empty($error)): ?>
  <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;">
    <?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?>
  </div>
<?php endif; ?>
<?php if (!empty($message)): ?>
  <div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;">
    <?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?>
  </div>
<?php endif; ?>

<details class="collapsible" id="products-help">
  <summary>Nápovìda – CSV a pole produktu</summary>
  <div class="collapsible-body">
    <p><strong>Popis sloupcù CSV (oddìlovaè ;):</strong></p>
    <ul>
      <li><code>sku</code> – povinný interní kód produktu.</li>
      <li><code>alt_sku</code> – volitelný alternativní kód (unikátní, nesmí být shodný se SKU).</li>
      <li><code>ean</code> – volitelný EAN / èárový kód.</li>
      <li><code>znaèka</code> / <code>skupina</code> – názvy definované v Nastavení.</li>
      <li><code>typ</code> – jedna z hodnot <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>balení</code>, <code>karton</code>.</li>
      <li><code>mìrná_jednotka</code> – kód jednotky z Nastavení (napø. <code>ks</code>, <code>kg</code>).</li>
      <li><code>název</code> – povinný název položky.</li>
      <li><code>min_zásoba</code> – bezpeèná zásoba; plánování se má držet alespoò této hodnoty.</li>
      <li><code>min_dávka</code> – minimální vyrábìná dávka. Menší množství výroba nespustí.</li>
      <li><code>krok_výroby</code> – o kolik lze dávku navyšovat nad minimum (napø. krok 50 ? 200, 250, 300 …).</li>
      <li><code>výrobní_doba_dní</code> – délka výroby v kalendáøních dnech.</li>
      <li><code>aktivní</code> – 1 = aktivní, 0 = skrytý produkt.</li>
      <li><code>poznámka</code> – libovolný text.</li>
    </ul>
    <p>Desetinné hodnoty pište s teèkou (napø. <code>0.25</code>). CSV musí být v UTF-8.</p>
  </div>
</details>

<details class="collapsible" id="product-create-panel">
  <summary>Pøidat produkt</summary>
  <div class="collapsible-body">
    <form method="post" action="/products/create" class="product-create-form">
      <label>SKU*</label><input type="text" name="sku" required />
      <label>Alt SKU</label><input type="text" name="alt_sku" />
      <label>EAN</label><input type="text" name="ean" />
      <label>Znaèka</label>
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
      <label>Mìrná jednotka*</label>
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
  <summary>Import a úprava produktù</summary>
  <div class="collapsible-body">
    <p><a href="/products/export">Stáhnout CSV (aktuální)</a></p>
    <?php if (!empty($errors)): ?>
      <div class="notice">
        <strong>Chyby importu:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <form method="post" action="/products/import" enctype="multipart/form-data">
      <label>Nahrát CSV</label><br>
      <input type="file" name="csv" accept=".csv" required />
      <br>
      <button type="submit">Importovat</button>
      <span class="muted">Používejte UTF-8 a støedník jako oddìlovaè.</span>
    </form>
  </div>
</details>

<div class="product-search-panel">
  <div class="section-title">Vyhledej produkt</div>
  <form method="get" action="/products" class="product-filter-form">
    <label>
      <span>Znaèka</span>
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

<div id="bom-tree-panel" class="bom-tree-panel" hidden>
  <div class="bom-tree-panel-header">
    <strong>BOM strom pro <span id="bom-tree-sku"></span></strong>
    <button type="button" id="bom-tree-close">Zavøít</button>
  </div>
  <pre id="bom-tree-content"></pre>
</div>

<?php if (!$hasSearchActive): ?>
  <p class="muted">Zadejte parametry vyhledávání a potvrïte tlaèítkem „Vyhledat“. Seznam produktù se zobrazí až po vyhledání.</p>
<?php elseif (empty($items)): ?>
  <p class="muted">Žádné produkty neodpovídají zadaným filtrùm.</p>
<?php else: ?>
<table class="products-table">
  <tr>
    <th>SKU</th>
    <th>Alt SKU</th>
    <th>EAN</th>
    <th>Znaèka</th>
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
    <td class="sku-cell" data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></td>
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
    <td class="editable" data-field="aktivni" data-type="select" data-options="active" data-value="<?= (int)$it['aktivni'] ?>"><?= (int)$it['aktivni'] ? '?' : '–' ?></td>
    <td class="editable" data-field="poznamka" data-type="textarea" data-value="<?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<script>
(function () {
  const meta = {
    brands: <?= json_encode(array_map(fn($b) => ['value'=>(string)$b['id'],'label'=>$b['nazev']], $brands ?? []), JSON_UNESCAPED_UNICODE) ?>,
    groups: <?= json_encode(array_map(fn($g) => ['value'=>(string)$g['id'],'label'=>$g['nazev']], $groups ?? []), JSON_UNESCAPED_UNICODE) ?>,
    units:  <?= json_encode(array_map(fn($u) => ['value'=>$u['kod'],'label'=>$u['kod']], $units ?? []), JSON_UNESCAPED_UNICODE) ?>,
    types:  <?= json_encode(array_map(fn($t) => ['value'=>$t,'label'=>$t], $types ?? []), JSON_UNESCAPED_UNICODE) ?>,
    active: [{value:'1',label:'?'},{value:'0',label:'–'}]
  };

  const table = document.querySelector('.products-table');
  const updateUrl = '/products/update';
  const bomPanel = document.getElementById('bom-tree-panel');
  const bomContent = document.getElementById('bom-tree-content');
  const bomSkuLabel = document.getElementById('bom-tree-sku');
  const bomClose = document.getElementById('bom-tree-close');

  bomClose?.addEventListener('click', () => {
    bomPanel.hidden = true;
    bomContent.textContent = '';
  });

  if (!table) {
    return;
  }

  table.addEventListener('dblclick', (event) => {
    const skuCell = event.target.closest('.sku-cell');
    if (skuCell) {
      const skuValue = skuCell.dataset.sku;
      if (skuValue) {
        loadBomTree(skuValue);
      }
      event.stopPropagation();
      return;
    }
    const cell = event.target.closest('.editable');
    if (!cell || cell.dataset.editing === '1') return;
    const row = cell.closest('tr');
    const sku = row?.dataset.sku;
    if (!sku) return;
    startEdit(cell, sku);
  });

  function loadBomTree(sku) {
    fetch('/products/bom-tree?sku=' + encodeURIComponent(sku), {headers:{'Accept':'application/json'}})
      .then((res) => res.ok ? res.json() : Promise.reject())
      .then((data) => {
        if (!data.ok) {
          alert(data.error || 'BOM strom se nepodaøilo naèíst.');
          return;
        }
        bomPanel.hidden = false;
        bomSkuLabel.textContent = sku;
        bomContent.textContent = renderBomText(data.tree);
      })
      .catch(() => alert('Nepodaøilo se naèíst BOM strom.'));
  }

  function renderBomText(node, depth = 0) {
    if (!node) return '';
    const indent = '  '.repeat(depth);
    const edge = node.edge;
    let line = `${indent}${node.sku} – ${node.nazev}`;
    if (node.typ) {
      line += ` [${node.typ}${node.merna_jednotka ? ', MJ ' + node.merna_jednotka : ''}]`;
    }
    if (edge) {
      line += `  ‹ ${edge.koeficient} ${edge.merna_jednotka || node.merna_jednotka} (${edge.druh_vazby})`;
    }
    let text = line + '\n';
    (node.children || []).forEach((child) => {
      text += renderBomText(child, depth + 1);
      if (child.cycle) {
        text += indent + '  ? cyklická vazba' + '\n';
      }
    });
    return text;
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
          if (ok) {
            cell.dataset.value = newValue;
            cell.textContent = formatDisplay(field, newValue);
          } else {
            cell.textContent = formatDisplay(field, currentValue);
          }
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
    select.appendChild(new Option('Všechny', ''));
    options.forEach((opt) => select.appendChild(new Option(opt.label, opt.value)));
  }

  function formatDisplay(field, value) {
    if (!value) return '–';
    if (field === 'aktivni') return value === '1' ? '?' : '–';
    if (field === 'znacka_id') return lookupLabel(meta.brands, value);
    if (field === 'skupina_id') return lookupLabel(meta.groups, value);
    if (field === 'merna_jednotka') return value;
    if (field === 'typ') return value;
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
        alert(data.error || 'Uložení se nezdaøilo.');
        return false;
      }
      return true;
    } catch (err) {
      alert('Nastala chyba pøi ukládání.');
      return false;
    }
  }
})();
</script>
