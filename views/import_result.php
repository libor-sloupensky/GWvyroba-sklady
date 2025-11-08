<h1>Import – výsledek</h1>
<?php if (!empty($notice)): ?><div class="notice"><?= htmlspecialchars((string)$notice,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<p><strong>Batch:</strong> <?= htmlspecialchars((string)($batch ?? ''),ENT_QUOTES,'UTF-8') ?></p>
<p><strong>Doklady:</strong> <?= (int)($summary['doklady'] ?? 0) ?>, <strong>Položky:</strong> <?= (int)($summary['polozky'] ?? 0) ?></p>

<?php if (!empty($missingSku)): ?>
  <h3>Chybějící SKU (poslední import)</h3>
  <table>
    <tr><th>DUZP</th><th>ESHOP</th><th>Doklad</th><th>Název</th><th>Množství</th><th>Code</th></tr>
    <?php foreach ($missingSku as $r): ?>
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
<?php endif; ?>

<?php if (!empty($outstandingMissing)): ?>
  <h3>Nespárované položky za posledních <?= (int)($outstandingDays ?? 30) ?> dní</h3>
  <?php foreach ($outstandingMissing as $eshopName => $items): if (empty($items)) continue; ?>
    <h4><?= htmlspecialchars((string)$eshopName,ENT_QUOTES,'UTF-8') ?></h4>
    <table>
      <tr><th>DUZP</th><th>Doklad</th><th>Název</th><th>Množství</th><th>Code</th></tr>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars((string)$item['duzp'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$item['cislo_dokladu'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$item['nazev'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$item['mnozstvi'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$item['code_raw'],ENT_QUOTES,'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
  <p class="muted">Úplný přehled najdete v sekci <a href="/report/missing-sku">Chybějící SKU</a>.</p>
<?php else: ?>
  <p class="muted">Za poslední období nejsou neevidované SKU.</p>
<?php endif; ?>

<hr>
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
  <label>XML soubor (Pohoda)</label><br>
  <input type="file" name="xml" accept=".xml" required />
  <br>
  <button type="submit"<?= $hasEshops ? '' : ' disabled' ?>>Importovat</button>
</form>
