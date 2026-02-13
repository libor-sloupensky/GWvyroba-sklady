<?php

  $activeFilters = $filters ?? ['brand'=>0,'group'=>0,'type'=>'','search'=>''];

  $filterBrand = (int)($activeFilters['brand'] ?? 0);

  $filterGroup = (int)($activeFilters['group'] ?? 0);

  $filterType  = (string)($activeFilters['type'] ?? '');

  $filterSearch= (string)($activeFilters['search'] ?? '');

  $hasSearchActive = (bool)($hasSearch ?? false);

?>

<h1>Produkty</h1>

<style>

.collapsible {

  border: 1px solid #ddd;

  border-radius: 4px;

  padding: 0.65rem 0.9rem;

  margin-bottom: 1rem;

}

.collapsible summary {

  cursor: pointer;

  font-weight: 600;

  list-style: none;

  display: flex;

  align-items: center;

}

.collapsible summary::-webkit-details-marker {

  display: none;

}

.collapsible summary::after {

  content: '\25BC';

  font-size: 1.4rem;

  margin-left: 0.5rem;

  color: #455a64;

}

.collapsible[open] summary::after {

  content: '\25B2';

}

.collapsible-body {

  margin-top: 0.75rem;

}

.product-filter-form {

  border: 1px solid #ddd;

  border-radius: 4px;

  padding: 0.9rem;

  display: flex;

  flex-wrap: wrap;

  gap: 1rem;

  margin-bottom: 1rem;

  background: #fafafa;

}

.product-filter-form label {

  display: flex;

  flex-direction: column;

  gap: 0.3rem;

  font-weight: 600;

  min-width: 200px;

}

.section-title {

  font-size: 1.1rem;

  font-weight: 600;

  margin: 1rem 0 0.4rem;

}

.muted {

  color: #607d8b;

}

.products-table {

  width: 100%;

  border-collapse: collapse;

  margin-top: 1rem;

}

.products-table th,

.products-table td {

  border: 1px solid #ddd;

  padding: 0.4rem 0.5rem;

  vertical-align: top;

}

.products-table th {

  background: #f3f6f9;

}

.sku-cell {
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  white-space: nowrap;
}
.sku-toggle {
  font-size: 0.9rem;
  color: #455a64;
}
.inline-input {
  width: 100%;
  box-sizing: border-box;
}
.bom-tree-row td {
  background: #fdfdfd;
  padding: 0.6rem;
  border-top: none;
}
.bom-tree-row pre {
  margin: 0;
  white-space: pre-wrap;
  font-family: "Fira Mono","Consolas",monospace;
  font-size: 0.9rem;
}
</style>



<?php if (!empty($error)): ?>

  <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;">

    <?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?>

  </div>

<?php endif; ?>

<?php if (!empty($message)): ?>

  <div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;">

    <?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?>

  </div>

<?php endif; ?>



<details class="collapsible" id="products-help">

  <summary>Npovda  CSV a pole produktu</summary>

  <div class="collapsible-body">

    <p><strong>Popis sloupc CSV (oddlova ;):</strong></p>

    <ul>

      <li><code>sku</code>  povinn intern kd produktu.</li>

      <li><code>alt_sku</code>  voliteln alternativn kd (uniktn, nesm bt shodn se SKU).</li>

      <li><code>ean</code>  voliteln EAN / rov kd.</li>

      <li><code>znaka</code> / <code>skupina</code>  nzvy definovan v Nastaven.</li>

      <li><code>typ</code>  jedna z hodnot <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>balen</code>, <code>karton</code>.</li>

      <li><code>mrn_jednotka</code>  kd jednotky z Nastaven (nap. <code>ks</code>, <code>kg</code>).</li>

      <li><code>nzev</code>  povinn nzev poloky.</li>

      <li><code>min_zsoba</code>  bezpen zsoba; plnovn se m dret alespo tto hodnoty.</li>

      <li><code>min_dvka</code>  minimln vyrbn dvka. Men mnostv vroba nespust.</li>

      <li><code>krok_vroby</code>  o kolik lze dvku navyovat nad minimum (nap. krok 50 ? 200, 250, 300 ).</li>

      <li><code>vrobn_doba_dn</code>  dlka vroby v kalendnch dnech.</li>

      <li><code>aktivn</code>  1 = aktivn, 0 = skryt produkt.</li>

      <li><code>poznmka</code>  libovoln text.</li>

    </ul>

    <p>Desetinn hodnoty pite s tekou (nap. <code>0.25</code>). CSV mus bt v UTF-8.</p>

  </div>

