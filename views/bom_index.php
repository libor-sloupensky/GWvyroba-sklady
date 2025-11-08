<h1>BOM (karton / sada)</h1>
<div class="csv-help">
  <strong>Popis sloupců (oddělovač ;):</strong>
  <ul>
    <li><code>rodic_sku</code> – finální produkt/karton, pro který definujete vazbu.</li>
    <li><code>potomek_sku</code> – komponenta, kterou rodič obsahuje.</li>
    <li><code>koeficient</code> – množství potomka potřebné na 1 jednotku rodiče.</li>
    <li><code>merna_jednotka_potomka</code> – volitelné; pokud necháte prázdné, převezme se MJ potomka z kmenových produktů.</li>
    <li><code>druh_vazby</code> – <code>karton</code> nebo <code>sada</code>; prázdná hodnota se automaticky odvodí z typu rodiče (karton ⇒ karton, jinak sada).</li>
  </ul>
</div>
<?php if (!empty($error)): ?><div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="notice">
  <strong>Chyby importu:</strong>
  <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>
</div><?php endif; ?>
<p><a href="/bom/export">Stáhnout CSV (aktuální)</a></p>
<form method="post" action="/bom/import" enctype="multipart/form-data">
  <label>Nahrát CSV</label><br>
  <input type="file" name="csv" accept=".csv" required />
  <br>
  <button type="submit">Importovat</button>
  <span class="muted">Tip: používejte UTF‑8; oddělovač středník.</span>
</form>

<hr>
<table>
  <tr>
    <th>Rodič (SKU)</th><th>Potomek (SKU)</th><th>Koeficient</th><th>MJ potomka</th><th>Druh vazby</th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr>
    <td><?= htmlspecialchars((string)$it['rodic_sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['potomek_sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['koeficient'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($it['merna_jednotka_potomka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['druh_vazby'],ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
