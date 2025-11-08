<h1>Produkty</h1>
<div class="csv-help">
  <strong>Popis sloupců:</strong>
  <ul>
    <li><code>sku</code> – povinný interní kód produktu, podle kterého se řádky párují/aktualizují.</li>
    <li><code>ean</code> – volitelný EAN / čárový kód.</li>
    <li><code>znacka</code> – volitelná značka výrobce, musí být nejprve definována v Nastavení.</li>
    <li><code>skupina</code> – volitelná skupina produktů definovaná v Nastavení.</li>
    <li><code>typ</code> – povinný typ: <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>baleni</code>, <code>karton</code>.</li>
    <li><code>merna_jednotka</code> – povinná měrná jednotka (např. <code>ks</code>, <code>kg</code>).</li>
    <li><code>nazev</code> – povinný název položky.</li>
    <li><code>min_zasoba</code>, <code>min_davka</code>, <code>krok_vyroby</code>, <code>vyrobni_doba_dni</code> – volitelné číselné hodnoty (pokud je systém vyžaduje, uveďte je).</li>
    <li><code>aktivni</code> – povinné 0/1.</li>
    <li><code>poznamka</code> – libovolná textová poznámka.</li>
  </ul>
</div>
<?php if (!empty($error)): ?><div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="notice">
  <strong>Chyby importu:</strong>
  <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>
</div><?php endif; ?>
<p><a href="/products/export">Stáhnout CSV (aktuální)</a></p>
<form method="post" action="/products/import" enctype="multipart/form-data">
  <label>Nahrát CSV</label><br>
  <input type="file" name="csv" accept=".csv" required />
  <br>
  <button type="submit">Importovat</button>
  <span class="muted">Tip: používejte UTF‑8. Nepovinná pole ponechte prázdná.</span>
</form>

<hr>
<table>
  <tr>
    <th class="help" title="Primární klíč pro párování">SKU</th>
    <th>EAN</th>
    <th>Značka</th>
    <th>Skupina</th>
    <th class="help" title="Typ položky: produkt/obal/etiketa/surovina/baleni/karton">Typ</th>
    <th class="help" title="Měrná jednotka (ks, kg)">MJ</th>
    <th>Název</th>
    <th class="help" title="Minimální zásoba">Min</th>
    <th class="help" title="Minimální dávka výroby">Min dávka</th>
    <th class="help" title="Zaokrouhlení návrhu na tento krok">Krok</th>
    <th class="help" title="Výrobní doba v dnech">Výrob.doba</th>
    <th>Aktivní</th>
    <th>Poznámka</th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr>
    <td><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($it['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($it['znacka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($it['skupina'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= (int)$it['aktivni'] ? '✔' : '✖' ?></td>
    <td><?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
