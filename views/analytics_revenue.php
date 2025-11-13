<?php
/** @var array $myFavorites */
/** @var array $sharedFavorites */
/** @var string $openAiStatus */
/** @var bool $openAiReady */
$upcomingSteps = [
    'Provázat role uživatelů s dostupnými pohledy (finanční, výroba, management).',
    'Rozšířit audit logy – ukládat každý prompt + SQL + výsledky.',
    'Doplnit možnost vypnout sdílení konkrétního oblíbeného promptu.',
];
?>
<h1>Analýza (AI)</h1>
<p class="muted">Dotazník umožňuje zadat přirozený jazyk, AI připraví SQL SELECT dotazy, spustí je nad databází a vrátí text, tabulky nebo spojnicové grafy.</p>

<style>
.analysis-layout { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:1.2rem; }
.analysis-panel { border:1px solid #e0e0e0; border-radius:10px; padding:1rem; background:#fff; }
.analysis-panel h2 { margin-top:0; }
.analysis-form label { display:block; font-weight:600; margin-bottom:0.4rem; }
.analysis-form textarea { width:100%; min-height:170px; border:1px solid #cfd8dc; border-radius:6px; padding:0.75rem; resize:vertical; font-family:inherit; }
.analysis-form input[type="text"] { width:100%; border:1px solid #cfd8dc; border-radius:6px; padding:0.5rem 0.65rem; }
.analysis-form button.primary { padding:0.55rem 1.3rem; border:none; border-radius:6px; background:#1e88e5; color:#fff; cursor:pointer; font-size:1rem; }
.analysis-form button.primary:disabled { background:#90caf9; cursor:not-allowed; }
.title-row { display:flex; gap:0.4rem; align-items:center; }
.star-toggle { border:1px solid #ffca28; color:#ffca28; background:#fff8e1; padding:0.45rem 0.6rem; border-radius:6px; cursor:pointer; font-size:1.1rem; }
.star-toggle.active { background:#ffca28; color:#4e342e; }
.analysis-guidelines { margin:0.8rem 0; background:#f4f6f8; border-radius:8px; padding:0.7rem 0.9rem; font-size:0.95rem; }
.analysis-guidelines ul { margin:0.4rem 0 0 1.2rem; }
.analysis-note { margin-top:0.6rem; font-size:0.9rem; color:#546e7a; }
.analysis-status { margin-top:0.5rem; font-size:0.95rem; }
.analysis-status.ready { color:#2e7d32; }
.analysis-status.warn { color:#c62828; }
#analysis-results { margin-top:1rem; border-top:1px solid #e0e0e0; padding-top:1rem; }
.analysis-text { font-size:1rem; margin-bottom:0.8rem; }
.analysis-output { border:1px solid #e3e8ee; border-radius:8px; padding:0.8rem; margin-bottom:0.8rem; background:#fafbfc; }
.analysis-output h4 { margin:0 0 0.5rem 0; }
.analysis-output table { width:100%; border-collapse:collapse; }
.analysis-output th, .analysis-output td { border-bottom:1px solid #e0e0e0; padding:0.35rem 0.4rem; text-align:left; font-size:0.92rem; }
.analysis-output canvas { width:100%; max-height:320px; }
.favorite-list { list-style:none; padding:0; margin:0; }
.favorite-list li { border:1px solid #eceff1; border-radius:8px; padding:0.65rem 0.8rem; margin-bottom:0.6rem; display:flex; justify-content:space-between; gap:0.6rem; }
.favorite-title { font-weight:600; }
.favorite-actions button { background:none; border:0; cursor:pointer; font-size:0.95rem; color:#1e88e5; }
.favorite-empty { font-size:0.9rem; color:#78909c; }
.info-block { border-left:4px solid #90a4ae; padding-left:0.8rem; margin-top:1rem; color:#455a64; }
.todo-list { list-style:disc; margin:0.4rem 0 0 1.2rem; color:#37474f; }
.error-banner { background:#ffebee; border:1px solid #ffcdd2; color:#c62828; padding:0.6rem 0.8rem; border-radius:6px; margin-top:0.7rem; }
.loader { display:inline-block; width:16px; height:16px; border:2px solid #bbdefb; border-top-color:#1e88e5; border-radius:50%; animation:spin 0.8s linear infinite; margin-left:0.4rem; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width: 960px) {
  .analysis-layout { grid-template-columns:1fr; }
}
</style>

<div class="analysis-layout">
  <section class="analysis-panel">
    <h2>AI dotaz</h2>
    <form id="analysis-form" class="analysis-form" action="javascript:void(0);">
      <label for="prompt-title">Název promptu (pro uložení)</label>
      <div class="title-row">
        <input type="text" id="prompt-title" placeholder="Např. Top objednávky">
        <button type="button" id="prompt-star" class="star-toggle" title="Označit jako oblíbené">☆</button>
      </div>
      <label for="prompt">Znění dotazu</label>
      <textarea id="prompt" placeholder="Popište, jaká data chcete, jaké období platí a v jaké formě výsledek zobrazit."></textarea>
      <div class="analysis-guidelines">
        <strong>Co by měl prompt obsahovat:</strong>
        <ul>
          <li>cíl dotazu (metrika, text, tabulka nebo graf),</li>
          <li>filtry / období / e-shop, kterých se analýza týká,</li>
          <li>preferovanou formu výstupu (text, tabulka, spojnicový graf nebo kombinaci).</li>
        </ul>
      </div>
      <button type="submit" id="analysis-submit" class="primary" <?= $openAiReady ? '' : 'disabled' ?>><?= $openAiReady ? 'Odeslat dotaz' : 'OpenAI není připraveno' ?></button>
      <div id="analysis-status" class="analysis-status <?= $openAiReady ? 'ready' : 'warn' ?>"><?= htmlspecialchars($openAiStatus, ENT_QUOTES, 'UTF-8') ?></div>
      <div id="analysis-error" class="error-banner" style="display:none;"></div>
    </form>

    <div id="analysis-results">
      <div class="analysis-text" id="analysis-text"></div>
      <div id="analysis-outputs"></div>
    </div>

    <div class="info-block">
      <strong>Další kroky:</strong>
      <ul class="todo-list">
        <?php foreach ($upcomingSteps as $item): ?>
          <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="analysis-panel">
    <h2>Oblíbené prompty</h2>
    <p class="muted">Prompt se uloží pouze tehdy, když při odeslání svítí hvězdička. Historie všech dotazů se nevede. Kliknutím na cizí prompt se obsah jen načte do formuláře – teprve poté jej můžete uložit jako vlastní.</p>

    <h3>Moje</h3>
    <ul class="favorite-list" id="favorite-mine">
      <?php if (empty($myFavorites)): ?>
        <li class="favorite-empty">Zatím nemáte žádné oblíbené prompty.</li>
      <?php else: ?>
        <?php foreach ($myFavorites as $fav): ?>
          <li data-id="<?= (int)$fav['id'] ?>">
            <div>
              <span class="favorite-title"><?= htmlspecialchars($fav['title'], ENT_QUOTES, 'UTF-8') ?></span>
              <p class="muted"><?= nl2br(htmlspecialchars(mb_strimwidth($fav['prompt'], 0, 160, '…'), ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
            <div class="favorite-actions">
              <button type="button" data-id="<?= (int)$fav['id'] ?>" data-prompt="mine">Načíst</button>
            </div>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>

    <h3>Inspirace ostatních</h3>
    <ul class="favorite-list" id="favorite-shared">
      <?php if (empty($sharedFavorites)): ?>
        <li class="favorite-empty">Zatím nejsou žádné sdílené prompty.</li>
      <?php else: ?>
        <?php foreach ($sharedFavorites as $fav): ?>
          <li data-id="<?= (int)$fav['id'] ?>">
            <div>
              <span class="favorite-title"><?= htmlspecialchars($fav['title'], ENT_QUOTES, 'UTF-8') ?></span>
              <p class="muted"><?= nl2br(htmlspecialchars(mb_strimwidth($fav['prompt'], 0, 160, '…'), ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
            <div class="favorite-actions">
              <button type="button" data-id="<?= (int)$fav['id'] ?>" data-prompt="shared">Načíst</button>
            </div>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  const state = {
    star: false,
    submitting: false,
    favorites: {
      mine: <?= json_encode($myFavorites, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
      shared: <?= json_encode($sharedFavorites, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
    }
  };
  const form = document.getElementById('analysis-form');
  const promptInput = document.getElementById('prompt');
  const titleInput = document.getElementById('prompt-title');
  const starBtn = document.getElementById('prompt-star');
  const submitBtn = document.getElementById('analysis-submit');
  const statusBox = document.getElementById('analysis-status');
  const errorBox = document.getElementById('analysis-error');
  const textBox = document.getElementById('analysis-text');
  const outputsBox = document.getElementById('analysis-outputs');
  const mineList = document.getElementById('favorite-mine');
  const sharedList = document.getElementById('favorite-shared');
  const apiReady = <?= $openAiReady ? 'true' : 'false' ?>;

  starBtn.addEventListener('click', () => {
    state.star = !state.star;
    starBtn.classList.toggle('active', state.star);
    starBtn.textContent = state.star ? '★' : '☆';
  });

  function renderFavorites() {
    const renderList = (items, container) => {
      container.innerHTML = '';
      if (!items.length) {
        const li = document.createElement('li');
        li.className = 'favorite-empty';
        li.textContent = 'Zatím nic uloženého.';
        container.appendChild(li);
        return;
      }
      items.forEach((fav) => {
        const li = document.createElement('li');
        li.dataset.id = fav.id;
        const wrap = document.createElement('div');
        const title = document.createElement('span');
        title.className = 'favorite-title';
        title.textContent = fav.title;
        const excerpt = document.createElement('p');
        excerpt.className = 'muted';
        excerpt.textContent = fav.prompt.slice(0, 160) + (fav.prompt.length > 160 ? '…' : '');
        wrap.appendChild(title);
        wrap.appendChild(excerpt);
        const actions = document.createElement('div');
        actions.className = 'favorite-actions';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Načíst';
        btn.addEventListener('click', () => loadFavorite(fav));
        actions.appendChild(btn);
        li.appendChild(wrap);
        li.appendChild(actions);
        container.appendChild(li);
      });
    };
    renderList(state.favorites.mine || [], mineList);
    renderList(state.favorites.shared || [], sharedList);
  }

  function loadFavorite(fav) {
    titleInput.value = fav.title;
    promptInput.value = fav.prompt;
    if (state.star) {
      state.star = false;
      starBtn.classList.remove('active');
      starBtn.textContent = '☆';
    }
    promptInput.focus();
  }

  function renderError(message) {
    if (!message) {
      errorBox.style.display = 'none';
      errorBox.textContent = '';
      return;
    }
    errorBox.style.display = 'block';
    errorBox.textContent = message;
  }

  function renderOutputs(data) {
    textBox.textContent = data.explanation || '';
    outputsBox.innerHTML = '';
    (data.outputs || []).forEach((item, index) => {
      const box = document.createElement('div');
      box.className = 'analysis-output';
      const title = document.createElement('h4');
      title.textContent = item.title || (item.type === 'line_chart' ? 'Graf' : 'Výsledky');
      box.appendChild(title);
      if (item.type === 'error') {
        const p = document.createElement('p');
        p.className = 'muted';
        p.textContent = item.message || 'Dotaz nelze zobrazit.';
        box.appendChild(p);
      } else if (item.type === 'line_chart') {
        const canvas = document.createElement('canvas');
        canvas.id = 'analysis-chart-' + index;
        box.appendChild(canvas);
        outputsBox.appendChild(box);
        renderChart(canvas, item);
        return;
      } else {
        const table = document.createElement('table');
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        const columns = item.columns && item.columns.length ? item.columns : deriveColumns(item.rows);
        columns.forEach((col) => {
          const th = document.createElement('th');
          th.textContent = col.label || col.key;
          headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);
        const tbody = document.createElement('tbody');
        (item.rows || []).forEach((row) => {
          const tr = document.createElement('tr');
          columns.forEach((col) => {
            const td = document.createElement('td');
            const key = col.key || col;
            td.textContent = row[key] ?? '';
            tr.appendChild(td);
          });
          tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        box.appendChild(table);
      }
      outputsBox.appendChild(box);
    });
  }

  function deriveColumns(rows) {
    if (!rows || !rows.length) {
      return [{ key: 'info', label: 'Hodnota' }];
    }
    return Object.keys(rows[0]).map((key) => ({ key, label: key }));
  }

  function renderChart(canvas, item) {
    if (!window.Chart) {
      const p = document.createElement('p');
      p.textContent = 'Nelze zobrazit graf (chybí Chart.js).';
      canvas.replaceWith(p);
      return;
    }
    const rows = item.rows || [];
    const xKey = item.xColumn || Object.keys(rows[0] || {})[0] || 'label';
    const yKey = item.yColumn || Object.keys(rows[0] || {})[1] || 'value';
    const labels = rows.map((row) => row[xKey]);
    const data = rows.map((row) => Number(row[yKey]) || 0);
    new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: item.seriesLabel || yKey,
          data,
          borderColor: '#1e88e5',
          backgroundColor: 'rgba(30,136,229,0.2)',
          tension: 0.2,
          fill: true,
        }]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: false } }
      }
    });
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!apiReady || state.submitting) {
      return;
    }
    const prompt = promptInput.value.trim();
    if (!prompt) {
      renderError('Zadejte prosím text dotazu.');
      promptInput.focus();
      return;
    }
    renderError('');
    state.submitting = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Odesílám...';
    const body = {
      prompt,
      title: titleInput.value.trim(),
      saveFavorite: state.star && titleInput.value.trim() !== ''
    };
    fetch('/analytics/ai', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.ok) {
          throw new Error(data.error || 'Neznámá chyba');
        }
        renderOutputs(data);
        if (data.favorites) {
          state.favorites = data.favorites;
          renderFavorites();
        }
        if (state.star && body.saveFavorite) {
          state.star = false;
          starBtn.classList.remove('active');
          starBtn.textContent = '☆';
        }
      })
      .catch((err) => {
        renderError(err.message || 'Dotaz nelze zpracovat.');
      })
      .finally(() => {
        state.submitting = false;
        submitBtn.disabled = !apiReady;
        submitBtn.textContent = apiReady ? 'Odeslat dotaz' : 'OpenAI není připraveno';
      });
  });

  renderFavorites();
})();
</script>
