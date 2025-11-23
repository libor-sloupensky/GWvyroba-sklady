<?php
/** @var string $title */
/** @var array $templates */
?>
<h1>Analýza v2 (katalog dotazů)</h1>

<p>Vyberte šablonu dotazu, upravte parametry a spusťte. Výstup se zobrazí jako tabulka; přepínání na graf doplníme později.</p>

<style>
.v2-form { display:flex; flex-direction:column; gap:0.6rem; max-width:520px; }
.v2-form label { font-weight:600; display:block; margin-bottom:0.15rem; }
.v2-form input, .v2-form select { width:100%; padding:0.4rem 0.5rem; }
.result-table { width:100%; border-collapse:collapse; margin-top:1rem; }
.result-table th, .result-table td { border:1px solid #e0e0e0; padding:0.35rem 0.4rem; text-align:left; }
.notice { padding:0.6rem 0.8rem; border:1px solid #e0e0e0; border-radius:8px; background:#f7f9fc; }
.muted { color:#607d8b; }
.error { color:#c62828; font-weight:600; }
</style>

<form id="v2-form" class="v2-form" action="javascript:void(0);">
  <div>
    <label for="template-id">Šablona</label>
    <select id="template-id" name="template_id">
      <?php foreach ($templates as $id => $tpl): ?>
        <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($tpl['title'], ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p class="muted" id="template-desc"></p>
  </div>
  <div id="param-fields"></div>
  <button type="submit">Spustit dotaz</button>
  <div id="v2-error" class="error" style="display:none;"></div>
</form>

<div id="v2-result"></div>

<script>
(() => {
  const templates = <?= json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const form = document.getElementById('v2-form');
  const select = document.getElementById('template-id');
  const paramBox = document.getElementById('param-fields');
  const descBox = document.getElementById('template-desc');
  const errorBox = document.getElementById('v2-error');
  const resultBox = document.getElementById('v2-result');

  function renderParams() {
    const tpl = templates[select.value];
    if (!tpl) return;
    descBox.textContent = tpl.description || '';
    paramBox.innerHTML = '';
    (tpl.params || []).forEach((p) => {
      const wrap = document.createElement('div');
      const label = document.createElement('label');
      label.textContent = p.name;
      wrap.appendChild(label);
      let input;
      if (p.type === 'enum') {
        input = document.createElement('select');
        const emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = '(neuvedeno)';
        input.appendChild(emptyOpt);
        (p.values || []).forEach((val) => {
          const opt = document.createElement('option');
          opt.value = val;
          opt.textContent = val;
          input.appendChild(opt);
        });
      } else {
        input = document.createElement('input');
        input.type = p.type === 'date' ? 'date' : 'text';
        if (p.default) input.value = p.default;
      }
      input.name = p.name;
      wrap.appendChild(input);
      paramBox.appendChild(wrap);
    });
  }

  function renderTable(rows) {
    if (!rows || !rows.length) {
      resultBox.innerHTML = '<p class="muted">Žádná data.</p>';
      return;
    }
    const cols = Object.keys(rows[0]);
    const table = document.createElement('table');
    table.className = 'result-table';
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');
    cols.forEach((c) => {
      const th = document.createElement('th');
      th.textContent = c;
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    rows.forEach((r) => {
      const tr = document.createElement('tr');
      cols.forEach((c) => {
        const td = document.createElement('td');
        td.textContent = r[c] ?? '';
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    resultBox.innerHTML = '';
    resultBox.appendChild(table);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';
    const tpl = templates[select.value];
    if (!tpl) return;
    const formData = new FormData(form);
    const params = {};
    (tpl.params || []).forEach((p) => {
      params[p.name] = formData.get(p.name) ?? '';
    });
    try {
      const res = await fetch('/analytics/v2/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ template_id: select.value, params })
      });
      const data = await res.json();
      if (!data.ok) {
        throw new Error(data.error || 'Dotaz selhal.');
      }
      renderTable(data.rows || []);
    } catch (err) {
      errorBox.style.display = 'block';
      errorBox.textContent = err.message || 'Dotaz selhal.';
    }
  });

  select.addEventListener('change', renderParams);
  renderParams();
})();
</script>
