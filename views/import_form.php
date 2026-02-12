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
.invoice-toggle { /* používá globální .row-toggle z _layout.php */ }
.invoice-detail-row { display:none; }
.invoice-detail-row.expanded { display:table-row; }
.invoice-detail { padding:1rem; background:#f9f9f9; }
.invoice-header-info { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; margin-bottom:1rem; padding:0.75rem; background:#fff; border-radius:4px; border:1px solid #e0e0e0; }
.invoice-header-field { font-size:13px; }
.invoice-header-field label { font-weight:600; color:#455a64; display:block; margin-bottom:2px; }
.invoice-header-field span { color:#263238; }
.invoice-items-table { width:100%; border-collapse:collapse; font-size:13px; }
.invoice-items-table th { background:#eceff1; padding:6px 8px; text-align:left; border-bottom:2px solid #cfd8dc; }
.invoice-items-table td { padding:6px 8px; border-bottom:1px solid #e0e0e0; }
.item-not-deducted { background:#ffebee; }
.item-not-deducted td { color:#c62828; }
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
  <label>M&#283;na</label><br>
  <select name="currency">
    <option value="">Auto (detekce z XML)</option>
    <option value="CZK">CZK</option>
    <option value="EUR">EUR</option>
  </select>
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
  <form method="get" class="view-mode-form" style="margin-top:0.5rem;">
    <input type="hidden" name="view" value="invoices">
    <label>Posledn&iacute;ch faktur</label>
    <select name="limit" onchange="this.form.submit()">
      <?php foreach ([50, 100, 200, 500] as $limit): ?>
        <option value="<?= $limit ?>"<?= (int)($invoiceLimit ?? 50) === $limit ? ' selected' : '' ?>><?= $limit ?></option>
      <?php endforeach; ?>
    </select>
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
        <th style="width:20px;"></th>
        <th>E-shop</th>
        <th>Datum</th>
        <th>&Ccaron;&iacute;slo faktury</th>
        <th>&Ccaron;&aacute;stka (K&#269;)</th>
        <th class="invoice-actions"></th>
      </tr>
      <?php foreach (($invoiceRows ?? []) as $idx => $row): ?>
        <tr>
          <td>
            <span class="row-toggle invoice-toggle" onclick="toggleInvoiceDetail(<?= $idx ?>)">▸</span>
          </td>
          <td><?= htmlspecialchars((string)($row['eshop_source'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)($row['duzp'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)($row['cislo_dokladu'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($formatCzk($row['castka_czk'] ?? 0),ENT_QUOTES,'UTF-8') ?> K&#269;</td>
          <td class="invoice-actions">
            <form method="post" action="/import/delete-invoice" onsubmit="return confirm('Opravdu smazat fakturu?');">
              <input type="hidden" name="eshop" value="<?= htmlspecialchars((string)($row['eshop_source'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
              <input type="hidden" name="cislo_dokladu" value="<?= htmlspecialchars((string)($row['cislo_dokladu'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
              <input type="hidden" name="limit" value="<?= (int)($invoiceLimit ?? 50) ?>">
              <button type="submit" class="invoice-delete" title="Smazat fakturu">&times;</button>
            </form>
          </td>
        </tr>
        <tr class="invoice-detail-row" id="invoice-detail-<?= $idx ?>" data-eshop="<?= htmlspecialchars((string)($row['eshop_source'] ?? ''),ENT_QUOTES,'UTF-8') ?>" data-cislo="<?= htmlspecialchars((string)($row['cislo_dokladu'] ?? ''),ENT_QUOTES,'UTF-8') ?>">
          <td colspan="6">
            <div class="invoice-detail">
              <div class="loading">Načítám...</div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php endif; ?>

<?php if (($viewMode ?? '') === 'history'): ?>
  <hr>
  <h2>Historie auto-importu</h2>
  <?php if (empty($historyRows ?? [])): ?>
    <p class="muted">Zatim zadna historie.</p>
  <?php else: ?>
    <table class="invoice-table">
      <tr>
        <th>Datum</th>
        <th>E-shop</th>
        <th>Mena</th>
        <th>Obdobi</th>
        <th>Doklady</th>
        <th>Polozky</th>
        <th>Status</th>
        <th>Zprava</th>
      </tr>
      <?php foreach (($historyRows ?? []) as $hRow): ?>
      <?php $isError = ($hRow['status'] ?? '') === 'error'; ?>
      <tr style="<?= $isError ? 'background:#ffebee;color:#c62828;' : '' ?>">
        <td><?= htmlspecialchars((string)($hRow['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($hRow['eshop_source'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($hRow['mena'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?php if (!empty($hRow['datum_od']) && !empty($hRow['datum_do'])): ?>
            <?= htmlspecialchars((string)$hRow['datum_od'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$hRow['datum_do'], ENT_QUOTES, 'UTF-8') ?>
          <?php else: ?>
            ---
          <?php endif; ?>
        </td>
        <td><?= (int)($hRow['doklady'] ?? 0) ?></td>
        <td><?= (int)($hRow['polozky'] ?? 0) ?></td>
        <td>
          <?php if ($isError): ?>
            <strong style="color:#c62828;">CHYBA</strong>
          <?php else: ?>
            <span style="color:#2e7d32;">OK</span>
          <?php endif; ?>
        </td>
        <td style="font-size:0.85rem;max-width:300px;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars((string)($hRow['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars((string)($hRow['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
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

<?php if (($viewMode ?? '') === 'invoices'): ?>
<script>
const loadedInvoices = new Set();

function toggleInvoiceDetail(idx) {
  const detailRow = document.getElementById('invoice-detail-' + idx);
  const toggle = detailRow.previousElementSibling.querySelector('.invoice-toggle');

  if (detailRow.classList.contains('expanded')) {
    // Sbalit
    detailRow.classList.remove('expanded');
    toggle.textContent = '▸';
  } else {
    // Rozbalit
    detailRow.classList.add('expanded');
    toggle.textContent = '▾';

    // Načíst data pokud ještě nebyla načtena
    if (!loadedInvoices.has(idx)) {
      loadInvoiceDetail(idx, detailRow);
    }
  }
}

function loadInvoiceDetail(idx, detailRow) {
  const eshop = detailRow.dataset.eshop;
  const cislo = detailRow.dataset.cislo;
  const container = detailRow.querySelector('.invoice-detail');

  fetch('/import/invoice-detail?eshop=' + encodeURIComponent(eshop) + '&cislo_dokladu=' + encodeURIComponent(cislo))
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        container.innerHTML = '<div class="error">' + data.error + '</div>';
        return;
      }

      loadedInvoices.add(idx);
      container.innerHTML = renderInvoiceDetail(data);
    })
    .catch(err => {
      container.innerHTML = '<div class="error">Chyba při načítání: ' + err.message + '</div>';
    });
}

function renderInvoiceDetail(data) {
  const header = data.header;
  const items = data.items;

  let html = '<div class="invoice-header-info">';

  // Základní informace
  if (header.typ_dokladu) {
    html += '<div class="invoice-header-field"><label>Typ dokladu:</label><span>' + escapeHtml(header.typ_dokladu) + '</span></div>';
  }
  if (header.datum_vystaveni) {
    html += '<div class="invoice-header-field"><label>Datum vystavení:</label><span>' + escapeHtml(header.datum_vystaveni) + '</span></div>';
  }
  if (header.splatnost) {
    html += '<div class="invoice-header-field"><label>Splatnost:</label><span>' + escapeHtml(header.splatnost) + '</span></div>';
  }
  if (header.sym_var) {
    html += '<div class="invoice-header-field"><label>Variabilní symbol:</label><span>' + escapeHtml(header.sym_var) + '</span></div>';
  }
  if (header.cislo_objednavky) {
    html += '<div class="invoice-header-field"><label>Číslo objednávky:</label><span>' + escapeHtml(header.cislo_objednavky) + '</span></div>';
  }
  if (header.platba_typ) {
    html += '<div class="invoice-header-field"><label>Typ platby:</label><span>' + escapeHtml(header.platba_typ) + '</span></div>';
  }
  if (header.mena_puvodni && header.mena_puvodni !== 'CZK') {
    html += '<div class="invoice-header-field"><label>Měna:</label><span>' + escapeHtml(header.mena_puvodni) + ' (kurz: ' + escapeHtml(header.kurz_na_czk) + ')</span></div>';
  }

  // Kontakt
  if (header.firma || header.jmeno) {
    html += '<div class="invoice-header-field"><label>Zákazník:</label><span>';
    if (header.firma) html += escapeHtml(header.firma);
    if (header.jmeno) html += (header.firma ? ' / ' : '') + escapeHtml(header.jmeno);
    html += '</span></div>';
  }
  if (header.ic) {
    html += '<div class="invoice-header-field"><label>IČ:</label><span>' + escapeHtml(header.ic) + '</span></div>';
  }
  if (header.email) {
    html += '<div class="invoice-header-field"><label>Email:</label><span>' + escapeHtml(header.email) + '</span></div>';
  }

  html += '</div>';

  // Položky faktury
  html += '<h4 style="margin-top:1rem;margin-bottom:0.5rem;">Položky faktury</h4>';
  html += '<table class="invoice-items-table">';
  html += '<thead><tr>';
  html += '<th>Název</th>';
  html += '<th>SKU</th>';
  html += '<th style="text-align:right;">Množství</th>';
  html += '<th style="text-align:right;">Cena/MJ</th>';
  html += '<th style="text-align:right;">Celkem</th>';
  html += '<th style="text-align:right;">Odpis ze skladu</th>';
  html += '</tr></thead><tbody>';

  items.forEach(item => {
    const notDeducted = !item.odpis_proveden;
    const rowClass = notDeducted ? ' class="item-not-deducted"' : '';

    html += '<tr' + rowClass + '>';
    html += '<td>' + escapeHtml(item.nazev || '') + '</td>';
    html += '<td>' + escapeHtml(item.sku || '—') + '</td>';
    html += '<td style="text-align:right;">' + formatQty(item.mnozstvi) + (item.merna_jednotka ? ' ' + escapeHtml(item.merna_jednotka) : '') + '</td>';
    html += '<td style="text-align:right;">' + formatCzk(item.cena_jedn_czk) + ' Kč</td>';
    html += '<td style="text-align:right;">' + formatCzk((parseFloat(item.mnozstvi) || 0) * (parseFloat(item.cena_jedn_czk) || 0)) + ' Kč</td>';

    if (item.odpis_proveden) {
      if (item.odpis_info && item.odpis_info.length > 0) {
        html += '<td style="text-align:right;">';
        if (item.odpis_info.length === 1 && item.odpis_info[0].sku === item.sku) {
          // Přímý odpis
          html += formatQty(Math.abs(item.odpis_info[0].mnozstvi));
          if (item.odpis_info[0].merna_jednotka) {
            html += ' ' + escapeHtml(item.odpis_info[0].merna_jednotka);
          }
        } else {
          // Nonstock - rozpad na potomky
          html += '<div style="font-size:11px;color:#607d8b;" title="Nonstock produkt - odepsány komponenty">';
          item.odpis_info.forEach((mov, i) => {
            if (i > 0) html += '<br>';
            html += escapeHtml(mov.sku) + ': ' + formatQty(Math.abs(mov.mnozstvi));
            if (mov.merna_jednotka) {
              html += ' ' + escapeHtml(mov.merna_jednotka);
            }
          });
          html += '</div>';
        }
        html += '</td>';
      } else {
        html += '<td style="text-align:right;">✓</td>';
      }
    } else {
      html += '<td style="text-align:right;color:#c62828;font-weight:600;">Neodepsáno</td>';
    }

    html += '</tr>';
  });

  html += '</tbody></table>';

  return html;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatQty(value) {
  const num = parseFloat(value) || 0;
  return num.toLocaleString('cs-CZ', { minimumFractionDigits: 0, maximumFractionDigits: 3 }).replace(/,000$/, '');
}

function formatCzk(value) {
  const num = parseFloat(value) || 0;
  return num.toLocaleString('cs-CZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
<?php endif; ?>
 
