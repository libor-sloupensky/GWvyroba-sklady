# Modul: admin

## Co modul dělá

Minimalistický modul pro zobrazení historie přihlášení uživatelů. Čte `data/access_log.csv` a zobrazuje reverzně (nejnovější první).

Záznam do logu probíhá v `public/index.php` při startu requestu — pokud je session přihlášená a poslední log pro daného uživatele je starší než hodina, přidá se nový řádek.

## Kam sahá v kódu

- `src/Controller/AdminController.php` — 58 řádků, jediná metoda `history()`
- `public/index.php:37-52` — zápis do logu
- `views/admin_history.php` — zobrazení tabulky
- `data/access_log.csv` — CSV soubor (nikoli DB)

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/admin/history` | `AdminController::history` |

## Tabulky

Modul nepoužívá databázi. Data jsou v `data/access_log.csv`:

```
2026-04-20 14:23:15,libor.sloupensky@grig.cz
2026-04-20 13:45:02,someone@grig.cz
```

## Závislosti

- Konzumuje: soubor `data/access_log.csv` (plněný v `public/index.php`)
- Nikdo nekonzumuje admin modul

## Aktuální stav

✅ **Hotovo**
- Zobrazení historie přihlášení
- Throttling zápisu (max 1× za hodinu na session)
- Superadmin-only přístup

⚠️ **Známé dluhy / gotchy**
- **CSV file, ne DB** — při hodně uživatelích soubor roste bez rotace. Pro současný počet (< 10 uživatelů) OK.
- **Append-only bez lock timeoutu** — `FILE_APPEND | LOCK_EX`, teoreticky safe, ale při dvou paralelních requestech na shared hostingu může vzniknout race condition
- **Žádná rotace / archivace** — soubor poroste donekonečna
- Nezachycuje **jednotlivé akce** (create product, delete invoice, ...) — jen login

❌ **Nezačato**
- Audit log uživatelských akcí (kdo co kdy udělal, hlavně destrukční operace jako `delete-invoice`, `delete-last-batch`)
- Filtrování / search v historii
- Rotace logu (měsíčně nebo při X MB)
- Přesun z CSV do DB
