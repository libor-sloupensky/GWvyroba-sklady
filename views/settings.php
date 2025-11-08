<h1>Nastavení</h1>

<h2>Fakturační řady</h2>
<form method="post" action="/settings/series">
  <input type="hidden" name="id" value="" />
  <label>E‑shop</label><input type="text" name="eshop_source" required />
  <label>Prefix</label><input type="text" name="prefix" required />
  <label>Číslo od</label><input type="text" name="cislo_od" required />
  <label>Číslo do</label><input type="text" name="cislo_do" required />
  <button type="submit">Uložit</button>
</form>
<table>
  <tr><th>E‑shop</th><th>Prefix</th><th>Od</th><th>Do</th></tr>
  <?php foreach (($series ?? []) as $s): ?>
  <tr><td><?= htmlspecialchars((string)$s['eshop_source'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$s['prefix'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$s['cislo_od'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$s['cislo_do'],ENT_QUOTES,'UTF-8') ?></td></tr>
  <?php endforeach; ?>
  </table>

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

