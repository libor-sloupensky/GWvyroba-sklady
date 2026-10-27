<?php
  // Výsledky vyhledávání produktů – vkládá products_index.php a vrací je i /products?partial=1 (živé hledání).
  $hasSearchActive = (bool)($hasSearch ?? false);
  $resultCount = (int)($resultCount ?? 0);
  $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  // Formát množství jako ve Výrobě: mezera jako oddělovač tisíců, bez koncových nul
  $formatQty = static function ($value, int $decimals = 4): string {
      $formatted = number_format((float)$value, max(0, $decimals), ',', ' ');
      if ($decimals > 0) {
          $formatted = rtrim(rtrim($formatted, '0'), ',');
      }
      return $formatted === '' ? '0' : $formatted;
  };
  // Množství s měrnou jednotkou (Dostupné, Cílový stav, Dovyrobit), ať je hned jasné, o jakou hodnotu jde
  $qtyUnit = static fn($unit): string => trim((string)$unit) !== '' ? ' <span class="qty-unit">' . htmlspecialchars(trim((string)$unit), ENT_QUOTES, 'UTF-8') . '</span>' : '';
  // Kusové jednotky bez desetin, kg/l apod. s desetinami
  $qtyDecimals = static fn($unit): int => in_array(mb_strtolower(trim((string)$unit)), ['ks', 'bal', 'balení', 'karton', ''], true) ? 0 : 3;

  // Sloupce tabulky: klíč => [hlavička, tooltip, povinný, výchozí viditelnost]. Pořadí = výchozí pořadí = pořadí buněk v řádku.
  // Povinné sloupce (SKU, Název) nejdou ve výběru sloupců skrýt. Výchozí viditelnost platí, dokud si uživatel výběr nezmění.
  $productColumns = [
      'sku'        => ['SKU', 'Interní kód produktu. Kliknutím rozbalíte kusovník (z čeho se produkt skládá).', true, true],
      'alt_sku'    => ['Alt SKU', 'Alternativní nebo starší označení, pod kterým produkt chodí z e-shopu či Pohody. Dvojklikem upravíte.', false, false],
      'ean'        => ['EAN', 'Čárový kód produktu. Dvojklikem upravíte. Ikona za platným EAN-13 / EAN-8 stáhne čárový kód jako vektor (SVG).', false, false],
      'znacka'     => ['Značka', 'Značka produktu (číselník v Nastavení). Dvojklikem upravíte.', false, true],
      'skupina'    => ['Skupina', 'Produktová skupina (číselník v Nastavení). Dvojklikem upravíte.', false, true],
      'typ'        => ['Typ', 'Typ položky – produkt, karton, surovina, obal … Určuje, jak se s položkou počítá ve výrobě a analýze. Dvojklikem upravíte.', false, true],
      'mj'         => ['MJ', 'Měrná jednotka skladové evidence (ks, kg, bal …). Dvojklikem upravíte.', false, false],
      'nazev'      => ['Název', 'Název produktu. Dvojklikem upravíte.', true, true],
      // Výrobní sloupce převzaté z Výroby (/production/plans)
      'sku_vyroba' => ['SKU výroba', "Kliknutím se rozbalí strom potomků se skladovými dostupnostmi (dostupné, cílový stav, chybí).\nBarevná stupnice ukazuje, jaký podíl z hodnoty Dovyrobit lze aktuálně vyrobit z dostupných přímých surovin (1. úroveň BOM).\nZelená = lze vyrobit vše, oranžová = částečně, červená = nedostatek materiálu.", false, false],
      'dostupne'   => ['Dostupné', 'Aktuální stav skladu (včetně rezervovaného). Kliknutím se rozbalí pohyby položky.', false, true],
      'cil'        => ["Cílový stav\n(rezervace)", "Celková poptávka po produktu z BOM kaskády.\nPro finální výrobky: denní spotřeba × cílové dny zásoby.\nPro komponenty: součet poptávky od všech rodičovských produktů.\nPokud existují rezervace, jsou zobrazeny v závorce.\nVztah: Dovyrobit = max(0, Cílový stav − dostupné), dostupné = stav − rezervace.", false, false],
      'dovyrobit'  => ['Dovyrobit', "Vychází z průměrné denní poptávky za nastavený počet dnů.\nU kořenových položek v auto režimu se násobí cílovým počtem dní zásoby, u komponent je cíl jen potřeba rodičů (dovyrobit rodiče × koeficient).\nOdečtou se aktuální zásoby mínus rezervace. U neskladových typů se cíl nekrátí o stav, jen se propaguje dál.\nBarevná stupnice (priorita): červená = vysoká, oranžová = střední, zelená = nízká.\nKliknutím se rozbalí strom poptávky.", false, true],
      'min_zasoba' => ['Min. zásoba', 'Minimální zásoba – pod tuto hranici se produkt dostane do plánu výroby. Dvojklikem upravíte.', false, false],
      'min_davka'  => ['Min. dávka', 'Nejmenší množství, které má smysl vyrobit najednou. Dvojklikem upravíte.', false, false],
      'krok'       => ['Krok výroby', 'Násobek, po kterém se vyrábí (např. po 25 ks). Dvojklikem upravíte.', false, false],
      'doba'       => ['Výrobní doba', 'Počet dní od zadání výroby do jejího dokončení. Dvojklikem upravíte.', false, false],
      'hodnota'    => ['Cena/kus', 'Skladová hodnota: jednotková skladová / nákladová hodnota v CZK. Oceňují se jí pohyby skladu a počítá marže. Dvojklikem upravíte.', false, false],
      'poznamka'   => ['Poznámka', 'Volná poznámka k produktu. Dvojklikem upravíte.', false, false],
  ];
  // Aktivní + Smazat v jednom sloupci na konci (Smazat jen pro superadmina)
  $productColumns['aktivni'] = !empty($isSuperadmin)
      ? ["Aktivní\nSmazat", "Fajfka = aktivní, křížek = neaktivní (přeškrtnuté SKU, řadí se na konec). Kliknutím přepnete.\nKřížek v kroužku smaže produkt – jen pokud není v kusovníku a nemá žádné pohyby skladu.", false, true]
      : ['Aktivní', 'Fajfka = aktivní, křížek = neaktivní (přeškrtnuté SKU, řadí se na konec). Kliknutím přepnete.', false, true];
