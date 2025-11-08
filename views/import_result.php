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

