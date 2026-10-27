<?php
/** @var array<string,mixed> $report */
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$n0 = static fn($v): string => number_format((float)$v, 0, ',', ' ');
$sez = $report['sezona'];
$tydny = $report['tydny'];
$dnes = date('Y-m-d');
$datum = static fn(string $d): string => date('j. n.', strtotime($d));

// Poslední ukončený týden a právě běžící týden
$ukonceny = null; $bezici = null;
foreach ($tydny as $t) {
    if ($t['skutecnost'] === null) continue;
    if ($t['skutecnost']['probiha']) $bezici = $t; else $ukonceny = $t;
}
// Stav výroby: kumulativně za ukončené týdny proti plánu
$stav = null;
if ($ukonceny) {
    $rozdil = $ukonceny['skutecnost']['vyroba_kum'] - $ukonceny['plan_vyroba_kum'];
    $pomer = $ukonceny['plan_vyroba_kum'] > 0 ? $ukonceny['skutecnost']['vyroba_kum'] / $ukonceny['plan_vyroba_kum'] : 1;
    $stav = ['rozdil' => $rozdil, 'trida' => $pomer >= 1 ? 'ok' : ($pomer >= 0.85 ? 'warn' : 'bad'),
        'text' => $pomer >= 1 ? 'Výroba v plánu' : ($pomer >= 0.85 ? 'Výroba mírně pod plánem' : 'Výroba pod plánem')];
}
$konec = end($tydny);
?>
<style>
.vn { display:flex; flex-direction:column; gap:14px; }
.vn-head h1 { margin:0 0 4px; }
.vn-sub { margin:0; color:var(--c-text-secondary); font-size:.9rem; }
.vn-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:10px; }
.vn-card { background:#fff; border:1px solid var(--c-border); border-radius:var(--radius-m); padding:10px 14px; display:flex; flex-direction:column; gap:2px; }
.vn-card .l { font-size:.78rem; color:var(--c-text-secondary); font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
.vn-card .v { font-size:1.45rem; font-weight:800; font-variant-numeric:tabular-nums; }
.vn-card .s { font-size:.82rem; color:var(--c-text-secondary); font-variant-numeric:tabular-nums; }
.vn-chip { display:inline-block; padding:2px 10px; border-radius:999px; font-weight:800; font-size:.85rem; }
.vn-chip.ok { background:#e8f5e9; color:var(--c-ok); }
.vn-chip.warn { background:#fff4e5; color:#a15c00; }
.vn-chip.bad { background:#ffebee; color:var(--c-danger); }
.vn-chip.info { background:var(--c-surface-2); color:var(--c-text-secondary); }
.vn-panel { background:#fff; border:1px solid var(--c-border); border-radius:var(--radius-m); padding:12px 16px; }
.vn-panel h2 { margin:0 0 2px; font-size:1.05rem; }
.vn-panel .note { margin:0 0 8px; color:var(--c-text-secondary); font-size:.85rem; }
.vn-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(420px, 1fr)); gap:14px; }
.vn-chart { position:relative; }
.vn-chart svg { display:block; width:100%; height:auto; overflow:visible; }
.vn-chart text { font-family:inherit; }
.vn-legend { display:flex; flex-wrap:wrap; gap:4px 16px; font-size:.82rem; color:var(--c-text-secondary); margin-top:4px; }
.vn-legend span { display:inline-flex; align-items:center; gap:6px; }
.vn-legend i { display:inline-block; width:18px; height:0; border-top:2.5px solid; }
.vn-legend i.d { border-top-style:dashed; }
.vn-tip { position:absolute; pointer-events:none; background:#fff; border:1px solid var(--c-border-strong); border-radius:var(--radius-s); box-shadow:var(--shadow-s); padding:6px 9px; font-size:.8rem; white-space:nowrap; z-index:5; font-variant-numeric:tabular-nums; }
.vn-tip b { display:block; margin-bottom:2px; }
.vn-table { width:100%; border-collapse:collapse; font-variant-numeric:tabular-nums; font-size:.88rem; }
.vn-table th, .vn-table td { padding:5px 8px; border-bottom:1px solid var(--c-border); text-align:right; white-space:nowrap; }
.vn-table th:first-child, .vn-table td:first-child { text-align:left; }
.vn-table tr.cur td { background:#fffbe6; }
.vn-table td.neg { color:var(--c-danger); font-weight:700; }
.vn-table td.pos { color:var(--c-ok); font-weight:700; }
.vn-table .muted { color:var(--c-text-muted); }
.vn-wrap { overflow-x:auto; }
.vn-list { margin:4px 0 0; padding-left:18px; color:var(--c-text-secondary); font-size:.85rem; line-height:1.5; }
@media (max-width:560px) { .vn-grid { grid-template-columns:1fr; } }
</style>

<div class="vn">
  <div class="vn-head">
    <h1><?= $e($sez['nazev']) ?></h1>
    <p class="vn-sub">Plán odbytu podle loňských stejných týdnů (<?= (int)$sez['zakladni_rok'] ?>), výroba <?= $n0($sez['vyroba_tydne']) ?> sáčků týdně, aby zásoba na konci zůstala jako na začátku sezóny. Vše v sáčcích (spotřebitelská balení). Data k <?= $e(date('j. n. Y H:i', strtotime($report['vygenerovano']))) ?>.</p>
  </div>

  <div class="vn-cards">
    <div class="vn-card">
      <span class="l">Stav výroby</span>
      <?php if ($stav): ?>
        <span class="v"><span class="vn-chip <?= $stav['trida'] ?>"><?= $e($stav['text']) ?></span></span>
        <span class="s">do <?= $datum($ukonceny['do']) ?>: vyrobeno <?= $n0($ukonceny['skutecnost']['vyroba_kum']) ?> z plánu <?= $n0($ukonceny['plan_vyroba_kum']) ?> (<?= ($stav['rozdil'] >= 0 ? '+' : '−') . $n0(abs($stav['rozdil'])) ?>)</span>
      <?php else: ?>
        <span class="v"><span class="vn-chip info">První týden běží</span></span>
        <span class="s">hodnocení po skončení prvního týdne</span>
      <?php endif; ?>
    </div>
    <?php if ($bezici): ?>
    <div class="vn-card">
      <span class="l">Tento týden (<?= $datum($bezici['od']) ?>–<?= $datum($bezici['do']) ?>)</span>
      <span class="v"><?= $n0($bezici['skutecnost']['vyroba']) ?> <small style="font-size:.9rem;font-weight:600;">/ <?= $n0($bezici['plan_vyroba']) ?></small></span>
      <span class="s">vyrobeno z týdenního cíle · odbyt <?= $n0($bezici['skutecnost']['odbyt_celkem']) ?> z plánu <?= $n0($bezici['plan_odbyt']) ?></span>
    </div>
    <?php endif; ?>
    <div class="vn-card">
      <span class="l">Hotové sáčky skladem</span>
      <span class="v"><?= $n0($report['sklad']['sacky']) ?></span>
      <span class="s">na konci sezóny podle plánu <?= $n0($konec['plan_zasoba']) ?></span>
    </div>
    <div class="vn-card">
      <span class="l">Zbývá do Vánoc</span>
      <span class="v"><?= $n0($report['zbyva']['vyroba']) ?></span>
      <span class="s">sáčků vyrobit · plánovaný odbyt <?= $n0($report['zbyva']['odbyt']) ?></span>
    </div>
    <div class="vn-card">
      <span class="l">Prázdné obaly skladem</span>
      <span class="v"><?= $n0($report['sklad']['obaly']) ?></span>
      <span class="s"><?= $report['sklad']['obaly'] >= $report['zbyva']['vyroba'] ? 'pokryjí' : 'nepokryjí' ?> zbývající výrobu (<?= $n0($report['zbyva']['vyroba']) ?>)</span>
    </div>
  </div>

  <div class="vn-grid">
    <div class="vn-panel">
      <h2>Odbyt a výroba kumulativně</h2>
      <p class="note">Čárkovaně plán, plnou čarou skutečnost. Výroba má být nad odbytem, rozdíl je zásoba navíc.</p>
      <div class="vn-chart" id="graf-kum"></div>
      <div class="vn-legend">
        <span><i style="border-color:var(--c-header)"></i>Odbyt skutečnost</span>
        <span><i class="d" style="border-color:var(--c-header)"></i>Odbyt plán</span>
        <span><i style="border-color:var(--c-ok)"></i>Výroba skutečnost</span>
        <span><i class="d" style="border-color:var(--c-ok)"></i>Výroba plán</span>
      </div>
    </div>
    <div class="vn-panel">
      <h2>Zásoba hotových sáčků</h2>
      <p class="note">Stav na konci týdne. Zásoba nemá klesnout pod červenou čáru (stav na začátku sezóny). Pokud skutečnost klesá pod plán, výroba nestíhá nebo se prodává víc.</p>
      <div class="vn-chart" id="graf-zasoba"></div>
      <div class="vn-legend">
        <span><i style="border-color:var(--c-primary)"></i>Zásoba skutečnost</span>
        <span><i class="d" style="border-color:var(--c-primary)"></i>Zásoba plán</span>
        <span><i class="d" style="border-color:var(--c-danger);border-top-width:1.5px"></i>Cíl: zásoba jako na začátku (<?= $n0($report['cil_zasoba']) ?>)</span>
      </div>
    </div>
  </div>

  <div class="vn-panel">
    <h2>Po týdnech</h2>
    <p class="note">Plán odbytu: maloobchod (grig.cz, grig.sk, WormUP.com) ×<?= $e(str_replace('.', ',', (string)($sez['koeficienty']['grig.cz'] ?? 1))) ?>, velkoobchod gogrig ×<?= $e(str_replace('.', ',', (string)($sez['koeficienty']['gogrig.com'] ?? 1))) ?>, stánky <?= $n0($sez['stanky']['sacku']) ?> sáčků rovnoměrně v týdnech <?= (int)$sez['stanky']['tyden_od'] ?>–<?= (int)$sez['stanky']['tyden_do'] ?>.</p>
    <div class="vn-wrap">
      <table class="vn-table">
        <thead><tr>
          <th>Týden</th><th>Maloobchod</th><th>Velkoobchod</th><th>Stánky</th><th>Odbyt plán</th><th>Odbyt skut.</th>
          <th>Výroba plán</th><th>Výroba skut.</th><th>Zásoba plán</th><th>Zásoba skut.</th>
        </tr></thead>
        <tbody>
        <?php foreach ($tydny as $t): $s = $t['skutecnost']; ?>
          <tr class="<?= $s && $s['probiha'] ? 'cur' : '' ?>">
            <td><?= (int)$t['tyden'] ?> · <?= $datum($t['od']) ?>–<?= $datum($t['do']) ?></td>
            <td><?= $n0($t['plan']['maloobchod']) ?></td>
            <td><?= $n0($t['plan']['velkoobchod']) ?></td>
            <td><?= $n0($t['plan']['stanky']) ?></td>
            <td><b><?= $n0($t['plan_odbyt']) ?></b></td>
            <td><?= $s ? $n0($s['odbyt_celkem']) : '<span class="muted">–</span>' ?></td>
            <td><?= $n0($t['plan_vyroba']) ?></td>
            <?php if ($s): $d = $s['vyroba'] - $t['plan_vyroba']; ?>
              <td class="<?= $s['probiha'] ? '' : ($d >= 0 ? 'pos' : 'neg') ?>"><?= $n0($s['vyroba']) ?></td>
            <?php else: ?><td><span class="muted">–</span></td><?php endif; ?>
            <td><?= $n0($t['plan_zasoba']) ?></td>
            <td class="<?= $s ? ($s['zasoba'] < $t['plan_zasoba'] && !$s['probiha'] ? 'neg' : '') : '' ?>"><?= $s ? $n0($s['zasoba']) : '<span class="muted">–</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="vn-grid">
    <div class="vn-panel">
      <h2>Suroviny do konce sezóny</h2>
      <p class="note">kg natural suroviny (dochucené přepočtené přes kusovník). Potřeba = náplň zbývající plánované výroby (skladba podle plánovaného odbytu) + surovina prodaná po kg. Zásoba hotových sáčků se nečerpá.</p>
      <table class="vn-table">
        <thead><tr><th>Druh</th><th>Skladem</th><th>Dodávky</th><th>Potřeba</th><th>Bilance</th></tr></thead>
        <tbody>
        <?php foreach ($report['suroviny'] as $s): ?>
          <tr>
            <td><?= $e($s['nazev']) ?></td>
            <td><?= $n0($s['sklad']) ?> kg</td>
            <td><?= $s['dodavky'] > 0 ? '+' . $n0($s['dodavky']) . ' kg' : '<span class="muted">–</span>' ?></td>
            <td><?= $n0($s['potreba']) ?> kg</td>
            <td class="<?= $s['bilance'] < 0 ? 'neg' : 'pos' ?>"><?= ($s['bilance'] >= 0 ? '+' : '−') . $n0(abs($s['bilance'])) ?> kg</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($sez['dodavky']): ?>
        <ul class="vn-list">
          <?php foreach ($sez['dodavky'] as $d): ?><li><?= $datum($d['datum']) ?>: <?= $e($d['poznamka']) ?> <?= $n0($d['kg']) ?> kg</li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="vn-panel">
      <h2>Jak se počítá</h2>
      <ul class="vn-list">
        <li>Odbyt = sáčky odeslané podle faktur (DUZP). Kartony a sady se rozpadají přes kusovník, položky mimo katalog podle názvu („15 ks - …“). Etikety, kupony, balné a propagace se nepočítají.</li>
        <li>Plán odbytu = odbyt ve stejném týdnu loni × koeficient e-shopu; stánky rovnoměrně podle odhadu (realistický scénář).</li>
        <li>Výroba = vyrobené hotové sáčky podle výrobních dokladů v Pohybech.</li>
        <li>Zásoba = kladné stavy hotových produktů na konci týdne (inventura + pohyby). Počítá s tím, že se celá zásoba dá prodat.</li>
        <li>Parametry sezóny (koeficienty, cíl výroby, dodávky) jsou v <code>VanoceService::SEZONA</code>.</li>
      </ul>
    </div>
  </div>
</div>

<script>
(function () {
  const data = <?= json_encode($tydny, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const cil = <?= json_encode((float)$report['cil_zasoba']) ?>;
  const NS = 'http://www.w3.org/2000/svg';
  const nf = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 0 });
  const fmt = (v) => nf.format(Math.round(v)).replace('-', '−');
  const dt = (d) => { const x = new Date(d + 'T00:00:00'); return x.getDate() + '. ' + (x.getMonth() + 1) + '.'; };
  const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim() || '#333';

  function el(name, attrs, parent) {
    const n = document.createElementNS(NS, name);
    for (const k in attrs) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function niceMax(v) {
    if (v <= 0) return 1;
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const m = v / p;
    return (m <= 1 ? 1 : m <= 2 ? 2 : m <= 2.5 ? 2.5 : m <= 5 ? 5 : 10) * p;
  }

  // series: [{key, color, dash, values:[...] (null = bez hodnoty)}]
  function chart(box, series, opts) {
    box.innerHTML = '';
    const W = Math.max(320, box.clientWidth), H = 260, mt = 10, mb = 26;
    const all = series.flatMap((s) => s.values.filter((v) => v !== null));
    const hi = niceMax(Math.max(...all, 1)), lo = Math.min(0, ...all);
    const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': opts.label }, box);
    const ticks = []; for (let i = 0; i <= 4; i++) ticks.push(lo + (hi - lo) * i / 4);
    // šířka popisků osy
    const probe = el('text', { 'font-size': 11 }, svg); let ml = 0;
    ticks.forEach((t) => { probe.textContent = fmt(t); ml = Math.max(ml, probe.getComputedTextLength()); });
    probe.remove(); ml += 10;
    const n = data.length;
    const x = (i) => ml + (W - ml - 12) * (n === 1 ? 0.5 : i / (n - 1));
    const y = (v) => mt + (H - mt - mb) * (1 - (v - lo) / (hi - lo));
    ticks.forEach((t) => {
      el('line', { x1: ml, x2: W - 6, y1: y(t), y2: y(t), stroke: css('--c-border'), 'stroke-width': 1 }, svg);
      const tx = el('text', { x: ml - 6, y: y(t) + 4, 'text-anchor': 'end', 'font-size': 11, fill: css('--c-text-muted') }, svg); tx.textContent = fmt(t);
    });
    if (lo < 0) el('line', { x1: ml, x2: W - 6, y1: y(0), y2: y(0), stroke: css('--c-danger'), 'stroke-width': 1 }, svg);
    data.forEach((t, i) => {
      if (n > 6 && i % 2 === 1 && W < 600) return;
      const tx = el('text', { x: x(i), y: H - 8, 'text-anchor': 'middle', 'font-size': 11, fill: css('--c-text-muted') }, svg); tx.textContent = dt(t.od);
    });
    // dnešek
    const dnes = '<?= $dnes ?>';
    const iDnes = data.findIndex((t) => t.od <= dnes && t.do >= dnes);
    if (iDnes >= 0) el('line', { x1: x(iDnes), x2: x(iDnes), y1: mt, y2: H - mb, stroke: css('--c-border-strong'), 'stroke-dasharray': '2 3' }, svg);
    series.forEach((s) => {
      const pts = s.values.map((v, i) => v === null ? null : [x(i), y(v)]);
      let d = ''; pts.forEach((p, i) => { if (!p) return; d += (d && pts[i - 1] ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); });
      if (d) el('path', { d, fill: 'none', stroke: s.color, 'stroke-width': s.thin ? 1.5 : (s.dash ? 2 : 2.5), 'stroke-dasharray': s.thin ? '2 4' : (s.dash ? '6 4' : ''), 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, svg);
      pts.forEach((p) => { if (p && !s.dash) el('circle', { cx: p[0], cy: p[1], r: 3, fill: s.color }, svg); });
    });
    // tooltip
    const tip = document.createElement('div'); tip.className = 'vn-tip'; tip.hidden = true; box.appendChild(tip);
    const hover = el('rect', { x: ml, y: mt, width: W - ml, height: H - mt - mb, fill: 'transparent' }, svg);
    hover.addEventListener('mousemove', (ev) => {
      const r = svg.getBoundingClientRect();
      const px = (ev.clientX - r.left) * (W / r.width);
      let i = Math.round((px - ml) / ((W - ml - 12) / Math.max(1, n - 1))); i = Math.max(0, Math.min(n - 1, i));
      tip.innerHTML = '';
      const b = document.createElement('b'); b.textContent = `Týden ${data[i].tyden} · ${dt(data[i].od)}–${dt(data[i].do)}`; tip.appendChild(b);
      series.forEach((s) => { if (s.values[i] === null) return; const d = document.createElement('div'); d.textContent = `${s.key}: ${fmt(s.values[i])}`; d.style.color = s.color; tip.appendChild(d); });
      tip.hidden = false;
      const bx = box.getBoundingClientRect();
      tip.style.left = Math.min(bx.width - tip.offsetWidth - 4, Math.max(4, ev.clientX - bx.left + 12)) + 'px';
      tip.style.top = Math.max(0, ev.clientY - bx.top - 10) + 'px';
    });
    hover.addEventListener('mouseleave', () => { tip.hidden = true; });
  }

  function draw() {
    const sk = (f) => data.map((t) => t.skutecnost ? t.skutecnost[f] : null);
    chart(document.getElementById('graf-kum'), [
      { key: 'Odbyt plán', color: css('--c-header'), dash: true, values: data.map((t) => t.plan_odbyt_kum) },
      { key: 'Výroba plán', color: css('--c-ok'), dash: true, values: data.map((t) => t.plan_vyroba_kum) },
      { key: 'Odbyt skutečnost', color: css('--c-header'), dash: false, values: sk('odbyt_kum') },
      { key: 'Výroba skutečnost', color: css('--c-ok'), dash: false, values: sk('vyroba_kum') },
    ], { label: 'Odbyt a výroba kumulativně' });
    chart(document.getElementById('graf-zasoba'), [
      { key: 'Zásoba plán', color: css('--c-primary'), dash: true, values: data.map((t) => t.plan_zasoba) },
      { key: 'Zásoba skutečnost', color: css('--c-primary'), dash: false, values: sk('zasoba') },
      { key: 'Cíl (zásoba jako na začátku)', color: css('--c-danger'), dash: true, thin: true, values: data.map(() => cil) },
    ], { label: 'Zásoba hotových sáčků' });
  }
  draw();
  let tm = null; window.addEventListener('resize', () => { clearTimeout(tm); tm = setTimeout(draw, 150); });
})();
</script>
