<h1>Import XML &ndash; v&yacute;sledek</h1>
<style>
.status-matched { background:#e6f4ea; }
.status-ignored { background:#fdecea; }
.status-note { font-size:12px; color:#607d8b; display:block; }
.cell-matched { background:#e6f4ea; }
.cell-ignored { background:#fdecea; }
.summary-ok { color:#2e7d32; font-weight:600; }
.invoice-table { border-collapse:collapse; width:100%; margin-top:0.5rem; }
.invoice-table th, .invoice-table td { padding:6px 8px; border-bottom:1px solid #e0e0e0; text-align:left; }
.invoice-table th { background:#fafafa; }
.invoice-actions { width:1%; white-space:nowrap; text-align:right; }
.invoice-delete { background:transparent; border:1px solid #d32f2f; color:#d32f2f; border-radius:4px; padding:0 6px; cursor:pointer; }
.invoice-delete:hover { background:#fdecea; }
</style>
<?php
$formatQty = static function ($value): string {
    $num = (float)$value;
    return number_format($num, 0, '', '');
};
$formatCzk = static function ($value): string {
    $num = is_numeric($value) ? (float)$value : 0.0;
    return number_format($num, 2, ',', ' ');
};
$currentView = $viewMode ?? 'unmatched';
?>
<?php if (!empty($notice)): ?><div class="notice"><?= htmlspecialchars((string)$notice,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<p class="summary-ok"><strong>Importovan&eacute; doklady:</strong> <?= (int)($summary['doklady'] ?? 0) ?>, <strong>Polo&#382;ky:</strong> <?= (int)($summary['polozky'] ?? 0) ?></p>
<?php if (!empty($skipped ?? [])): ?>
  <div class="error" style="margin:0.5rem 0; color:#b71c1c;">
    <strong>P&#345;esko&#269;en&eacute; doklady (chyba):</strong>
    <ul>
      <?php foreach ($skipped as $s): ?>
        <li><?= htmlspecialchars((string)($s['cislo_dokladu'] ?? ''),ENT_QUOTES,'UTF-8') ?> &ndash; <?= htmlspecialchars((string)($s['duvod'] ?? ''),ENT_QUOTES,'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<h2>Nahr&aacute;t dal&scaron;&iacute; XML</h2>
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
    <p class="notice" style="border-color:#ffe0b2;background:#fff8e1;color:#8c6d1f;">Nejprve p&#345;idejte e-shop v Nastaven&iacute; &gt; Faktura&#269;n&iacute; &#345;ady.</p>
  <?php endif; ?>
  <br>
  <label>XML soubor</label><br>
  <input type="file" name="xml" accept=".xml" required />
  <br>
  <button type="submit"<?= $hasEshops ? '' : ' disabled' ?>>Importovat</button>
</form>
<hr>

<?php if (!empty($missingSku)): ?>
  <h3>Chyb&#283;j&iacute;c&iacute; SKU (posledn&iacute; import)</h3>
  <table>
    <tr><th>DUZP</th><th>ESHOP</th><th>Doklad</th><th>SKU</th><th>N&aacute;zev</th><th>Mno&#382;stv&iacute;</th></tr>
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

<?php if (!empty($viewModes ?? [])): ?>
  <div style="margin:0.4rem 0;">
    <label for="view-select" class="muted">Zobrazen&iacute;:
      <select id="view-select" onchange="window.location.href='/import?view='+encodeURIComponent(this.value);">
        <?php foreach ($viewModes as $key => $label): ?>
          <option value="<?= htmlspecialchars((string)$key,ENT_QUOTES,'UTF-8') ?>"<?= $currentView === $key ? ' selected' : '' ?>><?= htmlspecialchars((string)$label,ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
<?php endif; ?>

<?php if ($currentView === 'invoices'): ?>
  <h3>Naimportovan&eacute; faktury</h3>
  <?php if (empty($invoiceRows ?? [])): ?>
    <p class="muted">&Zcaron;&aacute;dn&eacute; faktury.</p>
  <?php else: ?>
    <table class="invoice-table">
      <tr>
        <th>E-shop</th>
        <th>Datum</th>
        <th>&Ccaron;&iacute;slo faktury</th>
        <th>&Ccaron;&aacute;stka (K&#269;)</th>
        <th class="invoice-actions"></th>
      </tr>
      <?php foreach (($invoiceRows ?? []) as $row): ?>
        <tr>
          <td><?= htmlspecialchars((string)($row['eshop_source'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)($row['duzp'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)($row['cislo_dokladu'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($formatCzk($row['castka_czk'] ?? 0),ENT_QUOTES,'UTF-8') ?> K&#269;</td>
          <td class="invoice-actions">
            <form method="post" action="/import/delete-invoice" onsubmit="return confirm('Opravdu smazat fakturu?');">
              <input type="hidden" name="eshop" value="<?= htmlspecialchars((string)($row['eshop_source'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
              <input type="hidden" name="cislo_dokladu" value="<?= htmlspecialchars((string)($row['cislo_dokladu'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
              <button type="submit" class="invoice-delete" title="Smazat fakturu">&times;</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php elseif (!empty($outstandingMissing)): ?>
  <h3>Nesp&aacute;rovan&eacute; polo&#382;ky za posledn&iacute;ch <?= (int)($outstandingDays ?? 30) ?> dn&iacute;</h3>
  <?php foreach ($outstandingMissing as $eshopName => $items): if (empty($items)) continue; ?>
    <h4><?= htmlspecialchars((string)$eshopName,ENT_QUOTES,'UTF-8') ?></h4>
    <table>
      <tr><th>DUZP</th><th>Doklad</th><th>SKU</th><th>N&aacute;zev</th><th>Mno&#382;stv&iacute;</th></tr>
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
  <p class="muted">&Uacute;pln&yacute; p&#345;ehled najdete v sekci <a href="/report/missing-sku">Chyb&#283;j&iacute;c&iacute; SKU</a>.</p>
<?php else: ?>
  <p class="muted">Za posledn&iacute; obdob&iacute; nejsou neevidovan&eacute; polo&#382;ky.</p>
<?php endif; ?>
