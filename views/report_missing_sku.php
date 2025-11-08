<h1>Chybějící SKU</h1>
<p class="muted">Výpis za posledních <?= (int)($days ?? 30) ?> dní podle globálního nastavení. Zobrazuje unikátní položky bez SKU napříč všemi importy v období (ignor vzory se nadále aplikují).</p>
<?php
  $groupedRows = $grouped ?? [];
  if (empty($groupedRows) && !empty($rows)) {
      foreach ($rows as $row) {
          $groupedRows[$row['eshop_source']][] = $row;
      }
  }
?>
<?php if (empty($groupedRows)): ?>
  <p>Žádné nespárované položky nebyly nalezeny.</p>
<?php else: ?>
  <?php foreach ($groupedRows as $eshop => $items): ?>
    <h3><?= htmlspecialchars((string)$eshop,ENT_QUOTES,'UTF-8') ?></h3>
    <table>
      <tr>
        <th class="help" title="Datum uskutečnění zdanitelného plnění">DUZP</th>
        <th>Doklad</th>
        <th>Název</th>
        <th>Množství</th>
        <th>SKU</th>
        <th>Kód</th>
        <th>EAN</th>
      </tr>
      <?php foreach ($items as $r): ?>
      <tr>
        <td><?= htmlspecialchars((string)$r['duzp'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['nazev'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['mnozstvi'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($r['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['code_raw'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($r['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
<?php endif; ?>