?>
<?php if (!$hasSearchActive): ?>
  <p class="muted">Zadejte parametry vyhledávání – seznam produktů se zobrazí hned během psaní.</p>
<?php elseif (empty($items)): ?>
  <p class="muted">Žádné produkty neodpovídají zadaným filtrům.</p>
<?php else: ?>
<div class="products-toolbar">
  <p class="search-result-pill">Nalezeno <?= $resultCount ?><?= $resultCount >= 500 ? ' (zobrazeno prvních 500 – upřesněte hledání)' : '' ?></p>
  <div class="col-picker">
    <button type="button" class="col-picker-btn ikona-btn" aria-expanded="false" title="Výběr zobrazených sloupců"><?= ikona('columns-3', 16) ?> Sloupce</button>
    <div class="col-picker-menu" hidden>
      <div class="col-picker-title">Zobrazené sloupce</div>
      <?php foreach ($productColumns as $key => [$label, $tip, $required, $default]): ?>
        <label title="<?= $e($tip) ?>"<?= $required ? ' class="is-required"' : '' ?>>
          <input type="checkbox" value="<?= $key ?>" data-default="<?= $default ? '1' : '0' ?>" checked<?= $required ? ' disabled' : '' ?> />
          <?= $e(str_replace("\n", ' / ', $label)) ?><?= $required ? ' <span class="muted">(vždy)</span>' : '' ?>
        </label>
      <?php endforeach; ?>
      <button type="button" class="col-order-reset ikona-btn" title="Vrátit výchozí sloupce i jejich pořadí"><?= ikona('rotate-ccw', 14) ?> Výchozí sloupce</button>
    </div>
  </div>
