# Modul: vyroba

## Co modul dělá

Plánování a evidence výroby. Systém spočítá **demand tree** — co je potřeba vyrobit (na základě BOM, aktuálního stavu, `min_zasoba`, rezervací a historické spotřeby) a umožní uživateli zapsat skutečnou výrobu. Při zápisu výroby:

1. Přičte vyrobené množství rodiči (`polozky_pohyby` typ `vyroba`, kladné množství)
2. Odečte spotřebované potomky dle BOM (`polozky_pohyby` typ `korekce`, záporné množství)
3. Propojí pohyby přes `ref_id` pro možnost reverzovat

Modul také zobrazuje historii pohybů skladu (vizuálně propojené s inventurou — viz `sklad.md`).

## Kam sahá v kódu

- `src/Controller/ProductionController.php` — plány, zápis, pohyby (~1800 řádků)
- `src/Service/StockService.php` — demand tree logika, cache
- `views/production_plans.php` — UI

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/production/plans` | `ProductionController::plans` |
| POST | `/production/produce` | `ProductionController::produce` (zápis výroby) |
| POST | `/production/delete` | `ProductionController::deleteRecord` (smazat výrobu + korekce dle ref_id) |
| POST | `/production/check` | `ProductionController::check` (kontrola konzistence pohybů) |
| GET | `/production/demand-tree` | `ProductionController::demandTree` (JSON tree) |
| GET | `/production/movements` | `ProductionController::movements` |
| GET | `/production/filtered-movements` | `ProductionController::filteredMovements` |
| POST | `/production/recent-limit` | `ProductionController::updateRecentLimit` |

## Tabulky

Modul nemá vlastní tabulky — píše do:
- `polozky_pohyby` (typ `vyroba` pro rodiče, `korekce` pro potomky)
- `produkty.dovyrobit` — přepočítávaný ukazatel "kolik zbývá vyrobit"

Čte:
- `produkty` (min_zasoba, min_davka, krok_vyroby, vyrobni_doba_dni)
- `bom` (struktura rozpadu)
- `inventura_stavy` + `polozky_pohyby` (aktuální stavy zásob)
- `rezervace` (dostupný stav)
- `nastaveni_global` (okno_pro_prumer_dni, spotreba_prumer_dni, zasoba_cil_dni)

## Závislosti

- Konzumuje: `produkty`, `bom`, `sklad`, `rezervace`, `nastaveni`
- Konzumují: `sklad` (pohyby výroby jsou součást pohybů skladu), `analytics` (nepřímo přes `skl_hodnota` potomků)

## Aktuální stav

✅ **Hotovo**
- Demand tree — rekurzivní rozpad potřeby z root produktu na komponenty
- Výpočet `dovyrobit` z historie spotřeby + `min_zasoba` + plánované rezervy
- `nast_zasob = 'auto'` vs `'manual'` — u auto počítá systém min_zasoba z průměrné spotřeby (okno dle `nastaveni_global`)
- `min_davka` a `krok_vyroby` — zaokrouhlení výrobních dávek
- Zápis výroby s automatickým odečtem potomků (přes BOM)
- Reverze výroby dle `ref_id` (smazání všech spjatých pohybů jednou operací)
- Filtrované zobrazení pohybů (`recent_limit` pro výkon)
- Pohyby skladu ve filtru defaultně zapnuté (commit d16e32a)

⚠️ **Známé dluhy / gotchy**
- **Non-stock typy nejsou v demand tree** (filtr přes `product_types.is_nonstock`) — samotné se "nevyrábí", jen se rozpadají do skladových potomků
- **`ref_id`** je klíčové pro reverzi — pokud se někde přeruší řetězec (např. ručně smazaný pohyb), reverze může nechat orfanní data
- **Cyklické BOM → infinite recursion** (viz `bom.md`)
- **Demand tree a historické stavy** — počítá se pro "teď", ne pro budoucí datum (nezvažuje datum vypršení rezervací)
- `recent_limit` default je nastaven v `ProductionController::updateRecentLimit()` — ukládá se v session

❌ **Nezačato**
- Plánovací kalendář (kdy co vyrobit, s ohledem na `vyrobni_doba_dni`)
- Integrace s výrobními pracovníky (přiřazení úkolů)
- Skutečná vs. plánovaná spotřeba (variance tracking)
