# Modul: produkty

## Co modul dělá

CRUD katalogu produktů. Produkt = SKU + název + typ + měrná jednotka + sklad. konvence, min_zasoba, krok_vyroby, značka, skupina. Podporuje `alt_sku` (mapování na starší/alternativní označení). CSV import/export (idempotentní — `ON DUPLICATE KEY UPDATE`). Inline editace v tabulce. Integrace s BOM (kusovníky — přidání/odebrání potomků).

Zobrazuje aktuální stav skladu pro každé SKU (počítáno z `inventura_stavy` + `polozky_pohyby`).

## Kam sahá v kódu

- `src/Controller/ProductsController.php` — největší controller v projektu (~2500 řádků)
- `src/Service/StockService.php` — výpočty stavů a dovyrobit
- `views/products_index.php` — formuláře, hledání, BOM editor (JS), živé hledání + výběr sloupců (JS na konci)
- `views/products_results.php` — fragment s výsledky (tabulka, definice sloupců s tooltipy, menu výběru sloupců); vkládá ho index a vrací `/products?partial=1`

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/products` | `ProductsController::index` (s `partial=1` jen fragment výsledků pro živé hledání) |
| GET | `/products/export` | `ProductsController::exportCsv` |
| GET | `/products/ean-svg?ean=&sku=` | `ProductsController::eanSvg` (stažení čárového kódu jako SVG) |
| POST | `/products/import` | `ProductsController::importCsv` |
| POST | `/products/create` | `ProductsController::create` |
| POST | `/products/delete` | `ProductsController::delete` (jen superadmin) |
| POST | `/products/update` | `ProductsController::inlineUpdate` |
| GET | `/products/search` | `ProductsController::search` |
| GET | `/products/bom-tree` | `ProductsController::bomTree` |
| POST | `/products/bom/add` | `ProductsController::bomAdd` |
| POST | `/products/bom/delete` | `ProductsController::bomDelete` |

## Tabulky

- `produkty` (id, sku UNIQUE, alt_sku UNIQUE, ean UNIQUE, nazev, typ, merna_jednotka, skl_hodnota, dovyrobit, min_zasoba, nast_zasob ENUM('auto','manual'), min_davka, krok_vyroby, vyrobni_doba_dni, aktivni, znacka_id FK, skupina_id FK, poznamka)
- Číselníky (v `nastaveni.md`): `produkty_znacky`, `produkty_skupiny`, `produkty_merne_jednotky`, `product_types`

## Závislosti

- Konzumuje: `nastaveni` (značky, skupiny, typy, jednotky)
- Konzumují: `bom`, `sklad`, `vyroba`, `rezervace`, `analytics`, `import`

## Aktuální stav

✅ **Hotovo**
- Tabulka produktů s inline editací (všechny klíčové sloupce editovatelné)
- CSV export a import (`ON DUPLICATE KEY UPDATE` — safe pro opakovaný import)
- Hlavička CSV: `sku,alt_sku,ean,znacka,skupina,typ,merna_jednotka,nazev,min_zasoba,nast_zasob,min_davka,krok_vyroby,vyrobni_doba_dni,skl_hodnota,aktivni,poznamka`
- BOM tree view + add/delete potomků (úzká integrace s modulem `bom`)
- Search endpoint pro autocomplete (používá např. modul `rezervace`)
- Zobrazení aktuální zásoby, rezervací, dovyrobit
- Filtr aktivní/neaktivní, značka, skupina, typ
- **Živé hledání** (od 2026-10-01, vzor Pohyby): bez tlačítka, debounce 250 ms při psaní, select hned. Výsledky se načtou jako HTML fragment (`?partial=1`) a vymění v `#product-results`; URL se drží přes `history.replaceState` (reload/návrat ukáže stejný výpis). Delegace událostí (BOM strom, inline edit) je proto na `#product-results`, ne na tabulce; po výměně se posílá událost `products:replaced`. Bez filtrů se nic nevypisuje, strop 500 řádků (hláška).
- **Výběr sloupců**: poslední sloupec tabulky (ikona `columns-3`) otevře seznam sloupců s checkboxy. SKU a Název jsou povinné (bez tlačítka „Zobrazit vše“ – zrušeno na přání). Skryté sloupce se ukládají per prohlížeč v `localStorage` (`gworm.produkty.skryteSloupce`) a skrývají se CSS `nth-child` podle pořadí hlavičky — při přidání sloupce stačí upravit `$productColumns` a buňku řádku ve stejném pořadí.
- **Aktivní** se zobrazuje jako Lucide fajfka (zelená) / křížek (červený) místo Ano/Ne – výslovné přání uživatele (2026-10-01), výjimka z obecného pravidla „stav = slovo Ano/Ne" v CLAUDE.md. Jedno kliknutí otevře přepínač fajfka/křížek, volba se hned uloží (`/products/update`, pole `aktivni`) a přepne i přeškrtnutí SKU; Esc / klik mimo = beze změny. Dvojklik na buňku nic nedělá.
- **Přetahování sloupců** (2026-10-01): hlavičky jsou `draggable`, po najetí se ukáže úchyt (Lucide `grip-vertical`) a kurzor ruky, tažený sloupec zprůhlední, místo dopadu ukazuje oranžová svislá čára (před/za podle poloviny hlavičky). Pořadí se ukládá per prohlížeč v `localStorage` (`gworm.produkty.poradiSloupcu`), prázdné = výchozí. Server vždy vykreslí výchozí pořadí z `$productColumns`, JS (`applyOrder`) pak přeskládá buňky v řádcích (rozbalené detailní řádky s jednou buňkou přeskočí) – volá se před `applyColumns`, protože skrývání jde přes `nth-child`. Sloupce přidané později se vloží za svého výchozího předchůdce. Výběr sloupců (ikona) zůstává vždy poslední a má tlačítko „Výchozí pořadí".
- **Tooltipy hlaviček**: `title` u každého `<th>` z `$productColumns` (popis, co sloupec znamená).
- **Výrobní sloupce převzaté z Výroby** (2026-10-01, 1. krok slučování Výroba → Produkty): za Názvem jsou **SKU výroba** (klik = strom potomků s potřebou: Dostupné / Cílový stav / Chybí, `/products/bom-tree?required=`; proužek dostupnosti materiálu 1. úrovně), **Dostupné** (klik = pohyby položky, `/production/movements`), **Cílový stav (rezervace)** a **Dovyrobit** (proužek priority, klik = strom poptávky, `/production/demand-tree`). Buňky mají barvu `needs-production` / `is-blocked` jako řádky ve Výrobě. Výpočet je sdílený: `fetchProducts()` volá `StockService::recalcDovyrobit()` a `ProductionController::annotateProduction()` (stejná metoda jako `plans()`), ověřeno na 72 SKU typu produkt – dovyrobit shodné s Výrobou. JS rozbalování je samostatný `<script>` na konci `products_index.php`. Řazení zůstává podle Produktů (aktivní, název), ne podle priority jako ve Výrobě. Sloupec Akce a tabulka „Pohyby skladů" se záměrně nepřenášely. Zjednodušení sloupců uživatel navrhne později.
- **Čárový kód EAN jako vektor** (2026-10-05): za platným EAN-13 / EAN-8 ve sloupci EAN je ikona `download`, která stáhne SVG (`/products/ean-svg`, soubor `ean-<EAN>-<SKU>.svg`). Generuje `src/Support/EanSvg.php` – bez závislostí, číslice jsou vložené křivky písma OCR-B (CTAN ocr-b-outline, volná licence), takže SVG nepotřebuje font. Rozměr 37,29 × 18,81 mm (modul 0,33 mm = 100 %), výška čárek zkrácená na 48 modulů podle vzoru od uživatele (norma má cca 69) – konstanty nahoře ve třídě. Ikona se ukáže jen u EAN s platnou kontrolní číslicí (`EanSvg::jePlatny`, v JS `isValidEan` pro překreslení po inline editaci). Pozn.: `header_register_callback` v `bootstrap.php` přepisuje Content-Type všech odpovědí na `text/html` (i CSV export) – stažení přes `Content-Disposition` to nevadí.
- **Mazání produktů** (jen superadmin): křížek ✕ v tabulce jen u produktů, které **nejsou v BOM** (rodič ani potomek) a **nemají žádné pohyby** (`polozky_pohyby`) — kontrola přes `sku` i `alt_sku`. Flag `can_delete` počítá `annotateDeletable()`, server v `delete()` kritéria znovu ověří (nespoléhat na klienta). Pozn.: BOM/pohyby jsou string reference bez FK, takže gate je logický, ne přes FK.

