<?php
  $filters = $filters ?? ['brand'=>0,'group'=>0,'type'=>'','search'=>''];
  $filterBrand = (int)($filters['brand'] ?? 0);
  $filterGroup = (int)($filters['group'] ?? 0);
  $filterType  = (string)($filters['type'] ?? '');
  $filterSearch= (string)($filters['search'] ?? '');
  $hasSearchActive = (bool)($hasSearch ?? false);
  $items = $items ?? [];
  $resultCount = (int)($resultCount ?? ($hasSearchActive ? count($items) : 0));
  $recentProductions = $recentProductions ?? [];
  $recentLimit = $recentLimit ?? 30;
  $formatQty = static function ($value, int $decimals = 3): string {
      $formatted = number_format((float)$value, $decimals, ',', ' ');
      $formatted = rtrim(rtrim($formatted, '0'), ',');
      return $formatted === '' ? '0' : $formatted;
  };
  $formatInput = static function ($value): string {
      $formatted = number_format((float)$value, 3, '.', '');
      $formatted = rtrim(rtrim($formatted, '0'), '.');
      return $formatted;
  };
?>

<style>
.page-note { margin-top:-0.2rem; color:#546e7a; }
.product-filter-form {
  border:1px solid #dfe6eb;
  border-radius:6px;
  padding:0.9rem;
  display:flex;
  flex-wrap:wrap;
  gap:1rem;
  margin-bottom:1rem;
  background:#f9fbfd;
}
.product-filter-form label {
  display:flex;
  flex-direction:column;
  gap:0.3rem;
  font-weight:600;
  min-width:200px;
}
.product-filter-form select,
.product-filter-form input[type="text"] {
  padding:0.45rem 0.55rem;
  border:1px solid #cfd8dc;
  border-radius:4px;
  font-size:0.95rem;
}
.search-actions {
  align-self:flex-end;
  display:flex;
  align-items:center;
  gap:0.5rem;
  margin-left:auto;
}
.search-actions button {
  padding:0.5rem 1rem;
}
.search-result-pill {
  font-size:0.95rem;
  color:#546e7a;
  display:flex;
  align-items:center;
  gap:0.4rem;
}
.search-reset {
  text-decoration:none;
  font-size:1.3rem;
  color:#b00020;
  line-height:1;
}
.search-reset:hover { color:#d32f2f; }
.production-table-wrapper {
  max-height:70vh;
  overflow:auto;
  margin-top:1rem;
  border:1px solid #e0e7ef;
  border-radius:6px;
}
.production-table {
  width:100%;
  border-collapse:collapse;
}
.production-table th,
.production-table td {
  border:1px solid #e0e7ef;
  padding:0.45rem 0.55rem;
  vertical-align:top;
}
.production-table th {
  background:#f3f6fa;
  text-align:left;
  position:sticky;
  top:0;
  z-index:2;
}
.production-row.needs-production { background:#fffdf7; }
.production-row.is-blocked { background:#fff3f0; }
.sku-cell {
  cursor:pointer;
  display:flex;
  align-items:center;
  gap:0.35rem;
  font-weight:600;
  white-space:nowrap;
}
.sku-toggle {
  font-size:0.9rem;
  color:#455a64;
  width:1rem;
  text-align:center;
  flex-shrink:0;
}
.qty-cell { white-space:nowrap; font-variant-numeric:tabular-nums; }
.deficit-cell { font-weight:600; }
.ratio-cell { min-width:120px; }
.ratio-value { font-weight:600; margin-bottom:0.2rem; }
.ratio-bar {
  width:100%;
  height:6px;
  border-radius:999px;
  background:#e0e7ef;
  overflow:hidden;
}
.ratio-bar span {
  display:block;
  height:100%;
  background:#ff7043;
}
.ratio-bar span[data-state="ok"] { background:#66bb6a; }
.ratio-bar span[data-state="warn"] { background:#ffa726; }
.production-form {
  display:flex;
  gap:0.4rem;
  flex-wrap:wrap;
  align-items:center;
}
.production-form input[type="number"] {
  width:140px;
}
.production-tree-row td {
  background:#fdfefe;
  padding:0.8rem 0.6rem;
  border-top:none;
}
.bom-tree-table {
  width:100%;
  border-collapse:collapse;
  font-size:0.9rem;
}
.bom-tree-table th,
.bom-tree-table td {
  border:1px solid #e0e7ef;
  padding:0.35rem 0.45rem;
  vertical-align:top;
}
.bom-tree-table th { background:#f5f8fb; }
.bom-tree-label {
  display:flex;
  align-items:center;
  gap:0.35rem;
  white-space:nowrap;
}
.bom-tree-prefix {
  font-family:"Fira Mono","Consolas",monospace;
  color:#90a4ae;
}
.bom-node-critical { color:#b00020; font-weight:600; }
.bom-node-warning { color:#ef6c00; font-weight:600; }
.notice-empty {
  border:1px dashed #cfd8dc;
  border-radius:6px;
  padding:1rem;
  background:#fbfdff;
  color:#546e7a;
  margin-top:1rem;
}
.production-modal-overlay {
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.45);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:999;
}
.production-modal {
  background:#fff;
  border-radius:6px;
  padding:1.2rem;
  width:90%;
  max-width:520px;
  box-shadow:0 14px 32px rgba(0,0,0,0.25);
}
.production-modal h3 { margin-top:0; }
.production-modal ul { margin:0.6rem 0 0 1.2rem; }
.production-modal small { color:#607d8b; display:block; margin-top:0.4rem; }
.production-modal-buttons {
  display:flex;
  gap:0.6rem;
  margin-top:1rem;
}
.production-modal-buttons button {
  flex:1 1 auto;
  padding:0.5rem 0.75rem;
}
.production-log-controls {
  margin-top:1.5rem;
  display:flex;
  align-items:center;
  gap:0.6rem;
}
.production-log-controls form {
  display:flex;
  align-items:center;
  gap:0.4rem;
}
.production-log-controls input[type="number"] {
  width:80px;
  padding:0.3rem 0.4rem;
}
.production-log-table {
  width:100%;
  border-collapse:collapse;
  margin-top:1.5rem;
}
.production-log-table th,
.production-log-table td {
  border:1px solid #dfe6eb;
  padding:0.4rem 0.5rem;
}
.production-log-table th {
  background:#f3f6fa;
  text-align:left;
}
.production-log-title {
  margin:1.8rem 0 0.6rem;
  font-size:1.05rem;
  font-weight:600;
}
</style>

<form method="get" action="/production/plans" class="product-filter-form">
  <input type="hidden" name="search" value="1" />
  <label>
    <span>ZnaĂ„Ĺ¤ka</span>
    <select name="znacka_id">
      <option value="">VÄąË‡echny</option>
      <?php foreach (($brands ?? []) as $brand): $bid = (int)$brand['id']; ?>
        <option value="<?= $bid ?>"<?= $filterBrand === $bid ? ' selected' : '' ?>><?= htmlspecialchars((string)$brand['nazev'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>
    <span>Skupina</span>
    <select name="skupina_id">
      <option value="">VÄąË‡echny</option>
      <?php foreach (($groups ?? []) as $group): $gid = (int)$group['id']; ?>
        <option value="<?= $gid ?>"<?= $filterGroup === $gid ? ' selected' : '' ?>><?= htmlspecialchars((string)$group['nazev'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>
    <span>Typ</span>
    <select name="typ">
      <option value="">VÄąË‡echny</option>
      <?php foreach (($types ?? []) as $type): ?>
        <option value="<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>"<?= $filterType === (string)$type ? ' selected' : '' ?>><?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </label>
    <label style="flex:1 1 240px;">
    <span>Vyhledat</span>
    <input type="text" name="q" value="<?= htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="SKU, nÄ‚Ë‡zev, ALT SKU, EAN" />
  </label>
  <div class="search-actions">
    <?php if ($hasSearchActive): ?>
      <div class="search-result-pill">
        Zobrazeno <?= $resultCount ?>
        <a href="/production/plans" class="search-reset" title="ZruÄąË‡it filtr">&times;</a>
      </div>
    <?php endif; ?>
    <button type="submit">Vyhledat</button>
  </div>
</form>

<?php if (!$hasSearchActive): ?>
  <div class="notice-empty">Zadejte parametry vyhledÄ‚Ë‡vÄ‚Ë‡nÄ‚Â­ a potvrĂ„Ĺąte tlaĂ„Ĺ¤Ä‚Â­tkem Ă˘â‚¬ĹľVyhledatĂ˘â‚¬Ĺ›. Seznam produktÄąĹ» a nÄ‚Ë‡vrh vÄ‚Ëťroby se zobrazÄ‚Â­ aÄąÄľ po vyhledÄ‚Ë‡nÄ‚Â­.</div>
<?php elseif (empty($items)): ?>
  <div class="notice-empty">Pro zadanÄ‚Â© podmÄ‚Â­nky nejsou dostupnÄ‚Ë‡ ÄąÄľÄ‚Ë‡dnÄ‚Ë‡ data.</div>
<?php else: ?>
  <div class="production-table-wrapper">
  <table class="production-table">
    <thead>
      <tr>
        <th>SKU</th>
        <th>Typ</th>
        <th>NĂˇzev</th>
        <th>DostupnĂ©</th>
        <th>Rezervace</th>
        <th>CĂ­lovĂ˝ stav</th>
        <th>CÄ‚Â­lovÄ‚Ëť stav</th>
        <th>Dovyrobit</th>
        <th>Priorita</th>
        <th>Min. dÄ‚Ë‡vka</th>
        <th>Krok vÄ‚Ëťroby</th>
        <th>VÄ‚ËťrobnÄ‚Â­ doba (dny)</th>
        <th>Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item):
        $sku = (string)$item['sku'];
        $deficit = (float)($item['deficit'] ?? 0.0);
        $ratio = max(0.0, min(1.0, (float)($item['ratio'] ?? 0.0)));
        $ratioPct = (int)round($ratio * 100);
        $rowClasses = ['production-row'];
        if ($deficit > 0.0) {
            $rowClasses[] = 'needs-production';
        }
        if (!empty($item['blocked'])) {
            $rowClasses[] = 'is-blocked';
        }
        $ratioState = $ratio >= 0.85 ? 'critical' : ($ratio >= 0.5 ? 'warn' : 'ok');
      ?>
      <tr class="<?= implode(' ', $rowClasses) ?>" data-sku="<?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?>">
        <td class="sku-cell" data-sku="<?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?>">
          <span class="sku-toggle">Ă˘â€“Â¸</span>
          <span class="sku-value"><?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td><?= htmlspecialchars((string)($item['typ'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$item['nazev'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="qty-cell"><?= $formatQty($item['available'] ?? 0) ?></td>
        <td class="qty-cell"><?= $formatQty($item['reservations'] ?? 0) ?></td>
        <td class="qty-cell"><?= $formatQty($item['target'] ?? 0, 0) ?></td>
        <td class="qty-cell deficit-cell">
          <?= $formatQty($deficit, 0) ?>
        </td>
        <td class="ratio-cell">
          <div class="ratio-value"><?= $ratioPct ?> %</div>
          <div class="ratio-bar"><span data-state="<?= $ratioState ?>" style="width: <?= $ratioPct ?>%"></span></div>
        </td>
        <td class="qty-cell"><?= $formatQty($item['min_davka'] ?? 0, 0) ?></td>
        <td class="qty-cell"><?= $formatQty($item['krok_vyroby'] ?? 0, 0) ?></td>
        <td class="qty-cell"><?= $formatQty($item['vyrobni_doba_dni'] ?? 0, 0) ?></td>
        <td>
          <form method="post" action="/production/produce" class="production-form" data-sku="<?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sku" value="<?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="modus" value="odecti_subpotomky" />
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/production/plans', ENT_QUOTES, 'UTF-8') ?>" />
            <input type="number" step="any" name="mnozstvi" placeholder="mnoÄąÄľstvÄ‚Â­" required />
            <button type="submit">Zapsat mnoÄąÄľstvÄ‚Â­</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<div class="production-log-controls">
  <form method="post" action="/production/recent-limit">
    <label>PoÄŤet zobrazenĂ˝ch zĂˇznamĹŻ:
      <input type="number" name="recent_limit" min="1" max="500" value="<?= (int)($recentLimit ?? 30) ?>" />
    </label>
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/production/plans', ENT_QUOTES, 'UTF-8') ?>" />
    <button type="submit">Aktualizovat</button>
  </form>
</div>

<?php if (!empty($recentProductions)): ?>
  <table class="production-log-table">
    <thead>
      <tr>
        <th>Datum</th>
        <th>SKU</th>
        <th>Nďż˝ďż˝zev</th>
        <th>Mnoďż˝ďż˝stvďż˝ďż˝</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recentProductions as $log): ?>
        <tr>
          <td><?= htmlspecialchars((string)$log['datum'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$log['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)($log['nazev'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="qty-cell"><?= htmlspecialchars((string)round(abs((float)($log['mnozstvi'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class="muted">ZatĂ­m nejsou zapsanĂ© ĹľĂˇdnĂ© vĂ˝roby.</p>
<?php endif; ?>

<div class="production-modal-overlay" id="production-modal">
  <div class="production-modal">
    <h3>Nedostatek komponent</h3>
    <p>OdeĂ„Ĺ¤et komponent by nĂ„â€şkterÄ‚Â© poloÄąÄľky poslal do zÄ‚Ë‡pornÄ‚Â©ho stavu. Vyberte, jak postupovat:</p>
    <ul id="production-deficit-list"></ul>
    <small>Volba Ă˘â‚¬ĹľOdeĂ„Ĺ¤Ä‚Â­st subpotomkyĂ˘â‚¬Ĺ› automaticky odeĂ„Ĺ¤te vÄąË‡echny komponenty (i do mÄ‚Â­nusu). Volba Ă˘â‚¬ĹľOdeĂ„Ĺ¤Ä‚Â­st do mÄ‚Â­nusuĂ˘â‚¬Ĺ› zapÄ‚Â­ÄąË‡e jen hotovÄ‚Ëť produkt a komponenty je potÄąâ„˘eba odepsat ruĂ„Ĺ¤nĂ„â€ş.</small>
    <div class="production-modal-buttons">
      <button type="button" data-action="components">OdeĂ„Ĺ¤Ä‚Â­st subpotomky (doporuĂ„Ĺ¤eno)</button>
      <button type="button" data-action="minus">OdeĂ„Ĺ¤Ä‚Â­st do mÄ‚Â­nusu</button>
      <button type="button" data-action="cancel">ZruÄąË‡it</button>
    </div>
  </div>
</div>

<script>
(function(){
  const table = document.querySelector('.production-table');
  const forms = document.querySelectorAll('.production-form');
  const overlay = document.getElementById('production-modal');
  const listEl = document.getElementById('production-deficit-list');
  const bomUrl = '/products/bom-tree';
  let pendingForm = null;
  let treeState = { row: null, detail: null };

  if (table) {
    table.addEventListener('click', (event) => {
      const cell = event.target.closest('.sku-cell');
      if (!cell || !table.contains(cell)) {
        return;
      }
      event.preventDefault();
      toggleTreeRow(cell);
    });
  }

  forms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const qtyField = form.querySelector('input[name="mnozstvi"]');
      const qty = parseFloat((qtyField.value || ').replace(',', '.'));
      if (!qty || qty <= 0) {
        alert('Zadejte mnoÄąÄľstvÄ‚Â­ vÄ‚Ëťroby.');
        return;
      }
      const sku = form.dataset.sku || form.querySelector('input[name="sku"]').value;
      checkDeficits(sku, qty)
        .then((deficits) => {
          if (!deficits.length) {
            submitProduction(form, 'odecti_subpotomky');
          } else {
            pendingForm = form;
            renderDeficits(deficits);
            overlay.style.display = 'flex';
          }
        })
        .catch((err) => alert('Nelze ovĂ„â€şÄąâ„˘it komponenty: ' + (err.message || err)));
    });
  });

  overlay.querySelectorAll('button[data-action]').forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.dataset.action;
      if (!pendingForm) {
        closeModal();
        return;
      }
      if (action === 'components') {
        submitProduction(pendingForm, 'odecti_subpotomky');
      } else if (action === 'minus') {
        submitProduction(pendingForm, 'korekce');
      }
      closeModal();
    });
  });

  function closeModal() {
    overlay.style.display = 'none';
    listEl.innerHTML = ';
    pendingForm = null;
  }

  function renderDeficits(deficits) {
    listEl.innerHTML = ';
    deficits.forEach((item) => {
      const li = document.createElement('li');
      const name = item.nazev ? `${item.sku} Ă˘â‚¬â€ś ${item.nazev}` : item.sku;
      li.textContent = `${name}: potÄąâ„˘eba ${item.required}, dostupnÄ‚Â© ${item.available}, chybÄ‚Â­ ${item.missing}`;
      listEl.appendChild(li);
    });
  }

  function submitProduction(form, mode) {
    form.querySelector('input[name="modus"]').value = mode;
    form.submit();
  }

  function checkDeficits(sku, qty) {
    return fetch('/production/check', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({sku, mnozstvi: qty})
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.ok) throw new Error(data.error || 'Chyba kontroly.');
        return data.deficits || [];
      });
  }

  function toggleTreeRow(cell) {
    const row = cell.closest('tr');
    if (!row) return;
    if (treeState.row === row) {
      closeTreeRow();
      return;
    }
    closeTreeRow();
    const toggle = row.querySelector('.sku-toggle');
    if (toggle) toggle.textContent = 'Ă˘â€“Äľ';
    row.classList.add('bom-open');
    const detailRow = document.createElement('tr');
    detailRow.className = 'production-tree-row';
    const detailCell = document.createElement('td');
    detailCell.colSpan = row.children.length;
    detailCell.textContent = 'NaĂ„Ĺ¤Ä‚Â­tÄ‚Ë‡m strom vazebĂ˘â‚¬Â¦';
    detailRow.appendChild(detailCell);
    row.parentNode.insertBefore(detailRow, row.nextSibling);
    treeState = { row, detail: detailRow };
    loadBomTree(cell.dataset.sku || row.dataset.sku, detailCell);
  }

  function closeTreeRow() {
    if (!treeState.row) return;
    const toggle = treeState.row.querySelector('.sku-toggle');
    if (toggle) toggle.textContent = 'Ă˘â€“Â¸';
    treeState.row.classList.remove('bom-open');
    if (treeState.detail) treeState.detail.remove();
    treeState = { row: null, detail: null };
  }

  async function loadBomTree(sku, container) {
    if (!sku) {
      container.textContent = 'ChybÄ‚Â­ SKU.';
      return;
    }
    try {
      const response = await fetch(`${bomUrl}?sku=${encodeURIComponent(sku)}`);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      if (!data.ok) throw new Error(data.error || 'NepodaÄąâ„˘ilo se naĂ„Ĺ¤Ä‚Â­st strom.');
      container.innerHTML = ';
      container.appendChild(buildBomTable(data.tree));
    } catch (err) {
      container.textContent = `Chyba: ${err.message || err}`;
    }
  }

  function buildBomTable(tree) {
    if (!tree || !Array.isArray(tree.children) || tree.children.length === 0) {
      const wrap = document.createElement('div');
      wrap.textContent = 'Produkt nemÄ‚Ë‡ navÄ‚Ë‡zanÄ‚Â© potomky.';
      return wrap;
    }
    const table = document.createElement('table');
    table.className = 'bom-tree-table';
    table.innerHTML = '<thead><tr><th>Strom vazeb</th><th>Koeficient</th><th>MJ</th><th>Druh vazby</th><th>Typ poloÄąÄľky</th><th>DostupnÄ‚Â©</th><th>CÄ‚Â­lovÄ‚Ëť stav</th><th>ChybÄ‚Â­</th></tr></thead>';
    const body = document.createElement('tbody');
    flattenTree(tree).forEach((row) => {
      const tr = document.createElement('tr');
      const labelCell = document.createElement('td');
      const labelWrap = document.createElement('div');
      labelWrap.className = 'bom-tree-label';
      const prefix = document.createElement('span');
      prefix.className = 'bom-tree-prefix';
      prefix.textContent = buildPrefix(row.guides);
      if (!prefix.textContent.trim()) prefix.style.visibility = 'hidden';
      labelWrap.appendChild(prefix);
      const label = document.createElement('span');
      label.textContent = `${row.node.sku} Ă˘â‚¬â€ś ${row.node.nazev || '}`.trim();
      const status = row.node.status || null;
      if (status && (status.deficit || 0) > 0.0005) {
        label.className = 'bom-node-critical';
      } else if (status && (status.ratio || 0) > 0.4) {
        label.className = 'bom-node-warning';
      }
      labelWrap.appendChild(label);
      labelCell.appendChild(labelWrap);
      tr.appendChild(labelCell);
      const edge = row.node.edge || {};
      tr.appendChild(createCell(edge.koeficient));
      tr.appendChild(createCell(edge.merna_jednotka || row.node.merna_jednotka));
      tr.appendChild(createCell(edge.druh_vazby));
      tr.appendChild(createCell(row.node.typ));
      tr.appendChild(createCell(formatInteger(status ? status.available : null)));
      tr.appendChild(createCell(formatInteger(status ? status.target : null)));
      tr.appendChild(createCell(formatInteger(status ? status.deficit : null)));
      body.appendChild(tr);
    });
    table.appendChild(body);
    return table;
  }

  function flattenTree(node, guides = []) {
    const rows = [{ node, guides }];
    if (Array.isArray(node.children)) {
      node.children.forEach((child, index) => {
        const nextGuides = guides.concat([{ last: index === node.children.length - 1 }]);
        rows.push(...flattenTree(child, nextGuides));
      });
    }
    return rows;
  }

  function buildPrefix(guides) {
    if (!guides || !guides.length) return ';
    let prefix = ';
    guides.forEach((guide, idx) => {
      const isLast = guide.last;
      if (idx === guides.length - 1) {
        prefix += isLast ? 'Ă˘â€ťâ€ťĂ˘â€ťâ‚¬Ă˘â€ťâ‚¬ ' : 'Ă˘â€ťĹ›Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ ';
      } else {
        prefix += isLast ? '    ' : 'Ă˘â€ťâ€š   ';
      }
    });
    return prefix;
  }

  function createCell(value) {
    const td = document.createElement('td');
    td.textContent = value ?? 'Ă˘â‚¬â€ś';
    return td;
  }

  function formatInteger(value) {
    if (value === null || value === undefined || isNaN(value)) {
      return 'Ă˘â‚¬â€ś';
    }
    return String(Math.round(Number(value)));
  }
})();
</script>
