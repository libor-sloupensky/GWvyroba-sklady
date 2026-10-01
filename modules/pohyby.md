# Modul: pohyby

## Co modul dělá

Skladové doklady — záložka **Pohyby**. Uživatel založí doklad, vloží do něj položky a systém zapíše pohyby do skladu. Nahrazuje do budoucna sloupec „Akce" ve Výrobě (ten zatím zůstal beze změny, Výroba se bude slučovat s Produkty).

Dva typy dokladu / režimy položky:
- **Výroba** — příjem rodiče (+množství) a výdej **nejbližších skladových potomků** podle BOM (−množství × koeficient). Neskladové mezistupně (karton → balení) se prokaskádují až na první skladovou položku (`MovementDocService::stockComponents`, stejná logika jako `cascadeNonstockReservations`). Záporná výroba je povolena (oprava chybného zápisu: potomci se přičtou zpět).
- **Korekce** — jen tento řádek, bez dopadu na komponenty. Slouží i pro likvidaci, odpis a opravu stavu (např. dorovnání mínusu po výrobě).

Typ dokladu je jen **výchozí režim** pro nově vkládané položky; režim každé položky lze změnit.

Třetí typ **Inventura** (od 2026-09-30) vzniká **automaticky uzavřením inventury** (`InventoryController::close` → `MovementDocService::attachInventoryDoc`). Ručně založit nejde.
- Řádky dokladu jsou **existující rozdílové pohyby inventury** (`typ_pohybu='inventura'`, ref `inv:<inventura>:<zápis>`) – jen dostanou `doklad_id`, nic se nekopíruje. Položky beze změny pohyb nemají, takže doklad obsahuje jen rozdíly. V detailu se seskupují po SKU (`loadInventoryLines`): očekávaný stav = zjištěný − rozdíl, zjištěný = `inventura_stavy.stav`; SKU s nulovým součtem se nezobrazí a nepočítají.
- Datum = datum uzavření, `created_at` = čas uzavření, uživatel = kdo uzavřel (u zpětně doplněných „systém"). Vazba `sklad_doklady.inventura_id`.
- **Nelze editovat ani mazat** (`lockReason` vrací důvod vždy). Jediné, co jde měnit, je **poznámka** – admin/superadmin kdykoli (`canEditNote`), a má **jeden zdroj v `inventury.poznamka`** (doklad ji zobrazuje a při uložení zapíše tam; při uzavření se přebírá odtud).
- **Znovuotevření inventury** (`reopen`): řádkům se odpojí `doklad_id`, doklad **zůstane** se svým číslem (prázdný, log „inventura otevřena"). Opětovné uzavření řádky připojí ke **stejnému** dokladu, aktualizuje datum/uživatele. Žádné mezery v číslování.
- **Smazání inventury** (`delete`) smaže i doklad a log (pohyby maže inventura sama).
- Zpětně doplněno 2026-09-30 pro 9 uzavřených inventur (#5–#15) chronologicky: 25-0001, 26-0001 … 26-0008 (skript jednorázový, `_trash/`, smazán). Záloha DB před změnou: `C:\Users\HP\.codex\Gworm_backups\gworm_full_2026-09-30_135939.sql`.
- Zámek ostatních dokladů uzavřenou inventurou (viz níže) zůstává; inventární doklad sám lock nepotřebuje.

## Kam sahá v kódu

- `src/Service/MovementDocService.php` — schéma (auto-migrace), číslování, oprávnění/zámky, řádky + kaskáda BOM, log změn, seznam
- `src/Controller/MovementsController.php` — routing, vyhledávání produktů, JSON API pro autosave
- `views/movements.php` — seznam dokladů i detail (podle `$mode`), inline JS (autosave, překreslení řádků z JSON)

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/movements` | seznam dokladů (filtr od/do/typ/hledat) |
| POST | `/movements/create` | založit doklad (typ) → redirect na detail |
| GET | `/movements/doc?id=` | detail dokladu |
| GET | `/movements/search?q&znacka_id&skupina_id&typ` | JSON živého vyhledávání produktů: `{items (max 10), more, limit}`; bez filtru vrací prázdno |
| POST | `/movements/delete` | smazat prázdný doklad |
| POST | `/movements/header` | JSON: typ, poznámka (autosave) |
| POST | `/movements/line/add` | JSON: sku, mnozstvi, rezim |
| POST | `/movements/line/update` | JSON: line_id, mnozstvi, rezim |
| POST | `/movements/line/delete` | JSON: line_id (jen rodičovský řádek) |

Všechny JSON akce vrací `{ok, doc, lines, log}` — stránka si překreslí tabulku z odpovědi, žádný lokální stav.

## Tabulky

- `sklad_doklady` (id, cislo `RR-NNNN` per rok, datum DATE, typ ENUM vyroba/korekce/**inventura**, **inventura_id** INT NULL, poznamka, user_id, user_email, created_at, updated_at)
- `sklad_doklady_log` (doklad_id, pohyb_id, sku, akce vytvoreni/hlavicka/pridani/zmena_mnozstvi/zmena_rezimu/smazani/inventura_uzavrena/inventura_otevrena, stare_mnozstvi, nove_mnozstvi, detail, user_email, datum) — historie změn zobrazená v detailu
- `polozky_pohyby` + nové sloupce **`doklad_id`, `parent_pohyb_id`, `user_id`** (+ index `idx_pohyby_doklad`). Řádky dokladu jsou obyčejné pohyby, výpočet stavu skladu (`StockService`) nic nového nečte.
  - rodičovský řádek: `parent_pohyb_id IS NULL`, `typ_pohybu` = režim (vyroba/korekce), `ref_id = dok-<doklad>-<řádek>`
  - potomci: `parent_pohyb_id` = id rodiče, `typ_pohybu='vyroba'`, poznámka „odečet komponenty", stejný `ref_id` a `datum` jako rodič
  - staré zápisy z Výroby (`prod-…`, `corr-…`) mají `doklad_id NULL` — zpětně se z nich doklady nevyrábějí

Schéma si při prvním použití doplní `MovementDocService::ensureSchema()` (CREATE TABLE IF NOT EXISTS + SHOW COLUMNS/ADD COLUMN, MySQL 5.7 neumí `ADD COLUMN IF NOT EXISTS`). Na produkci proběhlo 2026-09-30 z lokálního testu.

## Pravidla (rozhodnuto 2026-09-30)

- **Datum dokladu** = den založení, neměnné. Datum řádku = datum dokladu + čas vytvoření řádku; při změně množství datum zůstává, změna je v logu.
- **Editace**: autor do konce následujícího dne; superadmin 7 dní od data dokladu; role `admin` = jako běžný uživatel. Ostatní uživatelé jen čtou.
- **Zámek inventurou**: jakmile je po `created_at` dokladu uzavřena libovolná inventura, doklad je uzamčen pro všechny (stav se od inventury počítá znovu, změna staršího řádku by ho rozjela). `lockReason()` vrací důvod, UI ho zobrazí.
- **Změna množství rodiče** = smazat potomky a vygenerovat znovu podle **aktuálního** BOM. Přepnutí na korekci potomky smaže.
- **Potomka** nelze smazat ani editovat samostatně; smazání rodiče smaže i potomky.
- **Smazat doklad** jde jen prázdný (tlačítko disabled s tooltipem) a jen v editačním okně.
- **Neskladové položky** ve vyhledávání šedé s tooltipem, nelze vložit (server odmítne). Neaktivní produkty vložit lze (odpis zbytku), SKU přeškrtnuté.
- **Mínus**: žádný blokující dialog — řádek se uloží a stav „po" svítí červeně (u rodiče i potomků). Vložená položka dostane množství 1 a fokus do inputu.
- **Autosave**: poznámka po 700 ms od psaní / při opuštění pole, množství po 600 ms / při změně / Enter. Konflikt dvou uživatelů = poslední zápis vyhrává. Autosave překresluje celé `tbody`, proto `renderLines()` vrací fokus do rozepsaného inputu množství (podle `data-id`) i s textem dopsaným během ukládání — jinak kurzor po pauze v psaní „vyskočil" z inputu.
- **Hodnota řádku** = množství × `polozky_pohyby.skl_hodnota_jedn` (snímek `produkty.skl_hodnota` uložený při zápisu řádku; při změně množství/režimu se snímek **obnoví na aktuální hodnotu**, potomci se regenerují s aktuální). Záporný pohyb = záporná hodnota. Doklad ukazuje součet (rodiče + komponenty), seznam sloupec Hodnota. Inventární doklad oceňuje rozdíl hodnotou při uzavření; u 9 zpětně doplněných dokladů je snímek z 2026-09-30 (historie `skl_hodnota` neexistuje). Řádky bez `skl_hodnota` mají hodnotu prázdnou.
- **Log slučuje změny množství** téhož řádku od téhož uživatele do 3 minut (`LOG_MERGE_SECONDS`) do jednoho záznamu „z původního na výsledné"; návrat na původní hodnotu záznam smaže. Klikání na šipky u množství tak nevyrobí deset řádků v historii.
- **Vyhledávání položek** je živé (debounce 250 ms, změna selectu hned), max 10 výsledků, při více výsledcích hláška pod tabulkou s odkazem „vypsat vše nalezené" (`/movements/search?all=1`, pojistka 1000 položek). Jakákoli změna podmínek vrací výpis zpět na 10. Založení dokladu je jedním tlačítkem vpravo nahoře, typ se volí až v dokladu (výchozí Výroba).

## Závislosti

- Konzumuje: `produkty`, `bom` (`StockService::getBomGraph`), `product_types.is_nonstock`, `sklad` (stav přes `StockService::getStockState`), `inventury` (zámek), `auth` (session user)
- Konzumují: `sklad` (řádky jsou pohyby), `vyroba` (dovyrobit se přepočítá při načtení plánů), `analytics` (šablona Produkty čte typ `vyroba`/`odpis` z pohybů — potomci dokladu mají typ `vyroba`, takže se do „výdeje" započítají jako dřív)

## Aktuální stav

✅ Hotovo 2026-09-30: celý tok založit → hlavička → vyhledat → vložit → množství → potomci + stavy → mazání → log → zámky. Ověřeno testovacím skriptem proti produkční DB (19 kontrol) a renderem obou stránek mimo prohlížeč.

⚠️ Dluhy
- Historie pohybů ve Výrobě (`/production/movements`) zatím neodkazuje na doklad (ref `dok-…` ale vidět je).
- Storno uzamčeného dokladu neexistuje — řeší se novým korekčním dokladem.
- Sloupec „Akce" ve Výrobě je od 2026-09-30 **skrytý** (`$showActionColumn = false` ve `views/production_plans.php`), kód i endpointy zůstaly; zruší se při sloučení Výroby s Produkty. `ProductionController::deleteRecord` odmítá reference `dok-…`.
- Datum dokladu nelze změnit (záměr, případně později pro superadmina).
