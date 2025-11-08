<h1>Analýza obratu</h1>
<form method="get">
  <label>Od</label><input type="date" name="from" value="<?= htmlspecialchars((string)($from ?? ''),ENT_QUOTES,'UTF-8') ?>" />
  <label>Do</label><input type="date" name="to" value="<?= htmlspecialchars((string)($to ?? ''),ENT_QUOTES,'UTF-8') ?>" />
  <button type="submit">Filtrovat</button>
</form>
<table>
  <tr>
    <th>DUZP</th><th>ESHOP</th><th>SKU</th><th>Název</th><th>Množství</th><th>Cena/ks CZK</th>
  </tr>
  <?php foreach (($rows ?? []) as $r): ?>
  <tr>
    <td><?= htmlspecialchars((string)$r['duzp'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['eshop_source'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($r['sku'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($r['nazev'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($r['mnozstvi'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($r['cena_jedn_czk'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

