# Modul: rezervace

## Co modul dělá

Jednoduché CRUD rezervací — blokuje zásoby konkrétního SKU do určitého data. Typ rezervace je volitelný (z číselníku `product_types`). Žádné propojení s konkrétními objednávkami — je to ručně spravovaná "poznámka", že X kusů je již zablokováno pro budoucí odběr.

Využití: výpočet **dostupného** stavu = fyzický stav − součet aktivních rezervací. Zobrazuje se v `produkty` modulu vedle stavu.

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
- Autocomplete vyhledávání produktů při tvorbě
- Typ = libovolný z `product_types` (ne jen produkt)

⚠️ **Známé dluhy / gotchy**
- **Žádná validace** na existenci SKU v `produkty` — lze rezervovat neexistující SKU (typo)
- **Bez expirace** — rezervace s `platna_do` v minulosti se nesmaže automaticky, jen se ignoruje při výpočtech (musí být explicitně smazaná)
- **Bez propojení s objednávkami** — rezervace je ručně udržovaná poznámka, ne automatizovaná blokace z eshopu
- `mnozstvi` není omezeno — lze rezervovat víc než je skladem

❌ **Nezačato**
- Cron / auto-cleanup expirovaných rezervací
- Bulk operace (import CSV, hromadné smazání)
- Napojení na eshopy (automatická rezervace při objednávce)
