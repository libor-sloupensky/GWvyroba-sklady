<h1>Rezervace</h1>
<p class="muted">Rezervace se vztahují jen na typ „produkt“. Aktivní do 23:59:59 zvoleného dne.</p>
<form method="post" action="/reservations">
  <input type="hidden" name="id" value="" />
  <label>SKU</label><br>
  <input type="text" name="sku" required />
  <br>
  <label>Množství</label><br>
  <input type="number" step="any" name="mnozstvi" required />
  <br>
  <label>Platná do</label><br>
  <input type="date" name="platna_do" required />
  <br>
  <label>Poznámka</label><br>
  <input type="text" name="poznamka" />
  <br>
  <button type="submit">Uložit</button>
</form>

<hr>
<table>
  <tr><th>SKU</th><th>Množství</th><th>Platná do</th><th>Poznámka</th><th>Akce</th></tr>
  <?php foreach (($rows ?? []) as $r): ?>
  <tr>
    <td><?= htmlspecialchars((string)$r['sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['mnozstvi'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$r['platna_do'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($r['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td>
      <form method="post" action="/reservations/delete" onsubmit="return confirm('Smazat rezervaci?')" style="display:inline;">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
        <button type="submit">Smazat</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

