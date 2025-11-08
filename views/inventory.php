<h1>Inventura</h1>
<p class="muted">Zadávejte řádky inventury (lze více pro stejné SKU, pro výpočet se sčítají). Ukládá se jako pohyb „inventura“.</p>
<form method="post" action="/inventory/move">
  <label>SKU</label><br>
  <input type="text" name="sku" required />
  <br>
  <label>Množství</label><br>
  <input type="number" step="any" name="mnozstvi" required />
  <br>
  <label>Měrná jednotka</label><br>
  <input type="text" name="merna_jednotka" placeholder="ks / kg" />
  <br>
  <label>Poznámka</label><br>
  <input type="text" name="poznamka" />
  <br>
  <button type="submit">Uložit</button>
  <span class="muted">MJ: ks bez desetin, kg se 2 desetinami, převod kg↔g = ×1000.</span>
  </form>

