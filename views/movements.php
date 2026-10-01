<?php
  $mode = $mode ?? 'list';
  $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  $fmtQty = static function ($v, int $dec = 3): string {
      $n = (float)$v;
      $s = number_format($n, $dec, ',', ' ');
      $s = rtrim(rtrim($s, '0'), ',');
      return $s === '-0' || $s === '' ? '0' : $s;
  };
  $typLabel = ['vyroba' => 'Výroba', 'korekce' => 'Korekce', 'inventura' => 'Inventura'];
?>
<style>
.mv-toolbar { display:flex; flex-wrap:wrap; gap:.8rem; align-items:flex-end; margin:0 0 1rem; }
.mv-toolbar form { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; }
.mv-toolbar label { display:flex; flex-direction:column; font-size:.85rem; color:#5f5e5a; gap:.2rem; }
.mv-toolbar input, .mv-toolbar select, .mv-filter input, .mv-filter select { padding:.35rem .5rem; border:1px solid #d6d4cf; border-radius:4px; font-size:.95rem; }
.mv-toolbar button, .mv-filter button, .mv-btn { padding:.4rem .8rem; border:1px solid #c14a00; background:#c14a00; color:#fff; border-radius:4px; cursor:pointer; font-size:.9rem; }
.mv-btn.secondary { background:#fff; color:#c14a00; }
.mv-btn.danger { background:#fff; color:#c62828; border-color:#c62828; }
.mv-btn:disabled { opacity:.45; cursor:not-allowed; }
.mv-head { border:1px solid #e5e5e5; border-radius:6px; padding:.9rem 1rem; background:#faf9f7; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:.8rem 1.2rem; margin-bottom:1rem; }
.mv-head .field { display:flex; flex-direction:column; gap:.25rem; font-size:.85rem; color:#5f5e5a; }
.mv-head .field strong { color:#111111; font-size:1rem; }
.mv-head .field.wide { grid-column: 1 / -1; }
.mv-head textarea { width:100%; min-height:2.6rem; padding:.4rem .5rem; border:1px solid #d6d4cf; border-radius:4px; font:inherit; resize:vertical; }
.mv-head select { padding:.3rem .4rem; border:1px solid #d6d4cf; border-radius:4px; font-size:.95rem; max-width:220px; }
.mv-lock { border:1px solid #ffcc80; background:#fff8e1; color:#6d4c41; border-radius:6px; padding:.6rem .9rem; margin-bottom:1rem; }
.mv-msg { border:1px solid #a5d6a7; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:.6rem .9rem; margin-bottom:1rem; }
.mv-err { border:1px solid #ef9a9a; background:#ffebee; color:#b71c1c; border-radius:6px; padding:.6rem .9rem; margin-bottom:1rem; }
.mv-lines td.num, .mv-lines th.num { text-align:right; white-space:nowrap; }
.mv-lines tr.child td { color:#5f5e5a; font-size:.9rem; background:#fafafa; }
.mv-lines tr.child td.tree { padding-left:1.6rem; font-family:monospace; white-space:nowrap; }
.mv-lines input.qty { width:7.5rem; text-align:right; padding:.3rem .4rem; border:1px solid #d6d4cf; border-radius:4px; font-size:.95rem; }
.mv-lines select.rezim { padding:.25rem .3rem; border:1px solid #d6d4cf; border-radius:4px; font-size:.9rem; }
.mv-lines .neg { color:#c62828; font-weight:600; }
.mv-lines .del { background:none; border:none; color:#c62828; cursor:pointer; font-size:1.1rem; line-height:1; }
.mv-lines .saving { opacity:.55; }
.mv-filter { border:1px solid #e5e5e5; border-radius:6px; padding:.9rem; display:flex; flex-wrap:wrap; gap:.8rem; align-items:flex-end; margin:1.2rem 0 .8rem; background:#fff; }
.mv-filter label { display:flex; flex-direction:column; font-size:.85rem; color:#5f5e5a; gap:.2rem; }
.mv-products tr.nonstock td { color:#9e9e9e; }
.mv-products tr.inactive td.sku { text-decoration: line-through; color:#9e9e9e; }
.mv-products td.num { text-align:right; white-space:nowrap; }
.mv-products .add { padding:.25rem .6rem; border:1px solid var(--c-primary-akcent); background:var(--c-primary-akcent); color:#fff; border-radius:4px; cursor:pointer; font-size:.85rem; }
.mv-products .add:disabled { opacity:.4; cursor:not-allowed; }
.mv-log { margin-top:1.5rem; }
.mv-log summary { cursor:pointer; color:#5f5e5a; }
.mv-log table { font-size:.85rem; }
.mv-muted { color:#5f5e5a; }
.mv-badge { display:inline-block; padding:.1rem .5rem; border-radius:10px; font-size:.8rem; }
.mv-badge.open { background:#e8f5e9; color:#1b5e20; }
.mv-badge.locked { background:#f6f5f2; color:#5f5e5a; }
.mv-badge.vyroba { background:#fbeee6; color:#c14a00; }
.mv-badge.korekce { background:#fff3e0; color:#e65100; }
.mv-badge.inventura { background:#ede7f6; color:#4527a0; }
.mv-help { display:inline-block; position:relative; color:#5f5e5a; cursor:help; margin-left:.25rem; vertical-align:middle; line-height:1; }
.mv-help:hover { color:#111111; }
.mv-help .mv-tip { display:none; position:absolute; left:0; top:1.5em; z-index:20; width:22rem; max-width:80vw; background:#111111; color:#f6f5f2; text-align:left; font-size:.85rem; line-height:1.45; padding:.6rem .8rem; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.25); white-space:normal; font-weight:normal; }
.mv-help:hover .mv-tip, .mv-help:focus .mv-tip { display:block; }
.mv-help .mv-tip b { color:#fff; }
.mv-toolbar .create { margin-left:auto; }
.mv-search-more { color:#5f5e5a; font-size:.9rem; margin:.4rem 0 0; }
</style>

<?php if (!empty($message)): ?><div class="mv-msg"><?= $h($message) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="mv-err"><?= $h($error) ?></div><?php endif; ?>

<?php if ($mode === 'list'): ?>
<h1>Pohyby – skladové doklady</h1>
<p class="mv-muted">Doklad typu <strong>Výroba</strong> přičte hotový produkt a odepíše jeho skladové komponenty podle kusovníku. Doklad typu <strong>Korekce</strong> mění jen zvolenou položku, bez dopadu na komponenty (slouží i pro likvidaci, odpis a opravu stavu). Režim lze změnit i u jednotlivé položky. Doklad typu <strong>Inventura</strong> vzniká automaticky uzavřením inventury a obsahuje jen položky s rozdílem.</p>

<div class="mv-toolbar">
  <form method="get" action="/movements">
    <label>Od <input type="date" name="od" value="<?= $h($filters['od']) ?>" /></label>
    <label>Do <input type="date" name="do" value="<?= $h($filters['do']) ?>" /></label>
    <label>Typ
      <select name="typ">
        <option value="">Vše</option>
        <option value="vyroba"<?= $filters['typ'] === 'vyroba' ? ' selected' : '' ?>>Výroba</option>
        <option value="korekce"<?= $filters['typ'] === 'korekce' ? ' selected' : '' ?>>Korekce</option>
        <option value="inventura"<?= $filters['typ'] === 'inventura' ? ' selected' : '' ?>>Inventura</option>
      </select>
    </label>
    <label>Hledat <input type="text" name="q" value="<?= $h($filters['q']) ?>" placeholder="číslo, SKU, poznámka, uživatel" /></label>
    <button type="submit">Filtrovat</button>
  </form>
  <?php if (!empty($canCreate)): ?>
  <form method="post" action="/movements/create" class="create" title="Založí nový doklad (typ Výroba / Korekce se volí uvnitř dokladu).">
    <button type="submit" class="ikona-btn"><?= ikona('plus', 14) ?> Založit doklad</button>
  </form>
  <?php endif; ?>
</div>

<?php if (empty($docs)): ?>
  <p class="mv-muted">Žádné doklady.</p>
<?php else: ?>
<table>
  <thead><tr><th>Číslo</th><th>Datum</th><th>Typ</th><th>Uživatel</th><th>Položky</th><th class="num" style="text-align:right;">Hodnota (CZK)</th><th>Poznámka</th><th>Stav</th></tr></thead>
  <tbody>
  <?php foreach ($docs as $d): ?>
    <tr>
      <td><a href="/movements/doc?id=<?= (int)$d['id'] ?>"><?= $h($d['cislo']) ?></a></td>
      <td><?= $h(date('j. n. Y', strtotime((string)$d['datum']))) ?></td>
      <td><span class="mv-badge <?= $h($d['typ']) ?>"><?= $h($typLabel[$d['typ']] ?? $d['typ']) ?></span></td>
      <td><?= $h($d['user_email']) ?></td>
      <td title="<?= $h($d['skus'] ?? '') ?>"><?= (int)$d['radku'] ?><?php if (!empty($d['skus'])): ?> <span class="mv-muted"><?= $h(mb_strimwidth((string)$d['skus'], 0, 40, '…')) ?></span><?php endif; ?></td>
      <td style="text-align:right; white-space:nowrap;<?= (float)($d['hodnota'] ?? 0) < 0 ? ' color:#c62828;' : '' ?>"><?= $d['hodnota'] === null ? '<span class="mv-muted">—</span>' : $h(number_format((float)$d['hodnota'], 2, ',', ' ')) ?></td>
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
  $isInv = (($doc['typ'] ?? '') === 'inventura');
  $canEditNote = !empty($canEditNote);
  $docId = (int)$doc['id'];
?>
<p><a href="/movements" class="ikona-btn"><?= ikona('arrow-left', 14) ?> Seznam dokladů</a></p>
<h1>Doklad <?= $h($doc['cislo']) ?> <span class="mv-badge <?= $h($doc['typ']) ?>" id="mv-typ-badge"><?= $h($typLabel[$doc['typ']] ?? $doc['typ']) ?></span></h1>

<?php if (!$canEdit): ?>
  <div class="mv-lock"><?= ikona('lock', 14) ?> <?= $h($lock) ?></div>
<?php endif; ?>

<div class="mv-head">
  <div class="field">Datum<strong><?= $h(date('j. n. Y', strtotime((string)$doc['datum']))) ?></strong></div>
  <div class="field">Založil<strong><?= $h($doc['user_email']) ?></strong></div>
  <div class="field"><span>Typ dokladu <span class="mv-help" tabindex="0"><?= ikona('circle-help', 14) ?><span class="mv-tip"><b>Výroba</b> – přičte produkt a odepíše jeho skladové komponenty podle kusovníku.<br><br><b>Korekce</b> – mění jen zvolenou položku, bez dopadu na komponenty. Slouží i pro likvidaci, odpis a opravu stavu.<br><br>Typ dokladu je výchozí režim pro nově vkládané položky, u každé položky jde režim změnit.</span></span></span>
    <?php if ($isInv): ?>
      <strong>Inventura <?php if (!empty($doc['inventura_id'])): ?><a href="/inventory?inventory_id=<?= (int)$doc['inventura_id'] ?>" style="font-weight:normal; font-size:.9rem;">detail inventury #<?= (int)$doc['inventura_id'] ?></a><?php endif; ?></strong>
    <?php elseif ($canEdit): ?>
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
      <?php if ($isInv): ?>
        <span class="mv-muted">Inventární doklad se maže jen spolu s inventurou.</span>
      <?php else: ?>
      <form method="post" action="/movements/delete" style="display:inline" onsubmit="return confirm('Smazat prázdný doklad <?= $h($doc['cislo']) ?>?');">
        <input type="hidden" name="id" value="<?= $docId ?>" />
        <button type="submit" class="mv-btn danger" id="mv-delete-doc"<?= (!$canEdit || (int)$doc['radku'] > 0) ? ' disabled' : '' ?> title="<?= (int)$doc['radku'] > 0 ? 'Doklad obsahuje položky – nejdřív je smažte.' : ($canEdit ? 'Smazat prázdný doklad' : 'Doklad je uzamčen.') ?>"><?= ikona('x', 14) ?> Smazat doklad</button>
      </form>
      <?php endif; ?>
    </span>
  </div>
  <div class="field wide">Poznámka (odůvodnění)
    <?php if ($canEdit || $canEditNote): ?>
      <textarea id="mv-poznamka" placeholder="Např. výroba pro objednávku X, likvidace prošlé šarže, oprava po inventuře…"><?= $h($doc['poznamka'] ?? '') ?></textarea>
      <span class="mv-muted" id="mv-head-status">Změny se ukládají automaticky.</span>
    <?php else: ?>
      <strong><?= nl2br($h($doc['poznamka'] ?? '')) ?: '<span class="mv-muted">—</span>' ?></strong>
    <?php endif; ?>
  </div>
</div>

<?php if ($canEdit): ?>
<h2>Přidat položku</h2>
<form class="mv-filter" id="mv-search" onsubmit="return false;">
  <label>Značka
    <select name="znacka_id">
      <option value="">Všechny</option>
      <?php foreach (($brands ?? []) as $b): ?>
        <option value="<?= (int)$b['id'] ?>"><?= $h($b['nazev']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Skupina
    <select name="skupina_id">
      <option value="">Všechny</option>
      <?php foreach (($groups ?? []) as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= $h($g['nazev']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Typ
    <select name="typ">
      <option value="">Všechny</option>
      <?php foreach (($types ?? []) as $t): ?>
        <option value="<?= $h($t) ?>"><?= $h($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Hledat <input type="text" name="q" value="" placeholder="SKU, název, alt. SKU, EAN – hledá se průběžně" autocomplete="off" autofocus /></label>
</form>
<div id="mv-search-results"></div>
<?php endif; ?>

<h2>Položky dokladu</h2>
<div id="mv-line-error" class="mv-err" style="display:none"></div>
<table class="mv-lines" id="mv-lines">
  <thead>
    <?php if ($isInv): ?>
    <tr>
      <th>SKU</th><th>Název</th>
      <th class="num">Očekávaný stav</th><th class="num">Zjištěný stav</th>
      <th class="num">Rozdíl <span class="mv-help" tabindex="0"><?= ikona('circle-help', 14) ?><span class="mv-tip">Zjištěný stav − očekávaný stav v okamžiku uzavření inventury. Zobrazují se jen položky s nenulovým rozdílem; položky beze změny doklad neobsahuje.</span></span></th>
      <th>MJ</th>
      <th class="num">Hodnota (CZK) <span class="mv-help" tabindex="0"><?= ikona('circle-help', 14) ?><span class="mv-tip">Rozdíl × skladová hodnota položky platná při uzavření inventury (u dokladů doplněných zpětně 30. 9. 2026 hodnota z toho dne). Záporný rozdíl = záporná hodnota.</span></span></th>
    </tr>
    <?php else: ?>
    <tr>
      <th>SKU</th><th>Název</th><th>Režim</th>
      <th class="num">Množství</th><th>MJ</th>
      <th class="num">Stav před</th><th class="num">Stav po <span class="mv-help" tabindex="0"><?= ikona('circle-help', 14) ?><span class="mv-tip">Aktuální fyzický stav skladu po zapsání tohoto řádku. <b>Červeně</b> = záporný stav, položka je vyskladněná do mínusu.</span></span></th>
      <th class="num">Hodnota (CZK) <span class="mv-help" tabindex="0"><?= ikona('circle-help', 14) ?><span class="mv-tip">Množství × skladová hodnota položky. Skladová hodnota se ukládá v okamžiku zápisu řádku; při pozdější změně množství se převezme hodnota platná v době změny. <b>Záporný pohyb = záporná hodnota.</b> Řádek Celkem sčítá rodiče i odepsané komponenty.</span></span></th>
      <th></th>
    </tr>
    <?php endif; ?>
  </thead>
  <tbody></tbody>
</table>

<details class="mv-log" id="mv-log-wrap">
  <summary>Historie změn dokladu (<span id="mv-log-count"><?= count($log ?? []) ?></span>)</summary>
  <table id="mv-log"><thead><tr><th>Kdy</th><th>Kdo</th><th>Akce</th><th>SKU</th><th class="num">Z</th><th class="num">Na</th><th>Detail</th></tr></thead><tbody></tbody></table>
</details>

<script>
(function () {
  const DOC_ID = <?= $docId ?>;
  const DOC_TYP = <?= json_encode((string)$doc['typ']) ?>;
  const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
  const CAN_EDIT_NOTE = <?= ($canEdit || $canEditNote) ? 'true' : 'false' ?>;
  let lines = <?= json_encode($lines ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  let log = <?= json_encode($log ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const tbody = document.querySelector('#mv-lines tbody');
  const errBox = document.getElementById('mv-line-error');
  const AKCE = { vytvoreni: 'založení', hlavicka: 'hlavička', pridani: 'přidání', zmena_mnozstvi: 'změna množství', zmena_rezimu: 'změna režimu', smazani: 'smazání', inventura_uzavrena: 'inventura uzavřena', inventura_otevrena: 'inventura otevřena' };

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
  const fmtCzk = (v) => {
    const n = Number(v);
    if (v === null || v === undefined || !Number.isFinite(n)) return '';
    return n.toLocaleString('cs-CZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  };
  function valueCell(v, strong) {
    const n = Number(v);
    const txt = fmtCzk(v);
    return `<td class="num${n < 0 ? ' neg' : ''}" title="${v === null ? 'Položka nemá skladovou hodnotu' : ''}">${strong ? '<strong>' + txt + '</strong>' : txt}</td>`;
  }
  function totalRow(colspan) {
    let total = 0;
    lines.forEach((l) => {
      if (l.hodnota !== null && l.hodnota !== undefined) total += Number(l.hodnota);
      (l.children || []).forEach((c) => { if (c.hodnota !== null && c.hodnota !== undefined) total += Number(c.hodnota); });
    });
    return `<tr class="total" style="background:#faf9f7; font-weight:600;"><td colspan="${colspan}" style="text-align:right;">Celkem hodnota</td>${valueCell(Math.round(total * 100) / 100, true)}<td></td></tr>`;
  }

  function renderLines() {
    if (DOC_TYP === 'inventura') {
      if (!lines.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="mv-muted">Inventura nemá žádné položky s rozdílem (nebo je znovu otevřená – položky se připojí při jejím uzavření).</td></tr>';
        return;
      }
      const rows = lines.map((l) => {
        const d = Number(l.mnozstvi);
        return `<tr data-id="${l.id}"><td><strong>${esc(l.sku)}</strong></td><td>${esc(l.nazev)}</td>${stockCell(l.stav_pred)}${stockCell(l.stav_po)}<td class="num${d < 0 ? ' neg' : ''}">${d > 0 ? '+' : ''}${fmt(d)}</td><td>${esc(l.mj)}</td>${valueCell(l.hodnota, false)}</tr>`;
      });
      rows.push(totalRow(6).replace(/<td><\/td><\/tr>$/, '</tr>'));
      tbody.innerHTML = rows.join('');
      return;
    }
    if (!lines.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="mv-muted">Doklad zatím nemá položky' + (CAN_EDIT ? ' – vyhledejte produkt níže a klikněte na Vložit.' : '.') + '</td></tr>';
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
      const del = CAN_EDIT ? `<button type="button" class="del" data-id="${l.id}" title="Smazat položku včetně odepsaných komponent">${LUCIDE.x}</button>` : '';
      html.push(`<tr data-id="${l.id}"><td><strong>${esc(l.sku)}</strong></td><td>${esc(l.nazev)}</td><td>${rezimCtl}</td><td class="num">${qtyCtl}</td><td>${esc(l.mj)}</td>${stockCell(l.stav_pred)}${stockCell(l.stav_po)}${valueCell(l.hodnota, false)}<td>${del}</td></tr>`);
      const kids = l.children || [];
      kids.forEach((c, i) => {
        const branch = i === kids.length - 1 ? '└──' : '├──';
        html.push(`<tr class="child"><td class="tree">${branch} ${esc(c.sku)}</td><td>${esc(c.nazev)}</td><td class="mv-muted">komponenta</td><td class="num">${fmt(c.mnozstvi)}</td><td>${esc(c.mj)}</td>${stockCell(c.stav_pred)}${stockCell(c.stav_po)}${valueCell(c.hodnota, false)}<td></td></tr>`);
      });
      if (l.rezim === 'vyroba' && !kids.length && Number(l.mnozstvi) !== 0) {
        html.push(`<tr class="child"><td class="tree">└──</td><td colspan="8" class="mv-muted">Produkt nemá v kusovníku žádné skladové komponenty, nic se neodepsalo.</td></tr>`);
      }
    });
    html.push(totalRow(7));
    // Překreslení po autosave nesmí vyhodit uživatele z rozepsaného množství:
    // zapamatovat fokus i aktuálně napsaný text (mohl dopsat během ukládání) a vrátit je.
    const act = document.activeElement;
    const keep = (act && act.matches('input.qty') && tbody.contains(act)) ? { id: act.dataset.id, value: act.value } : null;
    tbody.innerHTML = html.join('');
    if (keep) {
      const inp = tbody.querySelector(`input.qty[data-id="${keep.id}"]`);
      if (inp) {
        inp.focus();
        inp.value = '';
        inp.value = keep.value; // kurzor na konec (type=number neumí setSelectionRange)
      }
    }
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
  if (!CAN_EDIT && !CAN_EDIT_NOTE) return;

  // Hlavička – autosave (u inventárního dokladu jen poznámka, typ se nemění)
  const typSel = document.getElementById('mv-typ');
  const pozn = document.getElementById('mv-poznamka');
  const headStatus = document.getElementById('mv-head-status');
  let headTimer = null;
  async function saveHeader() {
    headStatus.textContent = 'Ukládám…';
    try {
      const typ = typSel ? typSel.value : DOC_TYP;
      await call('/movements/header', { typ, poznamka: pozn.value });
      if (typSel) {
        const badge = document.getElementById('mv-typ-badge');
        badge.textContent = typSel.value === 'vyroba' ? 'Výroba' : 'Korekce';
        badge.className = 'mv-badge ' + typSel.value;
      }
      headStatus.textContent = 'Uloženo ' + new Date().toLocaleTimeString('cs-CZ');
    } catch (e) {
      headStatus.textContent = 'Chyba: ' + e.message;
    }
  }
  if (typSel) typSel.addEventListener('change', saveHeader);
  pozn.addEventListener('input', () => { clearTimeout(headTimer); headTimer = setTimeout(saveHeader, 700); });
  pozn.addEventListener('blur', () => { clearTimeout(headTimer); saveHeader(); });
  if (!CAN_EDIT) return;

  // Živé vyhledávání produktů (bez tlačítka) – max 10 výsledků
  const searchForm = document.getElementById('mv-search');
  const resultsBox = document.getElementById('mv-search-results');
  let searchTimer = null;
  let searchSeq = 0;
  let searchAll = false;
  function renderSearch(data) {
    const items = data.items || [];
    if (!items.length) {
      resultsBox.innerHTML = data.empty ? '' : '<p class="mv-muted">Pro zadané podmínky nejsou žádné produkty.</p>';
      return;
    }
    const rows = items.map((p) => {
      const ns = Number(p.is_nonstock) === 1;
      const inactive = Number(p.aktivni) === 0;
      const title = ns ? 'Neskladová položka – nevyrábí se ani neskladuje, do dokladu ji nelze vložit. Vložte její skladové komponenty.' : (inactive ? 'Neaktivní produkt (lze použít např. pro odpis zbytku).' : '');
      const stav = ns ? '' : `${fmt(p.stav)} ${esc(p.merna_jednotka || '')}`.trim();
      return `<tr class="${ns ? 'nonstock' : ''}${inactive ? ' inactive' : ''}"${title ? ` title="${esc(title)}"` : ''}><td class="sku">${esc(p.sku)}</td><td>${esc(p.typ)}</td><td>${esc(p.nazev)}</td><td class="num">${stav}</td><td><button type="button" class="add" data-sku="${esc(p.sku)}"${ns ? ' disabled' : ''}>Vložit</button></td></tr>`;
    });
    let more = '';
    if (data.more && !searchAll) {
      more = `<p class="mv-search-more">Zobrazeno prvních ${data.limit || items.length} položek, nalezeno více – upřesněte hledání, nebo <a href="#" class="mv-search-all">vypsat vše nalezené</a>.</p>`;
    } else if (data.more) {
      more = `<p class="mv-search-more">Zobrazeno prvních ${data.limit || items.length} položek – upřesněte hledání.</p>`;
    }
    resultsBox.innerHTML = `<table class="mv-products"><thead><tr><th>SKU</th><th>Typ</th><th>Název</th><th class="num">Stav</th><th></th></tr></thead><tbody>${rows.join('')}</tbody></table>${more}`;
  }
  async function runSearch() {
    const params = new URLSearchParams(new FormData(searchForm));
    const seq = ++searchSeq;
    const hasAny = Array.from(params.values()).some((v) => String(v).trim() !== '');
    if (!hasAny) { resultsBox.innerHTML = ''; return; }
    if (searchAll) params.set('all', '1');
    try {
      const res = await fetch('/movements/search?' + params.toString(), { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (seq !== searchSeq) return; // mezitím přišel novější dotaz
      renderSearch(data);
    } catch (_) {
      if (seq === searchSeq) resultsBox.innerHTML = '<p class="mv-err">Vyhledávání selhalo.</p>';
    }
  }
  // Změna podmínek vrací výpis na prvních 10, „vypsat vše" platí jen pro aktuální hledání
  searchForm.addEventListener('input', () => { searchAll = false; clearTimeout(searchTimer); searchTimer = setTimeout(runSearch, 250); });
  searchForm.addEventListener('change', () => { searchAll = false; clearTimeout(searchTimer); runSearch(); });
  resultsBox.addEventListener('click', (ev) => {
    if (!ev.target.closest('a.mv-search-all')) return;
    ev.preventDefault();
    searchAll = true;
    clearTimeout(searchTimer);
    runSearch();
  });

  // Vložení produktu (delegace – výsledky se překreslují)
  resultsBox.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('button.add');
    if (!btn || btn.disabled) return;
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
