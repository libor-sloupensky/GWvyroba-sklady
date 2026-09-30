<?php
  $mode = $mode ?? 'list';
  $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  $fmtQty = static function ($v, int $dec = 3): string {
      $n = (float)$v;
      $s = number_format($n, $dec, ',', ' ');
      $s = rtrim(rtrim($s, '0'), ',');
      return $s === '-0' || $s === '' ? '0' : $s;
  };
  $typLabel = ['vyroba' => 'Výroba', 'korekce' => 'Korekce'];
?>
<style>
.mv-toolbar { display:flex; flex-wrap:wrap; gap:.8rem; align-items:flex-end; margin:0 0 1rem; }
.mv-toolbar form { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; }
.mv-toolbar label { display:flex; flex-direction:column; font-size:.85rem; color:#546e7a; gap:.2rem; }
.mv-toolbar input, .mv-toolbar select, .mv-filter input, .mv-filter select { padding:.35rem .5rem; border:1px solid #cfd8dc; border-radius:4px; font-size:.95rem; }
.mv-toolbar button, .mv-filter button, .mv-btn { padding:.4rem .8rem; border:1px solid #1565c0; background:#1565c0; color:#fff; border-radius:4px; cursor:pointer; font-size:.9rem; }
.mv-btn.secondary { background:#fff; color:#1565c0; }
.mv-btn.danger { background:#fff; color:#c62828; border-color:#c62828; }
.mv-btn:disabled { opacity:.45; cursor:not-allowed; }
.mv-head { border:1px solid #dfe6eb; border-radius:6px; padding:.9rem 1rem; background:#f8fbff; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:.8rem 1.2rem; margin-bottom:1rem; }
.mv-head .field { display:flex; flex-direction:column; gap:.25rem; font-size:.85rem; color:#546e7a; }
.mv-head .field strong { color:#263238; font-size:1rem; }
.mv-head .field.wide { grid-column: 1 / -1; }
.mv-head textarea { width:100%; min-height:2.6rem; padding:.4rem .5rem; border:1px solid #cfd8dc; border-radius:4px; font:inherit; resize:vertical; }
.mv-head select { padding:.3rem .4rem; border:1px solid #cfd8dc; border-radius:4px; font-size:.95rem; max-width:220px; }
.mv-lock { border:1px solid #ffcc80; background:#fff8e1; color:#6d4c41; border-radius:6px; padding:.6rem .9rem; margin-bottom:1rem; }
.mv-msg { border:1px solid #a5d6a7; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:.6rem .9rem; margin-bottom:1rem; }
.mv-err { border:1px solid #ef9a9a; background:#ffebee; color:#b71c1c; border-radius:6px; padding:.6rem .9rem; margin-bottom:1rem; }
.mv-lines td.num, .mv-lines th.num { text-align:right; white-space:nowrap; }
.mv-lines tr.child td { color:#546e7a; font-size:.9rem; background:#fafafa; }
.mv-lines tr.child td.tree { padding-left:1.6rem; font-family:monospace; white-space:nowrap; }
.mv-lines input.qty { width:7.5rem; text-align:right; padding:.3rem .4rem; border:1px solid #cfd8dc; border-radius:4px; font-size:.95rem; }
.mv-lines select.rezim { padding:.25rem .3rem; border:1px solid #cfd8dc; border-radius:4px; font-size:.9rem; }
.mv-lines .neg { color:#c62828; font-weight:600; }
.mv-lines .del { background:none; border:none; color:#c62828; cursor:pointer; font-size:1.1rem; line-height:1; }
.mv-lines .saving { opacity:.55; }
.mv-filter { border:1px solid #dfe6eb; border-radius:6px; padding:.9rem; display:flex; flex-wrap:wrap; gap:.8rem; align-items:flex-end; margin:1.2rem 0 .8rem; background:#fff; }
.mv-filter label { display:flex; flex-direction:column; font-size:.85rem; color:#546e7a; gap:.2rem; }
.mv-products tr.nonstock td { color:#9e9e9e; }
.mv-products tr.inactive td.sku { text-decoration: line-through; color:#9e9e9e; }
.mv-products td.num { text-align:right; white-space:nowrap; }
.mv-products .add { padding:.25rem .6rem; border:1px solid #2e7d32; background:#2e7d32; color:#fff; border-radius:4px; cursor:pointer; font-size:.85rem; }
.mv-products .add:disabled { opacity:.4; cursor:not-allowed; }
.mv-log { margin-top:1.5rem; }
.mv-log summary { cursor:pointer; color:#546e7a; }
.mv-log table { font-size:.85rem; }
.mv-muted { color:#78909c; }
.mv-badge { display:inline-block; padding:.1rem .5rem; border-radius:10px; font-size:.8rem; }
.mv-badge.open { background:#e8f5e9; color:#1b5e20; }
.mv-badge.locked { background:#eceff1; color:#546e7a; }
.mv-badge.vyroba { background:#e3f2fd; color:#0d47a1; }
.mv-badge.korekce { background:#fff3e0; color:#e65100; }
.mv-help { display:inline-block; width:1em; height:1em; line-height:1em; text-align:center; border-radius:50%; background:#cfd8dc; color:#263238; font-size:.75rem; cursor:help; margin-left:.3rem; }
</style>

<?php if (!empty($message)): ?><div class="mv-msg"><?= $h($message) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="mv-err"><?= $h($error) ?></div><?php endif; ?>

<?php if ($mode === 'list'): ?>
<h1>Pohyby – skladové doklady</h1>
<p class="mv-muted">Doklad typu <strong>Výroba</strong> přičte hotový produkt a odepíše jeho skladové komponenty podle kusovníku. Doklad typu <strong>Korekce</strong> mění jen zvolenou položku, bez dopadu na komponenty (slouží i pro likvidaci, odpis a opravu stavu). Režim lze změnit i u jednotlivé položky.</p>

<div class="mv-toolbar">
  <form method="post" action="/movements/create">
    <label>Nový doklad
      <select name="typ">
        <option value="vyroba">Výroba</option>
        <option value="korekce">Korekce</option>
      </select>
    </label>
    <button type="submit">Založit doklad</button>
  </form>
  <form method="get" action="/movements">
    <label>Od <input type="date" name="od" value="<?= $h($filters['od']) ?>" /></label>
    <label>Do <input type="date" name="do" value="<?= $h($filters['do']) ?>" /></label>
    <label>Typ
      <select name="typ">
        <option value="">Vše</option>
        <option value="vyroba"<?= $filters['typ'] === 'vyroba' ? ' selected' : '' ?>>Výroba</option>
        <option value="korekce"<?= $filters['typ'] === 'korekce' ? ' selected' : '' ?>>Korekce</option>
      </select>
    </label>
    <label>Hledat <input type="text" name="q" value="<?= $h($filters['q']) ?>" placeholder="číslo, SKU, poznámka, uživatel" /></label>
    <button type="submit">Filtrovat</button>
  </form>
</div>

<?php if (empty($docs)): ?>
  <p class="mv-muted">Žádné doklady.</p>
<?php else: ?>
<table>
  <thead><tr><th>Číslo</th><th>Datum</th><th>Typ</th><th>Uživatel</th><th>Položky</th><th>Poznámka</th><th>Stav</th></tr></thead>
  <tbody>
  <?php foreach ($docs as $d): ?>
    <tr>
      <td><a href="/movements/doc?id=<?= (int)$d['id'] ?>"><?= $h($d['cislo']) ?></a></td>
      <td><?= $h(date('j. n. Y', strtotime((string)$d['datum']))) ?></td>
      <td><span class="mv-badge <?= $h($d['typ']) ?>"><?= $h($typLabel[$d['typ']] ?? $d['typ']) ?></span></td>
      <td><?= $h($d['user_email']) ?></td>
      <td title="<?= $h($d['skus'] ?? '') ?>"><?= (int)$d['radku'] ?><?php if (!empty($d['skus'])): ?> <span class="mv-muted"><?= $h(mb_strimwidth((string)$d['skus'], 0, 40, '…')) ?></span><?php endif; ?></td>
      <td><?= $h(mb_strimwidth((string)($d['poznamka'] ?? ''), 0, 60, '…')) ?></td>
      <td><?php if ($d['lock'] === null): ?><span class="mv-badge open">otevřený</span><?php else: ?><span class="mv-badge locked" title="<?= $h($d['lock']) ?>">uzamčený</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php else: ?>
<?php
  $canEdit = ($lock === null);
  $filterBrand = (int)($filters['brand'] ?? 0);
  $filterGroup = (int)($filters['group'] ?? 0);
  $filterType  = (string)($filters['type'] ?? '');
  $filterSearch= (string)($filters['search'] ?? '');
  $docId = (int)$doc['id'];
?>
<p><a href="/movements">← Seznam dokladů</a></p>
<h1>Doklad <?= $h($doc['cislo']) ?> <span class="mv-badge <?= $h($doc['typ']) ?>" id="mv-typ-badge"><?= $h($typLabel[$doc['typ']] ?? $doc['typ']) ?></span></h1>

<?php if (!$canEdit): ?>
  <div class="mv-lock">🔒 <?= $h($lock) ?></div>
<?php endif; ?>

<div class="mv-head">
  <div class="field">Datum<strong><?= $h(date('j. n. Y', strtotime((string)$doc['datum']))) ?></strong></div>
  <div class="field">Založil<strong><?= $h($doc['user_email']) ?></strong></div>
  <div class="field">Typ dokladu <span class="mv-help" title="Výroba: přičte produkt a odepíše skladové komponenty podle kusovníku. Korekce: mění jen zvolenou položku, bez dopadu na komponenty – slouží i pro likvidaci, odpis a opravu stavu. Typ dokladu je výchozí režim pro nově vkládané položky; u každé položky jde režim změnit.">?</span>
    <?php if ($canEdit): ?>
      <select id="mv-typ">
        <option value="vyroba"<?= $doc['typ'] === 'vyroba' ? ' selected' : '' ?>>Výroba</option>
        <option value="korekce"<?= $doc['typ'] === 'korekce' ? ' selected' : '' ?>>Korekce</option>
      </select>
    <?php else: ?>
      <strong><?= $h($typLabel[$doc['typ']] ?? $doc['typ']) ?></strong>
    <?php endif; ?>
  </div>
  <div class="field">
    <span>Akce</span>
    <span>
      <form method="post" action="/movements/delete" style="display:inline" onsubmit="return confirm('Smazat prázdný doklad <?= $h($doc['cislo']) ?>?');">
        <input type="hidden" name="id" value="<?= $docId ?>" />
        <button type="submit" class="mv-btn danger" id="mv-delete-doc"<?= (!$canEdit || (int)$doc['radku'] > 0) ? ' disabled' : '' ?> title="<?= (int)$doc['radku'] > 0 ? 'Doklad obsahuje položky – nejdřív je smažte.' : ($canEdit ? 'Smazat prázdný doklad' : 'Doklad je uzamčen.') ?>">✕ Smazat doklad</button>
      </form>
    </span>
  </div>
  <div class="field wide">Poznámka (odůvodnění)
    <?php if ($canEdit): ?>
      <textarea id="mv-poznamka" placeholder="Např. výroba pro objednávku X, likvidace prošlé šarže, oprava po inventuře…"><?= $h($doc['poznamka'] ?? '') ?></textarea>
      <span class="mv-muted" id="mv-head-status">Změny se ukládají automaticky.</span>
    <?php else: ?>
      <strong><?= nl2br($h($doc['poznamka'] ?? '')) ?: '<span class="mv-muted">—</span>' ?></strong>
    <?php endif; ?>
  </div>
</div>

<h2>Položky dokladu</h2>
<div id="mv-line-error" class="mv-err" style="display:none"></div>
<table class="mv-lines" id="mv-lines">
  <thead>
    <tr>
      <th>SKU</th><th>Název</th><th>Režim</th>
      <th class="num">Množství</th><th>MJ</th>
      <th class="num">Stav před</th><th class="num">Stav po <span class="mv-help" title="Aktuální fyzický stav skladu po zapsání tohoto řádku. Červeně = záporný stav, položka je vyskladněná do mínusu.">?</span></th>
      <th></th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<?php if ($canEdit): ?>
<h2>Přidat položku</h2>
<form method="get" action="/movements/doc" class="mv-filter">
  <input type="hidden" name="id" value="<?= $docId ?>" />
  <input type="hidden" name="search" value="1" />
  <label>Značka
    <select name="znacka_id">
      <option value="">Všechny</option>
      <?php foreach (($brands ?? []) as $b): $bid = (int)$b['id']; ?>
        <option value="<?= $bid ?>"<?= $filterBrand === $bid ? ' selected' : '' ?>><?= $h($b['nazev']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Skupina
    <select name="skupina_id">
      <option value="">Všechny</option>
      <?php foreach (($groups ?? []) as $g): $gid = (int)$g['id']; ?>
        <option value="<?= $gid ?>"<?= $filterGroup === $gid ? ' selected' : '' ?>><?= $h($g['nazev']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Typ
    <select name="typ">
      <option value="">Všechny</option>
      <?php foreach (($types ?? []) as $t): ?>
        <option value="<?= $h($t) ?>"<?= $filterType === $t ? ' selected' : '' ?>><?= $h($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Hledat <input type="text" name="q" value="<?= $h($filterSearch) ?>" placeholder="SKU, název, alt. SKU, EAN" autofocus /></label>
  <button type="submit">Vyhledat</button>
</form>

<?php if (!empty($hasSearch)): ?>
  <?php if (empty($products)): ?>
    <p class="mv-muted">Pro zadané podmínky nejsou žádné produkty.</p>
  <?php else: ?>
  <table class="mv-products">
    <thead><tr><th>SKU</th><th>Název</th><th>Typ</th><th class="num">Stav</th><th>MJ</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): $ns = !empty($p['is_nonstock']); $inactive = (int)($p['aktivni'] ?? 1) === 0; ?>
      <tr class="<?= $ns ? 'nonstock' : '' ?><?= $inactive ? ' inactive' : '' ?>"<?= $ns ? ' title="Neskladová položka – nevyrábí se ani neskladuje, do dokladu ji nelze vložit. Vložte její skladové komponenty."' : ($inactive ? ' title="Neaktivní produkt (lze použít např. pro odpis zbytku)."' : '') ?>>
        <td class="sku"><?= $h($p['sku']) ?></td>
        <td><?= $h($p['nazev']) ?></td>
        <td><?= $h($p['typ']) ?></td>
        <td class="num"><?= $fmtQty($p['stav']) ?></td>
        <td><?= $h($p['merna_jednotka'] ?? '') ?></td>
        <td><button type="button" class="add" data-sku="<?= $h($p['sku']) ?>"<?= $ns ? ' disabled' : '' ?>>Vložit</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<details class="mv-log" id="mv-log-wrap">
  <summary>Historie změn dokladu (<span id="mv-log-count"><?= count($log ?? []) ?></span>)</summary>
  <table id="mv-log"><thead><tr><th>Kdy</th><th>Kdo</th><th>Akce</th><th>SKU</th><th class="num">Z</th><th class="num">Na</th><th>Detail</th></tr></thead><tbody></tbody></table>
</details>

<script>
(function () {
  const DOC_ID = <?= $docId ?>;
  const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
  let lines = <?= json_encode($lines ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  let log = <?= json_encode($log ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const tbody = document.querySelector('#mv-lines tbody');
  const errBox = document.getElementById('mv-line-error');
  const AKCE = { vytvoreni: 'založení', hlavicka: 'hlavička', pridani: 'přidání', zmena_mnozstvi: 'změna množství', zmena_rezimu: 'změna režimu', smazani: 'smazání' };

  const fmt = (v) => {
    const n = Number(v);
    if (!Number.isFinite(n)) return '';
    return n.toLocaleString('cs-CZ', { maximumFractionDigits: 3 });
  };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const showError = (msg) => { errBox.textContent = msg; errBox.style.display = msg ? '' : 'none'; };

  function stockCell(v) {
    const n = Number(v);
    return `<td class="num${n < 0 ? ' neg' : ''}">${fmt(n)}</td>`;
  }

  function renderLines() {
    if (!lines.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="mv-muted">Doklad zatím nemá položky' + (CAN_EDIT ? ' – vyhledejte produkt níže a klikněte na Vložit.' : '.') + '</td></tr>';
      return;
    }
    const html = [];
    lines.forEach((l) => {
      const rezimCtl = CAN_EDIT
        ? `<select class="rezim" data-id="${l.id}"><option value="vyroba"${l.rezim === 'vyroba' ? ' selected' : ''}>Výroba</option><option value="korekce"${l.rezim === 'korekce' ? ' selected' : ''}>Korekce</option></select>`
        : esc(l.rezim === 'vyroba' ? 'Výroba' : 'Korekce');
      const qtyCtl = CAN_EDIT
        ? `<input type="number" step="any" class="qty" data-id="${l.id}" value="${l.mnozstvi}" />`
        : fmt(l.mnozstvi);
      const del = CAN_EDIT ? `<button type="button" class="del" data-id="${l.id}" title="Smazat položku včetně odepsaných komponent">✕</button>` : '';
      html.push(`<tr data-id="${l.id}"><td><strong>${esc(l.sku)}</strong></td><td>${esc(l.nazev)}</td><td>${rezimCtl}</td><td class="num">${qtyCtl}</td><td>${esc(l.mj)}</td>${stockCell(l.stav_pred)}${stockCell(l.stav_po)}<td>${del}</td></tr>`);
      const kids = l.children || [];
      kids.forEach((c, i) => {
        const branch = i === kids.length - 1 ? '└──' : '├──';
        html.push(`<tr class="child"><td class="tree">${branch} ${esc(c.sku)}</td><td>${esc(c.nazev)}</td><td class="mv-muted">komponenta</td><td class="num">${fmt(c.mnozstvi)}</td><td>${esc(c.mj)}</td>${stockCell(c.stav_pred)}${stockCell(c.stav_po)}<td></td></tr>`);
      });
      if (l.rezim === 'vyroba' && !kids.length && Number(l.mnozstvi) !== 0) {
        html.push(`<tr class="child"><td class="tree">└──</td><td colspan="7" class="mv-muted">Produkt nemá v kusovníku žádné skladové komponenty, nic se neodepsalo.</td></tr>`);
      }
    });
    tbody.innerHTML = html.join('');
  }

  function renderLog() {
    const body = document.querySelector('#mv-log tbody');
    document.getElementById('mv-log-count').textContent = log.length;
    body.innerHTML = log.map((e) => `<tr><td>${esc(e.datum)}</td><td>${esc(e.user_email)}</td><td>${esc(AKCE[e.akce] || e.akce)}</td><td>${esc(e.sku || '')}</td><td class="num">${e.stare_mnozstvi === null ? '' : fmt(e.stare_mnozstvi)}</td><td class="num">${e.nove_mnozstvi === null ? '' : fmt(e.nove_mnozstvi)}</td><td>${esc(e.detail || '')}</td></tr>`).join('');
  }

  async function call(url, payload) {
    const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ doklad_id: DOC_ID }, payload)) });
    let data = null;
    try { data = await res.json(); } catch (_) { throw new Error('Neplatná odpověď serveru.'); }
    if (!data.ok) {
      if (data.locked) { window.location.reload(); }
      throw new Error(data.error || 'Uložení selhalo.');
    }
    lines = data.lines || [];
    log = data.log || [];
    renderLines();
    renderLog();
    const delBtn = document.getElementById('mv-delete-doc');
    if (delBtn) {
      const has = (data.doc && data.doc.radku > 0);
      delBtn.disabled = has;
      delBtn.title = has ? 'Doklad obsahuje položky – nejdřív je smažte.' : 'Smazat prázdný doklad';
    }
    return data;
  }

  renderLines();
  renderLog();
  if (!CAN_EDIT) return;

  // Hlavička – autosave
  const typSel = document.getElementById('mv-typ');
  const pozn = document.getElementById('mv-poznamka');
  const headStatus = document.getElementById('mv-head-status');
  let headTimer = null;
  async function saveHeader() {
    headStatus.textContent = 'Ukládám…';
    try {
      await call('/movements/header', { typ: typSel.value, poznamka: pozn.value });
      const badge = document.getElementById('mv-typ-badge');
      badge.textContent = typSel.value === 'vyroba' ? 'Výroba' : 'Korekce';
      badge.className = 'mv-badge ' + typSel.value;
      headStatus.textContent = 'Uloženo ' + new Date().toLocaleTimeString('cs-CZ');
    } catch (e) {
      headStatus.textContent = 'Chyba: ' + e.message;
    }
  }
  typSel.addEventListener('change', saveHeader);
  pozn.addEventListener('input', () => { clearTimeout(headTimer); headTimer = setTimeout(saveHeader, 700); });
  pozn.addEventListener('blur', () => { clearTimeout(headTimer); saveHeader(); });

  // Vložení produktu
  document.querySelectorAll('.mv-products .add').forEach((btn) => {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      showError('');
      try {
        const data = await call('/movements/line/add', { sku: btn.dataset.sku, mnozstvi: 1, rezim: typSel.value });
        const input = tbody.querySelector(`input.qty[data-id="${data.line_id}"]`);
        if (input) { input.focus(); input.select(); input.scrollIntoView({ block: 'center' }); }
      } catch (e) {
        showError(e.message);
      } finally {
        btn.disabled = false;
      }
    });
  });

  // Řádky – množství, režim, smazání (delegace)
  const pending = new Map();
  async function saveLine(id) {
    const row = tbody.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    const qty = row.querySelector('input.qty').value;
    const rezim = row.querySelector('select.rezim').value;
    if (qty === '' || Number.isNaN(Number(qty))) { showError('Zadejte číselné množství.'); return; }
    row.classList.add('saving');
    showError('');
    try {
      await call('/movements/line/update', { line_id: id, mnozstvi: Number(qty), rezim });
    } catch (e) {
      showError(e.message);
      row.classList.remove('saving');
    }
  }
  tbody.addEventListener('input', (ev) => {
    const input = ev.target.closest('input.qty');
    if (!input) return;
    const id = Number(input.dataset.id);
    clearTimeout(pending.get(id));
    pending.set(id, setTimeout(() => saveLine(id), 600));
  });
  tbody.addEventListener('change', (ev) => {
    const ctl = ev.target.closest('input.qty, select.rezim');
    if (!ctl) return;
    const id = Number(ctl.dataset.id);
    clearTimeout(pending.get(id));
    saveLine(id);
  });
  tbody.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter' && ev.target.closest('input.qty')) { ev.preventDefault(); ev.target.blur(); }
  });
  tbody.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('button.del');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    const line = lines.find((l) => l.id === id);
    if (!confirm(`Smazat položku ${line ? line.sku : ''} včetně odepsaných komponent?`)) return;
    showError('');
    try { await call('/movements/line/delete', { line_id: id }); } catch (e) { showError(e.message); }
  });
})();
</script>
<?php endif; ?>
