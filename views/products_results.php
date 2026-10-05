<?php
  // Výsledky vyhledávání produktů – vkládá products_index.php a vrací je i /products?partial=1 (živé hledání).
  $hasSearchActive = (bool)($hasSearch ?? false);
  $resultCount = (int)($resultCount ?? 0);
  $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  // Formát množství jako ve Výrobě: mezera jako oddělovač tisíců, bez koncových nul
  $formatQty = static function ($value, int $decimals = 3): string {
      $formatted = number_format((float)$value, max(0, $decimals), ',', ' ');
      if ($decimals > 0) {
          $formatted = rtrim(rtrim($formatted, '0'), ',');
      }
      return $formatted === '' ? '0' : $formatted;
  };

  // Sloupce tabulky: klíč => [hlavička, tooltip, povinný]. Pořadí = pořadí buněk v řádku.
  // Povinné sloupce (SKU, Název) nejdou ve výběru sloupců skrýt.
  $productColumns = [
      'sku'        => ['SKU', 'Interní kód produktu. Kliknutím rozbalíte kusovník (z čeho se produkt skládá).', true],
      'alt_sku'    => ['Alt SKU', 'Alternativní nebo starší označení, pod kterým produkt chodí z e-shopu či Pohody. Dvojklikem upravíte.', false],
      'ean'        => ['EAN', 'Čárový kód produktu. Dvojklikem upravíte. Ikona za platným EAN-13 / EAN-8 stáhne čárový kód jako vektor (SVG).', false],
      'znacka'     => ['Značka', 'Značka produktu (číselník v Nastavení). Dvojklikem upravíte.', false],
      'skupina'    => ['Skupina', 'Produktová skupina (číselník v Nastavení). Dvojklikem upravíte.', false],
      'typ'        => ['Typ', 'Typ položky – produkt, karton, surovina, obal … Určuje, jak se s položkou počítá ve výrobě a analýze. Dvojklikem upravíte.', false],
      'mj'         => ['MJ', 'Měrná jednotka skladové evidence (ks, kg, bal …). Dvojklikem upravíte.', false],
      'nazev'      => ['Název', 'Název produktu. Dvojklikem upravíte.', true],
      // Výrobní sloupce převzaté z Výroby (/production/plans)
      'sku_vyroba' => ['SKU výroba', "Kliknutím se rozbalí strom potomků se skladovými dostupnostmi (dostupné, cílový stav, chybí).\nBarevná stupnice ukazuje, jaký podíl z hodnoty Dovyrobit lze aktuálně vyrobit z dostupných přímých surovin (1. úroveň BOM).\nZelená = lze vyrobit vše, oranžová = částečně, červená = nedostatek materiálu.", false],
      'dostupne'   => ['Dostupné', 'Aktuální stav skladu (včetně rezervovaného). Kliknutím se rozbalí pohyby položky.', false],
      'cil'        => ['Cílový stav (rezervace)', "Celková poptávka po produktu z BOM kaskády.\nPro finální výrobky: denní spotřeba × cílové dny zásoby.\nPro komponenty: součet poptávky od všech rodičovských produktů.\nPokud existují rezervace, jsou zobrazeny v závorce.\nVztah: Dovyrobit = max(0, Cílový stav − dostupné), dostupné = stav − rezervace.", false],
      'dovyrobit'  => ['Dovyrobit', "Vychází z průměrné denní poptávky za nastavený počet dnů.\nU kořenových položek v auto režimu se násobí cílovým počtem dní zásoby, u komponent je cíl jen potřeba rodičů (dovyrobit rodiče × koeficient).\nOdečtou se aktuální zásoby mínus rezervace. U neskladových typů se cíl nekrátí o stav, jen se propaguje dál.\nBarevná stupnice (priorita): červená = vysoká, oranžová = střední, zelená = nízká.\nKliknutím se rozbalí strom poptávky.", false],
      'min_zasoba' => ['Min. zásoba', 'Minimální zásoba – pod tuto hranici se produkt dostane do plánu výroby. Dvojklikem upravíte.', false],
      'min_davka'  => ['Min. dávka', 'Nejmenší množství, které má smysl vyrobit najednou. Dvojklikem upravíte.', false],
      'krok'       => ['Krok výroby', 'Násobek, po kterém se vyrábí (např. po 25 ks). Dvojklikem upravíte.', false],
      'doba'       => ['Výrobní doba', 'Počet dní od zadání výroby do jejího dokončení. Dvojklikem upravíte.', false],
      'hodnota'    => ['Skladová hodnota', 'Jednotková skladová / nákladová hodnota v CZK. Oceňují se jí pohyby skladu a počítá marže. Dvojklikem upravíte.', false],
      'aktivni'    => ['Aktivní', 'Fajfka = aktivní, křížek = neaktivní (přeškrtnuté SKU, řadí se na konec). Kliknutím přepnete.', false],
      'poznamka'   => ['Poznámka', 'Volná poznámka k produktu. Dvojklikem upravíte.', false],
  ];
  if (!empty($isSuperadmin)) {
      $productColumns['smazat'] = ['Smazat', 'Smazat jde jen produkt, který není v kusovníku a nemá žádné pohyby skladu.', false];
  }
