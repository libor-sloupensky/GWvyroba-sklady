<h1>Import XML</h1>
<style>
.status-matched { background:#e6f4ea; }
.status-ignored { background:#fdecea; }
.status-note { font-size:12px; color:#607d8b; display:block; }
.cell-matched { background:#e6f4ea; }
.cell-ignored { background:#fdecea; }
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
?>
<p class="muted">Postup: vyberte e-shop a XML soubor. Pokud nejsou nastaven&eacute; &rcaron;ady, import propust&iacute; v&scaron;echny doklady. P&#345;i nesouladu &rcaron;ad se import zastav&iacute; a nic se neulo&#382;&iacute;.</p>
<?php if (!empty($error)): ?><div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
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

<?php if (!empty($viewModes ?? [])): ?>
  <form method="get" class="view-mode-form" style="margin-top:1rem;">
    <label>Zobrazen&iacute;</label>
    <select name="view" onchange="this.form.submit()">
      <?php foreach (($viewModes ?? []) as $key => $label): ?>
        <option value="<?= htmlspecialchars((string)$key,ENT_QUOTES,'UTF-8') ?>"<?= ($viewMode ?? 'unmatched') === $key ? ' selected' : '' ?>><?= htmlspecialchars((string)$label,ENT_QUOTES,'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button type="submit">Zobrazit</button></noscript>
  </form>
<?php endif; ?>

<?php if (($viewMode ?? '') === 'invoices'): ?>
  <hr>
  <h2>Naimportovan&eacute; faktury</h2>
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
<?php endif; ?>

<?php if (!empty($outstandingMissing)): ?>
  <hr>
  <h2>Nesp&aacute;rovan&eacute; polo&#382;ky za posledn&iacute;ch <?= (int)($outstandingDays ?? 30) ?> dn&iacute;</h2>
  <?php foreach (($outstandingMissing ?? []) as $eshopName => $items): if (empty($items)) continue; ?>
    <h3><?= htmlspecialchars((string)$eshopName,ENT_QUOTES,'UTF-8') ?></h3>
    <table>
      <tr><th>DUZP</th><th>Doklad</th><th>SKU</th><th>N&aacute;zev</th><th>Mno&#382;stv&iacute;</th></tr>
      <?php foreach ($items as $item): ?>
      <?php
        $status = $item['status'] ?? 'unmatched';
        $highlight = $item['highlight_field'] ?? '';
        $note = $item['status_note'] ?? '';
        $rowHighlight = ($highlight === 'code') ? ($status === 'matched' ? 'cell-matched' : ($status === 'ignored' ? 'cell-ignored' : '')) : '';
        $cellClass = function(string $field) use ($highlight,$status) {
            if ($highlight !== $field) return '';
            return $status === 'matched' ? 'cell-matched' : ($status === 'ignored' ? 'cell-ignored' : '');
        };
      ?>
      <tr class="<?= $rowHighlight ?>">
        <td class="<?= $cellClass('duzp') ?>"><?= htmlspecialchars((string)$item['duzp'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('doklad') ?>"><?= htmlspecialchars((string)$item['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('sku') ?>"><?= htmlspecialchars((string)($item['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
        <td class="<?= $cellClass('nazev') ?>"><?= htmlspecialchars((string)$item['nazev'],ENT_QUOTES,'UTF-8') ?></td>
        <td><?= htmlspecialchars($formatQty($item['mnozstvi'] ?? 0),ENT_QUOTES,'UTF-8') ?><?php if ($note !== ''): ?><small class="status-note"><?= htmlspecialchars($note,ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
<?php endif; ?>