⚠️ **Známé dluhy / gotchy**
- **`skl_hodnota` je aktuální, ne historická** (jednotková skladová/nákladová hodnota pro oceňování). Mění se v čase (např. při změně výrobní ceny). **Analytics to používá jako náklad i pro staré prodeje** → zkreslení historických marží. Viz `analytics.md`.
- **`alt_sku` + SKU namespace** — join v `polozky_eshop LEFT JOIN produkty p ON p.sku = pe.sku OR p.alt_sku = pe.sku` může **duplikovat řádky**, pokud jedno pe.sku matchne víc produktů (kolize SKU s alt_sku jiného produktu). Viděno v analytics (2 případy v Q1/2026, zanedbatelné).
- **`skl_hodnota`** je v UI popsaná jako "jednotková skladová hodnota / nákladová cena" — nikde není explicitní, jestli je s DPH nebo bez
- **`dovyrobit`** se přepočítává přes `StockService::recalcDovyrobit()` **při každém výpisu Produktů** (i při živém hledání) – zapisuje do celé tabulky `produkty` a trvá cca 2–3 s (měřeno z lokálu proti DB na Webglobe). Souběžné přepočty se v transakci serializují zámky. Pokud bude výpis pomalý: počítat jen při zobrazených výrobních sloupcích nebo přepočet přesunout mimo výpis.
- `nast_zasob='auto'` vs `'manual'` — auto počítá `min_zasoba` z historie prodejů (okno z `nastaveni_global.okno_pro_prumer_dni`)

❌ **Nezačato**
- Historie změn produktu (kdo a kdy změnil cenu/parametry)
- Obrazová dokumentace SKU
