# Modul: vanoce

## Co modul dělá

Sezónní stránka **Vánoce 2026** (`/vanoce`, jen čtení, všichni přihlášení vč. čtenáře) – sleduje, jestli vánoční výroba stíhá plánovaný odbyt. Plánuje se jen **celkový objem v sáčcích** (spotřebitelská balení), ne konkrétní produkty – sortiment se proti loňsku změnil.

- **Plán odbytu po ISO týdnech 44–52/2026** = odbyt ve stejných týdnech 2025 × koeficient e-shopu (maloobchod grig.cz, grig.sk, WormUP.com ×1,5; velkoobchod gogrig.com ×1,0). Stánky (grigsupply.cz) = odhad kolegyně 15 517 sáčků (realistický scénář) rovnoměrně v týdnech 45–51.
- **Plán výroby** = 5 000 sáčků týdně do týdne 51.
- **Skutečnost**: odbyt z faktur (DUZP), výroba z výrobních pohybů produktů (`polozky_pohyby.typ_pohybu='vyroba'`, typ produkt, čistý součet), zásoba = kladné stavy hotových produktů na konci týdne (`StockService::buildStockMap(null, asOf)`).
- Karty: stav výroby (kumulativně za ukončené týdny proti plánu: ≥100 % v plánu, ≥85 % mírně pod, jinak pod plánem), tento týden, hotové sáčky skladem, zbývá vyrobit, prázdné obaly skladem (typ `obal`, kladné stavy).
- Suroviny podle druhu (červíci / cvrčci / sarančata = kořen kusovníku SX9001 / SX9002 / SX9003; dochucené přepočtené na natural): skladem + plánované dodávky − náplň zbývajícího plánovaného odbytu (bez náplně v hotových sáčcích skladem).

## Kam sahá v kódu

- `src/Service/VanoceService.php` – parametry sezóny v `SEZONA` (koeficienty, stánky, cíl výroby, dodávky surovin) a výpočet `report()`
- `src/Controller/VanoceController.php` – `index()`
- `views/vanoce.php` – karty, dva SVG grafy (vanilla JS), týdenní tabulka, suroviny
- `views/_layout.php` – položka menu „Vánoce 2026“

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/vanoce` | `VanoceController::index` |

## Tabulky

Vlastní tabulky nemá. Čte `polozky_eshop`, `produkty`, `product_types`, `bom`, `polozky_pohyby`, `inventury`, `inventura_stavy` (přes `StockService`).

## Převod na sáčky

- Položka z katalogu (i přes `alt_sku`): produkt = 1 sáček; neskladový karton/balení se rozpadá přes kusovník na produkty; surovina prodaná po kg se nepočítá do sáčků (jen do kg).
- Položka mimo katalog (hlavně gogrig 2025): podle názvu – „15 ks - …“ = karton po 15, „5x20g“ = sada po 5 (do 6 ks), jinak 1 sáček; gramáž z názvu (výchozí 20 g). Nevýrobní položky (etikety, kupony, balné, propagace, Billa, zkumavky…) se ignorují.

## Závislosti

- Konzumuje: `import` (faktury), `produkty` + `bom` (rozpad, druh suroviny), `sklad` (`StockService`), `pohyby` (výrobní doklady)
- Nikdo nekonzumuje

## Aktuální stav

✅ **Hotovo (2026-10-27)** – stránka, grafy kumulovaný odbyt/výroba a zásoba (plán čárkovaně, skutečnost plně), týdenní tabulka, suroviny, obaly. Ověřeno v headless Chrome (bez chyb JS, 400 px bez přetečení).

⚠️ **Gotchy**
- Plán týdne počítá s celým týdnem výroby; u běžícího týdne je skutečnost dílčí (zvýrazněný řádek, hodnocení stavu až po ukončených týdnech).
- Zásoba počítá s tím, že se celá kladná zásoba hotových produktů dá prodat (i vyřazený sortiment, krátká expirace).
- Druhy surovin u stánků podle loňského složení grigsupply, ne podle nového sortimentu.
- Výroba se v Gworm zapisuje až od 18. 11. 2025 – loňská skutečná kapacita před tím není známá.
- Po sezóně stránku (a položku menu) odstranit, nebo převést `SEZONA` na další rok.
