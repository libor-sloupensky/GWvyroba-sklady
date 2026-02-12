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

<style>
.settings-toggle { cursor:pointer; user-select:none; }
.settings-toggle:hover { color:#1565c0; }
.settings-toggle .tri { display:inline-block; width:1em; font-size:0.8em; }
</style>

<h2 class="settings-toggle"><span class="tri">▸</span> E-shopy a fakturační řady</h2>
<div class="settings-section" style="display:none;">
<style>
.series-form fieldset { border:1px solid #cfd8dc; border-radius:6px; padding:0.75rem 1rem; margin-bottom:0.75rem; }
.series-form legend { font-weight:600; font-size:0.95rem; padding:0 0.4rem; }
.series-form .field-row { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin-bottom:0.5rem; }
.series-form .field-row > div { display:flex; flex-direction:column; }
.series-form .field-row label { font-size:0.85rem; color:#455a64; margin-bottom:2px; }
.series-form .field-row input { min-width:120px; }
.series-form .field-row input[name="eshop_source"] { min-width:180px; }
.series-form .field-row input[name="admin_url"] { min-width:240px; }
.series-form .field-row input[name="admin_email"] { min-width:200px; }
.series-badge { display:inline-block; padding:1px 6px; border-radius:3px; font-size:0.8rem; }
.series-badge-ok { background:#e6f4ea; color:#1b5e20; }
.series-badge-no { background:#eceff1; color:#78909c; }
</style>
<form method="post" action="/settings/series" id="series-form" class="series-form">
  <input type="hidden" name="id" value="" />
  <fieldset>
    <legend>Fakturacni rada</legend>
    <div class="field-row">
      <div><label>E-shop</label><input type="text" name="eshop_source" required /></div>
      <div><label>Prefix</label><input type="text" name="prefix" /></div>
      <div><label>Cislo od</label><input type="text" name="cislo_od" /></div>
      <div><label>Cislo do</label><input type="text" name="cislo_do" /></div>
    </div>
  </fieldset>
  <fieldset>
    <legend>Auto-import (prihlaseni do adminu)</legend>
    <div class="field-row">
      <div><label>Admin URL</label><input type="url" name="admin_url" placeholder="https://www.example.com" /></div>
      <div><label>E-mail</label><input type="email" name="admin_email" placeholder="admin@example.com" /></div>
      <div><label>Heslo</label><input type="password" name="admin_password" placeholder="nechte prazdne = beze zmeny" autocomplete="new-password" /></div>
    </div>
    <p class="muted" style="margin:0.25rem 0 0;font-size:0.82rem;">Heslo je ulozeno sifrovane. Pri editaci nechte prazdne, pokud nechcete menit.</p>
  </fieldset>
  <button type="submit">Ulozit e-shop</button>
</form>
<?php
  $cfg = include __DIR__ . '/../config/config.php';
  $cronToken = (string)($cfg['cron_token'] ?? '');
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
  $cronUrl = $scheme . '://' . $host . '/cron.php' . ($cronToken !== '' ? '?token=' . urlencode($cronToken) : '');
?>
<div style="margin-top:0.75rem;padding:0.75rem 1rem;background:#e3f2fd;border:1px solid #90caf9;border-radius:6px;font-size:0.85rem;">
  <strong>Automaticky import (CRON)</strong><br>
  <span style="color:#455a64;">
    Pro automaticke stahovani faktur ze Shoptetu nastavte v hostingu (Webglobe: HOSTING &rarr; WEB &rarr; CRON)
    pravidelne spousteni nasledujici adresy. Doporuceny interval je kazdych 15&ndash;30 minut.
    Vzdy se zpracuje pouze jeden e-shop na jedno spusteni, takze nehrozí pretizeni serveru.
  </span>
  <div style="margin-top:0.5rem;padding:0.4rem 0.6rem;background:#fff;border:1px solid #bbdefb;border-radius:4px;font-family:monospace;word-break:break-all;user-select:all;cursor:text;">
    <?= htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8') ?>
  </div>
  <span style="color:#78909c;font-size:0.8rem;">Kliknete do pole a zkopirujte celou adresu.</span>
</div>
<table>
  <tr><th>E-shop</th><th>Prefix</th><th>Od</th><th>Do</th><th>Auto-import</th><th>Akce</th></tr>
  <?php foreach (($series ?? []) as $s): ?>
  <?php $hasCredentials = !empty($s['admin_url']) && !empty($s['admin_email']) && !empty($s['admin_password_enc']); ?>
  <tr>
    <td><?= htmlspecialchars((string)$s['eshop_source'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['prefix'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['cislo_od'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$s['cislo_do'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>
      <?php if ($hasCredentials): ?>
        <span class="series-badge series-badge-ok" title="Prihlasovaci udaje nastaveny">aktivni</span>
      <?php else: ?>
        <span class="series-badge series-badge-no" title="Prihlasovaci udaje chybi" style="background:#ffebee;color:#c62828;">neaktivni</span>
      <?php endif; ?>
    </td>
    <td>
      <button type="button"
        class="js-edit-series"
        data-id="<?= (int)$s['id'] ?>"
        data-eshop="<?= htmlspecialchars((string)$s['eshop_source'], ENT_QUOTES, 'UTF-8') ?>"
        data-prefix="<?= htmlspecialchars((string)$s['prefix'], ENT_QUOTES, 'UTF-8') ?>"
        data-cislo-od="<?= htmlspecialchars((string)$s['cislo_od'], ENT_QUOTES, 'UTF-8') ?>"
        data-cislo-do="<?= htmlspecialchars((string)$s['cislo_do'], ENT_QUOTES, 'UTF-8') ?>"
        data-admin-url="<?= htmlspecialchars((string)($s['admin_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        data-admin-email="<?= htmlspecialchars((string)($s['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        data-has-password="<?= !empty($s['admin_password_enc']) ? '1' : '0' ?>"
      >Upravit</button>
      <?php if (empty($s['has_imports'])): ?>
        <form method="post" action="/settings/series/delete" style="display:inline;margin-left:8px;">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
          <button type="submit" class="link-danger" title="Smazat e-shop" aria-label="Smazat e-shop">&#10005;</button>
        </form>
      <?php else: ?>
        <span class="muted" title="E-shop ma importovana data, nejde smazat.">nelze smazat</span>
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
    to: form.querySelector('input[name="cislo_do"]'),
    adminUrl: form.querySelector('input[name="admin_url"]'),
    adminEmail: form.querySelector('input[name="admin_email"]'),
    adminPassword: form.querySelector('input[name="admin_password"]'),
  };
  const setValue = (input, value = '') => { if (input) input.value = value; };
  document.querySelectorAll('.js-edit-series').forEach((btn) => {
    btn.addEventListener('click', () => {
      setValue(fields.id, btn.dataset.id || '');
      setValue(fields.eshop, btn.dataset.eshop || '');
      setValue(fields.prefix, btn.dataset.prefix || '');
      setValue(fields.from, btn.dataset.cisloOd || '');
      setValue(fields.to, btn.dataset.cisloDo || '');
      setValue(fields.adminUrl, btn.dataset.adminUrl || '');
      setValue(fields.adminEmail, btn.dataset.adminEmail || '');
      setValue(fields.adminPassword, '');
      if (fields.adminPassword && btn.dataset.hasPassword === '1') {
        fields.adminPassword.placeholder = '*** (ulozeno, nechte prazdne)';
      } else if (fields.adminPassword) {
        fields.adminPassword.placeholder = 'nechte prazdne = beze zmeny';
      }
      fields.eshop?.focus();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();
</script>
</div>

<h2 class="settings-toggle"><span class="tri">▸</span> Ignorované položky</h2>
<div class="settings-section" style="display:none;">
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
</div>

<h2 class="settings-toggle"><span class="tri">▸</span> Značky produktů</h2>
<div class="settings-section" style="display:none;">
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
</div>

<h2 class="settings-toggle"><span class="tri">▸</span> Skupiny produktů</h2>
<div class="settings-section" style="display:none;">
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
</div>

<h2 class="settings-toggle"><span class="tri">▸</span> Typy produktů</h2>
<div class="settings-section" style="display:none;">
<form method="post" action="/settings/type" id="product-type-form">
  <input type="hidden" name="id" value="" />
  <label>Kód typu (bez diakritiky)</label>
  <input type="text" name="code" required />
  <label>Název typu</label>
  <input type="text" name="name" required />
  <label>
    <input type="checkbox" name="is_nonstock" />
    Neskladová sada
    <span class="info-icon" title="Neskladová sada: neodepisuje se sama, ale odepisují se její potomci.">i</span>
  </label>
  <button type="submit">Uložit typ</button>
</form>
<table>
  <tr><th>Kód</th><th>Název</th><th>Chování</th><th>Použití</th><th>Akce</th></tr>
  <?php foreach (($types ?? []) as $t): ?>
  <?php $usedTotal = (int)($t['used_products'] ?? 0) + (int)($t['used_reservations'] ?? 0); ?>
  <tr>
    <td><?= htmlspecialchars((string)$t['code'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= ((int)($t['is_nonstock'] ?? 0) === 1) ? 'neskladová sada' : 'skladová položka' ?></td>
    <td><?= $usedTotal ?></td>
    <td>
      <button type="button" class="js-edit-type"
        data-id="<?= (int)$t['id'] ?>"
        data-code="<?= htmlspecialchars((string)$t['code'], ENT_QUOTES, 'UTF-8') ?>"
        data-name="<?= htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8') ?>"
        data-nonstock="<?= (int)$t['is_nonstock'] ?>"
      >Upravit</button>
      <?php if ($usedTotal === 0): ?>
        <form method="post" action="/settings/type/delete" style="display:inline;margin-left:8px;">
          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>" />
          <button type="submit" class="link-danger" title="Smazat typ" aria-label="Smazat typ">✕</button>
        </form>
      <?php else: ?>
        <span class="muted">nelze smazat (<?= $usedTotal ?>)</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<script>
(function(){
  const form = document.getElementById('product-type-form');
  if (!form) return;
  const fields = {
    id: form.querySelector('input[name="id"]'),
    code: form.querySelector('input[name="code"]'),
    name: form.querySelector('input[name="name"]'),
    nonstock: form.querySelector('input[name="is_nonstock"]'),
  };
  document.querySelectorAll('.js-edit-type').forEach((btn) => {
    btn.addEventListener('click', () => {
      fields.id.value = btn.dataset.id || '';
      fields.code.value = btn.dataset.code || '';
      fields.code.readOnly = true;
      fields.name.value = btn.dataset.name || '';
      fields.nonstock.checked = btn.dataset.nonstock === '1';
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      fields.name.focus();
    });
  });
  form.addEventListener('submit', () => {
    fields.code.readOnly = false;
  });
})();
</script>
</div>

<h2 class="settings-toggle"><span class="tri">▸</span> Měrné jednotky</h2>
<div class="settings-section" style="display:none;">
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
</div>

<h2 class="settings-toggle"><span class="tri">▸</span> Globální nastavení</h2>
<div class="settings-section" style="display:none;">
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
.global-setting-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
</style>
<form method="post" action="/settings/global" class="global-settings-form">
  <label>
    Počet dní sledování chyb importu XML
    <span class="info-icon" title="Kolik dní zpětně se v importu XML vyhodnocují nenapárované položky.">i</span>
  </label>
  <div class="global-setting-row">
    <input type="number" name="okno_pro_prumer_dni" value="<?= (int)($glob['okno_pro_prumer_dni'] ?? 30) ?>" min="1" />
    <button type="submit">Uložit</button>
  </div>

  <label>
    Počet dní pro výpočet průměrné spotřeby
    <span class="info-icon" title="Délka okna pro výpočet průměrného denního odběru (např. 90 dní = 3 měsíce).">i</span>
  </label>
  <div class="global-setting-row">
    <input type="number" name="spotreba_prumer_dni" value="<?= (int)($glob['spotreba_prumer_dni'] ?? 90) ?>" min="1" />
    <button type="submit">Uložit</button>
  </div>

  <label>
    Počet dní skladových zásob
    <span class="info-icon" title="Na kolik dní dopředu mají být sklady naplněny (cílový stav hotových produktů).">i</span>
  </label>
  <div class="global-setting-row">
    <input type="number" name="zasoba_cil_dni" value="<?= (int)($glob['zasoba_cil_dni'] ?? 30) ?>" min="1" />
    <button type="submit">Uložit</button>
  </div>
</form>
</div>

<?php if (!empty($canManageUsers)): ?>
<h2 class="settings-toggle"><span class="tri">▸</span> Uživatelé (superadmin)</h2>
<div class="settings-section" style="display:none;">
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
</div>
<?php endif; ?>

<script>
(function(){
  document.querySelectorAll('.settings-toggle').forEach(function(h2) {
    h2.addEventListener('click', function() {
      var body = this.nextElementSibling;
      var tri = this.querySelector('.tri');
      if (!body) return;
      if (body.style.display === 'none') {
        body.style.display = '';
        tri.textContent = '\u25BE';
      } else {
        body.style.display = 'none';
        tri.textContent = '\u25B8';
      }
    });
  });
})();
</script>
