<h1>NastavenĂ­</h1>

<h2>FakturaÄŤnĂ­ Ĺ™ady</h2>
<form method="post" action="/settings/series" id="series-form">
  <input type="hidden" name="id" value="" />
  <label>Eâ€‘shop</label><input type="text" name="eshop_source" required />
  <label>Prefix</label><input type="text" name="prefix" />
  <label>ÄŚĂ­slo od</label><input type="text" name="cislo_od" />
  <label>ÄŚĂ­slo do</label><input type="text" name="cislo_do" />
  <button type="submit">UloĹľit</button>
</form>
<table>
  <tr><th>Eâ€‘shop</th><th>Prefix</th><th>Od</th><th>Do</th></tr>
  <?php foreach (($series ?? []) as $s): ?>
  <tr><td><?= htmlspecialchars((string)$s['eshop_source'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$s['prefix'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$s['cislo_od'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$s['cislo_do'],ENT_QUOTES,'UTF-8') ?></td></tr>
  <?php endforeach; ?>
  </table>

<h2>IgnorovanĂ© poloĹľky</h2>
<form method="post" action="/settings/ignore">
  <label>Glob vzor (napĹ™. *SHIPPING*)</label><input type="text" name="vzor" required />
  <button type="submit">PĹ™idat</button>
</form>
<ul>
  <?php foreach (($ignores ?? []) as $i): ?>
    <li><?= htmlspecialchars((string)$i['vzor'],ENT_QUOTES,'UTF-8') ?></li>
  <?php endforeach; ?>
</ul>

<h2>GlobĂˇlnĂ­ nastavenĂ­</h2>
<form method="post" action="/settings/global">
  <label>Okno prĹŻmÄ›ru (dnĂ­)</label><input type="number" name="okno_pro_prumer_dni" value="<?= (int)($glob['okno_pro_prumer_dni'] ?? 30) ?>" />
  <label>MÄ›na</label><input type="text" name="mena_zakladni" value="<?= htmlspecialchars((string)($glob['mena_zakladni'] ?? 'CZK'),ENT_QUOTES,'UTF-8') ?>" />
  <label>ZaokrouhlenĂ­</label><input type="text" name="zaokrouhleni" value="<?= htmlspecialchars((string)($glob['zaokrouhleni'] ?? 'half_up'),ENT_QUOTES,'UTF-8') ?>" />
  <label>Timezone</label><input type="text" name="timezone" value="<?= htmlspecialchars((string)($glob['timezone'] ?? 'Europe/Prague'),ENT_QUOTES,'UTF-8') ?>" />
  <button type="submit">UloĹľit</button>
  <span class="muted">PouĹľĂ­vej UTFâ€‘8; importy mimo UTFâ€‘8 pĹ™evĂˇdÄ›j na UTFâ€‘8.</span>
</form>
