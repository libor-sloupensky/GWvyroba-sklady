# Modul: nastaveni

## Co modul dělá

Centrální místo pro všechny konfigurační číselníky a globální parametry aplikace:

- **Řady dokladů** (`nastaveni_rady`) — eshop_source, prefix, rozsah čísel, admin URL + šifrované přihlašovací údaje ke Shoptet API
- **Ignorované položky** (`nastaveni_ignorovane_polozky`) — vzory/regex SKU, které se při importu přeskočí (např. dopravné)
- **Značky / skupiny / jednotky / typy produktů** — číselníky používané produkty
- **Globální parametry** (`nastaveni_global`) — okno pro výpočet průměrné spotřeby, cílová zásoba ve dnech, zaokrouhlení, timezone, základní měna
- **Uživatelé** — správa uživatelů (jen superadmin)

## Kam sahá v kódu

- `src/Controller/SettingsController.php` — všechny CRUD endpointy
- `src/Service/CryptoService.php` — AES-256-CBC pro šifrování hesel k Shoptet API
- `views/settings.php` — jedna velká stránka se sekcemi

## Routes

Všechny POST endpointy vrací JSON nebo redirect:

| Kategorie | Routes |
|-----------|--------|
| Index | `GET /settings` |
| Řady | `POST /settings/series`, `POST /settings/series/delete` |
| Ignorované | `POST /settings/ignore`, `POST /settings/ignore/delete` |
| Značky | `POST /settings/brand`, `POST /settings/brand/delete` |
| Skupiny | `POST /settings/group`, `POST /settings/group/delete` |
| Jednotky | `POST /settings/unit`, `POST /settings/unit/delete` |
| Typy produktů | `POST /settings/type`, `POST /settings/type/delete` |
| Globální | `POST /settings/global` |
| Uživatelé | `POST /settings/users/save` (jen superadmin) |

## Tabulky

- `nastaveni_rady` (id, eshop_source, prefix, cislo_od, cislo_do, admin_url, admin_email, admin_password_enc, aktivni)
- `nastaveni_ignorovane_polozky` (id, vzor)
- `nastaveni_global` (id=1, okno_pro_prumer_dni, spotreba_prumer_dni, zasoba_cil_dni, mena_zakladni, zaokrouhleni, timezone) — single-row tabulka
- `produkty_znacky` (id, nazev UNIQUE)
- `produkty_skupiny` (id, nazev UNIQUE)
- `produkty_merne_jednotky` (id, kod UNIQUE)
- `product_types` (id, code UNIQUE, name, is_nonstock)
- `users` (viz `auth.md`)

## Závislosti

- Konzumují: **všechny ostatní moduly** (produkty, import, vyroba, analytics, rezervace)

## Aktuální stav

✅ **Hotovo**
- Všechny číselníky mají CRUD (add/delete, s kontrolou používání — nedovolí smazat značku použitou v produktech)
- Šifrování Shoptet hesel (`CryptoService::encrypt` — AES-256-CBC s `ENCRYPTION_KEY`)
- Globální parametry:
  - `okno_pro_prumer_dni` — kolik dní nazpět se bere pro výpočet průměrné denní spotřeby (`min_zasoba` auto)
  - `spotreba_prumer_dni` — alternativní okno (TODO: ověřit rozdíl)
  - `zasoba_cil_dni` — na kolik dní dopředu se má držet zásoba
  - `mena_zakladni` — default CZK
  - `zaokrouhleni` — strategy (např. `half_up`)
  - `timezone` — IANA (Europe/Prague default)
- Uživatelská správa (superadmin): add, edit role, deactivate

⚠️ **Známé dluhy / gotchy**
- **`ENCRYPTION_KEY` default** je slabá (`'gw0rm-s3cr3t-k3y-ch4ng3-m3'`) — pokud není v env, Shoptet hesla jsou de-facto čitelná. Nastavit silný klíč v `config/config.local.php` (`encryption_key`) nebo env.
- **Single-row `nastaveni_global`** — přidání nových globálních parametrů vyžaduje ALTER TABLE (nebo JSON sloupec, což by byl refactor)
- **Žádná historie změn** — kdo změnil řadu / globální parametr, nelze dohledat
- **Kontrola existence sloupce `skl_hodnota`** v `ProductsController` — `hasProductStockValueColumn()` s cached flag. Legacy kód z migrace, lze odstranit až potvrdíme, že všechny instance schema mají sloupec.

❌ **Nezačato**
- Audit log pro změny v nastavení
- Export/import celé konfigurace (např. pro dev/prod sync)