</div>
<table class="products-table">
  <tr>
    <?php foreach ($productColumns as $key => [$label, $tip, $required]): ?>
      <th data-col="<?= $key ?>" draggable="true" title="<?= $e($tip . "\n\nHlavičku můžete přetáhnout na jiné místo.") ?>"><span class="col-grip" aria-hidden="true"><?= ikona('grip-vertical', 14) ?></span><?= nl2br($e($label), false) ?></th>
    <?php endforeach; ?>
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
    <?php $unit = (string)($it['merna_jednotka'] ?? ''); $dec = $qtyDecimals($unit); ?>
    <?php if (!empty($it['is_nonstock'])): ?>
    <td class="qty-cell"><span class="nonstock-label" title="Tato položka se neeviduje jako skladová – nemá stav ani zásobu, sklad se odepisuje u jejích komponent.">neskladové</span></td>
    <td class="qty-cell"><span class="muted">–</span></td>
    <td class="qty-cell"><span class="muted">–</span></td>
    <?php else: ?>
    <td class="qty-cell<?= $vyrobaClass ?>">
      <span class="available-cell" data-sku="<?= $e($it['sku']) ?>">
        <span class="available-toggle"><?= ikona('chevron-right', 14) ?></span>
        <span><?= $formatQty((float)($it['available'] ?? 0) + (float)($it['reservations'] ?? 0)) ?><?= $qtyUnit($unit) ?></span>
      </span>
    </td>
    <td class="qty-cell<?= $vyrobaClass ?>">
      <?= $formatQty($it['target'] ?? 0, $dec) ?><?= $qtyUnit($unit) ?>
      <?php if (($it['reservations'] ?? 0) > 0): ?>
        <br><span class="muted" style="font-size:0.85em;">(<?= $formatQty($it['reservations']) ?><?= $qtyUnit($unit) ?>)</span>
      <?php endif; ?>
    </td>
    <td class="qty-cell deficit-cell<?= $vyrobaClass ?>">
      <div class="deficit-with-bar">
        <span class="demand-cell" data-sku="<?= $e($it['sku']) ?>">
          <span class="demand-toggle"><?= ikona('chevron-right', 14) ?></span>
          <span><?= $formatQty($deficit, $dec) ?><?= $qtyUnit($unit) ?></span>
        </span>
        <div class="ratio-bar"><span data-state="<?= $ratioState ?>" style="width: <?= (int)round($ratio * 100) ?>%"></span></div>
      </div>
    </td>
    <?php endif; ?>
    <td class="editable" data-field="min_zasoba" data-type="number" data-step="0.001" data-value="<?= $e($it['min_zasoba']) ?>"><?= $e($formatQty($it['min_zasoba'])) ?></td>
    <td class="editable" data-field="min_davka" data-type="number" data-step="0.001" data-value="<?= $e($it['min_davka']) ?>"><?= $e($formatQty($it['min_davka'])) ?></td>
    <td class="editable" data-field="krok_vyroby" data-type="number" data-step="0.001" data-value="<?= $e($it['krok_vyroby']) ?>"><?= $e($formatQty($it['krok_vyroby'])) ?></td>
    <td class="editable" data-field="vyrobni_doba_dni" data-type="number" data-step="1" data-value="<?= $e($it['vyrobni_doba_dni']) ?>"><?= $e($it['vyrobni_doba_dni']) ?></td>
    <td class="editable" data-field="skl_hodnota" data-type="number" data-step="0.01" data-value="<?= $e($it['skl_hodnota']) ?>"><?= $e($it['skl_hodnota']) ?></td>
    <td class="editable" data-field="poznamka" data-type="textarea" data-value="<?= $e($it['poznamka'] ?? '') ?>"><?= $e($it['poznamka'] ?? '') ?></td>
    <td class="aktivni-cell">
      <?php // Aktivní = přepínač (zapnutý zelený / vypnutý šedý), kliknutím se hned přepne a uloží ?>
      <span class="editable active-switch<?= (int)$it['aktivni'] ? ' is-on' : '' ?>" data-field="aktivni" data-type="select" data-options="active" data-value="<?= (int)$it['aktivni'] ?>" role="switch" aria-checked="<?= (int)$it['aktivni'] ? 'true' : 'false' ?>" title="<?= (int)$it['aktivni'] ? 'Aktivní – kliknutím vypnete' : 'Neaktivní – kliknutím zapnete' ?>"><?= ikona((int)$it['aktivni'] ? 'toggle-right' : 'toggle-left', 22) ?></span>
      <?php if (!empty($isSuperadmin)): ?>
        <span class="del-slot">
        <?php if ((int)($it['can_delete'] ?? 0) === 1): ?>
          <form method="post" action="/products/delete" class="del-form" onsubmit="return confirm('Opravdu smazat produkt <?= $e($it['sku']) ?>? Akce je nevratná.');">
            <input type="hidden" name="sku" value="<?= $e($it['sku']) ?>">
            <button type="submit" class="del-btn" title="Smazat produkt (není v kusovníku a nemá pohyby)"><?= ikona('trash-2', 16) ?></button>
          </form>
        <?php else: ?>
          <span class="del-disabled" title="Nelze smazat – produkt <?= $e($it['delete_block'] ?? 'je používán') ?>"><?= ikona('trash-2', 16) ?></span>
        <?php endif; ?>
        </span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
