<h1>Produkty</h1>
<div class="csv-help">
  <strong>Popis sloupců CSV (oddělovač ;):</strong>
  <ul>
    <li><code>sku</code> – povinný interní kód produktu.</li>
    <li><code>ean</code> – volitelný EAN / čárový kód.</li>
    <li><code>znacka</code> – název značky definovaný v Nastavení (povolené hodnoty).</li>
    <li><code>skupina</code> – název produktové skupiny z Nastavení.</li>
    <li><code>typ</code> – jedna z hodnot <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>baleni</code>, <code>karton</code>.</li>
    <li><code>merna_jednotka</code> – kód jednotky nadefinovaný v Nastavení (např. <code>ks</code>, <code>kg</code>).</li>
    <li><code>nazev</code> – povinný název položky.</li>
    <li><code>min_zasoba</code>, <code>min_davka</code>, <code>krok_vyroby</code>, <code>vyrobni_doba_dni</code> – číselné hodnoty (volitelné).</li>
    <li><code>aktivni</code> – 1 = aktivní, 0 = skrytý produkt.</li>
    <li><code>poznamka</code> – libovolný text.</li>
  </ul>
</div>

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
<?php if (!empty($errors)): ?>
  <div class="notice">
    <strong>Chyby importu:</strong>
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<p><a href="/products/export">Stáhnout CSV (aktuální)</a></p>
<form method="post" action="/products/import" enctype="multipart/form-data">
  <label>Nahrát CSV</label><br>
  <input type="file" name="csv" accept=".csv" required />
  <br>
  <button type="submit">Importovat</button>
  <span class="muted">Tip: používejte UTF‑8 (středník jako oddělovač).</span>
</form>

<hr>
<h2>Přidat nový produkt</h2>
<form method="post" action="/products/create" class="product-create-form">
  <label>SKU*</label><input type="text" name="sku" required />
  <label>EAN</label><input type="text" name="ean" />
  <label>Značka</label>
  <select name="znacka_id">
    <option value="">—</option>
    <?php foreach (($brands ?? []) as $b): ?>
      <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>
    <?php endforeach; ?>
  </select>
  <label>Skupina</label>
  <select name="skupina_id">
    <option value="">—</option>
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

<hr>
<table class="products-table">
  <tr>
    <th>SKU</th>
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
    <td><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="ean" data-type="text" data-value="<?= htmlspecialchars((string)($it['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)($it['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="znacka_id" data-type="select" data-options="brands" data-value="<?= (int)($it['znacka_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['znacka'] ?? '—'),ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="skupina_id" data-type="select" data-options="groups" data-value="<?= (int)($it['skupina_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['skupina'] ?? '—'),ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="typ" data-type="select" data-options="types" data-value="<?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="merna_jednotka" data-type="select" data-options="units" data-value="<?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="nazev" data-type="text" data-value="<?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="min_zasoba" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="min_davka" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="krok_vyroby" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="vyrobni_doba_dni" data-type="number" data-step="1" data-value="<?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?></td>
    <td class="editable" data-field="aktivni" data-type="select" data-options="active" data-value="<?= (int)$it['aktivni'] ?>"><?= (int)$it['aktivni'] ? '✔' : '✖' ?></td>
    <td class="editable" data-field="poznamka" data-type="textarea" data-value="<?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<script>
(function () {
  const meta = {
    brands: <?= json_encode(array_map(fn($b) => ['value'=>(string)$b['id'],'label'=>$b['nazev']], $brands ?? []), JSON_UNESCAPED_UNICODE) ?>,
    groups: <?= json_encode(array_map(fn($g) => ['value'=>(string)$g['id'],'label'=>$g['nazev']], $groups ?? []), JSON_UNESCAPED_UNICODE) ?>,
    units:  <?= json_encode(array_map(fn($u) => ['value'=>$u['kod'],'label'=>$u['kod']], $units ?? []), JSON_UNESCAPED_UNICODE) ?>,
    types:  <?= json_encode(array_map(fn($t) => ['value'=>$t,'label'=>$t], $types ?? []), JSON_UNESCAPED_UNICODE) ?>,
    active: [{value:'1',label:'✔'},{value:'0',label:'✖'}]
  };

  const table = document.querySelector('.products-table');
  if (!table) return;

  const updateUrl = '/products/update';

  table.addEventListener('dblclick', (event) => {
    const cell = event.target.closest('.editable');
    if (!cell || cell.dataset.editing === '1') return;
    const row = cell.closest('tr');
    const sku = row?.dataset.sku;
    if (!sku) return;
    startEdit(cell, sku);
  });

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
    select.appendChild(new Option('—', ''));
    options.forEach((opt) => select.appendChild(new Option(opt.label, opt.value)));
  }

  function formatDisplay(field, value) {
    if (!value) return '—';
    if (field === 'aktivni') return value === '1' ? '✔' : '✖';
    if (field === 'znacka_id') return lookupLabel(meta.brands, value);
    if (field === 'skupina_id') return lookupLabel(meta.groups, value);
    if (field === 'merna_jednotka') return value;
    if (field === 'typ') return value;
    return value;
  }

  function lookupLabel(list, value) {
    const found = list.find((item) => item.value === String(value));
    return found ? found.label : '—';
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
</script>