</details>



<details class="collapsible" id="product-create-panel">

  <summary>Pidat produkt</summary>

  <div class="collapsible-body">

    <form method="post" action="/products/create" class="product-create-form">

      <label>SKU*</label><input type="text" name="sku" required />

      <label>Alt SKU</label><input type="text" name="alt_sku" />

      <label>EAN</label><input type="text" name="ean" />

      <label>Znaka</label>

      <select name="znacka_id">

        <option value="">Vechny</option>

        <?php foreach (($brands ?? []) as $b): ?>

          <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Skupina</label>

      <select name="skupina_id">

        <option value="">Vechny</option>

        <?php foreach (($groups ?? []) as $g): ?>

          <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Typ*</label>

      <select name="typ" required>

        <?php foreach (($types ?? []) as $t): ?>

          <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Mrn jednotka*</label>

      <select name="merna_jednotka" required>

        <?php foreach (($units ?? []) as $u): ?>

          <option value="<?= htmlspecialchars((string)$u['kod'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$u['kod'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Nzev*</label><input type="text" name="nazev" required />

      <label>Min. zsoba</label><input type="number" step="0.001" name="min_zasoba" />

      <label>Min. dvka</label><input type="number" step="0.001" name="min_davka" />

      <label>Krok vroby</label><input type="number" step="0.001" name="krok_vyroby" />

      <label>Vrobn doba (dny)</label><input type="number" step="1" name="vyrobni_doba_dni" />

      <label>Aktivn*</label>

      <select name="aktivni">

        <option value="1">Aktivn</option>

        <option value="0">Skryto</option>

      </select>

      <label>Poznmka</label><textarea name="poznamka" rows="2"></textarea>

      <button type="submit">Uloit produkt</button>

    </form>

  </div>

</details>



<details class="collapsible" id="product-import-panel">

  <summary>Import a prava produkt</summary>

  <div class="collapsible-body">

    <p><a href="/products/export">Sthnout CSV (aktuln)</a></p>

    <?php if (!empty($errors)): ?>

      <div class="notice">

        <strong>Chyby importu:</strong>

        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>

      </div>

    <?php endif; ?>

    <form method="post" action="/products/import" enctype="multipart/form-data">

      <label>Nahrt CSV</label><br>

      <input type="file" name="csv" accept=".csv" required />

      <br>

      <button type="submit">Importovat</button>

      <span class="muted">Pouvejte UTF-8 a stednk jako oddlova.</span>

    </form>

  </div>

</details>



<div class="product-search-panel">

  <div class="section-title">Vyhledej produkt</div>

  <form method="get" action="/products" class="product-filter-form">

    <input type="hidden" name="search" value="1" />

    <label>

      <span>Znaka</span>

      <select name="znacka_id">

        <option value="">Vechny</option>

        <?php foreach (($brands ?? []) as $b): $id = (int)$b['id']; ?>

          <option value="<?= $id ?>"<?= $filterBrand === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

    </label>

    <label>

      <span>Skupina</span>

      <select name="skupina_id">

        <option value="">Vechny</option>

        <?php foreach (($groups ?? []) as $g): $id = (int)$g['id']; ?>

          <option value="<?= $id ?>"<?= $filterGroup === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

    </label>

    <label>

      <span>Typ</span>

      <select name="typ">

        <option value="">Vechny</option>

        <?php foreach (($types ?? []) as $t): ?>

          <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"<?= $filterType === $t ? ' selected' : '' ?>><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

    </label>

    <label>

      <span>Hledat</span>

      <input type="text" name="q" value="<?= htmlspecialchars($filterSearch,ENT_QUOTES,'UTF-8') ?>" placeholder="SKU / nzev / EAN" />

    </label>

    <div style="align-self:flex-end;display:flex;gap:0.5rem;">

      <button type="submit">Vyhledat</button>

      <a href="/products" style="align-self:center;">Zruit filtr</a>

    </div>

  </form>

