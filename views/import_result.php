<h1>Import XML – výsledek</h1>
<style>
.status-matched { background:#e6f4ea; }
.status-ignored { background:#fdecea; }
.status-note { font-size:12px; color:#607d8b; display:block; }
.cell-matched { background:#e6f4ea; }
.cell-ignored { background:#fdecea; }
</style>
<?php $formatQty = static function ($value): string {
    $num = (float)$value;
    return number_format($num, 0, '', '');
}; ?>
<?php if (!empty($notice)): ?><div class="notice"><?= htmlspecialchars((string)$notice,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<p><strong>Batch:</strong> <?= htmlspecialchars((string)($batch ?? ''),ENT_QUOTES,'UTF-8') ?></p>
<p><strong>Doklady:</strong> <?= (int)($summary['doklady'] ?? 0) ?>, <strong>Položky:</strong> <?= (int)($summary['polozky'] ?? 0) ?></p>
<?php if (!empty($viewModes ?? []) && isset($viewMode)): ?>
  <p class="muted">Zobrazení: <?= htmlspecialchars((string)($viewModes[$viewMode] ?? ''),ENT_QUOTES,'UTF-8') ?></p>
<?php endif; ?>

<h2>Nahrát další XML</h2>
<?php
  $eshopList = $eshops ?? [];
  $hasEshops = !empty($eshopList);
  $selectedEshop = (string)($selectedEshop ?? '');
?>
<form method="post" action="/import/pohoda" enctype="multipart/form-data">
  <label>E-shop (eshop_source)</label><br>
  <?php if ($hasEshops): ?>
    <select name="eshop" required>
      <option value="">-- vyberte --</option>
      <?php foreach ($eshopList as $s): $value = (string)$s['eshop_source']; ?>
        <option value="<?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?>"<?= $selectedEshop === $value ? ' selected' : '' ?>><?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <p class="notice" style="border-color:#ffe0b2;background:#fff8e1;color:#8c6d1f;">Nejprve přidejte e-shop v Nastavení &gt; Fakturační řady.</p>
  <?php endif; ?>
  <br>
  <label>XML soubor</label><br>
  <input type="file" name="xml" accept=".xml" required />
  <br>
  <button type="submit"<?= $hasEshops ? '' : ' disabled' ?>>Importovat</button>
</form>
<hr>

<?php if (!empty($missingSku)): ?>
  <h3>Chybějící SKU (poslední import)</h3>
  <table>
    <tr><th>DUZP</th><th>ESHOP</th><th>Doklad</th><th>SKU</th><th>Název</th><th>Množství</th></tr>
    <?php foreach ($missingSku as $r): ?>
      <tr>
        <td><?= htmlspecialchars((string)$r['duzp'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['eshop_source'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($r['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['nazev'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars($formatQty($r['mnozstvi'] ?? 0),ENT_QUOTES,'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php if (!empty($outstandingMissing)): ?>
  <h3>Nespárované položky za posledních <?= (int)($outstandingDays ?? 30) ?> dní</h3>
  <?php foreach ($outstandingMissing as $eshopName => $items): if (empty($items)) continue; ?>
    <h4><?= htmlspecialchars((string)$eshopName,ENT_QUOTES,'UTF-8') ?></h4>
    <table>
      <tr><th>DUZP</th><th>Doklad</th><th>SKU</th><th>Název</th><th>Množství</th></tr>
      <?php foreach ($items as $item): ?>
      <?php
        $status = $item['status'] ?? 'unmatched';
        $highlight = $item['highlight_field'] ?? '';
        $note = $item['status_note'] ?? '';
        $cellClass = function(string $field) use ($highlight,$status) {
            if ($highlight !== $field) return '';
            return $status === 'matched' ? 'cell-matched' : ($status === 'ignored' ? 'cell-ignored' : '');
        };
      ?>
      <tr>
        <td class="<?= $cellClass('duzp') ?>"><?= htmlspecialchars((string)$item['duzp'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('doklad') ?>"><?= htmlspecialchars((string)$item['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('sku') ?>"><?= htmlspecialchars((string)($item['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('nazev') ?>"><?= htmlspecialchars((string)$item['nazev'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars($formatQty($item['mnozstvi'] ?? 0),ENT_QUOTES,'UTF-8') ?><?php if ($note !== ''): ?><small class="status-note"><?= htmlspecialchars($note,ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
  <p class="muted">Úplný přehled najdete v sekci <a href="/report/missing-sku">Chybějící SKU</a>.</p>
<?php else: ?>
  <p class="muted">Za poslední období nejsou neevidované položky.</p>
<?php endif; ?>
