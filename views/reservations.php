<h1>Rezervace</h1>
<style>
.product-search {
  position: relative;
  max-width: 420px;
}
.product-search input[type="text"] {
  width: 100%;
}
.product-search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #ccc;
  z-index: 5;
  max-height: 220px;
  overflow-y: auto;
  display: none;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.product-search-results.visible {
  display: block;
}
.product-search-results button {
  width: 100%;
  padding: 0.4rem 0.6rem;
  border: 0;
  text-align: left;
  background: #fff;
  cursor: pointer;
}
.product-search-results button:hover,
.product-search-results button:focus {
  background: #f1f5f9;
}
.reservation-form label {
  font-weight: 600;
  margin-top: 0.6rem;
  display: block;
}
.reservation-form input[type="text"],
.reservation-form input[type="number"],
.reservation-form input[type="date"],
.reservation-form select {
  width: 280px;
  max-width: 100%;
}
.muted-note {
  color: #607d8b;
  font-size: 0.9rem;
}
</style>
<p class="muted">Rezervace platí do 23:59:59 zvoleného dne. Nejdříve vyhledejte produkt, poté zadejte množství.</p>
<form method="post" action="/reservations" class="reservation-form" id="reservation-form" autocomplete="off">
  <input type="hidden" name="id" value="" />
  <input type="hidden" name="sku" id="reservation-sku" />

  <label>Typ</label>
  <select name="typ" id="reservation-type">
    <?php foreach (($types ?? []) as $t): ?>
      <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"<?= $t === 'produkt' ? ' selected' : '' ?>>
        <?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label>Produkt</label>
  <div class="product-search">
    <input type="text" id="product-search-input" placeholder="Hledejte podle SKU, názvu nebo EAN" autocomplete="off" />
    <div class="product-search-results" id="product-search-results"></div>
    <small class="muted-note" id="product-search-hint"></small>
  </div>

  <label>Množství <span class="muted-note" id="product-unit-label"></span></label>
  <input type="number" step="any" name="mnozstvi" id="reservation-qty" required />

  <label>Platná do</label>
  <input type="date" name="platna_do" required />

  <label>Poznámka</label>
  <input type="text" name="poznamka" />

  <br>
  <button type="submit">Uložit</button>
</form>

<hr>
<table>
  <tr><th>SKU</th><th>Typ</th><th>Množství</th><th>Platná do</th><th>Poznámka</th><th>Akce</th></tr>
  <?php foreach (($rows ?? []) as $r): ?>
  <tr>
    <td><?= htmlspecialchars((string)$r['sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($r['typ'] ?? 'produkt'),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['mnozstvi'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['platna_do'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($r['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td>
      <form method="post" action="/reservations/delete" onsubmit="return confirm('Smazat rezervaci?')" style="display:inline;">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
        <button type="submit">Smazat</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<script>
(function() {
  const form = document.getElementById('reservation-form');
  const skuInput = document.getElementById('reservation-sku');
  const searchInput = document.getElementById('product-search-input');
  const resultsBox = document.getElementById('product-search-results');
  const hint = document.getElementById('product-search-hint');
  const unitLabel = document.getElementById('product-unit-label');
  let debounceTimer = null;

  function hideResults() {
    resultsBox.classList.remove('visible');
    resultsBox.innerHTML = '';
  }

  function selectProduct(item) {
    skuInput.value = item.sku;
    searchInput.value = item.sku + ' – ' + item.nazev;
    hint.textContent = 'Vybráno: ' + item.sku + ' · ' + item.nazev + (item.ean ? ' (EAN ' + item.ean + ')' : '');
    unitLabel.textContent = item.merna_jednotka ? '(MJ: ' + item.merna_jednotka + ')' : '';
    hideResults();
  }

  function renderResults(items) {
    if (!items.length) {
      hideResults();
      return;
    }
    resultsBox.innerHTML = '';
    items.forEach((item) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      const ean = item.ean ? ' · EAN ' + item.ean : '';
      btn.textContent = item.sku + ' – ' + item.nazev + ean;
      btn.addEventListener('click', () => selectProduct(item));
      resultsBox.appendChild(btn);
    });
    resultsBox.classList.add('visible');
  }

  function runSearch() {
    const term = searchInput.value.trim();
    skuInput.value = '';
    hint.textContent = '';
    unitLabel.textContent = '';
    if (term.length < 2) {
      hideResults();
      return;
    }
    const url = new URL('/reservations/search-products', window.location.origin);
    url.searchParams.set('q', term);
    fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
      .then((res) => res.ok ? res.json() : Promise.reject())
      .then((data) => renderResults(data.items || []))
      .catch(() => hideResults());
  }

  searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSearch, 250);
  });

  document.addEventListener('click', (event) => {
    if (!resultsBox.contains(event.target) && event.target !== searchInput) {
      hideResults();
    }
  });

  form.addEventListener('submit', (event) => {
    if (!skuInput.value) {
      event.preventDefault();
      alert('Vyberte produkt ze seznamu.');
      searchInput.focus();
    }
  });
})();
</script>
