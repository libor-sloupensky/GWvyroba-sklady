<h1>BOM (karton / sada)</h1>
<style>
.csv-help {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 0.75rem;
  margin: 0 0 1rem;
}
.csv-help summary {
  cursor: pointer;
  font-weight: bold;
  list-style: none;
}
.csv-help summary::-webkit-details-marker {
  display: none;
}
.csv-help summary::after {
  content: ' ?';
  font-weight: normal;
}
.csv-help[open] summary::after {
  content: ' ?';
}
.csv-help-body {
  margin-top: 0.5rem;
}
</style>
<details class="csv-help" id="bom-help">
  <summary>Nápovìda – BOM import</summary>
  <div class="csv-help-body">
    <p><strong>Popis sloupcù (oddìlovaè ;):</strong></p>
    <ul>
      <li><code>rodic_sku</code> – finální produkt nebo karton, pro který skládáte recepturu.</li>
      <li><code>potomek_sku</code> – komponenta, která do rodièe vstupuje.</li>
      <li><code>koeficient</code> – množství potomka na 1 jednotku rodièe (ve stejné MJ jako má potomek).</li>
      <li><code>merna_jednotka_potomka</code> – volitelné; pokud ponecháte prázdné, vezme se MJ potomka z kmenových produktù.</li>
      <li><code>druh_vazby</code> – <code>karton</code> pouze pro rodièe typu karton; ve všech ostatních pøípadech je vazba vždy <em>sada</em>. Prázdné pole systém doplní stejnì.</li>
    </ul>
    <p>Desetinné hodnoty zadávejte s teèkou. Každou vazbu lze nahrát kdykoliv – rodiè i potomek jen musí existovat v tabulce produktù.</p>
  </div>
</details>
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
  <span class="muted">Tip: používejte UTF-8 a støedník.</span>
</form>

<hr>
<table>
  <tr>
    <th>Rodiè (SKU)</th><th>Potomek (SKU)</th><th>Koeficient</th><th>MJ potomka</th><th>Druh vazby</th>
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
