<h1>Chybějící SKU</h1>
<p class="muted">Výpis za posledních <?= (int)($days ?? 30) ?> dní podle DUZP. Ignor vzory (glob, case-insensitive) jsou aplikovány na code/sku a takové řádky se nezobrazují.</p>
<table>
  <tr>
    <th class="help" title="Datum uskutečnění zdanitelného plnění">DUZP</th>
    <th>ESHOP</th>
    <th>Doklad</th>
    <th>Název</th>
    <th>Množství</th>
    <th>Kód (code)</th>
  </tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars((string)$r['duzp'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['eshop_source'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['nazev'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['mnozstvi'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['code_raw'],ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

