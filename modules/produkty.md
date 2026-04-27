# Modul: produkty

## Co modul dělá

CRUD katalogu produktů. Produkt = SKU + název + typ + měrná jednotka + sklad. konvence, min_zasoba, krok_vyroby, značka, skupina. Podporuje `alt_sku` (mapování na starší/alternativní označení). CSV import/export (idempotentní — `ON DUPLICATE KEY UPDATE`). Inline editace v tabulce. Integrace s BOM (kusovníky — přidání/odebrání potomků).

Zobrazuje aktuální stav skladu pro každé SKU (počítáno z `inventura_stavy` + `polozky_pohyby`).

## Kam sahá v kódu

- `src/Controller/ProductsController.php` — největší controller v projektu (~2500 řádků)
- `src/Service/StockService.php` — výpočty stavů a dovyrobit
- `views/products_index.php` — tabulka + formulář + BOM editor

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/products` | `ProductsController::index` |
| GET | `/products/export` | `ProductsController::exportCsv` |
| POST | `/products/import` | `ProductsController::importCsv` |
| POST | `/products/create` | `ProductsController::create` |
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

⚠️ **Známé dluhy / gotchy**
- **`skl_hodnota` je aktuální, ne historická** (jednotková skladová/nákladová hodnota pro oceňování). Mění se v čase (např. při změně výrobní ceny). **Analytics to používá jako náklad i pro staré prodeje** → zkreslení historických marží. Viz `analytics.md`.
- **`alt_sku` + SKU namespace** — join v `polozky_eshop LEFT JOIN produkty p ON p.sku = pe.sku OR p.alt_sku = pe.sku` může **duplikovat řádky**, pokud jedno pe.sku matchne víc produktů (kolize SKU s alt_sku jiného produktu). Viděno v analytics (2 případy v Q1/2026, zanedbatelné).
- **`skl_hodnota`** je v UI popsaná jako "jednotková skladová hodnota / nákladová cena" — nikde není explicitní, jestli je s DPH nebo bez
- **`dovyrobit`** se přepočítává v `ProductionController::recalcDovyrobit()` — když se změní `min_zasoba`/`min_davka`, je třeba reload výroby
- `nast_zasob='auto'` vs `'manual'` — auto počítá `min_zasoba` z historie prodejů (okno z `nastaveni_global.okno_pro_prumer_dni`)

❌ **Nezačato**
- Historie změn produktu (kdo a kdy změnil cenu/parametry)
- Obrazová dokumentace SKU
