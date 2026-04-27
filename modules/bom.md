# Modul: bom

## Co modul dělá

Kusovníky (bill of materials) — strom rodič → potomek s koeficientem (kolik potomků na 1 jednotku rodiče). Slouží pro:

1. **Výrobu** — demand tree (rekurzivní rozpad potřeby na komponenty)
2. **Nonstock produkty** — v analytics při výpočtu nákladu se rekurzivně rozpadají na skladové komponenty

Editor je integrovaný do modulu `produkty` (záložka "BOM strom" na produktu). `BomController` je tenký wrapper pro CSV import/export.

## Kam sahá v kódu

- `src/Controller/BomController.php` — tenký wrapper (redirect na /products, CSV in/out)
- `src/Controller/ProductsController.php` — editor stromu (metody `bomTree`, `bomAdd`, `bomDelete`)
- `src/Service/StockService.php` — cache BOM (`$bomCache`) pro demand tree
- `src/Controller/AnalyticsController.php` — `loadBomCache()`, `calculateItemCost()` pro rozpad nonstock

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/bom` | `BomController::index` (redirect na `/products#bom-import`) |
| GET | `/bom/export` | `BomController::exportCsv` |
| POST | `/bom/import` | `BomController::importCsv` |

Úpravy stromu přes `ProductsController`: `/products/bom-tree`, `/products/bom/add`, `/products/bom/delete`.

## Tabulky

- `bom` (id, rodic_sku, potomek_sku, koeficient DECIMAL(18,6), merna_jednotka_potomka)

## Závislosti

- Konzumuje: `produkty` (SKU musí existovat jako rodič i potomek)
- Konzumují: `vyroba` (demand tree), `analytics` (rozpad nákladu pro nonstock), `sklad` (výrobní korekce odečítají potomky)

## Aktuální stav

✅ **Hotovo**
- CSV import/export
- Hlavička CSV: `rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka`
- **Replace per parent** — při re-uploadu se všechny existující řádky daného rodiče smažou a vloží nové
- Jinou jednotku potomka než rodiče lze definovat (`merna_jednotka_potomka`)
- Tree editor v produktu (add/delete potomka)

⚠️ **Známé dluhy / gotchy**
- **Cyklické kusovníky nejsou ošetřené** — pokud by někdo nastavil A → B → A, rekurzivní funkce (`StockService::demandTree`, `AnalyticsController::calculateItemCost`) dojdou k infinite loop / stack overflow
- **Nonstock typy** — BOM rozpad v analytics se spouští jen pro produkty s `product_types.is_nonstock = 1`. Pro "běžné" produkty se vezme `skl_hodnota` přímo. Hranice mezi typ = `produkt` (skladový) a nonstock typy (např. `baleni`, `sada`) je zásadní pro správnost výpočtů.
- **Historické BOM** — když se změní kusovník, všechny historické výroby/analytiky pracují s aktuálním BOM (BOM je "live", ne versionovaný)
- Duplicitní rodič+potomek nejsou explicitně zakázané v DB (není UNIQUE klíč na `(rodic_sku, potomek_sku)`)

❌ **Nezačato**
- Detekce cyklů při uložení
- Versioning BOM (historie změn kusovníku)
- UI pro masové přesuny (změna potomka u všech rodičů)
