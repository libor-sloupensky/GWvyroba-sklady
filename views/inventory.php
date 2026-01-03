<?php
  $filters = $filters ?? ['brand'=>0,'group'=>0,'type'=>'','search'=>''];
  $filterBrand = (int)($filters['brand'] ?? 0);
  $filterGroup = (int)($filters['group'] ?? 0);
  $filterType  = (string)($filters['type'] ?? '');
  $filterSearch= (string)($filters['search'] ?? '');
  $hasSearchActive = (bool)($hasSearch ?? false);
  $resultCount = isset($items) ? count($items) : 0;
  $inventory = $inventory ?? null;
  $allowEntries = !empty($allowEntries);
  $allowEntries = !empty($allowEntries);
  $inventories = $inventories ?? [];
  $selectedInventoryId = (int)($selectedInventoryId ?? 0);
  $latestInventoryId = (int)($latestInventoryId ?? 0);
  $activeInventoryId = (int)($activeInventoryId ?? 0);
  $isAdmin = !empty($isAdmin);
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
.inventory-actions form { display:inline-flex; flex-direction:column; gap:0.4rem; margin-right:0.5rem; }
.inventory-actions button { padding:0.35rem 0.8rem; }
.inventory-date-label {
  font-weight:600;
  display:flex;
  flex-direction:column;
  gap:0.3rem;
}
.inventory-date-label input[type="datetime-local"] {
  padding:0.35rem 0.45rem;
  width:220px;
}
.help-badge {
  display:inline-block;
  margin-left:0.3rem;
  font-size:0.85rem;
  background:#eceff1;
  border-radius:50%;
  width:1.2rem;
  height:1.2rem;
  line-height:1.2rem;
  text-align:center;
  cursor:default;
}
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
.inventory-row--active td { background:#f5f5f5; }
.inventory-expression { font-family:"Fira Mono","Consolas",monospace; white-space:nowrap; }
.inventory-expected { font-family:"Fira Mono","Consolas",monospace; white-space:nowrap; color:#37474f; }
.inventory-diff { font-weight:600; }
.inventory-input { display:flex; align-items:center; gap:0.35rem; }
.inventory-input input { width:140px; padding:0.3rem 0.4rem; }
.inventory-input span { color:#607d8b; }
.inventory-print-blank {
  display:none;
  border:1px dashed #cfd8dc;
  height:2.2rem;
  margin-top:0.35rem;
}
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
.inventory-history {
  margin-top:2rem;
  border:1px solid #e0e0e0;
  border-radius:6px;
  padding:0.8rem;
}
.inventory-history table { width:100%; border-collapse:collapse; margin-top:0.6rem; }
.inventory-history th,
.inventory-history td { border:1px solid #e0e0e0; padding:0.4rem 0.5rem; }
.inventory-history th { background:#f7f9fb; }
.inventory-history-row--selected { background:#fff8e1; }
.inventory-history-row--active { font-weight:600; }
.inventory-history-actions { text-align:center; }
.inventory-history-actions button {
  border:0;
  background:none;
  color:#b00020;
  font-size:1.2rem;
  cursor:pointer;
}
.inventory-history-modal-overlay {
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.5);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:1000;
}
.inventory-history-modal {
  background:#fff;
  border-radius:6px;
  padding:1.1rem;
  width:90%;
  max-width:420px;
  box-shadow:0 12px 24px rgba(0,0,0,0.25);
}
.inventory-history-modal-buttons { display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:1rem; }
.inventory-history-modal-buttons button { flex:1 1 auto; padding:0.45rem 0.7rem; }
button.disabled { opacity:0.5; cursor:not-allowed; }
.inventory-print-btn {
  border:1px solid #b0bec5;
  background:#fff;
  border-radius:4px;
  padding:0.25rem 0.5rem;
  cursor:pointer;
  font-size:1rem;
  display:inline-flex;
  align-items:center;
  gap:0.3rem;
}
.inventory-print-btn__icon { display:inline-flex; }
.no-print {}
.print-only { display:none; }
@media print {
  body { background:#fff; color:#000; }
  .no-print { display:none !important; }
  .inventory-input { display:none !important; }
  .inventory-print-blank { display:block !important; border:1px solid #90a4ae; height:2.6rem; }
  .inventory-table { font-size:12px; }
  .notice { display:none !important; }
  .print-only { display:block !important; }
  .inventory-table .col-ean,
  .inventory-table .col-group,
  .inventory-table .col-type,
  .inventory-table .col-inventarizovano,
  .inventory-table .col-rozdil { display:none !important; }
}
</style>

<?php if (!empty($message)): ?>
  <div class="notice no-print"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="notice error no-print"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div>
<?php endif; ?>

<?php if (!$inventory): ?>
  <div class="inventory-empty no-print">
    <p><strong>Aktuálně neprobíhá žádná inventura.</strong></p>
    <?php if ($isAdmin): ?>
      <form method="post" action="/inventory/start">
        <button type="submit">Zahájit inventuru</button>
      </form>
    <?php else: ?>
      <p class="muted">Kontaktujte administrátora pro zahájení inventury.</p>
    <?php endif; ?>
    <?php if (!empty($lastClosed['closed_at'] ?? null)): ?>
      <p class="muted">Poslední inventura byla uzavřena <?= htmlspecialchars((string)$lastClosed['closed_at'],ENT_QUOTES,'UTF-8') ?>.</p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="inventory-meta no-print">
    <div>
      <strong>Inventura #<?= (int)$inventory['id'] ?></strong><br>
      Zahájeno: <?= htmlspecialchars((string)$inventory['opened_at'],ENT_QUOTES,'UTF-8') ?><br>
      <?= empty($inventory['closed_at']) ? '<span class="muted">Neuzavřená inventura</span>' : 'Uzavřeno: ' . htmlspecialchars((string)$inventory['closed_at'],ENT_QUOTES,'UTF-8') ?><br>
      <?php if (!empty($inventory['poznamka'])): ?>
        Poznámka: <?= htmlspecialchars((string)$inventory['poznamka'],ENT_QUOTES,'UTF-8') ?><br>
      <?php endif; ?>
      <?php if (!$allowEntries && $inventory['id'] !== $activeInventoryId && empty($inventory['closed_at'])): ?>
        <span class="muted">Zobrazuje se starší inventura.</span>
      <?php endif; ?>
    </div>
    <?php if ($allowEntries && $isAdmin): ?>
      <div class="inventory-actions">
        <form method="post" action="/inventory/close" onsubmit="return confirm('Uzavřít aktuální inventuru?');">
          <label class="inventory-date-label">
            Datum provedení inventury
            <span class="help-badge" title="Datum provedení inventury určuje, ke kterému okamžiku se inventura vztahuje. Pohyby s pozdějším datem budou zahrnuty až do další inventury.">i</span>
            <?php
              $defaultPerformed = $inventory['opened_at'] ?? date('Y-m-d H:i:s');
              $defaultPerformed = date('Y-m-d\TH:i', strtotime($defaultPerformed));
            ?>
            <input type="datetime-local" name="performed_at" value="<?= htmlspecialchars($defaultPerformed,ENT_QUOTES,'UTF-8') ?>" required />
          </label>
          <button type="submit">Uzavřít inventuru</button>
        </form>
      </div>
    <?php elseif (!$activeInventoryId && $isAdmin): ?>
      <div>
        <form method="post" action="/inventory/start">
          <button type="submit">Zahájit novou inventuru</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="get" action="/inventory" class="inventory-search no-print">
  <input type="hidden" name="search" value="1" />
  <?php if ($inventory): ?>
    <input type="hidden" name="inventory_id" value="<?= (int)$inventory['id'] ?>" />
  <?php endif; ?>
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
  <div class="actions">
    <button type="submit">Vyhledat</button>
    <?php if ($hasSearchActive): ?>
      <span class="inventory-pill">Zobrazeno <?= $resultCount ?></span>
      <button type="button" class="inventory-print-btn" title="Tisk inventury" onclick="window.print()">
        <span class="inventory-print-btn__icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 3 18 3 18 9"></polyline>
            <path d="M6 14h12v7H6z"></path>
            <path d="M6 18h12"></path>
            <path d="M6 14H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2"></path>
            <circle cx="18" cy="10" r="1"></circle>
          </svg>
        </span>
        <span>Tisk</span>
      </button>
      <a href="/inventory<?= $inventory ? '?inventory_id='.(int)$inventory['id'] : '' ?>" class="inventory-reset" title="Zrušit filtr" aria-label="Zrušit filtr">&times;</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($inventory && !$allowEntries): ?>
  <p class="muted no-print">Inventura je pouze pro čtení. Změny lze provádět pouze u právě otevřené inventury.</p>
<?php endif; ?>

<?php if (!$hasSearchActive): ?>
  <p class="muted">Zadejte parametry vyhledávání a potvrďte tlačítkem „Vyhledat“. Přehled produktů se zobrazí až po filtrování.</p>
<?php elseif (empty($items)): ?>
  <p class="muted">Žádné produkty neodpovídají zadanému filtru.</p>
<?php else: ?>
  <table class="inventory-table" id="inventory-table">
    <tr>
      <th class="col-sku">SKU</th>
      <th class="col-ean">EAN</th>
      <th class="col-brand">Značka</th>
      <th class="col-group">Skupina</th>
      <th class="col-type">Typ</th>
      <th class="col-unit">MJ</th>
      <th class="col-name">Název</th>
      <th class="col-expected">Očekávaný stav</th>
      <th class="col-inventarizovano">Inventarizováno</th>
      <th class="col-rozdil">Rozdíl</th>
      <th class="col-input">Počet</th>
    </tr>
    <?php foreach ($items as $row): ?>
      <tr data-sku="<?= htmlspecialchars((string)$row['sku'],ENT_QUOTES,'UTF-8') ?>" data-unit="<?= htmlspecialchars((string)$row['merna_jednotka'],ENT_QUOTES,'UTF-8') ?>">
        <td class="col-sku"><?= htmlspecialchars((string)$row['sku'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="col-ean"><?= htmlspecialchars((string)$row['ean'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="col-brand"><?= htmlspecialchars((string)$row['znacka'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="col-group"><?= htmlspecialchars((string)$row['skupina'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="col-type"><?= htmlspecialchars((string)$row['typ'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="col-unit"><?= htmlspecialchars((string)$row['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="col-name"><?= htmlspecialchars((string)$row['nazev'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="inventory-expected col-expected"><?= htmlspecialchars((string)$row['expected'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="inventory-expression col-inventarizovano"><?= $row['inventarizovano_html'] ?? htmlspecialchars((string)$row['inventarizovano'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="inventory-diff col-rozdil"><?= htmlspecialchars((string)$row['rozdil'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="col-input">
          <?php if ($allowEntries): ?>
          <div class="inventory-input">
            <input type="number" step="any" data-sku="<?= htmlspecialchars((string)$row['sku'],ENT_QUOTES,'UTF-8') ?>" class="inventory-qty" placeholder="+/-" />
            <span><?= htmlspecialchars((string)$row['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></span>
          </div>
          <?php endif; ?>
          <div class="inventory-print-blank"></div>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php if (!empty($inventories)): ?>
  <div class="inventory-history no-print">
    <h2>Historie inventur</h2>
    <table>
      <tr>
        <th>ID</th>
        <th>Začátek</th>
        <th>Uzavření</th>
        <th>Poznámka</th>
        <th>Akce</th>
      </tr>
      <?php foreach ($inventories as $row):
        $rowId = (int)$row['id'];
        $isSelected = $rowId === $selectedInventoryId;
        $isActiveRow = empty($row['closed_at']);
        $isLatest = $rowId === $latestInventoryId;
      ?>
        <tr class="<?= $isSelected ? 'inventory-history-row--selected' : '' ?> <?= $isActiveRow ? 'inventory-history-row--active' : '' ?>">
          <td><a href="/inventory?inventory_id=<?= $rowId ?>">#<?= $rowId ?></a></td>
          <td><?= htmlspecialchars((string)$row['opened_at'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= $row['closed_at'] ? htmlspecialchars((string)$row['closed_at'],ENT_QUOTES,'UTF-8') : '<em>neuzavřena</em>' ?></td>
          <td><?= $row['poznamka'] ? htmlspecialchars((string)$row['poznamka'],ENT_QUOTES,'UTF-8') : '–' ?></td>
          <td class="inventory-history-actions">
            <?php if ($isAdmin && $isLatest): ?>
              <button type="button" class="inventory-manage-trigger" data-id="<?= $rowId ?>" data-closed="<?= $row['closed_at'] ? '1' : '0' ?>" title="Spravovat inventuru">×</button>
            <?php else: ?>
              <span class="muted">–</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>

<?php if ($allowEntries): ?>
<script>
(function(){
  const table = document.getElementById('inventory-table');
  if (!table) return;
  const inputs = Array.from(table.querySelectorAll('.inventory-qty'));
  inputs.forEach((input, index) => {
    const row = input.closest('tr');
    input.addEventListener('focus', () => {
      if (row) row.classList.add('inventory-row--active');
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const nextInput = inputs[index + 1];
        if (nextInput) {
          nextInput.focus();
        } else {
          input.blur();
        }
      }
    });
    input.addEventListener('blur', () => {
      if (row) row.classList.remove('inventory-row--active');
      submitValue(input);
    });
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
      .then((response) => response.text().then((text) => {
        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch (_) {}
        if (!response.ok) {
          const message = data && data.error ? data.error : (text || `HTTP ${response.status}`);
          throw new Error(message);
        }
        if (!data) {
          const snippet = text ? text.slice(0, 200) : '(žádná data)';
          throw new Error('Neplatná odpověď: ' + snippet);
        }
        return data;
      }))
      .then((data) => {
        if (!data.ok) {
          alert(data.error || 'Uložení se nezdařilo.');
          return;
        }
        updateRow(data.row);
        input.value = '';
      })
      .catch((err) => alert('Nelze uložit inventuru: ' + (err.message || err)))
      .finally(() => { input.disabled = false; });
  }

  function updateRow(row) {
    if (!row || !row.sku) return;
    const tr = table.querySelector(`tr[data-sku="${row.sku}"]`);
    if (!tr) return;
    const exprCell = tr.querySelector('.inventory-expression');
    const diffCell = tr.querySelector('.inventory-diff');
    if (exprCell) {
      if (row.inventarizovano_html) {
        exprCell.innerHTML = row.inventarizovano_html;
      } else {
        exprCell.textContent = row.inventarizovano;
      }
    }
    if (diffCell) diffCell.textContent = row.rozdil;
  }
})();
</script>
<?php endif; ?>

<?php if ($isAdmin): ?>
<form id="inventory-delete-form" method="post" action="/inventory/delete" style="display:none;" class="no-print">
  <input type="hidden" name="inventory_id" value="">
</form>
<form id="inventory-reopen-form" method="post" action="/inventory/reopen" style="display:none;" class="no-print">
  <input type="hidden" name="inventory_id" value="">
</form>
<div class="inventory-history-modal-overlay no-print" id="inventory-history-modal">
  <div class="inventory-history-modal">
    <h3>Správa inventury #<span id="inventory-modal-id"></span></h3>
    <p>Opravdu chcete pokračovat? Tato akce může ovlivnit skladové pohyby.</p>
    <div class="inventory-history-modal-buttons">
      <button type="button" data-action="delete">Smazat inventuru</button>
      <button type="button" data-action="reopen">Znovu otevřít inventuru</button>
      <button type="button" data-action="cancel">Neprovést změnu</button>
    </div>
  </div>
</div>
<script>
(function(){
  const triggers = document.querySelectorAll('.inventory-manage-trigger');
  if (!triggers.length) return;
  const overlay = document.getElementById('inventory-history-modal');
  const deleteForm = document.getElementById('inventory-delete-form');
  const reopenForm = document.getElementById('inventory-reopen-form');
  const deleteBtn = overlay.querySelector('button[data-action="delete"]');
  const reopenBtn = overlay.querySelector('button[data-action="reopen"]');
  const cancelBtn = overlay.querySelector('button[data-action="cancel"]');
  const label = document.getElementById('inventory-modal-id');
  let currentId = null;

  function openModal(id, closed) {
    currentId = id;
    label.textContent = id;
    if (closed === '1') {
      reopenBtn.disabled = false;
      reopenBtn.classList.remove('disabled');
    } else {
      reopenBtn.disabled = true;
      reopenBtn.classList.add('disabled');
    }
    overlay.style.display = 'flex';
  }

  function closeModal() {
    overlay.style.display = 'none';
    currentId = null;
  }

  triggers.forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn.dataset.id, btn.dataset.closed));
  });

  deleteBtn.addEventListener('click', () => {
    if (!currentId) return;
    deleteForm.querySelector('input[name="inventory_id"]').value = currentId;
    deleteForm.submit();
  });

  reopenBtn.addEventListener('click', () => {
    if (reopenBtn.disabled || !currentId) return;
    reopenForm.querySelector('input[name="inventory_id"]').value = currentId;
    reopenForm.submit();
  });

  cancelBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) closeModal();
  });
})();
</script>
<?php endif; ?>