</div>



php

  $activeFilters = $filters ?? ['brand'=>0,'group'=>0,'type'=>'','search'=>''];

  $filterBrand = (int)($activeFilters['brand'] ?? 0);

  $filterGroup = (int)($activeFilters['group'] ?? 0);

  $filterType  = (string)($activeFilters['type'] ?? '');

  $filterSearch= (string)($activeFilters['search'] ?? '');

  $hasSearchActive = (bool)($hasSearch ?? false);

?>

<h1>Produkty</h1>

<style>

.collapsible {

  border: 1px solid #ddd;

  border-radius: 4px;

  padding: 0.65rem 0.9rem;

  margin-bottom: 1rem;

}

.collapsible summary {

  cursor: pointer;

  font-weight: 600;

  list-style: none;

  display: flex;

  align-items: center;

}

.collapsible summary::-webkit-details-marker {

  display: none;

}

.collapsible summary::after {

  content: '\25BC';

  font-size: 1.4rem;

  margin-left: 0.5rem;

  color: #455a64;

}

.collapsible[open] summary::after {

  content: '\25B2';

}

.collapsible-body {

  margin-top: 0.75rem;

}

.product-filter-form {

  border: 1px solid #ddd;

  border-radius: 4px;

  padding: 0.9rem;

  display: flex;

  flex-wrap: wrap;

  gap: 1rem;

  margin-bottom: 1rem;

  background: #fafafa;

}

.product-filter-form label {

  display: flex;

  flex-direction: column;

  gap: 0.3rem;

  font-weight: 600;

  min-width: 200px;

}

.section-title {

  font-size: 1.1rem;

  font-weight: 600;

  margin: 1rem 0 0.4rem;

}

.muted {

  color: #607d8b;

}

.products-table {

  width: 100%;

  border-collapse: collapse;

  margin-top: 1rem;

}

.products-table th,

.products-table td {

  border: 1px solid #ddd;

  padding: 0.4rem 0.5rem;

  vertical-align: top;

}

.products-table th {

  background: #f3f6f9;

}

.sku-cell {
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  white-space: nowrap;
}
.sku-toggle {
  font-size: 0.9rem;
  color: #455a64;
}
.inline-input {
  width: 100%;
  box-sizing: border-box;
}
.bom-tree-row td {
  background: #fdfdfd;
  padding: 0.6rem;
  border-top: none;
}
.bom-tree-row pre {
  margin: 0;
  white-space: pre-wrap;
  font-family: "Fira Mono","Consolas",monospace;
  font-size: 0.9rem;
}
</style>



<?php if (!empty($error)): ?>

  <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;">

    <?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?>

  </div>

<?php endif; ?>

<?php if (!empty($message)): ?>

  <div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;">

    <?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?>

  </div>

<?php endif; ?>



<details class="collapsible" id="products-help">

  <summary>Npovda  CSV a pole produktu</summary>

  <div class="collapsible-body">

    <p><strong>Popis sloupc CSV (oddlova ;):</strong></p>

    <ul>

      <li><code>sku</code>  povinn intern kd produktu.</li>

      <li><code>alt_sku</code>  voliteln alternativn kd (uniktn, nesm bt shodn se SKU).</li>

      <li><code>ean</code>  voliteln EAN / rov kd.</li>

      <li><code>znaka</code> / <code>skupina</code>  nzvy definovan v Nastaven.</li>

      <li><code>typ</code>  jedna z hodnot <code>produkt</code>, <code>obal</code>, <code>etiketa</code>, <code>surovina</code>, <code>balen</code>, <code>karton</code>.</li>

      <li><code>mrn_jednotka</code>  kd jednotky z Nastaven (nap. <code>ks</code>, <code>kg</code>).</li>

      <li><code>nzev</code>  povinn nzev poloky.</li>

      <li><code>min_zsoba</code>  bezpen zsoba; plnovn se m dret alespo tto hodnoty.</li>

      <li><code>min_dvka</code>  minimln vyrbn dvka. Men mnostv vroba nespust.</li>

      <li><code>krok_vroby</code>  o kolik lze dvku navyovat nad minimum (nap. krok 50 ? 200, 250, 300 ).</li>

      <li><code>vrobn_doba_dn</code>  dlka vroby v kalendnch dnech.</li>

      <li><code>aktivn</code>  1 = aktivn, 0 = skryt produkt.</li>

      <li><code>poznmka</code>  libovoln text.</li>

    </ul>

    <p>Desetinn hodnoty pite s tekou (nap. <code>0.25</code>). CSV mus bt v UTF-8.</p>

  </div>

