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

.production-actions {


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


  font-weight:inherit;


}
.bom-root-label { font-weight:700; }


.bom-tree-prefix {


  font-family:"Fira Mono","Consolas",monospace;


  color:#90a4ae;


  display:inline-block;


  white-space:pre;


}


.demand-cell {


  display:inline-flex;


  align-items:center;


  gap:0.35rem;


  cursor:pointer;


}


.demand-cell .demand-toggle {


  font-size:0.9rem;


  color:#455a64;


  width:1rem;


  text-align:center;


}


.demand-cell .demand-value {


  font-variant-numeric:tabular-nums;


}


.demand-root-label {


  font-weight:700;


}

.demand-root-row td,
.bom-root-row td {


  font-weight:700;


}

.demand-root-row td,
.bom-root-row td {

  font-weight:700;

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


    <span>Značka</span>


    <select name="znacka_id">


      <option value="">Všechny</option>


      <?php foreach (($brands ?? []) as $brand): $bid = (int)$brand['id']; ?>


        <option value="<?= $bid ?>"<?= $filterBrand === $bid ? ' selected' : '' ?>><?= htmlspecialchars((string)$brand['nazev'], ENT_QUOTES, 'UTF-8') ?></option>


      <?php endforeach; ?>


    </select>


  </label>


  <label>


    <span>Skupina</span>


    <select name="skupina_id">


      <option value="">Všechny</option>


      <?php foreach (($groups ?? []) as $group): $gid = (int)$group['id']; ?>


        <option value="<?= $gid ?>"<?= $filterGroup === $gid ? ' selected' : '' ?>><?= htmlspecialchars((string)$group['nazev'], ENT_QUOTES, 'UTF-8') ?></option>


      <?php endforeach; ?>


    </select>


  </label>


  <label>


    <span>Typ</span>


    <select name="typ">


      <option value="">Všechny</option>


      <?php foreach (($types ?? []) as $type): ?>


        <option value="<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>"<?= $filterType === (string)$type ? ' selected' : '' ?>><?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?></option>


      <?php endforeach; ?>


    </select>


  </label>


    <label style="flex:1 1 240px;">


    <span>Vyhledat</span>


    <input type="text" name="q" value="<?= htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="SKU, název, ALT SKU, EAN" />


  </label>


  <div class="search-actions">


    <?php if ($hasSearchActive): ?>


      <div class="search-result-pill">


        Zobrazeno <?= $resultCount ?>


        <a href="/production/plans" class="search-reset" title="Zrušit filtr">&times;</a>


      </div>


    <?php endif; ?>


    <button type="submit">Vyhledat</button>


  </div>


</form>





<?php if (!$hasSearchActive): ?>


  <div class="notice-empty">Zadejte parametry vyhledávání a potvrďte tlačítkem Vyhledat. Seznam produktů a návrh výroby se zobrazí až po vyhledání.</div>


<?php elseif (empty($items)): ?>


  <div class="notice-empty">Pro zadané podmínky nejsou dostupná žádná data.</div>


<?php else: ?>


  <div class="production-table-wrapper">


  <table class="production-table">


    <thead>


      <tr>


        <th>SKU</th>


        <th>Typ</th>


        <th>Název</th>


        <th>Dostupné</th>


        <th>Rezervace</th>


        <th>Cílový stav</th>


        <th>Dovyrobit</th>


        <th>Priorita</th>


        <th>Min. dávka</th>


        <th>Krok výroby</th>


        <th>Výrobní doba (dny)</th>


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


          <span class="sku-toggle">▸</span>


          <span class="sku-value"><?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?></span>


        </td>


        <td><?= htmlspecialchars((string)($item['typ'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>


        <td><?= htmlspecialchars((string)$item['nazev'], ENT_QUOTES, 'UTF-8') ?></td>


        <td class="qty-cell"><?= $formatQty($item['available'] ?? 0) ?></td>


        <td class="qty-cell"><?= $formatQty($item['reservations'] ?? 0) ?></td>


        <td class="qty-cell"><?= $formatQty($item['target'] ?? 0, 0) ?></td>


        <td class="qty-cell deficit-cell">


          <?php if ($deficit > 0.0005): ?>


            <span class="demand-cell" data-sku="<?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?>">


              <span class="demand-toggle">▸</span>


              <span class="demand-value"><?= $formatQty($deficit, 0) ?></span>


            </span>


          <?php else: ?>


            <?= $formatQty($deficit, 0) ?>


          <?php endif; ?>


        </td>


        <td class="ratio-cell">


          <div class="ratio-value"><?= $ratioPct ?> %</div>


          <div class="ratio-bar"><span data-state="<?= $ratioState ?>" style="width: <?= $ratioPct ?>%"></span></div>


        </td>


        <td class="qty-cell"><?= $formatQty($item['min_davka'] ?? 0, 0) ?></td>


        <td class="qty-cell"><?= $formatQty($item['krok_vyroby'] ?? 0, 0) ?></td>


        <td class="qty-cell"><?= $formatQty($item['vyrobni_doba_dni'] ?? 0, 0) ?></td>


        <td>

        <td>

          <?php if (!empty($item['is_nonstock'])): ?>
            <div class="muted">Neskladov? sada ? v?roba/korekce vypnuta</div>
          <?php else: ?>

          <form method="post" action="/production/produce" class="production-form" data-sku="<?= htmlspecialchars($sku, ENT_QUOTES, "UTF-8") ?>">

            <input type="hidden" name="sku" value="<?= htmlspecialchars($sku, ENT_QUOTES, "UTF-8") ?>" />

            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER["REQUEST_URI"] ?? "/production/plans", ENT_QUOTES, "UTF-8") ?>" />

            <input type="number" step="any" name="mnozstvi" placeholder="Mno?stv?" required />

            <div class="production-actions">

              <button type="submit" name="modus" value="odecti_subpotomky" data-mode="odecti_subpotomky" title="Vyrobit: zap??e hotov? produkt a ode?te nav?zan? komponenty podle BOM.">Vyrobit</button>

              <?php if (!empty($isAdmin)): ?>

                <button type="submit" name="modus" value="korekce_skladu" data-mode="korekce_skladu" title="Korekce: ru?n? p?i?te nebo ode?te z?sobu pouze tohoto produktu, bez dopadu na komponenty.">Korekce</button>

              <?php endif; ?>

            </div>

          </form>
          <?php endif; ?>

        </td>


        </td>


      </tr>


      <?php endforeach; ?>


    </tbody>


  </table>


  </div>


<?php endif; ?>





<div class="production-log-controls">


  <form method="post" action="/production/recent-limit">


    <label>Počet zobrazených záznamů:


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


        <th>Název</th>


        <th>Množství</th>


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


  <p class="muted">Zatím nejsou zapsané žádné výroby.</p>


<?php endif; ?>





<div class="production-modal-overlay" id="production-modal">


  <div class="production-modal">


    <h3>Nedostatek komponent</h3>


    <p>Odečet komponent by některé položky poslal do záporného stavu. Vyberte, jak postupovat:</p>


    <ul id="production-deficit-list"></ul>


    <small>Volba "Odečíst subpotomky" automaticky odečte všechny komponenty (i do mínusu). Volba "Odečíst do mínusu" zapíše jen hotový produkt a komponenty je potřeba odepsat ručně.</small>


    <div class="production-modal-buttons">


      <button type="button" data-action="components">Odečíst subpotomky (doporučeno)</button>


      <button type="button" data-action="minus">Odečíst do mínusu</button>


      <button type="button" data-action="cancel">Zrušit</button>


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
  const demandUrl = '/production/demand-tree';


  let pendingForm = null;


  let treeState = { row: null, detail: null };


  let demandState = { row: null, detail: null, toggle: null };





  if (table) {


    table.addEventListener('click', (event) => {


      const demandCell = event.target.closest('.demand-cell');


      if (demandCell && table.contains(demandCell)) {


        event.preventDefault();


        toggleDemandRow(demandCell);


        return;


      }


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


      const submitter = event.submitter || null;


      const mode = submitter && submitter.dataset ? (submitter.dataset.mode || submitter.value) : 'odecti_subpotomky';


      const qtyField = form.querySelector('input[name="mnozstvi"]');


      const qty = parseFloat((qtyField.value || '').replace(',', '.'));


      if (!Number.isFinite(qty)) {


        event.preventDefault();


        alert('Zadejte mno?stv?.');


        return;


      }


      if (mode === 'korekce_skladu') {


        event.preventDefault();


        if (qty === 0) {


          alert('Zadejte nenulovou hodnotu korekce.');


          return;


        }


        submitProduction(form, 'korekce_skladu');


        return;


      }


      event.preventDefault();


      if (qty <= 0) {


        alert('Zadejte mno?stv? v?roby.');


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


        .catch((err) => alert('Nelze ov??it komponenty: ' + (err.message || err)));


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


    listEl.innerHTML = '';


    pendingForm = null;


  }





  function renderDeficits(deficits) {


    listEl.innerHTML = '';


    deficits.forEach((item) => {


      const li = document.createElement('li');


      const name = item.nazev ? `${item.sku} – ${item.nazev}` : item.sku;


      li.textContent = `${name}: potřeba ${item.required}, dostupné ${item.available}, chybí ${item.missing}`;


      listEl.appendChild(li);


    });


  }





  function submitProduction(form, mode) {


    let modeField = form.querySelector('input[name="modus"][type="hidden"]');


    if (!modeField) {


      modeField = document.createElement('input');


      modeField.type = 'hidden';


      modeField.name = 'modus';


      form.appendChild(modeField);


    }


    modeField.value = mode;


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


    if (toggle) toggle.textContent = '▾';


    row.classList.add('bom-open');


    const detailRow = document.createElement('tr');


    detailRow.className = 'production-tree-row';


    const detailCell = document.createElement('td');


    detailCell.colSpan = row.children.length;


    detailCell.textContent = 'Načítám strom vazeb…';


    detailRow.appendChild(detailCell);


    row.parentNode.insertBefore(detailRow, row.nextSibling);


    treeState = { row, detail: detailRow };


    loadBomTree(cell.dataset.sku || row.dataset.sku, detailCell);


  }





  function closeTreeRow() {


    if (!treeState.row) return;


    const toggle = treeState.row.querySelector('.sku-toggle');


    if (toggle) toggle.textContent = '▸';


    treeState.row.classList.remove('bom-open');


    if (treeState.detail) treeState.detail.remove();


    treeState = { row: null, detail: null };


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


      if (!data.ok) throw new Error(data.error || 'Nepodařilo se načíst strom.');


      container.innerHTML = '';


      container.appendChild(buildBomTable(data.tree));


    } catch (err) {


      container.textContent = `Chyba: ${err.message || err}`;


    }


  }





  function buildBomTable(tree) {


    if (!tree || !Array.isArray(tree.children) || tree.children.length === 0) {


      const wrap = document.createElement('div');


      wrap.textContent = 'Produkt nemá navázané potomky.';


      return wrap;


    }


    const table = document.createElement('table');


    table.className = 'bom-tree-table';


    table.innerHTML = '<thead><tr><th>Strom vazeb</th><th>Koeficient</th><th>MJ</th><th>Typ položky</th><th>Dostupné</th><th>Cílový stav</th><th>Chybí</th></tr></thead>';


    const body = document.createElement('tbody');


    flattenBomTreePlan(tree).forEach((row) => {


      const tr = document.createElement('tr');


      if (row.node.is_root) tr.classList.add('bom-root-row');


      const labelCell = document.createElement('td');


      const labelWrap = document.createElement('div');


      labelWrap.className = 'bom-tree-label';


      const prefix = document.createElement('span');


      prefix.className = 'bom-tree-prefix';


      prefix.textContent = buildPrefix(row.guides);


      if (!prefix.textContent.trim()) prefix.style.visibility = 'hidden';


      labelWrap.appendChild(prefix);


      const label = document.createElement('span');


      label.textContent = `${row.node.sku}${row.node.nazev ? ` – ${row.node.nazev}` : ''}`.trim();


      if (row.node.is_root) {
        label.classList.add('bom-root-label');
      }


      const status = row.node.status || null;


      if (!row.node.is_root && status && (status.deficit || 0) > 0.0005) {


        label.classList.add('bom-node-critical');


      } else if (!row.node.is_root && status && (status.ratio || 0) > 0.4) {


        label.classList.add('bom-node-warning');


      }


      labelWrap.appendChild(label);


      labelCell.appendChild(labelWrap);


      tr.appendChild(labelCell);


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





  function toggleDemandRow(cell) {


    const row = cell.closest('tr');


    if (demandState.row === row) {


      closeDemandRow();


      return;


    }


    openDemandRow(cell);


  }





  function openDemandRow(cell) {


    const row = cell.closest('tr');


    closeDemandRow();


    const toggle = cell.querySelector('.demand-toggle');


    if (toggle) toggle.textContent = '▾';


    row.classList.add('demand-open');


    const detailRow = document.createElement('tr');


    detailRow.className = 'production-tree-row demand-tree-row';


    const detailCell = document.createElement('td');


    detailCell.colSpan = row.children.length;


    detailCell.textContent = 'Načítám zdroje poptávky…';


    detailRow.appendChild(detailCell);


    row.parentNode.insertBefore(detailRow, row.nextSibling);


    demandState = { row, detail: detailRow, toggle };


    loadDemandTree(cell.dataset.sku || row.dataset.sku, detailCell);


  }





  function closeDemandRow() {


    if (!demandState.row) return;


    if (demandState.toggle) demandState.toggle.textContent = '▸';


    demandState.row.classList.remove('demand-open');


    if (demandState.detail) demandState.detail.remove();


    demandState = { row: null, detail: null, toggle: null };


  }





  async function loadDemandTree(sku, container) {


    if (!sku) {


      container.textContent = 'Chybí SKU.';


      return;


    }


    try {


      const response = await fetch(`${demandUrl}?sku=${encodeURIComponent(sku)}`);


      if (!response.ok) {


        throw new Error(`HTTP ${response.status}`);


      }


      const data = await response.json();


      if (!data.ok) {


        throw new Error(data.error || 'Nepodařilo se načíst zdroje poptávky.');


      }


      if (!data.tree) {


        container.textContent = 'Nenalezeny žádné zdroje poptávky.';


        return;


      }


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


    table.innerHTML = `<thead><tr><th>Strom poptávky</th><th>MJ</th><th>Potřeba uzlu</th><th>Požadavek na ${tree.sku}</th><th>Koeficient</th><th>Režim</th></tr></thead>`;


    const body = document.createElement('tbody');


    flattenTree(tree).forEach((row) => {


      const tr = document.createElement('tr');


      if (row.node.is_root) {

        tr.classList.add('bom-root-row');

      }


      const labelCell = document.createElement('td');


      const labelWrap = document.createElement('div');


      labelWrap.className = 'bom-tree-label';


      const prefix = document.createElement('span');


      prefix.className = 'bom-tree-prefix';


      prefix.textContent = buildPrefix(row.guides);


      if (!prefix.textContent.trim()) prefix.style.visibility = 'hidden';


      labelWrap.appendChild(prefix);


      const label = document.createElement('span');


      label.textContent = `${row.node.sku}${row.node.nazev ? ` – ${row.node.nazev}` : ''}`.trim();


      labelWrap.appendChild(label);


      labelCell.appendChild(labelWrap);


      tr.appendChild(labelCell);


      tr.appendChild(createCell(row.node.merna_jednotka || ''));


      tr.appendChild(createCell(formatNumber(row.node.needed)));


      tr.appendChild(createCell(formatNumber(row.node.contribution)));


      tr.appendChild(createCell(formatDemandEdge(row.node.edge)));


      const mode = row.node.status && row.node.status.mode ? row.node.status.mode : '—';


      tr.appendChild(createCell(mode));


      body.appendChild(tr);


    });


    table.appendChild(body);


    return table;


  }





  function formatDemandEdge(edge) {


    if (!edge || !edge.koeficient) {


      return '—';


    }


    let text = formatNumber(edge.koeficient);


    if (edge.merna_jednotka) {


      text += ` ${edge.merna_jednotka}`;


    }


    return text;


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


  function flattenTreeBottomUp(node) {


    return flattenTree(node).reverse();


  }



  function flattenBomTreePlan(root) {


    const rows = [];


    const walk = (node, guides = []) => {


      rows.push({ node, guides });


      const children = Array.isArray(node.children) ? node.children : [];


      children.forEach((child, index) => {


        walk(child, guides.concat([{ last: index === children.length - 1 }]));


      });


    };


    walk(root, []);


    return rows;


  }





  function buildPrefix(guides) {


    if (!guides || !guides.length) return '';


    let prefix = '';


    guides.forEach((guide, idx) => {


      const isLast = typeof guide === 'object' && guide !== null
        ? !!guide.last
        : !!guide;


      if (idx === guides.length - 1) {


        prefix += isLast ? '└── ' : '├── ';


      } else {


        prefix += isLast ? '    ' : '│   ';


      }


    });


    return prefix;


  }





  function createCell(value) {


    const td = document.createElement('td');


    td.textContent = value ?? '—';


    return td;


  }




  function formatNumber(value, decimals = 3) {


    if (value === null || value === undefined || value === '') {


      return '—';


    }


    const num = Number(value);


    if (!Number.isFinite(num)) {


      return '—';


    }


    const fixed = num.toFixed(decimals);


    const trimmed = fixed.replace(/\.?0+$/, '');


    return trimmed === '' ? '0' : trimmed;


  }





  function formatInteger(value) {


    if (value === null || value === undefined || isNaN(value)) {


      return '—';


    }


    return String(Math.round(Number(value)));


  }


})();


</script>




