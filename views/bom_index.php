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
  content: '\25BC';
  font-size: 1.4rem;
  margin-left: 0.5rem;
  color: #455a64;
}
.csv-help[open] summary::after {
  content: '\25B2';
}
.csv-help-body {
  margin-top: 0.5rem;
}
</style>
<details class="csv-help" id="bom-help">
  <summary>Nápověda – BOM import</summary>
  <div class="csv-help-body">
    <p><strong>Popis sloupců (oddělovač ;):</strong></p>
    <ul>
      <li><code>rodic_sku</code> – finální produkt nebo karton, pro který skládáte recepturu.</li>
      <li><code>potomek_sku</code> – komponenta, která do rodiče vstupuje.</li>
      <li><code>koeficient</code> – množství potomka na 1 jednotku rodiče (ve stejné MJ jako má potomek).</li>
      <li><code>merna_jednotka_potomka</code> – volitelné; pokud ponecháte prázdné, použije se MJ potomka z kmenových produktů.</li>
      <li><code>druh_vazby</code> – <code>karton</code> pouze pro rodiče typu karton; ve všech ostatních případech je vazba vždy <em>sada</em>. Prázdné pole systém dopočítá stejně.</li>
    </ul>
    <p>Desetinné hodnoty zadávejte s tečkou. Každou vazbu lze nahrát kdykoliv – rodič i potomek jen musí existovat v tabulce produktů.</p>
  </div>
</details>
<?php if (!empty()): ?><div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string),ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty()): ?><div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string),ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty()): ?><div class="notice">
  <strong>Chyby importu:</strong>
  <ul><?php foreach ( as ): ?><li><?= htmlspecialchars((string),ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>
</div><?php endif; ?>
<p><a href="/bom/export">Stáhnout CSV (aktuální)</a></p>
<form method="post" action="/bom/import" enctype="multipart/form-data">
  <label>Nahrát CSV</label><br>
  <input type="file" name="csv" accept=".csv" required />
  <br>
  <button type="submit">Importovat</button>
  <span class="muted">Tip: používejte UTF‑8 a středník jako oddělovač.</span>
</form>

<hr>
<table>
  <tr>
    <th>Rodič (SKU)</th><th>Potomek (SKU)</th><th>Koeficient</th><th>MJ potomka</th><th>Druh vazby</th>
  </tr>
  <?php foreach (( ?? []) as ): ?>
  <tr>
    <td><?= htmlspecialchars((string)['rodic_sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)['potomek_sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)['koeficient'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)(['merna_jednotka_potomka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)['druh_vazby'],ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
