<h1>Nastavení</h1>

<?php if (!empty($flashError)): ?>
  <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;">
    <?= htmlspecialchars((string)$flashError, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>
<?php if (!empty($flashMessage)): ?>
  <div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;">
    <?= htmlspecialchars((string)$flashMessage, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<h2>Fakturační řady</h2>
<form method="post" action="/settings/series" id="series-form">
  <input type="hidden" name="id" value="" />
  <label>E-shop</label>
  <input type="text" name="eshop_source" required />
  <label>Prefix</label>
  <input type="text" name="prefix" />
  <label>Číslo od</label>
  <input type="text" name="cislo_od" />
  <label>Číslo do</label>
  <input type="text" name="cislo_do" />
  <button type="submit">Uložit</button>
</form>
<table>
  <tr><th>E-shop</th><th>Prefix</th><th>Od</th><th>Do</th><th>Akce</th></tr>
  <?php foreach (($series ?? []) as $s): ?>
  <tr>
    <td><?= htmlspecialchars((string)$s['eshop_source'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['prefix'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['cislo_od'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['cislo_do'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>
      <button type="button"
        class="js-edit-series"
        data-id="<?= (int)$s['id'] ?>"
        data-eshop="<?= htmlspecialchars((string)$s['eshop_source'], ENT_QUOTES, 'UTF-8') ?>"
        data-prefix="<?= htmlspecialchars((string)$s['prefix'], ENT_QUOTES, 'UTF-8') ?>"
        data-cislo-od="<?= htmlspecialchars((string)$s['cislo_od'], ENT_QUOTES, 'UTF-8') ?>"
        data-cislo-do="<?= htmlspecialchars((string)$s['cislo_do'], ENT_QUOTES, 'UTF-8') ?>"
      >Upravit</button>
      <?php if (empty($s['has_imports'])): ?>
        <form method="post" action="/settings/series/delete" style="display:inline;margin-left:8px;">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
          <button type="submit" class="link-danger" title="Smazat e-shop" aria-label="Smazat e-shop">✕</button>
        </form>
      <?php else: ?>
        <span class="muted" title="E-shop má importovaná data, nejde smazat.">nelze smazat</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<script>
(function(){
  const form = document.getElementById('series-form');
  if (!form) return;
  const fields = {
    id: form.querySelector('input[name="id"]'),
    eshop: form.querySelector('input[name="eshop_source"]'),
    prefix: form.querySelector('input[name="prefix"]'),
    from: form.querySelector('input[name="cislo_od"]'),
    to: form.querySelector('input[name="cislo_do"]')
  };
  const setValue = (input, value = '') => { if (input) input.value = value; };
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
  <label>Glob vzor (např. *SHIPPING*)</label>
  <input type="text" name="vzor" required />
  <button type="submit">Přidat</button>
</form>
<ul>
  <?php foreach (($ignores ?? []) as $i): ?>
    <li>
      <span><?= htmlspecialchars((string)$i['vzor'], ENT_QUOTES, 'UTF-8') ?></span>
      <form method="post" action="/settings/ignore/delete" style="display:inline;margin-left:8px;">
        <input type="hidden" name="id" value="<?= (int)$i['id'] ?>" />
        <button type="submit" class="link-danger" title="Odebrat vzor" aria-label="Odebrat vzor">✕</button>
      </form>
    </li>
  <?php endforeach; ?>
</ul>

<h2>Značky produktů</h2>
<form method="post" action="/settings/brand">
  <label>Název značky</label>
  <input type="text" name="nazev" required />
  <button type="submit">Přidat značku</button>
</form>
<table>
  <tr><th>Značka</th><th>Akce</th></tr>
  <?php foreach (($brands ?? []) as $b): ?>
  <tr>
    <td><?= htmlspecialchars((string)$b['nazev'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>
      <?php if ((int)($b['used_count'] ?? 0) === 0): ?>
        <form method="post" action="/settings/brand/delete" style="display:inline;">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>" />
          <button type="submit" class="link-danger" title="Smazat značku" aria-label="Smazat značku">✕</button>
        </form>
      <?php else: ?>
        <span class="muted">nelze smazat (<?= (int)$b['used_count'] ?>)</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h2>Skupiny produktů</h2>
<form method="post" action="/settings/group">
  <label>Název skupiny</label>
  <input type="text" name="nazev" required />
  <button type="submit">Přidat skupinu</button>
</form>
<table>
  <tr><th>Skupina</th><th>Akce</th></tr>
  <?php foreach (($groups ?? []) as $g): ?>
  <tr>
    <td><?= htmlspecialchars((string)$g['nazev'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>
      <?php if ((int)($g['used_count'] ?? 0) === 0): ?>
        <form method="post" action="/settings/group/delete" style="display:inline;">
          <input type="hidden" name="id" value="<?= (int)$g['id'] ?>" />
          <button type="submit" class="link-danger" title="Smazat skupinu" aria-label="Smazat skupinu">✕</button>
        </form>
      <?php else: ?>
        <span class="muted">nelze smazat (<?= (int)$g['used_count'] ?>)</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h2>Měrné jednotky</h2>
<form method="post" action="/settings/unit">
  <label>Kód jednotky (např. ks, kg)</label>
  <input type="text" name="kod" required />
  <button type="submit">Přidat jednotku</button>
</form>
<table>
  <tr><th>Jednotka</th><th>Akce</th></tr>
  <?php foreach (($units ?? []) as $u): ?>
  <tr>
    <td><?= htmlspecialchars((string)$u['kod'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>
      <?php if ((int)($u['used_count'] ?? 0) === 0): ?>
        <form method="post" action="/settings/unit/delete" style="display:inline;">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
          <button type="submit" class="link-danger" title="Smazat jednotku" aria-label="Smazat jednotku">✕</button>
        </form>
      <?php else: ?>
        <span class="muted">nelze smazat (<?= (int)$u['used_count'] ?>)</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h2>Globální nastavení</h2>
<style>
.info-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #eceff1;
  color: #37474f;
  font-size: 0.8rem;
  margin-left: 0.35rem;
  cursor: help;
}
.global-settings-form label {
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.global-settings-form input[type="number"] {
  width: 200px;
  margin: 0.3rem 0 0.8rem;
}
</style>
<form method="post" action="/settings/global" class="global-settings-form">
  <label>
    Počet dní sledování chyb importu XML
    <span class="info-icon" title="Kolik dní zpětně se v importu XML vyhodnocují nenapárované položky.">i</span>
  </label>
  <input type="number" name="okno_pro_prumer_dni" value="<?= (int)($glob['okno_pro_prumer_dni'] ?? 30) ?>" min="1" />

  <label>
    Počet dní pro výpočet průměrné spotřeby
    <span class="info-icon" title="Délka okna pro výpočet průměrného denního odběru (např. 90 dní = 3 měsíce).">i</span>
  </label>
  <input type="number" name="spotreba_prumer_dni" value="<?= (int)($glob['spotreba_prumer_dni'] ?? 90) ?>" min="1" />

  <label>
    Počet dní skladových zásob
    <span class="info-icon" title="Na kolik dní dopředu mají být sklady naplněny (cílový stav hotových produktů).">i</span>
  </label>
  <input type="number" name="zasoba_cil_dni" value="<?= (int)($glob['zasoba_cil_dni'] ?? 30) ?>" min="1" />

  <button type="submit">Uložit</button>
</form>

<?php if (!empty($canManageUsers)): ?>
<h2>Uživatelé (superadmin)</h2>
<p class="muted">Přihlášení probíhá přes Google Workspace. Přidáním e-mailu jej povolíte, odebrání provedete deaktivací účtu. U každého vidíte aktuální roli.</p>
<form method="post" action="/settings/users/save" id="user-form">
  <input type="hidden" name="id" value="" />
  <label>E-mail</label>
  <input type="email" name="email" required />
  <label>Role</label>
  <select name="role">
    <option value="admin">Admin</option>
    <option value="superadmin">Superadmin</option>
    <option value="employee">Zaměstnanec</option>
  </select>
  <label>
    <input type="checkbox" name="active" checked /> Aktivní
  </label>
  <button type="submit">Uložit uživatele</button>
</form>
<table>
  <tr><th>E-mail</th><th>Role</th><th>Stav</th><th>Vytvořen</th><th>Akce</th></tr>
  <?php foreach (($users ?? []) as $user): ?>
  <tr>
    <td><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$user['role'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= (int)$user['active'] ? 'aktivní' : 'blokován' ?></td>
    <td><?= htmlspecialchars((string)$user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>
      <button type="button" class="js-edit-user"
        data-id="<?= (int)$user['id'] ?>"
        data-email="<?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?>"
        data-role="<?= htmlspecialchars((string)$user['role'], ENT_QUOTES, 'UTF-8') ?>"
        data-active="<?= (int)$user['active'] ?>"
      >Upravit</button>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<script>
(function(){
  const form = document.getElementById('user-form');
  if (!form) return;
  const fields = {
    id: form.querySelector('input[name="id"]'),
    email: form.querySelector('input[name="email"]'),
    role: form.querySelector('select[name="role"]'),
    active: form.querySelector('input[name="active"]'),
  };
  document.querySelectorAll('.js-edit-user').forEach((btn) => {
    btn.addEventListener('click', () => {
      fields.id.value = btn.dataset.id || '';
      fields.email.value = btn.dataset.email || '';
      fields.email.readOnly = true;
      fields.role.value = btn.dataset.role || 'admin';
      fields.active.checked = btn.dataset.active === '1';
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
  form.addEventListener('submit', () => {
    fields.email.readOnly = false;
  });
})();
</script>
<?php endif; ?>
