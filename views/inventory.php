<?php
  $filters = $filters ?? ['brand'=>0,'group'=>0,'type'=>'','search'=>''];
  $filterBrand = (int)($filters['brand'] ?? 0);
  $filterGroup = (int)($filters['group'] ?? 0);
  $filterType  = (string)($filters['type'] ?? '');
  $filterSearch= (string)($filters['search'] ?? '');
  $hasSearchActive = (bool)($hasSearch ?? false);
  $resultCount = isset($items) ? count($items) : 0;
  $inventory = $inventory ?? null;
?>

<h1>Inventura</h1>
<style>
.inventory-meta {
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  padding: 0.85rem;
  margin-bottom: 1rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: center;
  background:#f8fbff;
}
.inventory-meta strong { font-size:1.1rem; }
.inventory-actions form { display:inline; margin-right:0.5rem; }
.inventory-actions button { padding:0.35rem 0.8rem; }
.inventory-search {
  border:1px solid #ddd;
  border-radius:4px;
  padding:0.9rem;
  display:flex;
  flex-wrap:wrap;
  gap:1rem;
  margin-bottom:1rem;
  background:#fafafa;
}
.inventory-search label {
  display:flex;
  flex-direction:column;
  gap:0.3rem;
  min-width:200px;
  font-weight:600;
}
.inventory-search select,
.inventory-search input[type="text"] {
  width:100%;
  box-sizing:border-box;
  padding:0.35rem 0.45rem;
}
.inventory-search .actions {
  align-self:flex-end;
  display:flex;
  align-items:center;
  gap:0.5rem;
}
.inventory-pill { color:#607d8b; font-size:0.9rem; }
.inventory-reset { text-decoration:none; font-size:1.3rem; color:#b00020; }
.inventory-reset:hover { color:#d32f2f; }
.inventory-table { width:100%; border-collapse:collapse; }
.inventory-table th,
.inventory-table td { border:1px solid #ddd; padding:0.45rem 0.55rem; vertical-align:top; }
.inventory-table th { background:#f3f6f9; }
.inventory-expression { font-family:"Fira Mono","Consolas",monospace; white-space:nowrap; }
.inventory-diff { font-weight:600; }
.inventory-input { display:flex; align-items:center; gap:0.35rem; }
.inventory-input input {
  width:140px;
  padding:0.3rem 0.4rem;
}
.inventory-input span { color:#607d8b; }
.inventory-empty {
  border:1px dashed #b0bec5;
  padding:1rem;
  border-radius:6px;
  background:#f9fcff;
}
.notice {
  border:1px solid #c8e6c9;
  border-radius:4px;
  padding:0.6rem 0.8rem;
  margin-bottom:1rem;
  background:#f1f8f1;
  color:#2e7d32;
}
.notice.error {
  border-color:#ffbdbd;
  background:#fff5f5;
  color:#b00020;
}
</style>

<?php if (!empty($message)): ?>
  <div class="notice"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="notice error"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div>
<?php endif; ?>

<?php if (!$inventory): ?>
  <div class="inventory-empty">
    <p><strong>Aktuálně neprobíhá žádná inventura.</strong></p>
    <form method="post" action="/inventory/start">
      <button type="submit">Zahájit inventuru</button>
    </form>
    <?php if (!empty($lastClosed['closed_at'] ?? null)): ?>
      <p class="muted">Poslední inventura byla uzavřena <?= htmlspecialchars((string)$lastClosed['closed_at'],ENT_QUOTES,'UTF-8') ?>.</p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="inventory-meta">
    <div>
      <strong>Inventura #<?= (int)$inventory['id'] ?></strong><br>
      Zahájeno: <?= htmlspecialchars((string)$inventory['opened_at'],ENT_QUOTES,'UTF-8') ?><br>
      <?php if (!empty($inventory['poznamka'])): ?>
        Poznámka: <?= htmlspecialchars((string)$inventory['poznamka'],ENT_QUOTES,'UTF-8') ?>
      <?php endif; ?>
    </div>
    <div class="inventory-actions">
      <form method="post" action="/inventory/close" onsubmit="return confirm('Uzavřít aktuální inventuru?')">
        <button type="submit">Uzavřít inventuru</button>
      </form>
    </div>
  </div>

  <form method="get" action="/inventory" class="inventory-search">
    <input type="hidden" name="search" value="1" />
    <label>
      <span>Značka</span>
      <select name="znacka_id">
        <option value="">Všechny</option>
        <?php foreach (($brands ?? []) as $b): $id=(int)$b['id']; ?>
          <option value="<?= $id ?>"<?= $filterBrand === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Skupina</span>
      <select name="skupina_id">
        <option value="">Všechny</option>
        <?php foreach (($groups ?? []) as $g): $gid=(int)$g['id']; ?>
          <option value="<?= $gid ?>"<?= $filterGroup === $gid ? ' selected' : '' ?>><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>
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
    <div class="actions">
      <button type="submit">Vyhledat</button>
      <?php if ($hasSearchActive): ?>
        <span class="inventory-pill">Zobrazeno <?= $resultCount ?></span>
        <a href="/inventory" class="inventory-reset" title="Zrušit filtr" aria-label="Zrušit filtr">×</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!$hasSearchActive): ?>
    <p class="muted">Zadejte parametry vyhledávání a potvrďte tlačítkem „Vyhledat“. Přehled produktů se zobrazí až po filtrování.</p>
  <?php elseif (empty($items)): ?>
    <p class="muted">Žádné produkty neodpovídají zadanému filtru.</p>
  <?php else: ?>
    <table class="inventory-table" id="inventory-table">
      <tr>
        <th>SKU</th>
        <th>EAN</th>
        <th>Značka</th>
        <th>Skupina</th>
        <th>Typ</th>
        <th>MJ</th>
        <th>Název</th>
        <th>Inventarizováno</th>
        <th>Rozdíl</th>
        <th>Počet</th>
      </tr>
      <?php foreach ($items as $row): ?>
        <tr data-sku="<?= htmlspecialchars((string)$row['sku'],ENT_QUOTES,'UTF-8') ?>" data-unit="<?= htmlspecialchars((string)$row['merna_jednotka'],ENT_QUOTES,'UTF-8') ?>">
          <td><?= htmlspecialchars((string)$row['sku'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$row['ean'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$row['znacka'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$row['skupina'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$row['typ'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$row['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$row['nazev'],ENT_QUOTES,'UTF-8') ?></td>
          <td class="inventory-expression"><?= htmlspecialchars((string)$row['inventarizovano'],ENT_QUOTES,'UTF-8') ?></td>
          <td class="inventory-diff"><?= htmlspecialchars((string)$row['rozdil'],ENT_QUOTES,'UTF-8') ?></td>
          <td>
            <div class="inventory-input">
              <input type="number" step="any" data-sku="<?= htmlspecialchars((string)$row['sku'],ENT_QUOTES,'UTF-8') ?>" class="inventory-qty" placeholder="+/-" />
              <span><?= htmlspecialchars((string)$row['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php endif; ?>

<?php if (!empty($lastClosed['closed_at'] ?? null)): ?>
  <p class="muted">Poslední uzavřená inventura: <?= htmlspecialchars((string)$lastClosed['closed_at'],ENT_QUOTES,'UTF-8') ?></p>
<?php endif; ?>

<?php if ($inventory): ?>
<script>
(function(){
  const table = document.getElementById('inventory-table');
  if (!table) return;
  const inputs = table.querySelectorAll('.inventory-qty');
  inputs.forEach((input) => {
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        submitValue(input);
      }
    });
    input.addEventListener('blur', () => submitValue(input));
  });

  function submitValue(input) {
    const value = input.value.trim();
    if (value === '') return;
    const sku = input.dataset.sku;
    input.disabled = true;
    fetch('/inventory/entry', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({sku, quantity: value})
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.ok) {
          alert(data.error || 'Uložení se nezdařilo.');
          return;
        }
        updateRow(data.row);
        input.value = '';
      })
      .catch(() => alert('Nelze uložit inventuru.'))
      .finally(() => { input.disabled = false; });
  }

  function updateRow(row) {
    if (!row || !row.sku) return;
    const tr = table.querySelector(`tr[data-sku="${row.sku}"]`);
    if (!tr) return;
    const exprCell = tr.querySelector('.inventory-expression');
    const diffCell = tr.querySelector('.inventory-diff');
    if (exprCell) exprCell.textContent = row.inventarizovano;
    if (diffCell) diffCell.textContent = row.rozdil;
  }
})();
</script>
<?php endif; ?>
