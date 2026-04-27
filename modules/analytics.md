# Modul: analytics

## Co modul dělá

Analytický dashboard nad prodejními doklady — tržby, marže, top kontakty, top produkty, měsíční přehledy. Používá data z modulu `import` (`doklady_eshop`, `polozky_eshop`). Součástí je také **AI SQL generátor** — uživatel napíše prompt, OpenAI API vygeneruje SQL dotaz proti schématu DB, systém ho spustí a zobrazí výsledek. Oblíbené dotazy se ukládají do `ai_prompts`.

Hlavní stránka: `/analytics/revenue`. Obsahuje šablony (templates) — předdefinované queries (např. `margins`, `monthly_revenue_by_ic`) + AI režim.

## Kam sahá v kódu

- `src/Controller/AnalyticsController.php` — ~2100 řádků, jádro modulu
- `src/Service/AnalyticsSchema.php` — DB schema knowledge pro AI (popis tabulek, sloupců, příklady)
- `views/analytics_revenue.php` — UI (formulář s filtry, tabulka, detailní view, AI chat)

Klíčové metody v controlleru:
- `buildMarginRows()` — výpočet marží pro šablonu `margins` (invoices / contacts / products agregace)
- `calculateItemCost()` — rekurzivní rozpad nonstock produktů na skladové potomky přes BOM
- `invoiceItemsV2()` — JSON detail položek konkrétní faktury (drill-down)
- `runTemplateV2()` — spuštění SQL šablony nebo AI-generovaného dotazu

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/analytics/revenue` | `AnalyticsController::revenue` |
| POST | `/analytics/run` | `AnalyticsController::runTemplateV2` |
| GET | `/analytics/contacts` | `AnalyticsController::searchContactsV2` |
| GET | `/analytics/contacts/by-id` | `AnalyticsController::searchContactsByIdsV2` |
| GET | `/analytics/invoice-items` | `AnalyticsController::invoiceItemsV2` |
| GET | `/analytics/favorite/list` | `AnalyticsController::favoriteListV2` |
| POST | `/analytics/favorite` | `AnalyticsController::saveFavoriteV2` |
| POST | `/analytics/favorite/delete` | `AnalyticsController::deleteFavoriteV2` |

## Tabulky

Modul nemá vlastní DB schema kromě:
- `ai_prompts` (id, user_id, title, prompt, is_public, created_at)

Čte hlavně: `doklady_eshop`, `polozky_eshop`, `kontakty`, `produkty`, `bom`, `product_types`, `produkty_znacky`, `produkty_skupiny`.

## Definice marže (aktuální po opravě 2026-04-20)

**Šablona `margins`** (tabulka produktů/kontaktů/faktur):

- **Tržby** = `Σ polozky_eshop.cena_jedn_czk × polozky_eshop.mnozstvi` přes filtrovanou množinu
- **Náklady** = `Σ calculateItemCost(sku, mnozstvi, ...)`, kde:
  - standardní produkt → `produkty.skl_hodnota × mnozstvi`
  - nonstock produkt (`product_types.is_nonstock = 1`) → rekurzivní součet nákladů potomků přes `bom` (pokud potomek je také nonstock, rekurze pokračuje)
- **Zisk** = Tržby − Náklady
- **Marže %** = Zisk / Tržby × 100

## Závislosti

- Konzumuje: `import` (doklady, položky), `produkty`, `bom`, `nastaveni` (číselníky)
- Nikdo nekonzumuje analytics — read-only modul

## Aktuální stav

✅ **Hotovo**
- Šablona `margins` s agregací podle faktur / kontaktů / produktů
- Šablona `monthly_revenue_by_ic` a další přes AI
- AI SQL generátor (gpt-4o-mini, temperature 0.2, response_format JSON)
- Uložení oblíbených dotazů (`ai_prompts`)
- Invoice detail drill-down (klik na řádek faktury → položky)
- Filtry: datum, eshop_source, kontakt, SKU, značka, skupina, typ produktu
- Ochrana proti nebezpečným SQL (povoleny jen SELECT)

⚠️ **Známé dluhy / gotchy / historie oprav**

### 🔴 OPRAVA 2026-04-20: `ratio` odstraněno z výpočtu tržeb
Předchozí implementace `buildMarginRows()` počítala tržby jako:
```
trzby = cena_jedn_czk × mnozstvi × ratio
ratio = doklady_eshop.castka_celkem / Σ(cena_jedn_czk × mnozstvi) přes FILTROVANÉ items
```
**Problém:** `$items` bylo už filtrované (typ/značka/skupina/SKU), ale `castka_celkem` je za celou fakturu. Pokud filtr vyhodil část položek (např. při filtru `typ=produkt` zůstaly jen "produkty", ale `castka_celkem` obsahuje i ceny "balení"), `ratio` se nafouklo (viděno až 4561×!) a tržby filtrovaných položek se napumpovaly hodnotami ostatních řádků.

**Dopad před opravou:** u filtru gogrig.com, typ=produkt, Q1-Q2/2026 se zobrazovala marže 80.1 %, reálná je ~39.9 %.

**Oprava:** `AnalyticsController::buildMarginRows()` nyní počítá `trzby = cena × qty` bez ratia. Konzistentní s `invoiceItemsV2()`, která ratio nikdy nepoužívala. Viz commit z 2026-04-20.

**Ponaučení:** když zavádíš váhování/ratio přes několik položek, vždy ověř, že denominator obsahuje správnou podmnožinu. A vždy ověř dopad na reálných datech před pushem — ne jen unit testem formule.

### Další známé problémy

- **Filtr `typ produktu = produkt` sám je neúplný pro celkový prodej.** Eshopy prodávají jak jednotlivé kusy (`typ=produkt`), tak velkoobchodní balení (`typ=karton`, např. "25 ks - WormUp..."). Pro plný obraz tržeb/marže je potřeba ve filtru vybrat **oba typy** (produkt + karton). Samotný `produkt` vypadá jako podtížené tržby.
- **`skl_hodnota` je aktuální, ne historická** — prodeje před rokem se oceňují dnešní nákladovou cenou. Změny nákupních cen nebo re-evaluace skladu → starší analytika se zpětně mění. Pokud chceš zpětně korektní marže, je potřeba snapshot `skl_hodnota` v čase prodeje.
- **Produkt nenalezený v `produkty`** (`resolveMainSku` vrátí null) → náklad = 0 → marže 100 %. Stejně `skl_hodnota = 0` u legitimního produktu. Diagnostiku lze udělat přes `/report/missing-sku` + kontrolu `SELECT sku FROM produkty WHERE skl_hodnota=0 AND aktivni=1`.
- **Duplikace přes `OR JOIN`** v `loadInvoiceItems()` (`JOIN produkty p ON p.sku = pe.sku OR p.alt_sku = pe.sku`) — pokud pe.sku matchne víc produktů, řádek se zduplikuje. V praxi marginální (2 případy v Q1/2026).
- **`castka_celkem` vs DPH** — schema tvrdí "bez DPH", ale data často obsahují DPH. Neovlivňuje aktuální výpočet marží (už ratio nepoužíváme), ale pokud přidáš novou logiku používající `castka_celkem`, pozor.
- **Nonstock rekurze** přes BOM + `skl_hodnota` potomků — stejný problém s "vždy aktuální" jako u obyčejných produktů, jen znásobený kusovníkovou strukturou.
- **`ai_prompts`** jsou uložené queries; pokud AI vygeneruje DROP/UPDATE, systém by ho měl odmítnout — ověřit, že ochrana je robustní (doporučuji whitelist SELECT).

❌ **Nezačato**
- Historický snapshot `skl_hodnota` (versioning nákladových cen)
- Export reportů do PDF/Excel
- Scheduled reports (emailem)
- Přednastavené dashboards (KPI karty)
