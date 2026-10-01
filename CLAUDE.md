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

## Vzhled — tokeny podle Kalkulia / Projektovny (od 2026-09-30)
- Zdroj pravdy: `:root` v `views/_layout.php`. **Brand oranžová nikdy hexem**, vždy tokenem: `--c-primary` (#dd5500, dekorace, linka pod menu, ikony), `--c-header` (#3a3c3f, tmavě šedá hlavička s bílým textem – pod ní 3px oranžová linka, aktivní položka má světlejší podklad a bílý úsek linky), `--c-primary-text` (#c14a00, text a odkazy na světlém), `--c-primary-akcent` (#c14a00, plocha tlačítek s bílým textem), `--c-primary-akcent-hover` (#aa4400), `--c-primary-tint` (jemný podklad badge).
- Neutrály teplé, ne modrošedé: `--c-surface` #f6f5f2 (podklad stránky), `--c-surface-2` #faf9f7 (hlavičky tabulek), `--c-text` #111, `--c-text-secondary` #5f5e5a, `--c-text-muted` #8a8985, `--c-border` #e5e5e5, `--c-border-strong` #d6d4cf. Staré modrošedé hexy (#263238 #455a64 #546e7a #607d8b #78909c #90a4ae #cfd8dc #eceff1 #f1f5f9 #1565c0 #e3f2fd) byly hromadně nahrazeny – **nezavádět je znovu**.
- Font **Nunito** (Google Fonts, 600 body / 700 tlačítka / 800 nadpisy), načtený v layoutu. Zaoblení 4 / 8 / 12 px.
- Pravidlo 10 % oranžová, 90 % neutrály: oranžová jen pro akce (submit tlačítka, aktivní položka menu, odkazy). Nebezpečí zůstává červené (`--c-danger`), potvrzení zelené (`--c-ok`).
- Globální styl tlačítek, inputů, tabulek a odkazů je v layoutu; view přidávají jen výjimky.

## Ikony — Lucide (od 2026-09-30, stejný přístup jako Kalkulio / TupTuDu Office)
- **Vždy Lucide** přes `ikona('nazev', velikost, 'css-trida')` (helper v `src/bootstrap.php`, sada v `src/Support/Lucide.php`, vložené SVG, žádná závislost ani CDN). V JavaScriptu `LUCIDE['chevron-down']`, `LUCIDE.x` … (mapa vložená v `views/_layout.php`).
- **Nikdy emoji ani ručně psané znaky** (✕ ▸ ▾ × ✓ ⚠ 🔒 ＋ ←) jako UI ikony. Textové značky ✓/✕ u stavu nahrazuje slovo Ano/Ne. Stromové čáry `└── ├──` u komponent zůstávají (nejsou ikona).
- Barva se dědí z textu (`stroke="currentColor"`), velikost 14 px u tlačítek v tabulce, 16 px standard, 18 px nadpisy sekcí. Pomocné třídy `.ikona-btn` (ikona + text), `.ikona-muted`, `.ikona-danger`.
- Novou ikonu **nekresli ručně**: `curl -sSL https://unpkg.com/lucide-static@1.34.0/icons/NAZEV.svg`, vnitřek `<svg>` vlož do `Lucide::IKONY`. Seznam dostupných: `Lucide::seznam()`, přehled na lucide.dev/icons.
- Tooltipy: otazník `circle-help`, informace `info`; řazení tabulek přes CSS `background-image` s data-URI (viz `analytics_revenue.php`), protože `content:` SVG neumí.

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
| pohyby | `modules/pohyby.md` | Skladové doklady (výroba/korekce), autosave, zámky, log změn |
| analytics | `modules/analytics.md` | /analytics/revenue, tržby/marže, AI SQL šablony |
| nastaveni | `modules/nastaveni.md` | Řady, ignor vzory, značky/skupiny/typy/jednotky, uživatelé, globální parametry |
| deploy | `modules/deploy.md` | GitHub Actions + FTPS na Webglobe |