?>
<?php if (!$hasSearchActive): ?>
  <p class="muted">Zadejte parametry vyhledávání – seznam produktů se zobrazí hned během psaní.</p>
<?php elseif (empty($items)): ?>
  <p class="muted">Žádné produkty neodpovídají zadaným filtrům.</p>
<?php else: ?>
<p class="search-result-pill">Nalezeno <?= $resultCount ?><?= $resultCount >= 500 ? ' (zobrazeno prvních 500 – upřesněte hledání)' : '' ?></p>
<table class="products-table">
  <tr>
    <?php foreach ($productColumns as $key => [$label, $tip, $required]): ?>
      <th data-col="<?= $key ?>" draggable="true" title="<?= $e($tip . "\n\nHlavičku můžete přetáhnout na jiné místo.") ?>"><span class="col-grip" aria-hidden="true"><?= ikona('grip-vertical', 14) ?></span><?= $e($label) ?></th>
    <?php endforeach; ?>
    <th class="col-picker-th" data-col="_picker" title="Výběr zobrazených sloupců">
      <button type="button" class="col-picker-btn" aria-label="Výběr sloupců" aria-expanded="false"><?= ikona('columns-3', 16) ?></button>
      <div class="col-picker-menu" hidden>
        <div class="col-picker-title">Zobrazené sloupce</div>
        <?php foreach ($productColumns as $key => [$label, $tip, $required]): ?>
          <label title="<?= $e($tip) ?>"<?= $required ? ' class="is-required"' : '' ?>>
            <input type="checkbox" value="<?= $key ?>" checked<?= $required ? ' disabled' : '' ?> />
            <?= $e($label) ?><?= $required ? ' <span class="muted">(vždy)</span>' : '' ?>
          </label>
        <?php endforeach; ?>
        <button type="button" class="col-order-reset ikona-btn" title="Vrátit sloupce do výchozího pořadí"><?= ikona('rotate-ccw', 14) ?> Výchozí pořadí</button>
      </div>
    </th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr data-sku="<?= $e($it['sku']) ?>">
    <td class="sku-cell" data-sku="<?= $e($it['sku']) ?>">
      <span class="sku-toggle"><?= ikona('chevron-right', 14) ?></span>
      <span class="<?= (int)$it['aktivni'] ? '' : 'inactive-sku' ?>"><?= $e($it['sku']) ?></span>
    </td>
    <td class="editable" data-field="alt_sku" data-type="text" data-value="<?= $e($it['alt_sku'] ?? '') ?>">
      <?= isset($it['alt_sku']) && $it['alt_sku'] !== '' ? $e($it['alt_sku']) : '' ?>
    </td>
    <td class="editable" data-field="ean" data-type="text" data-value="<?= $e($it['ean'] ?? '') ?>">
      <?= isset($it['ean']) && $it['ean'] !== '' ? $e($it['ean']) : '' ?>
      <?php if (\App\Support\EanSvg::jePlatny((string)($it['ean'] ?? ''))): ?>
        <a class="ean-svg" href="/products/ean-svg?ean=<?= $e(rawurlencode((string)$it['ean'])) ?>&amp;sku=<?= $e(rawurlencode((string)$it['sku'])) ?>" title="Stáhnout čárový kód jako vektor (SVG)" aria-label="Stáhnout čárový kód (SVG)"><?= ikona('download', 14) ?></a>
      <?php endif; ?>
    </td>
    <td class="editable" data-field="znacka_id" data-type="select" data-options="brands" data-value="<?= (int)($it['znacka_id'] ?? 0) ?>"><?= $e($it['znacka'] ?? '') ?></td>
    <td class="editable" data-field="skupina_id" data-type="select" data-options="groups" data-value="<?= (int)($it['skupina_id'] ?? 0) ?>"><?= $e($it['skupina'] ?? '') ?></td>
    <td class="editable" data-field="typ" data-type="select" data-options="types" data-value="<?= $e($it['typ']) ?>"><?= $e($it['typ']) ?></td>
    <td class="editable" data-field="merna_jednotka" data-type="select" data-options="units" data-value="<?= $e($it['merna_jednotka']) ?>"><?= $e($it['merna_jednotka']) ?></td>
    <td class="editable" data-field="nazev" data-type="text" data-value="<?= $e($it['nazev']) ?>"><?= $e($it['nazev']) ?></td>
    <?php
      // Výrobní sloupce – stejné výpočty a barvy jako ve Výrobě (ProductionController::annotateProduction)
      $deficit = (float)($it['deficit'] ?? 0.0);
      $ratio = max(0.0, min(1.0, (float)($it['ratio'] ?? 0.0)));
      $ratioState = $ratio >= 0.85 ? 'critical' : ($ratio >= 0.5 ? 'warn' : 'ok');
      $materialRatio = max(0.0, min(1.0, (float)($it['material_availability_ratio'] ?? 1.0)));
      $materialState = $materialRatio >= 0.8 ? 'ok' : ($materialRatio >= 0.4 ? 'warn' : 'critical');
      $vyrobaClass = !empty($it['blocked']) ? ' is-blocked' : ($deficit > 0.0 ? ' needs-production' : '');
    ?>
    <td class="sku-vyroba-cell<?= $vyrobaClass ?>" data-sku="<?= $e($it['sku']) ?>" data-deficit="<?= $e($deficit) ?>">
      <div class="sku-availability">
        <div class="sku-availability-content">
          <span class="sku-vyroba-toggle"><?= ikona('chevron-right', 14) ?></span>
          <span class="<?= (int)$it['aktivni'] ? '' : 'inactive-sku' ?>"><?= $e($it['sku']) ?></span>
        </div>
        <?php if ($deficit > 0): ?>
          <div class="sku-availability-bar" title="Dostupnost materiálu 1. úrovně: <?= (int)round($materialRatio * 100) ?> %">
            <span data-state="<?= $materialState ?>" style="width: <?= (int)round($materialRatio * 100) ?>%"></span>
          </div>
        <?php endif; ?>
      </div>
    </td>
    <td class="qty-cell<?= $vyrobaClass ?>">
      <span class="available-cell" data-sku="<?= $e($it['sku']) ?>">
        <span class="available-toggle"><?= ikona('chevron-right', 14) ?></span>
        <span><?= $formatQty((float)($it['available'] ?? 0) + (float)($it['reservations'] ?? 0)) ?></span>
      </span>
    </td>
    <td class="qty-cell<?= $vyrobaClass ?>">
      <?= $formatQty($it['target'] ?? 0, 0) ?>
      <?php if (($it['reservations'] ?? 0) > 0): ?>
        <br><span class="muted" style="font-size:0.85em;">(<?= $formatQty($it['reservations']) ?>)</span>
      <?php endif; ?>
    </td>
    <td class="qty-cell deficit-cell<?= $vyrobaClass ?>">
      <div class="deficit-with-bar">
        <span class="demand-cell" data-sku="<?= $e($it['sku']) ?>">
          <span class="demand-toggle"><?= ikona('chevron-right', 14) ?></span>
          <span><?= $formatQty($deficit, 0) ?></span>
        </span>
        <div class="ratio-bar"><span data-state="<?= $ratioState ?>" style="width: <?= (int)round($ratio * 100) ?>%"></span></div>
      </div>
    </td>
    <td class="editable" data-field="min_zasoba" data-type="number" data-step="0.001" data-value="<?= $e($it['min_zasoba']) ?>"><?= (int)$it['min_zasoba'] ?></td>
    <td class="editable" data-field="min_davka" data-type="number" data-step="0.001" data-value="<?= $e($it['min_davka']) ?>"><?= (int)$it['min_davka'] ?></td>
    <td class="editable" data-field="krok_vyroby" data-type="number" data-step="0.001" data-value="<?= $e($it['krok_vyroby']) ?>"><?= (int)$it['krok_vyroby'] ?></td>
    <td class="editable" data-field="vyrobni_doba_dni" data-type="number" data-step="1" data-value="<?= $e($it['vyrobni_doba_dni']) ?>"><?= $e($it['vyrobni_doba_dni']) ?></td>
    <td class="editable" data-field="skl_hodnota" data-type="number" data-step="0.01" data-value="<?= $e($it['skl_hodnota']) ?>"><?= $e($it['skl_hodnota']) ?></td>
    <td class="editable" data-field="aktivni" data-type="select" data-options="active" data-value="<?= (int)$it['aktivni'] ?>"><?= (int)$it['aktivni']
      ? '<span class="active-mark active-yes" title="Aktivní – kliknutím změníte">' . ikona('check', 16) . '</span>'
      : '<span class="active-mark active-no" title="Neaktivní – kliknutím změníte">' . ikona('x', 16) . '</span>' ?></td>
    <td class="editable" data-field="poznamka" data-type="textarea" data-value="<?= $e($it['poznamka'] ?? '') ?>"><?= $e($it['poznamka'] ?? '') ?></td>
    <?php if (!empty($isSuperadmin)): ?>
    <td class="del-cell" style="text-align:center;">
      <?php if ((int)($it['can_delete'] ?? 0) === 1): ?>
        <form method="post" action="/products/delete" style="margin:0;" onsubmit="return confirm('Opravdu smazat produkt <?= $e($it['sku']) ?>? Akce je nevratná.');">
          <input type="hidden" name="sku" value="<?= $e($it['sku']) ?>">
          <button type="submit" class="del-btn" title="Smazat produkt (není v kusovníku a nemá pohyby)"><?= ikona('x', 14) ?></button>
        </form>
      <?php else: ?>
        <span class="del-disabled" title="Nelze smazat — produkt je v kusovníku (BOM) nebo má historické pohyby">–</span>
      <?php endif; ?>
    </td>
    <?php endif; ?>
    <td class="col-picker-td"></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
