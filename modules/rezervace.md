# Modul: rezervace

## Co modul dělá

Jednoduché CRUD rezervací — blokuje zásoby konkrétní položky do určitého data. Typ rezervace **není volitelný** — odvozuje se z `produkty.typ` zvolené položky. Žádné propojení s konkrétními objednávkami — je to ručně spravovaná "poznámka", že X kusů je již zablokováno pro budoucí odběr (typicky konkrétní objednávka odběratele).

Využití: výpočet **dostupného** stavu = fyzický stav − součet aktivních rezervací. Zobrazuje se v `produkty` modulu vedle stavu.

**Rezervace neskladové položky (karton, balení) se kaskáduje přes BOM** do skladových potomků — viz `vyroba.md`. Karton se sám nevyrábí ani neskladuje, takže bez kaskády by rezervace na něm neměla žádný efekt.

## Kam sahá v kódu

- `src/Controller/ReservationsController.php` — CRUD + autocomplete SKU
- `views/reservations.php` — seznam + formulář

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/reservations` | `ReservationsController::index` |
| POST | `/reservations` | `ReservationsController::save` (create i update) |
| POST | `/reservations/delete` | `ReservationsController::delete` |
| GET | `/reservations/search-products` | `ReservationsController::searchProducts` (autocomplete) |

## Tabulky

- `rezervace` (id, sku, typ, mnozstvi, platna_do DATE, poznamka)

## Závislosti

- Konzumuje: `produkty` (SKU), `nastaveni` (typy rezervace z `product_types`)
- Konzumují: `produkty` (zobrazení dostupného stavu), `vyroba` (případně při plánování — viz `vyroba.md`)

## Aktuální stav

✅ **Hotovo**
- CRUD rezervací
- Autocomplete vyhledávání položek při tvorbě (vrací i `typ`, zobrazuje se v našeptávači)
- Typ se odvozuje z `produkty.typ` přes `resolveTypeForSku()` — z formuláře se `typ` neposílá, takže nemůže vzniknout nesoulad (dřív šlo uložit karton jako "produkt")
- Ve výpisu je sloupec **Název** (LEFT JOIN na `produkty`), u SKU mimo katalog se vypíše "SKU není v katalogu"
- Kaskáda rezervací neskladových položek do potomků (`StockService::cascadeNonstockReservations`)

⚠️ **Známé dluhy / gotchy**
- **Žádná validace** na existenci SKU v `produkty` — lze rezervovat neexistující SKU (typo); ve výpisu se pak název nezobrazí a typ spadne na `produkt`
- **Bez expirace** — rezervace s `platna_do` v minulosti se nesmaže automaticky, jen se ignoruje při výpočtech (musí být explicitně smazaná)
- **Bez propojení s objednávkami** — rezervace je ručně udržovaná poznámka, ne automatizovaná blokace z eshopu
- `mnozstvi` není omezeno — lze rezervovat víc než je skladem

❌ **Nezačato**
- Cron / auto-cleanup expirovaných rezervací
- Bulk operace (import CSV, hromadné smazání)
- Napojení na eshopy (automatická rezervace při objednávce)