</details>



<details class="collapsible" id="product-create-panel">

  <summary>Pidat produkt</summary>

  <div class="collapsible-body">

    <form method="post" action="/products/create" class="product-create-form">

      <label>SKU*</label><input type="text" name="sku" required />

      <label>Alt SKU</label><input type="text" name="alt_sku" />

      <label>EAN</label><input type="text" name="ean" />

      <label>Znaka</label>

      <select name="znacka_id">

        <option value="">Vechny</option>

        <?php foreach (($brands ?? []) as $b): ?>

          <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Skupina</label>

      <select name="skupina_id">

        <option value="">Vechny</option>

        <?php foreach (($groups ?? []) as $g): ?>

          <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Typ*</label>

      <select name="typ" required>

        <?php foreach (($types ?? []) as $t): ?>

          <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Mrn jednotka*</label>

      <select name="merna_jednotka" required>

        <?php foreach (($units ?? []) as $u): ?>

          <option value="<?= htmlspecialchars((string)$u['kod'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$u['kod'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

      <label>Nzev*</label><input type="text" name="nazev" required />

      <label>Min. zsoba</label><input type="number" step="0.001" name="min_zasoba" />

      <label>Min. dvka</label><input type="number" step="0.001" name="min_davka" />

      <label>Krok vroby</label><input type="number" step="0.001" name="krok_vyroby" />

      <label>Vrobn doba (dny)</label><input type="number" step="1" name="vyrobni_doba_dni" />

      <label>Aktivn*</label>

      <select name="aktivni">

        <option value="1">Aktivn</option>

        <option value="0">Skryto</option>

      </select>

      <label>Poznmka</label><textarea name="poznamka" rows="2"></textarea>

      <button type="submit">Uloit produkt</button>

    </form>

  </div>

</details>



<details class="collapsible" id="product-import-panel">

  <summary>Import a prava produkt</summary>

  <div class="collapsible-body">

    <p><a href="/products/export">Sthnout CSV (aktuln)</a></p>

    <?php if (!empty($errors)): ?>

      <div class="notice">

        <strong>Chyby importu:</strong>

        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>

      </div>

    <?php endif; ?>

    <form method="post" action="/products/import" enctype="multipart/form-data">

      <label>Nahrt CSV</label><br>

      <input type="file" name="csv" accept=".csv" required />

      <br>

      <button type="submit">Importovat</button>

      <span class="muted">Pouvejte UTF-8 a stednk jako oddlova.</span>

    </form>

  </div>

</details>



