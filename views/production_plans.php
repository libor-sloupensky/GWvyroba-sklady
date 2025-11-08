<h1>Výroba – návrhy</h1>
<p class="muted">Náhled. Finální výpočet doplň dle MASTER PROMPT (forecast, rezervace, dostupné, návrh, krok/min.dávka).</p>
<table>
  <tr>
    <th>SKU</th><th>Název</th><th>Min zásoba</th><th>Min dávka</th><th>Krok</th><th>Výrobní doba (dny)</th><th>Akce</th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr>
    <td><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?></td>
    <td>
      <form method="post" action="/production/produce" style="display:inline;">
        <input type="hidden" name="sku" value="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>" />
        <input type="number" step="any" name="mnozstvi" placeholder="množství" required />
        <select name="modus">
          <option value="korekce" selected>korekce (default)</option>
          <option value="odecti_subpotomky">odečíst subpotomky</option>
        </select>
        <button type="submit">Zapsat vyrobené</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

