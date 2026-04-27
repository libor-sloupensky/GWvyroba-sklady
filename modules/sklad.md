# Modul: sklad

## Co modul dělá

Fyzický sklad — evidence stavů zásob přes **inventury** (otevřít → zapisovat položky → zavřít) a **pohyby** (`polozky_pohyby`: `inventura` / `vyroba` / `korekce` / `odpis`).

**Klíčový princip:** aktuální stav SKU = poslední uzavřená inventura (`inventura_stavy.stav`) **plus** součet pohybů od jejího uzavření. Stavy se neúdržbovsky aktualizují — místo toho se inkrementálně dopočítávají z event-log stylu `polozky_pohyby`.

Výroba (viz `vyroba.md`) píše do stejných pohybů (+rodič, -potomci přes korekci).

## Kam sahá v kódu

- `src/Controller/InventoryController.php` — CRUD inventur, zápis položek, zavírání/otevírání
- `src/Service/StockService.php` — výpočet stavů, `$baselineCache`, helpers pro ostatní moduly
- `src/Controller/ProductionController.php` — metody `movements`, `filteredMovements` čtou `polozky_pohyby` (filtr pohybů skladu defaultně zapnut — commit d16e32a)
- `views/inventory.php` — UI inventur

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/inventory` | `InventoryController::index` |
| POST | `/inventory/start` | `InventoryController::start` |
| POST | `/inventory/close` | `InventoryController::close` |
| POST | `/inventory/entry` | `InventoryController::addEntry` |
| POST | `/inventory/delete` | `InventoryController::delete` |
| POST | `/inventory/reopen` | `InventoryController::reopen` |

Pohyby skladu jsou dostupné přes `vyroba` modul (viz `vyroba.md` — `/production/movements`, `/production/filtered-movements`).

## Tabulky

- `inventury` (id, opened_at DATETIME, closed_at DATETIME NULL, baseline_inventory_id, poznamka)
- `inventura_polozky` (inventura_id FK, sku, mnozstvi, poznamka, created_at) — syrové zápisy
- `inventura_stavy` (inventura_id FK + sku, stav DECIMAL(18,6)) — **PK je `(inventura_id, sku)`**, vypočtené stavy (při zavření)
- `polozky_pohyby` (id, datum DATETIME, sku, mnozstvi, typ_pohybu ENUM, poznamka, ref_id) — event log

## Závislosti

- Konzumuje: `produkty` (SKU)
- Konzumují: `produkty` (UI zobrazení stavu), `vyroba` (píše do `polozky_pohyby`), `rezervace` (dostupný stav = stav - rezervace), `analytics` (nepřímo přes `skl_hodnota`)

## Aktuální stav

✅ **Hotovo**
- Inventura: otevřít / zapisovat / zavřít / znovu otevřít
- **Baseline inventory** — nová inventura může navázat na starší (`baseline_inventory_id`), stavy pak = baseline + pohyby mezi nimi + nové položky
- Inkrementální výpočet stavu z `inventura_stavy` + `polozky_pohyby`
- Pohyby typu `inventura` / `vyroba` / `korekce` / `odpis`
- `ref_id` pro tracebak (hlavně u výroby — původní event → rodič + potomci)
- Inventarizovaný stav zobrazen modře v pohybech (commit ed4ee34)
- Filtr pohybů skladu defaultně zapnutý (commit d16e32a)
- Oprava: inventarizovaný stav z `inventura_stavy` místo přepočtu z pohybů (commit 34350e8)

⚠️ **Známé dluhy / gotchy**
- **Reopen inventury** vrací stav do `inventura_polozky` a smaže `inventura_stavy` — ale pokud mezitím proběhly pohyby s `ref_id` vázaným na zavřenou inventuru, data mohou být nekonzistentní
- **DECIMAL(18,6) pro stav vs. DECIMAL(18,3) pro množství** — při sčítání pozor na truncation
- **Historické stavy** se pro konkrétní datum počítají rekurzivně — u SKU s tisíci pohybů to může být pomalé. StockService má statické cache per request.
- Pohyb typu `odpis` je "odečet bez odpovídajícího příjmu" — nepoužívá se často, ale existuje
- `inventura_polozky.mnozstvi` může být i záporné? (viz UI — záleží na zápisu)

❌ **Nezačato**
- Report srovnání fyzický vs. systémový stav pro konkrétní datum
- Vícedivizové sklady (všechno je jeden sklad)
