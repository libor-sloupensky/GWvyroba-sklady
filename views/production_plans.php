ď»ż<?php


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


.bom-tree-label.is-root {


  font-weight:700;


  color:#1a237e;


}


.demand-direct-note {


  margin-top:0.6rem;


  padding:0.6rem 0.8rem;


  background:#f4f8fb;


  border:1px solid #dbe3ea;


  border-radius:6px;


  color:#37474f;


  font-size:0.9rem;


}


.demand-direct-note ul {


  margin:0.2rem 0 0 1.2rem;


  padding:0;


}


.demand-direct-note li {


  margin:0.15rem 0;


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


  <div class="notice-empty">Zadejte parametry vyhledÄ‚Ë‡vÄ‚Ë‡nÄ‚Â­ a potvrĂ„Ĺąte tlaĂ„Ĺ¤Ä‚Â­tkem Vyhledat. Seznam produktÄąĹ» a nÄ‚Ë‡vrh vÄ‚Ëťroby se zobrazÄ‚Â­ aÄąÄľ po vyhledÄ‚Ë‡nÄ‚Â­.</div>


<?php elseif (empty($items)): ?>


  <div class="notice-empty">Pro zadanÄ‚Â© podmÄ‚Â­nky nejsou dostupnÄ‚Ë‡ ÄąÄľÄ‚Ë‡dnÄ‚Ë‡ data.</div>


<?php else: ?>


  <div class="production-table-wrapper">


  <table class="production-table">


    <thead>


      <tr>


        <th>SKU</th>


        <th>Typ</th>


        <th>NÄ‚Ë‡zev</th>


        <th>DostupnÄ‚Â©</th>


        <th>Rezervace</th>


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


          <span class="sku-toggle">â–¸</span>


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


              <span class="demand-toggle">â–¸</span>


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


          <form method="post" action="/production/produce" class="production-form" data-sku="<?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?>">


            <input type="hidden" name="sku" value="<?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') ?>" />


            <input type="hidden" name="modus" value="odecti_subpotomky" />


            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/production/plans', ENT_QUOTES, 'UTF-8') ?>" />


            <input type="number" step="any" name="mnozstvi" placeholder="MnoÄąÄľstvÄ‚Â­" required />


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


    <label>PoĂ„Ĺ¤et zobrazenÄ‚Ëťch zÄ‚Ë‡znamÄąĹ»:


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


        <th>NÄ‚Ë‡zev</th>


        <th>MnoÄąÄľstvÄ‚Â­</th>


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


  <p class="muted">ZatÄ‚Â­m nejsou zapsanÄ‚Â© ÄąÄľÄ‚Ë‡dnÄ‚Â© vÄ‚Ëťroby.</p>


<?php endif; ?>





<div class="production-modal-overlay" id="production-modal">


  <div class="production-modal">


    <h3>Nedostatek komponent</h3>


    <p>OdeĂ„Ĺ¤et komponent by nĂ„â€şkterÄ‚Â© poloÄąÄľky poslal do zÄ‚Ë‡pornÄ‚Â©ho stavu. Vyberte, jak postupovat:</p>


    <ul id="production-deficit-list"></ul>


    <small>Volba "OdeĂ„Ĺ¤Ä‚Â­st subpotomky" automaticky odeĂ„Ĺ¤te vÄąË‡echny komponenty (i do mÄ‚Â­nusu). Volba "OdeĂ„Ĺ¤Ä‚Â­st do mÄ‚Â­nusu" zapÄ‚Â­ÄąË‡e jen hotovÄ‚Ëť produkt a komponenty je potÄąâ„˘eba odepsat ruĂ„Ĺ¤nĂ„â€ş.</small>


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


      event.preventDefault();


      const qtyField = form.querySelector('input[name="mnozstvi"]');


      const qty = parseFloat((qtyField.value || '').replace(',', '.'));


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


    listEl.innerHTML = '';


    pendingForm = null;


  }





  function renderDeficits(deficits) {


    listEl.innerHTML = '';


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


    if (toggle) toggle.textContent = 'â–ľ';


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


    if (toggle) toggle.textContent = 'â–¸';


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


      container.innerHTML = '';


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


      label.textContent = `${row.node.sku}${row.node.nazev ? ` Ă˘â‚¬â€ś ${row.node.nazev}` : ''}`.trim();


      if (row.node.is_root) {


        labelWrap.classList.add('is-root');


      }


      const status = row.node.status || null;


      if (status && (status.deficit || 0) > 0.0005) {


        label.classList.add('bom-node-critical');


      } else if (status && (status.ratio || 0) > 0.4) {


        label.classList.add('bom-node-warning');


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


    if (toggle) toggle.textContent = 'â–ľ';


    row.classList.add('demand-open');


    const detailRow = document.createElement('tr');


    detailRow.className = 'production-tree-row demand-tree-row';


    const detailCell = document.createElement('td');


    detailCell.colSpan = row.children.length;


    detailCell.textContent = 'NaĂ„Ĺ¤Ä‚Â­tÄ‚Ë‡m zdroje poptÄ‚Ë‡vkyĂ˘â‚¬Â¦';


    detailRow.appendChild(detailCell);


    row.parentNode.insertBefore(detailRow, row.nextSibling);


    demandState = { row, detail: detailRow, toggle };


    loadDemandTree(cell.dataset.sku || row.dataset.sku, detailCell);


  }





  function closeDemandRow() {


    if (!demandState.row) return;


    if (demandState.toggle) demandState.toggle.textContent = 'â–¸';


    demandState.row.classList.remove('demand-open');


    if (demandState.detail) demandState.detail.remove();


    demandState = { row: null, detail: null, toggle: null };


  }





  async function loadDemandTree(sku, container) {


    if (!sku) {


      container.textContent = 'ChybÄ‚Â­ SKU.';


      return;


    }


    try {


      const response = await fetch(`${demandUrl}?sku=${encodeURIComponent(sku)}`);


      if (!response.ok) {


        throw new Error(`HTTP ${response.status}`);


      }


      const data = await response.json();


      if (!data.ok || !data.tree) {


        throw new Error(data.error || 'NepodaÄąâ„˘ilo se naĂ„Ĺ¤Ä‚Â­st zdroje poptÄ‚Ë‡vky.');


      }


      container.innerHTML = '';


      const oriented = orientDemandTree(data.tree);


      container.appendChild(buildDemandTable(oriented, data.tree.sku));


      if (!data.tree.children || !data.tree.children.length) {


        container.appendChild(renderDirectDemandNote(data.tree.status || {}));


      }


    } catch (err) {


      container.textContent = err.message || 'NepodaÄąâ„˘ilo se naĂ„Ĺ¤Ä‚Â­st zdroje poptÄ‚Ë‡vky.';


    }


  }





  function buildDemandTable(tree, rootSku) {


    const table = document.createElement('table');


    table.className = 'bom-tree-table demand-tree-table';


    table.innerHTML = `<thead><tr><th>Strom poptÄ‚Ë‡vky</th><th>MJ</th><th>PotÄąâ„˘eba uzlu</th><th>PoÄąÄľadavek na ${rootSku}</th><th>Koeficient</th><th>ReÄąÄľim</th></tr></thead>`;


    const body = document.createElement('tbody');


    const skipGuide = !tree.node || !tree.node.sku;
    flattenTree(tree).forEach((row) => {


      if (!row.node || !row.node.sku) {


        return;


      }


      const tr = document.createElement('tr');


      const labelCell = document.createElement('td');


      const labelWrap = document.createElement('div');


      labelWrap.className = 'bom-tree-label';


      const prefix = document.createElement('span');


      prefix.className = 'bom-tree-prefix';


      const guides = skipGuide ? row.guides.slice(1) : row.guides;


      prefix.textContent = buildPrefix(guides);


      if (!prefix.textContent.trim()) prefix.style.visibility = 'hidden';


      labelWrap.appendChild(prefix);


      const label = document.createElement('span');


      label.textContent = `${row.node.sku}${row.node.nazev ? ` Ă˘â‚¬â€ś ${row.node.nazev}` : ''}`.trim();


      if (row.node.is_root) {


        labelWrap.classList.add('is-root');


      }


      labelWrap.appendChild(label);


      const status = row.node.status || null;


      if (status && (status.deficit || 0) > 0.0005) {


        label.classList.add('bom-node-critical');


      } else if (status && (status.ratio || 0) > 0.4) {


        label.classList.add('bom-node-warning');


      }


      labelCell.appendChild(labelWrap);


      tr.appendChild(labelCell);


      tr.appendChild(createCell(row.node.merna_jednotka || ''));


      tr.appendChild(createCell(formatNumber(row.node.needed)));


      tr.appendChild(createCell(formatNumber(row.node.contribution)));


      tr.appendChild(createCell(formatDemandEdge(row.node.edge)));


      const mode = row.node.status && row.node.status.mode ? row.node.status.mode : 'Ă˘â‚¬â€ť';


      tr.appendChild(createCell(mode));


      body.appendChild(tr);


    });


    table.appendChild(body);


    return table;


  }





  function formatDemandEdge(edge) {


    if (!edge || !edge.koeficient) {


      return 'Ă˘â‚¬â€ť';


    }


    let text = formatNumber(edge.koeficient);


    if (edge.merna_jednotka) {


      text += ` ${edge.merna_jednotka}`;


    }


    if (edge.druh_vazby) {


      text += ` (${edge.druh_vazby})`;


    }


    return text;


  }





  function orientDemandTree(tree) {


    if (!tree) {


      return { node: null, children: [] };


    }


    const paths = [];


    collectDemandPaths(tree, [], paths);


    if (!paths.length) {


      paths.push([tree]);


    }


    const root = { node: null, children: [] };


    paths.forEach((path) => {


      const reversed = path.slice().reverse();


      let cursor = root;


      reversed.forEach((nodeData) => {


        if (!nodeData || !nodeData.sku) {


          return;


        }


        let child = cursor.children.find((entry) => entry.node && entry.node.sku === nodeData.sku);


        if (!child) {


          child = { node: cloneDemandNode(nodeData), children: [] };


          cursor.children.push(child);


        }


        cursor = child;


      });


    });


    return root;


  }





  function collectDemandPaths(node, path, bucket) {


    const nextPath = [...path, node];


    if (!node.children || !node.children.length) {


      bucket.push(nextPath);


      return;


    }


    node.children.forEach((child) => collectDemandPaths(child, nextPath, bucket));


  }





  function cloneDemandNode(node) {


    return {


      sku: node.sku,


      nazev: node.nazev,


      typ: node.typ,


      merna_jednotka: node.merna_jednotka,


      status: node.status || null,


      needed: node.needed || 0,


      contribution: node.contribution || 0,


      edge: node.edge || null,


      is_root: !!node.is_root,


      children: [],


    };


  }





  function renderDirectDemandNote(status) {


    const wrap = document.createElement('div');


    wrap.className = 'demand-direct-note';


    const stock = Number(status.stock || 0);


    const target = Number(status.target || 0);


    const reservations = Math.max(0, Number(status.reservations || 0));


    const deficit = Math.max(0, Number(status.deficit || 0));


    const minGap = Math.max(0, target - stock);


    const reservationGap = Math.max(0, deficit - minGap);


    wrap.innerHTML = `
      <p><strong>PÄąâ„˘Ä‚Â­mÄ‚Â© dÄąĹ»vody poptÄ‚Ë‡vky:</strong></p>
      <ul>
        <li>Rezervace / otevÄąâ„˘enÄ‚Â© objednÄ‚Ë‡vky: <strong>${formatNumber(reservationGap, 3)}</strong></li>
        <li>MinimÄ‚Ë‡lnÄ‚Â­ zÄ‚Ë‡soba / cÄ‚Â­lovÄ‚Ëť stav: <strong>${formatNumber(minGap, 3)}</strong></li>
      </ul>
    `;


    return wrap;


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


    if (!guides || !guides.length) return '';


    let prefix = '';


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


    td.textContent = value ?? 'Ă˘â‚¬â€ť';


    return td;


  }




  function formatNumber(value, decimals = 3) {


    if (value === null || value === undefined || value === '') {


      return 'Ă˘â‚¬â€ť';


    }


    const num = Number(value);


    if (!Number.isFinite(num)) {


      return 'Ă˘â‚¬â€ť';


    }


    const fixed = num.toFixed(decimals);


    const trimmed = fixed.replace(/\.?0+$/, '');


    return trimmed === '' ? '0' : trimmed;


  }





  function formatInteger(value) {


    if (value === null || value === undefined || isNaN(value)) {


      return 'Ă˘â‚¬â€ť';


    }


    return String(Math.round(Number(value)));


  }


})();


</script>





