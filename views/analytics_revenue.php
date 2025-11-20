<?php
/** @var array $myFavorites */
/** @var array $sharedFavorites */
/** @var string $openAiStatus */
/** @var bool $openAiReady */
?>
<h1>Analýza (AI)</h1>
<div class="analysis-guidelines">
  <strong>Tipy pro zadání:</strong>
  <ul>
    <li>Co chcete vidět (metrika, tabulka nebo graf).</li>
    <li>Období/filtry (např. poslední měsíc, konkrétní e-shop).</li>
    <li>Preferovanou formu výstupu (text, tabulka, graf).</li>
  </ul>
</div>

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
.star-toggle { border:1px solid #ffca28; color:#ffca28; background:#fff8e1; padding:0.45rem 0.6rem; border-radius:6px; cursor:pointer; font-size:1.05rem; }
.star-toggle.active { background:#ffca28; color:#4e342e; }
.analysis-guidelines { margin:0.8rem 0; background:#f4f6f8; border-radius:8px; padding:0.7rem 0.9rem; font-size:0.95rem; }
.analysis-guidelines ul { margin:0.4rem 0 0 1.2rem; }
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
      <label for="prompt-title">Název (uložíte až po ověření výsledku)</label>
      <div class="title-row">
        <input type="text" id="prompt-title" placeholder="Např. Top objednávky">
        <button type="button" id="prompt-star" class="star-toggle" title="Uložit jako oblíbené" disabled>☆</button>
      </div>
      <label for="prompt">Znění dotazu</label>
      <textarea id="prompt" placeholder="Popište, co chcete zjistit, za jaké období a v jaké formě výstup zobrazit (text/tabulka/graf)."></textarea>
      <button type="submit" id="analysis-submit" class="primary" <?= $openAiReady ? '' : 'disabled' ?>><?= $openAiReady ? 'Odeslat dotaz' : 'OpenAI není připraveno' ?></button>
      <div id="analysis-status" class="analysis-status <?= $openAiReady ? 'ready' : 'warn' ?>"><?= htmlspecialchars($openAiStatus, ENT_QUOTES, 'UTF-8') ?></div>
      <div id="analysis-error" class="error-banner" style="display:none;"></div>
    </form>

    <div id="analysis-results">
      <div class="analysis-text" id="analysis-text"></div>
      <div id="analysis-outputs"></div>
    </div>

  </section>

  <section class="analysis-panel">
    <h2>Oblíbené prompty</h2>
    <!-- Hláška o ukládání odstraněna na přání -->

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
    starReady: false,
    submitting: false,
    lastPrompt: '',
    lastTitle: '',
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
    if (!state.starReady) {
      renderError('Nejprve odešlete dotaz a zobrazte výsledek.');
      return;
    }
    const title = titleInput.value.trim();
    const prompt = state.lastPrompt.trim();
    if (!prompt) {
      renderError('Nejprve odešlete dotaz.');
      return;
    }
    if (!title) {
      renderError('Doplňte název promptu pro uložení.');
      titleInput.focus();
      return;
    }
    renderError('');
    starBtn.disabled = true;
    starBtn.textContent = 'Ukládám...';
    fetch('/analytics/favorite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, prompt })
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.ok) { throw new Error(data.error || 'Uložení selhalo.'); }
        state.favorites = data.favorites;
        renderFavorites();
        starBtn.classList.add('active');
        starBtn.textContent = '★ Uloženo';
      })
      .catch((err) => {
        renderError(err.message || 'Uložení selhalo.');
      })
      .finally(() => {
        starBtn.disabled = false;
      });
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
        const text = fav.prompt || '';
        excerpt.textContent = text.slice(0, 160) + (text.length > 160 ? '…' : '');
        wrap.appendChild(title);
        wrap.appendChild(excerpt);
        const actions = document.createElement('div');
        actions.className = 'favorite-actions';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Načíst';
        btn.addEventListener('click', () => loadFavorite(fav));
        actions.appendChild(btn);
        if (container === mineList) {
          const del = document.createElement('button');
          del.type = 'button';
          del.textContent = 'Smazat';
          del.addEventListener('click', () => deleteFavorite(fav.id));
          actions.appendChild(del);
        }
        li.appendChild(wrap);
        li.appendChild(actions);
        container.appendChild(li);
      });
    };
    renderList(state.favorites.mine || [], mineList);
    renderList(state.favorites.shared || [], sharedList);
  }

  function loadFavorite(fav) {
    promptInput.value = fav.prompt || '';
    titleInput.value = fav.title || '';
    textBox.textContent = '';
    outputsBox.innerHTML = '';
    state.starReady = false;
    starBtn.classList.remove('active');
    starBtn.disabled = true;
    starBtn.textContent = '☆';
  }

  function deleteFavorite(id) {
    if (!id) return;
    fetch('/analytics/favorite/delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.ok) { throw new Error(data.error || 'Smazání selhalo.'); }
        state.favorites = data.favorites;
        renderFavorites();
      })
      .catch((err) => {
        renderError(err.message || 'Smazání selhalo.');
      });
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
    state.starReady = false;
    starBtn.disabled = true;
    starBtn.classList.remove('active');
    starBtn.textContent = '☆';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Odesílám...';
    const body = { prompt, title: titleInput.value.trim(), saveFavorite: false };
    fetch('/analytics/ai', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.ok) { throw new Error(data.error || 'Neznámá chyba'); }
        renderOutputs(data);
        if (data.favorites) {
          state.favorites = data.favorites;
          renderFavorites();
        }
        state.lastPrompt = prompt;
        state.lastTitle = titleInput.value.trim();
        state.starReady = true;
        starBtn.disabled = false;
        starBtn.textContent = '☆ Uložit';
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
