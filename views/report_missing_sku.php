<h1>Chybějící SKU</h1>
<style>
.status-matched { background:#e6f4ea; }
.status-ignored { background:#fdecea; }
.status-note { font-size:12px; color:#607d8b; display:block; }
.cell-matched { background:#e6f4ea; }
.cell-ignored { background:#fdecea; }
</style>
<p class="muted">Výpis za posledních <?= (int)($days ?? 30) ?> dní podle globálního nastavení. Zobrazuje všechny položky napříč importy a zvýrazňuje spárování (zeleně) či ignorace (červeně).</p>
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
        $highlight = $r['highlight_field'] ?? '';
        $note = $r['status_note'] ?? '';
        $cellClass = function(string $field) use ($highlight,$status) {
            if ($highlight !== $field) return '';
            return $status === 'matched' ? 'cell-matched' : ($status === 'ignored' ? 'cell-ignored' : '');
        };
      ?>
      <tr>
        <td class="<?= $cellClass('duzp') ?>"><?= htmlspecialchars((string)$r['duzp'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('doklad') ?>"><?= htmlspecialchars((string)$r['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('nazev') ?>"><?= htmlspecialchars((string)$r['nazev'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['mnozstvi'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('sku') ?>"><?= htmlspecialchars((string)($r['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('code') ?>"><?= htmlspecialchars((string)$r['code_raw'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($r['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td><?php if ($note !== ''): ?><small class="status-note"><?= htmlspecialchars($note,ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
<?php endif; ?>