<div class="product-search-panel">

  <div class="section-title">Vyhledej produkt</div>

  <form method="get" action="/products" class="product-filter-form">

    <input type="hidden" name="search" value="1" />

    <label>

      <span>Znaka</span>

      <select name="znacka_id">

        <option value="">Vechny</option>

        <?php foreach (($brands ?? []) as $b): $id = (int)$b['id']; ?>

          <option value="<?= $id ?>"<?= $filterBrand === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$b['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

    </label>

    <label>

      <span>Skupina</span>

      <select name="skupina_id">

        <option value="">Vechny</option>

        <?php foreach (($groups ?? []) as $g): $id = (int)$g['id']; ?>

          <option value="<?= $id ?>"<?= $filterGroup === $id ? ' selected' : '' ?>><?= htmlspecialchars((string)$g['nazev'],ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

    </label>

    <label>

      <span>Typ</span>

      <select name="typ">

        <option value="">Vechny</option>

        <?php foreach (($types ?? []) as $t): ?>

          <option value="<?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?>"<?= $filterType === $t ? ' selected' : '' ?>><?= htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8') ?></option>

        <?php endforeach; ?>

      </select>

    </label>

    <label>

      <span>Hledat</span>

      <input type="text" name="q" value="<?= htmlspecialchars($filterSearch,ENT_QUOTES,'UTF-8') ?>" placeholder="SKU / nzev / EAN" />

    </label>

    <div style="align-self:flex-end;display:flex;gap:0.5rem;">

      <button type="submit">Vyhledat</button>

      <a href="/products" style="align-self:center;">Zruit filtr</a>

    </div>

  </form>

</div>



<div id="bom-tree-panel" class="bom-tree-panel" hidden>

  <div class="bom-tree-panel-header">

    <strong>BOM strom pro <span id="bom-tree-sku"></span></strong>

    <button type="button" id="bom-tree-close">Zavt</button>

  </div>

  <pre id="bom-tree-content"></pre>

</div>



<?php if (!$hasSearchActive): ?>

  <p class="muted">Zadejte parametry vyhledvn a potvrte tlaĭtkem Vyhledat. Seznam produkt se zobraz a po vyhledn.</p>

<?php elseif (empty($items)): ?>

  <p class="muted">dn produkty neodpovdaj zadanm filtrm.</p>

<?php else: ?>

<table class="products-table">

  <tr>

    <th>SKU</th>

    <th>Alt SKU</th>

    <th>EAN</th>

    <th>Znaka</th>

    <th>Skupina</th>

    <th>Typ</th>

    <th>MJ</th>

    <th>Nzev</th>

    <th>Min. zsoba</th>

    <th>Min. dvka</th>

    <th>Krok vroby</th>

    <th>Vrobn doba</th>

    <th>Aktivn</th>

    <th>Poznmka</th>

  </tr>

  <?php foreach (($items ?? []) as $it): ?>

  <tr data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>">

    <td class="sku-cell" data-sku="<?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?>">
      <span class="sku-toggle"></span>
      <span class="sku-text"><?= htmlspecialchars((string)$it['sku'],ENT_QUOTES,'UTF-8') ?></span>
    </td>

    <td class="editable" data-field="alt_sku" data-type="text" data-value="<?= htmlspecialchars((string)($it['alt_sku'] ?? ''),ENT_QUOTES,'UTF-8') ?>">

      <?= isset($it['alt_sku']) && $it['alt_sku'] !== '' ? htmlspecialchars((string)$it['alt_sku'],ENT_QUOTES,'UTF-8') : '' ?>

    </td>

    <td class="editable" data-field="ean" data-type="text" data-value="<?= htmlspecialchars((string)($it['ean'] ?? ''),ENT_QUOTES,'UTF-8') ?>">

      <?= isset($it['ean']) && $it['ean'] !== '' ? htmlspecialchars((string)$it['ean'],ENT_QUOTES,'UTF-8') : '' ?>

    </td>

    <td class="editable" data-field="znacka_id" data-type="select" data-options="brands" data-value="<?= (int)($it['znacka_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['znacka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>

    <td class="editable" data-field="skupina_id" data-type="select" data-options="groups" data-value="<?= (int)($it['skupina_id'] ?? 0) ?>"><?= htmlspecialchars((string)($it['skupina'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>

    <td class="editable" data-field="typ" data-type="select" data-options="types" data-value="<?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['typ'],ENT_QUOTES,'UTF-8') ?></td>

    <td class="editable" data-field="merna_jednotka" data-type="select" data-options="units" data-value="<?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['merna_jednotka'],ENT_QUOTES,'UTF-8') ?></td>

    <td class="editable" data-field="nazev" data-type="text" data-value="<?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['nazev'],ENT_QUOTES,'UTF-8') ?></td>

    <td class="editable" data-field="min_zasoba" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_zasoba'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['min_zasoba'] ?></td>

    <td class="editable" data-field="min_davka" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['min_davka'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['min_davka'] ?></td>

    <td class="editable" data-field="krok_vyroby" data-type="number" data-step="0.001" data-value="<?= htmlspecialchars((string)$it['krok_vyroby'],ENT_QUOTES,'UTF-8') ?>"><?= (int)$it['krok_vyroby'] ?></td>

    <td class="editable" data-field="vyrobni_doba_dni" data-type="number" data-step="1" data-value="<?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$it['vyrobni_doba_dni'],ENT_QUOTES,'UTF-8') ?></td>

    <td class="editable" data-field="aktivni" data-type="select" data-options="active" data-value="<?= (int)$it['aktivni'] ?>"><?= (int)$it['aktivni'] ? '?' : '' ?></td>

    <td class="editable" data-field="poznamka" data-type="textarea" data-value="<?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)($it['poznamka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>

  </tr>

  <?php endforeach; ?>

</table>

<?php endif; ?>



<script>
(function () {
  const meta = {
    brands: <?= json_encode(array_map(fn($b) => ['value'=>(string)$b['id'],'label'=>$b['nazev']], $brands ?? []), JSON_UNESCAPED_UNICODE) ?>,
    groups: <?= json_encode(array_map(fn($g) => ['value'=>(string)$g['id'],'label'=>$g['nazev']], $groups ?? []), JSON_UNESCAPED_UNICODE) ?>,
    units:  <?= json_encode(array_map(fn($u) => ['value'=>$u['kod'],'label'=>$u['kod']], $units ?? []), JSON_UNESCAPED_UNICODE) ?>,
    types:  <?= json_encode(array_map(fn($t) => ['value'=>$t,'label'=>$t], $types ?? []), JSON_UNESCAPED_UNICODE) ?>,
    active: [{value:'1',label:''},{value:'0',label:''}]
  };

  const table = document.querySelector('.products-table');
  const updateUrl = '/products/update';
  let bomState = { row: null, detail: null };

  if (table) {
    table.addEventListener('click', (event) => {
      const skuCell = event.target.closest('.sku-cell');
      if (skuCell && skuCell.dataset.sku) {
        event.preventDefault();
        toggleBomRow(skuCell);
      }
    });

    table.addEventListener('dblclick', (event) => {
      const cell = event.target.closest('.editable');
      if (!cell || cell.dataset.editing === '1') return;
      const row = cell.closest('tr');\n      if (!row) return;\n      const sku = row.dataset.sku;\n      if (!sku) return;
      startEdit(cell, sku);
    });
  }

  function toggleBomRow(cell) {
    const row = cell.closest('tr');
    if (!row) return;
    if (bomState.row === row) {
      closeBomRow();
      return;
    }
    closeBomRow();
    const toggle = cell.querySelector('.sku-toggle');
    if (toggle) toggle.textContent = '';
    row.classList.add('bom-open');
    const detailRow = document.createElement('tr');
    detailRow.className = 'bom-tree-row';
    const detailCell = document.createElement('td');
    detailCell.colSpan = row.children.length;
    detailCell.textContent = 'Natm';
    detailRow.appendChild(detailCell);
    row.parentNode.insertBefore(detailRow, row.nextSibling);
    bomState = { row, detail: detailRow };
    loadBomTree(cell.dataset.sku, detailCell);
  }

  function closeBomRow() {
    if (!bomState.row) return;
    bomState.row.classList.remove('bom-open');
    const toggle = bomState.row.querySelector('.sku-toggle');
    if (toggle) toggle.textContent = '';
    if (bomState.detail) { bomState.detail.remove(); }
    bomState = { row: null, detail: null };
  }

  function loadBomTree(sku, targetCell) {
    fetch('/products/bom-tree?sku=' + encodeURIComponent(sku), { headers: { 'Accept': 'application/json' } })
      .then((res) => res.ok ? res.json() : Promise.reject())
      .then((data) => {
        if (!bomState.detail || !targetCell.isConnected) {
          return;
        }
        if (!data.ok) {
          targetCell.textContent = data.error || 'BOM strom se nepodailo nast.';
          return;
        }
        const text = formatBomText(data.tree);
        targetCell.innerHTML = '';
        const pre = document.createElement('pre');
        pre.textContent = text;
        targetCell.appendChild(pre);
      })
      .catch(() => {
        if (targetCell.isConnected) {
          targetCell.textContent = 'BOM strom se nepodailo nast.';
        }
      });
  }

  function formatBomText(node, depth = 0) {
    if (!node) return '';
    const indent = '  '.repeat(depth);
    const metaParts = [];
    if (node.typ) metaParts.push(node.typ);
    if (node.merna_jednotka) metaParts.push('MJ ' + node.merna_jednotka);
    let line = ${indent}  ;
    if (metaParts.length) {
      line +=  [];
    }
    if (node.edge) {
      const edgeMj = node.edge.merna_jednotka || node.merna_jednotka || '';
      line +=      ();
    }
    let text = line + '\n';
    const children = node.children || [];
    if (!children.length && depth === 0) {
      text += indent + '  (bez navzanch poloek)\n';
    }
    children.forEach((child) => {
      text += formatBomText(child, depth + 1);
      if (child.cycle) {
        text += ${'  '.repeat(depth + 1)} cyklick vazba (zkrceno)\n;
      }
    });
    return text;
  }

  function startEdit(cell, sku) {
    cell.dataset.editing = '1';
    const field = cell.dataset.field;
    const type = cell.dataset.type || 'text';
    const currentValue = cell.dataset.value ?? cell.textContent.trim();
    let input;
    if (type === 'select') {
      const optionsKey = cell.dataset.options;
      input = document.createElement('select');
      appendOptions(input, meta[optionsKey] ?? []);
      input.value = currentValue;
    } else if (type === 'textarea') {
      input = document.createElement('textarea');
      input.rows = 3;
      input.value = currentValue;
    } else {
      input = document.createElement('input');
      input.type = type === 'number' ? 'number' : 'text';
      if (type === 'number' && cell.dataset.step) {
        input.step = cell.dataset.step;
      }
      input.value = currentValue;
    }
    input.className = 'inline-input';
    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();
    if (input.select) input.select();

    const finish = (commit) => {
      cell.dataset.editing = '0';
      input.removeEventListener('blur', onBlur);
      input.removeEventListener('keydown', onKey);
      if (!commit) {
        cell.textContent = formatDisplay(field, currentValue);
        cell.dataset.value = currentValue;
        return;
      }
      const newValue = input.value.trim();
      if (newValue === currentValue) {
        cell.textContent = formatDisplay(field, currentValue);
        return;
      }
      saveChange(sku, field, newValue)
        .then((ok) => {
          if (ok) {
            cell.dataset.value = newValue;
            cell.textContent = formatDisplay(field, newValue);
          } else {
            cell.textContent = formatDisplay(field, currentValue);
          }
        });
    };

    const onBlur = () => finish(true);
    const onKey = (e) => {
      if (e.key === 'Enter' && type !== 'textarea') {
        e.preventDefault();
        finish(true);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        finish(false);
      }
    };
    input.addEventListener('blur', onBlur);
    input.addEventListener('keydown', onKey);
  }

  function appendOptions(select, options) {
    select.innerHTML = '';
    select.appendChild(new Option('Vechny', ''));
    options.forEach((opt) => select.appendChild(new Option(opt.label, opt.value)));
  }

  function formatDisplay(field, value) {
    if (!value) return '';
    if (field === 'aktivni') return value === '1' ? '' : '';
    if (field === 'znacka_id') return lookupLabel(meta.brands, value);
    if (field === 'skupina_id') return lookupLabel(meta.groups, value);
    if (field === 'merna_jednotka') return value;
    if (field === 'typ') return value;
    return value;
  }

  function lookupLabel(list, value) {
    const found = list.find((item) => item.value === String(value));
    return found ? found.label : '';
  }

  async function saveChange(sku, field, value) {
    try {
      const response = await fetch(updateUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({sku, field, value})
      });
      const data = await response.json();
      if (!data.ok) {
        alert(data.error || 'Uloen se nezdailo.');
        return false;
      }
      return true;
    } catch (err) {
      alert('Nastala chyba pi ukldn.');
      return false;
    }
  }
})();
</script>










