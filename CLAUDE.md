# Gworm — Pravidla pro Claude Code

## Jazyk
- Commit messages, komentáře v kódu a komunikace: **česky**
- Ceny a náklady: **CZK** (bez DPH u tržeb — viz `modules/analytics.md`)

## Technologický kontext
- **Čistý PHP 8.x** bez frameworku, vlastní mini-router, vlastní PSR-4 autoload v `src/bootstrap.php`
- **Bez Composeru** (pokud se přidá dep, probrat předem)
- **MySQL** na Webglobe hostingu, collation **`utf8mb4_czech_ci`**
- Detaily viz `modules/ARCHITECTURE.md`

## Workflow — obecný vývoj
- Před úpravou souboru nejprve přečíst aktuální stav
- Preferovat úpravu existujících souborů před vytvářením nových
- Po každé změně v PHP: `php -l <soubor>` (syntax check) před commitem
- Commit messages v češtině, krátké, věcné (viz `git log` pro styl)
- Nikdy `git push --no-verify`, `git commit --amend` u pushnutých commitů
- Diagnostické skripty psát do `_trash/` (gitignored), po použití smazat

## Workflow — změny v analytických výpočtech
- **Vždy ověřit dopad čísel na reálných datech** před pushem (dotaz do produkční DB nebo srovnávací skript v `_trash/`)
- Marže a tržby dokázat přes diagnostiku: stav před / stav po
- Nepoužívat složitou logiku (ratio, váhování) bez ověření, že filtr nezpůsobí degradaci — viz známá past v `modules/analytics.md`

## Workflow — ověřování frontend změn
- Produkce: https://gworm.wormup.com
- Lokální dev: `public/` jako web root, přihlášení `admin@local` / `dokola` (pokud není Google OAuth aktivní)
- Po změně views/JS: otevřít stránku v prohlížeči a projít scénář — čistý syntax check nestačí

## Databázové konvence
- Všude `utf8mb4_czech_ci`
- Názvy sloupců česky, snake_case (`castka_celkem`, `cislo_dokladu`, `duzp`)
- `id INT AUTO_INCREMENT PRIMARY KEY` standard
- Datum: `DATE` pro `duzp`, `DATETIME` pro import/log timestampy
- Peníze: `DECIMAL(18,2)` pro CZK, `DECIMAL(18,4)` pro jednotkové ceny
- Množství: `DECIMAL(18,3)` nebo `DECIMAL(18,6)` (pro BOM koeficienty)
- FK přes `FOREIGN KEY ... ON DELETE CASCADE` tam, kde má smysl

## Bezpečnost — známé nedostatky
- **Žádná CSRF ochrana** ve formulářích — při zásazích do `views/*` nezavádět nové POST bez zvážení (viz `modules/ARCHITECTURE.md`)
- Hesla k Shoptet API se šifrují přes `CryptoService` (AES-256-CBC), klíč v `config.local.php`
- `display_errors=1` v produkci — zvážit při větší refaktorizaci

## Správa projektu — modulární CLAUDE.md systém

### Struktura
- `modules/ARCHITECTURE.md` — celkový přehled projektu, tech stack, DB, vztahy modulů, prostředí
- `modules/{nazev}.md` — stav konkrétního modulu (flat soubory, bez podadresářů)

### Pravidla spolupráce
- Na začátku práce na modulu přečíst příslušný `modules/{nazev}.md`
- Na konci sezení nebo na výzvu **"aktualizuj kontext"**: aktualizovat příslušný modul
- Nikdy nedělat změny v rozporu s `modules/ARCHITECTURE.md` bez upozornění a souhlasu
- Pokud rozhodnutí ovlivní více modulů: upozornit a navrhnout úpravu `ARCHITECTURE.md`
- Pokud chybí kontext: říct to a požádat o příslušný soubor — nikdy nedomýšlet

### Moduly
| Modul | Soubor | Popis |
|-------|--------|-------|
| auth | `modules/auth.md` | Lokální login + Google OAuth, role admin/superadmin/user |
| import | `modules/import.md` | Pohoda/Shoptet XML import, auto-cron, chybějící SKU report |
| produkty | `modules/produkty.md` | Katalog produktů, CSV in/out, typy/značky/skupiny, alt_sku |
| bom | `modules/bom.md` | Kusovníky (rodič→potomek, koeficienty), CSV in/out |
| sklad | `modules/sklad.md` | Inventura, stavy zásob, pohyby skladu |
| rezervace | `modules/rezervace.md` | CRUD rezervací zásob |
| vyroba | `modules/vyroba.md` | Plány výroby, demand tree, záznamy pohybů |
| analytics | `modules/analytics.md` | /analytics/revenue, tržby/marže, AI SQL šablony |
| nastaveni | `modules/nastaveni.md` | Řady, ignor vzory, značky/skupiny/typy/jednotky, uživatelé, globální parametry |
| admin | `modules/admin.md` | Historie přihlášení (access_log.csv) |
| deploy | `modules/deploy.md` | GitHub Actions + FTPS na Webglobe |
