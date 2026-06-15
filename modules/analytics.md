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

### 🟢 ZMĚNA 2026-06-15: přepínač „pouze/kromě" u výběru firem (šablona Marže)
Šablona `margins` má nový enum parametr `contact_mode` (`pouze` = default / `krome`). Při výběru konkrétních firem lze přepnout, zda se zobrazí **jen vybrané firmy** (`IN`), nebo **vše kromě nich** (`NOT IN`). V režimu „kromě" se ponechávají i faktury bez kontaktu (`OR de.kontakt_id IS NULL`). Logika v `buildMarginRows()`. UI: přepínač (`.toggle-switch`) uvnitř pole kontaktů ve `views/analytics_revenue.php`, viditelný jen u šablon s parametrem `contact_mode` **a** když je vybraná aspoň jedna firma; default „pouze", reset při změně šablony, stav v `state.contactMode`, ukládá/obnovuje se v oblíbených. Zatím jen pro `margins` (ne pro `monthly_revenue_by_ic`, kde kontakt řídí i grupování sérií).

### 🟢 ZMĚNA 2026-06-15: popisek „Zisk %" → „Marže %" + nový sloupec „Přirážka %"
Sloupec procentní marže v `margins_table` (režimy faktury/kontakty/produkty i v rozbaleném detailu položek) se ve `views/analytics_revenue.php` přejmenoval ze „Zisk %" na „Marže %" (resp. „Průměrná marže %"). **Výpočet marže se neměnil** — už dříve = `zisk / tržba × 100`, tedy marže z prodejní (celkové) ceny. Sloupec „Zisk (CZK)" (absolutní zisk) zůstal.

Přidán nový sloupec **„Přirážka %"** (resp. „Průměrná přirážka %") vedle marže = `zisk / náklad × 100`. Klíč `prirazka_pct` doplněn ve všech agregacích `buildMarginRows()` (položka/faktura/kontakt/produkt) i v detail endpointu `invoiceItemsV2()`. Pozor na rozdíl: **marže** = zisk/tržba (jmenovatel = prodejní cena), **přirážka** = zisk/náklad (jmenovatel = nákladová cena) → přirážka je vždy vyšší číslo. Při nákladu 0 se přirážka nastaví na 0 (edge case chybějící `skl_hodnota`). Footer počítá průměry z celkových součtů (`Σzisk/Σtržba`, resp. `Σzisk/Σnáklad`), ne průměr procent.

Pozn.: pozorovaný „nesoulad" souhrnného řádku faktury vs. rozbalený detail = **feature, ne bug** — souhrn (`loadInvoiceItems`) respektuje filtry (typ/značka/skupina/SKU), kdežto detailní drill-down (`invoiceItemsV2`) filtry ignoruje a ukáže všechny položky faktury. Při filtru typ=produkt tak souhrn vynechá kartony, které detail zobrazí.

### 🟢 ZMĚNA 2026-06-04: analýzy už nefiltrují na `aktivni`
Dříve šablony **Sklady** (`stock_value_by_month`) a **Produkty** + PHP varianty pohybů filtrovaly na `p.aktivni = 1`, takže deaktivované produkty mizely z výsledků (skladová hodnota klesala po deaktivaci, i když na produktu fyzicky ležely zásoby).

**Změna:** odstraněn filtr `p.aktivni = 1` ze **všech** analytických výpočtů — `fetchStockProducts()`, SQL šablony `stock_value_by_month` (`JOIN ... ON 1=1`), `products` i `buildProductMovementRows()`. Odebrán i přepínač „Jen aktivní" (`active_only`) a instrukce pro AI SQL generátor upravena na „NEFILTRUJ na p.aktivni". Sloupec `aktivni` ve výstupu a řazení aktivních nahoru zůstaly (jen informativní, nic nevyřazují). Viz commit `ff6a7f4`.

**Pozor:** výpočet skladové hodnoty ve **Výrobě** (`vyroba`) zůstal beze změny — pokud se má chování sjednotit i tam, je to samostatná úprava.

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
- **Produkt nenalezený v `produkty`** (`resolveMainSku` vrátí null) → náklad = 0 → marže 100 %. Stejně `skl_hodnota = 0` u legitimního produktu. Diagnostiku lze udělat přes `/report/missing-sku` + kontrolu `SELECT sku FROM produkty WHERE skl_hodnota=0` (pozn.: od 2026-06-04 analytika nefiltruje na `aktivni`, viz výše).
- **Duplikace přes `OR JOIN`** v `loadInvoiceItems()` (`JOIN produkty p ON p.sku = pe.sku OR p.alt_sku = pe.sku`) — pokud pe.sku matchne víc produktů, řádek se zduplikuje. V praxi marginální (2 případy v Q1/2026).
- **`castka_celkem` vs DPH** — schema tvrdí "bez DPH", ale data často obsahují DPH. Neovlivňuje aktuální výpočet marží (už ratio nepoužíváme), ale pokud přidáš novou logiku používající `castka_celkem`, pozor.
- **Nonstock rekurze** přes BOM + `skl_hodnota` potomků — stejný problém s "vždy aktuální" jako u obyčejných produktů, jen znásobený kusovníkovou strukturou.
- **`ai_prompts`** jsou uložené queries; pokud AI vygeneruje DROP/UPDATE, systém by ho měl odmítnout — ověřit, že ochrana je robustní (doporučuji whitelist SELECT).

❌ **Nezačato**
- Historický snapshot `skl_hodnota` (versioning nákladových cen)
- Export reportů do PDF/Excel
- Scheduled reports (emailem)
- Přednastavené dashboards (KPI karty)
