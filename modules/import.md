# Modul: import

## Co modul dělá

Importuje prodejní doklady z eshopů do databáze. Dva zdroje:

1. **Pohoda XML** — ruční upload přes formulář `/import` (historicky původní import)
2. **Shoptet XML** — automatický stahování z admin panelu Shoptetu (vícero eshopů: gogrig.com, grig.cz, grig.sk, wormup.com, ...)

Parsuje hlavičku dokladu (`doklady_eshop`) + položky (`polozky_eshop`), napárovává kontakty (IČ → `kontakty`), detekuje chybějící SKU v katalogu. Auto-import běží přes cron přes HTTP endpoint s token autentizací.

## Kam sahá v kódu

- `src/Controller/ImportController.php` — formuláře, správa dokladů, reporty
- `src/Service/ShoptetImportService.php` — Shoptet stahování + XML parser
- `cron.php` — tenký wrapper pro cron (kontroluje token, volá auto-import)
- `scripts/shoptet_auto_import.php` — CLI varianta
- `views/import_form.php`, `views/import_result.php`, `views/report_missing_sku.php`

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/import` | `ImportController::form` |
| POST | `/import/pohoda` | `ImportController::importPohoda` |
| POST | `/import/delete-last` | `ImportController::deleteLastBatch` |
| POST | `/import/delete-invoice` | `ImportController::deleteInvoice` |
| GET | `/import/invoice-detail` | `ImportController::getInvoiceDetail` |
| GET | `/import/auto-run` | `ImportController::autoRun` (ruční spuštění auto-importu) |
| GET | `/report/missing-sku` | `ImportController::reportMissingSku` |

Cron endpoint (mimo router): `GET /cron.php?token=...`

## Tabulky

- `doklady_eshop` (id, eshop_source, cislo_dokladu, typ_dokladu, platba_typ, dopravce_ids, cislo_objednavky, sym_var, datum_vystaveni, duzp, splatnost, mena_puvodni, kurz_na_czk, castka_celkem, kontakt_id, import_batch_id, import_ts)
- `polozky_eshop` (id, eshop_source, cislo_dokladu, code_raw, sku, ean, nazev, mnozstvi, merna_jednotka, cena_jedn_mena, cena_jedn_czk, mena_puvodni, sazba_dph_hint, plati_dph, sleva_procento, duzp, import_batch_id)
- `kontakty` (vytvářené při importu z IČ/emailu)
- `nastaveni_rady` — definice řad dokladů (prefix, admin URL, šifrované heslo k Shoptet API)
- `nastaveni_ignorovane_polozky` — regex/glob vzory SKU, které se při importu přeskočí

## Závislosti

- Konzumuje: `nastaveni` (řady `nastaveni_rady`, ignor vzory `nastaveni_ignorovane_polozky`)
- Produkuje: `doklady_eshop`, `polozky_eshop`, `kontakty` → využívá `analytics`, `admin`

## Aktuální stav

✅ **Hotovo**
- Shoptet XML parser (`ShoptetImportService::parsePohodaXml()`)
- Auto-import přes cron, jeden eshop za běh (zabrání timeout)
- File lock — jedna instance najednou
- Persistentní Shoptet session (commit a10ae6e) — login jen při vypršení
- Delete last batch, delete konkrétního dokladu
- Invoice detail endpoint (JSON)
- Report chybějících SKU — seznam SKU z `polozky_eshop`, které nejsou v `produkty`
- Logging do `log/import_xml_shoptet.log`

⚠️ **Známé dluhy / gotchy**
- **Pohoda XML parser je zastaralý** — README říká "TODO dle MASTER_PROMPT; zatím stub", většina práce jde přes Shoptet XML
- **`castka_celkem`** — schema říká "bez DPH", ale reálně v importovaných datech je často **s DPH** (viz analytika). Pokud budeš upravovat import, zvaž co má sloupec skutečně obsahovat, ať se jiné moduly nerozbijí. 🔴 Relevantní pro `analytics.md` — historická chyba zobrazování marží.
- **Cron token v URL** — bude v access logu hostingu. Při rotaci změnit v `config.local.php` i v cron nastavení Webglobe.
- Kontakty se cachují během importu (`ic`/`email` key) — duplikátní emaily na různé IČ = jedna osoba
- `import_batch_id` = timestamp `YmdHis` — lze reverzovat poslední dávku

❌ **Nezačato**
- Automatické párování chybějících SKU (např. fuzzy match na `alt_sku`)
- Plánované vs. skutečné importy (alert když cron neběží)
