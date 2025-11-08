<h1>Nastavení</h1>

<h2>Fakturační řady</h2>
<form method="post" action="/settings/series" id="series-form">
  <input type="hidden" name="id" value="" />
  <label>E‑shop</label><input type="text" name="eshop_source" required />
  <label>Prefix</label><input type="text" name="prefix" />
  <label>Číslo od</label><input type="text" name="cislo_od" />
  <label>Číslo do</label><input type="text" name="cislo_do" />
  <button type="submit">Uložit</button>
  <button type="button" id="series-form-clear">Nový záznam</button>
</form>
<table>
  <tr><th>E‑shop</th><th>Prefix</th><th>Od</th><th>Do</th><th>Akce</th></tr>
  <?php foreach (($series ?? []) as $s): ?>
  <tr>
    <td><?= htmlspecialchars((string)$s['eshop_source'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['prefix'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['cislo_od'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['cislo_do'],ENT_QUOTES,'UTF-8') ?></td>
    <td>
      <button type="button"
        class="js-edit-series"
        data-id="<?= (int)$s['id'] ?>"
        data-eshop="<?= htmlspecialchars((string)$s['eshop_source'],ENT_QUOTES,'UTF-8') ?>"
        data-prefix="<?= htmlspecialchars((string)$s['prefix'],ENT_QUOTES,'UTF-8') ?>"
        data-cislo-od="<?= htmlspecialchars((string)$s['cislo_od'],ENT_QUOTES,'UTF-8') ?>"
        data-cislo-do="<?= htmlspecialchars((string)$s['cislo_do'],ENT_QUOTES,'UTF-8') ?>"
      >Upravit</button>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<script>
(function () {
  const form = document.getElementById('series-form');
  if (!form) return;
  const fields = {
    id: form.querySelector('input[name="id"]'),
    eshop: form.querySelector('input[name="eshop_source"]'),
    prefix: form.querySelector('input[name="prefix"]'),
    from: form.querySelector('input[name="cislo_od"]'),
    to: form.querySelector('input[name="cislo_do"]')
  };
  const setValue = (input, value = '') => { if (input) { input.value = value; } };
  const clearForm = () => {
    setValue(fields.id);
    setValue(fields.eshop);
    setValue(fields.prefix);
    setValue(fields.from);
    setValue(fields.to);
    fields.eshop?.focus();
  };
  document.getElementById('series-form-clear')?.addEventListener('click', clearForm);
  document.querySelectorAll('.js-edit-series').forEach((btn) => {
    btn.addEventListener('click', () => {
      setValue(fields.id, btn.dataset.id || '');
      setValue(fields.eshop, btn.dataset.eshop || '');
      setValue(fields.prefix, btn.dataset.prefix || '');
      setValue(fields.from, btn.dataset.cisloOd || '');
      setValue(fields.to, btn.dataset.cisloDo || '');
      fields.eshop?.focus();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();
</script>

<h2>Ignorované položky</h2>
<form method="post" action="/settings/ignore">
  <label>Glob vzor (např. *SHIPPING*)</label><input type="text" name="vzor" required />
  <button type="submit">Přidat</button>
</form>
<ul>
  <?php foreach (($ignores ?? []) as $i): ?>
    <li><?= htmlspecialchars((string)$i['vzor'],ENT_QUOTES,'UTF-8') ?></li>
  <?php endforeach; ?>
</ul>

<h2>Globální nastavení</h2>
<form method="post" action="/settings/global">
  <label>Okno průměru (dní)</label><input type="number" name="okno_pro_prumer_dni" value="<?= (int)($glob['okno_pro_prumer_dni'] ?? 30) ?>" />
  <label>Měna</label><input type="text" name="mena_zakladni" value="<?= htmlspecialchars((string)($glob['mena_zakladni'] ?? 'CZK'),ENT_QUOTES,'UTF-8') ?>" />
  <label>Zaokrouhlení</label><input type="text" name="zaokrouhleni" value="<?= htmlspecialchars((string)($glob['zaokrouhleni'] ?? 'half_up'),ENT_QUOTES,'UTF-8') ?>" />
  <label>Timezone</label><input type="text" name="timezone" value="<?= htmlspecialchars((string)($glob['timezone'] ?? 'Europe/Prague'),ENT_QUOTES,'UTF-8') ?>" />
  <button type="submit">Uložit</button>
  <span class="muted">Používej UTF‑8; importy mimo UTF‑8 převáděj na UTF‑8.</span>
</form>
