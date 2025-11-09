<h1>Výroba – návrhy</h1>
<p class="muted">Náhled. Finální výpočet doplňte dle MASTER PROMPT (forecast, rezervace, dostupnost, návrh, krok/min. dávka).</p>

<style>
.production-form {
  display:flex;
  gap:0.4rem;
  flex-wrap:wrap;
  align-items:center;
}
.production-form input[type="number"] {
  width:120px;
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
  width:92%;
  max-width:520px;
  box-shadow:0 12px 28px rgba(0,0,0,0.25);
}
.production-modal h3 { margin-top:0; }
.production-modal ul { margin:0.5rem 0 0 1.1rem; padding:0; }
.production-modal-buttons {
  display:flex;
  flex-wrap:wrap;
  gap:0.5rem;
  margin-top:1rem;
}
.production-modal-buttons button {
  flex:1 1 auto;
  padding:0.5rem 0.8rem;
}
.production-modal small { color:#607d8b; display:block; margin-top:0.4rem; }
</style>

<table>
  <tr>
    <th>SKU</th>
    <th>Název</th>
    <th>Min. zásoba</th>
    <th>Min. dávka</th>
    <th>Krok výroby</th>
    <th>Výrobní doba (dny)</th>
    <th>Akce</th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr>
    <td><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?></td>
    <td>
      <form method="post" action="/production/produce" class="production-form" data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>">
        <input type="hidden" name="sku" value="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>" />
        <input type="hidden" name="modus" value="odecti_subpotomky" />
        <input type="number" step="any" name="mnozstvi" placeholder="množství" required />
        <button type="submit">Zapsat množství</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<div class="production-modal-overlay" id="production-modal">
  <div class="production-modal">
    <h3>Nedostatek komponent</h3>
    <p>Odečtení komponent by vytvořilo záporný stav. Vyberte, jak pokračovat:</p>
    <ul id="production-deficit-list"></ul>
    <small>„Odečíst subpotomky (doporučené)“ odečte komponenty i do mínusu. „Odečíst do mínusu“ zapíše pouze korekci pro ruční řešení.</small>
    <div class="production-modal-buttons">
      <button type="button" data-action="components">Odečíst subpotomky (doporučené)</button>
      <button type="button" data-action="minus">Odečíst do mínusu</button>
      <button type="button" data-action="cancel">Zrušit</button>
    </div>
  </div>
</div>

<script>
(function(){
  const forms = document.querySelectorAll('.production-form');
  const overlay = document.getElementById('production-modal');
  const listEl = document.getElementById('production-deficit-list');
  let pendingForm = null;

  forms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const qtyField = form.querySelector('input[name="mnozstvi"]');
      const qty = parseFloat((qtyField.value || '').replace(',', '.'));
      if (!qty || qty <= 0) {
        alert('Zadejte množství.');
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
        .catch((err) => alert('Nelze ověřit komponenty: ' + (err.message || err)));
    });
  });

  overlay.querySelectorAll('button[data-action]').forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.dataset.action;
      if (!pendingForm) { closeModal(); return; }
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
      li.textContent = `${name} | potřeba ${item.required}, k dispozici ${item.available}, chybí ${item.missing}`;
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
})();
</script>
