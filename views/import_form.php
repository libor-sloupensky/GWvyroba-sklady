<h1>Import Pohoda XML</h1>
<p class="muted">Postup: vyberte e-shop a XML (Stormware Pohoda). Pokud nejsou nastavené řady, import propustí všechny doklady. Při nesouladu řad import zastaví a nic neuloží.</p>
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
    <p class="notice" style="border-color:#ffe0b2;background:#fff8e1;color:#8c6d1f;">Nejprve přidejte e-shop v Nastavení &gt; Fakturační řady.</p>
  <?php endif; ?>
  <br>
  <label>XML soubor (Pohoda)</label><br>
  <input type="file" name="xml" accept=".xml" required />
  <br>
<button type="submit"<?= $hasEshops ? '' : ' disabled' ?>>Importovat</button>
</form>

<?php if (!empty($outstandingMissing)): ?>
  <hr>
  <h2>Nespárované položky za posledních <?= (int)($outstandingDays ?? 30) ?> dní</h2>
  <?php foreach (($outstandingMissing ?? []) as $eshopName => $items): if (empty($items)) continue; ?>
    <h3><?= htmlspecialchars((string)$eshopName,ENT_QUOTES,'UTF-8') ?></h3>
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
<?php endif; ?>

<hr>
<h2>Smazat poslední import</h2>
<?php if (!$hasEshops): ?>
  <p class="muted">Nemáte definovaný žádný e-shop, není co mazat.</p>
<?php else: ?>
  <table>
    <tr><th>E-shop</th><th>Poslední batch</th><th>Akce</th></tr>
    <?php foreach ($eshopList as $s): $value = (string)$s['eshop_source']; $last = (string)($s['last_batch'] ?? ''); $hasBatch = $last !== ''; ?>
    <tr>
      <td><?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?></td>
      <td><?= $hasBatch ? htmlspecialchars($last,ENT_QUOTES,'UTF-8') : '—' ?></td>
      <td>
        <?php if ($hasBatch): ?>
          <form method="post" action="/import/delete-last" class="inline-delete-form" style="display:inline;">
            <input type="hidden" name="eshop" value="<?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?>" />
            <button type="submit" class="link-danger" title="Smazat poslední import" aria-label="Smazat poslední import">×</button>
          </form>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
