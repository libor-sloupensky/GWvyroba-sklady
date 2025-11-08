<h1>Chybějící SKU</h1>
<style>
.status-matched { background:#e6f4ea; }
.status-ignored { background:#fdecea; }
.status-note { font-size:12px; color:#607d8b; display:block; }
</style>
<p class="muted">Výpis za posledních <?= (int)($days ?? 30) ?> dní podle globálního nastavení. Zobrazuje všechny položky napříč importy a zvýrazňuje spárované (zeleně) / ignorované (červeně).</p>
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
        <th>Stav</th>
      </tr>
      <?php foreach ($items as $r): ?>
      <?php
        $status = $r['status'] ?? 'unmatched';
        $class = $status === 'matched' ? 'status-matched' : ($status === 'ignored' ? 'status-ignored' : '');
        $note = '';
        if ($status === 'matched') {
            $note = 'Spárováno (SKU)';
        } elseif ($status === 'ignored') {
            $pattern = (string)($r['ignore_pattern'] ?? '');
            $note = $pattern !== '' ? 'Ignorováno dle: ' . $pattern : 'Ignorováno';
        }
      ?>
      <tr class="<?= $class ?>">
        <td><?= htmlspecialchars((string)$r['duzp'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['nazev'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['mnozstvi'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($r['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['code_raw'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($r['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td><?php if ($note !== ''): ?><small class="status-note"><?= htmlspecialchars($note,ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
<?php endif; ?>
