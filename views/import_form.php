<h1>Import Pohoda XML</h1>
<p class="muted">Postup: vyberte e‑shop a XML (Stormware Pohoda). Pokud nejsou nastavené řady, import propustí všechny doklady. Při nesouladu řad import zastaví a nic neuloží.</p>
<?php if (!empty($error)): ?><div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<form method="post" action="/import/pohoda" enctype="multipart/form-data">
  <label>E‑shop (eshop_source)</label><br>
  <input type="text" name="eshop" placeholder="ESHOP1" required />
  <br>
  <label>XML soubor (Pohoda)</label><br>
  <input type="file" name="xml" accept=".xml" required />
  <br>
  <button type="submit">Importovat</button>
</form>

<hr>
<h2>Smazat poslední import</h2>
<form method="post" action="/import/delete-last">
  <label>E‑shop</label><br>
  <input type="text" name="eshop" placeholder="ESHOP1" required />
  <br>
  <button type="submit">Smazat poslední import</button>
</form>

