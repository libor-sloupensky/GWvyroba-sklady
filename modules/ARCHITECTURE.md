# Architektura projektu — Gworm

## Popis aplikace

Webová aplikace pro **správu skladu + výroby + analýzu prodejů** firmy Grig/WormUp (Gworm = **G**rig + **Worm**up). Běží na doméně **gworm.wormup.com**.

Hlavní činnosti:
1. **Import** faktur z eshopů (Shoptet XML + Pohoda XML)
2. **Správa produktů** (SKU katalog, typy, BOM/kusovníky)
3. **Sklad** — inventury, stavy zásob, pohyby
4. **Výroba** — návrhy (demand tree), zápis vyrobeného, sledování pohybů
5. **Analýzy** — tržby, marže, AI SQL queries

Cílová skupina: interní tým (admin/superadmin/user). Aplikace je privátní, ne veřejná.

---

## Technologický stack

| Vrstva | Technologie |
|--------|-------------|
| Backend | Čistý PHP 8.x (`declare(strict_types=1)`), bez frameworku |
| Autoload | Vlastní PSR-4 v `src/bootstrap.php` (namespace `App\`) |
| Composer | **Není** — pure PHP bez externích dependencies |
| Frontend | Server-rendered PHP šablony v `views/`, vanilla JS, minimum CSS |
| Databáze | MySQL 5.7/MariaDB na Webglobe hostingu, `utf8mb4_czech_ci` |
| Router | Vlastní mini-router (`src/Support/Router.php`) — jen GET/POST, žádný regex |
| Session | Nativní PHP, 7 dní lifetime, HttpOnly, SameSite=Lax |
| Auth | Lokální heslo + Google OAuth (Google Identity Services) |
| AI | OpenAI API (gpt-4o-mini default) — jen v modulu analytics |
| Deploy | GitHub Actions + FTPS na Webglobe — viz `deploy.md` |

---

## Moduly a jejich vztahy

```
┌───────────────────────────────────────────────────────────┐
│                         ADMIN (audit)                      │
└──────────────────────────────┬────────────────────────────┘
                               │
     ┌─────────────┬───────────┼──────────┬────────────┐
     ▼             ▼           ▼          ▼            ▼
   AUTH       NASTAVENI     IMPORT    ANALYTICS     DEPLOY
                  │            │          │
                  │            ▼          │
                  │      doklady_eshop    │
                  │      polozky_eshop    │
                  │            │          │
                  ▼            ▼          ▼
              PRODUKTY ─── BOM            (dotazuje vše)
                  │         │
                  │    ┌────┴────┐
                  ▼    ▼         ▼
              REZERVACE  VYROBA ── SKLAD
                            │        │
                            └────────┘
                         polozky_pohyby
                         inventury
                         inventura_stavy
```

### Závislosti mezi moduly

- `auth` → nezávislý, poskytuje kontrolu rolí pro vše ostatní
- `nastaveni` → spravuje řady, značky, skupiny, typy, uživatele — konzumují všechny ostatní
- `import` → konzumuje `nastaveni` (řady, ignor vzory), produkuje `doklady_eshop` + `polozky_eshop` → využívá `analytics`
- `produkty` → závisí na číselnících z `nastaveni`, propojené s `bom`
- `bom` → závisí na `produkty` (rodič/potomek SKU)
- `sklad` → základ pro `vyroba`, `rezervace`, `produkty.skl_hodnota`
- `rezervace` → čte `produkty`, ovlivňuje dostupný stav počítaný ve `vyroba`/`sklad`
- `vyroba` → využívá `bom` (demand tree), zapisuje do `polozky_pohyby` (jako `sklad`)
- `analytics` → čte `doklady_eshop`, `polozky_eshop`, `produkty`, `bom`
- `admin` → jen čte `data/access_log.csv`
- `deploy` → průřezový

---

## Struktura projektu

```
gworm/
├── public/
│   ├── index.php          # front controller + router setup
│   └── .htaccess          # URL rewrite na index.php
├── src/
│   ├── bootstrap.php      # autoload, timezone, session
│   ├── Controller/        # 11 controllers (1 per modul)
│   ├── Service/           # ShoptetImportService, StockService, CryptoService, AnalyticsSchema
│   └── Support/           # Router (mini HTTP router), DB (PDO singleton)
├── views/                 # 15 .php šablon + _layout.php
├── db/
│   └── schema.sql         # všechny CREATE TABLE
├── config/
│   ├── config.php         # DB, Google OAuth, OpenAI, encryption key
│   └── config.local.php   # gitignored, env-overrides
├── scripts/
│   ├── migrate.php              # vytvoří schema
│   ├── seed_admin.php           # vytvoří superadmin uživatele
│   └── shoptet_auto_import.php  # CLI auto-import
├── data/
│   └── access_log.csv     # login history
├── cron.php               # HTTP cron endpoint (token-protected)
├── xml/                   # vzorky XML faktur
├── _trash/                # gitignored, dev/diagnostické skripty
├── .github/workflows/
│   └── deploy.yml         # FTPS deploy
├── .htaccess              # fallback rewrite (pokud web root = projekt root)
└── README.md
```

---

## Databáze

**Hosting:** `db.dw164.webglobe.com`, databáze `db_gworm`, user `gworm` (viz `config/config.php`).

**Charset/collation:** `utf8mb4` + `utf8mb4_czech_ci` na všech tabulkách.

**Engine:** InnoDB (podporuje FK constraints).

### Klíčové tabulky

| Tabulka | Modul | Popis |
|---------|-------|-------|
| `users` | auth | Uživatelé + role (`admin`/`superadmin`/`user`) |
| `kontakty` | import | Kontakty z importovaných dokladů (IČ, jméno, firma) |
| `doklady_eshop` | import | Faktury z eshopů (číslo, datum, částka, kontakt) |
| `polozky_eshop` | import | Položky faktur (SKU, množství, cena) |
| `produkty` | produkty | Katalog SKU + atributy + `skl_hodnota` |
| `produkty_znacky`, `produkty_skupiny`, `produkty_merne_jednotky`, `product_types` | nastaveni | Číselníky |
| `bom` | bom | Kusovníky (rodič_sku, potomek_sku, koeficient) |
| `inventury` | sklad | Inventury (otevřená/zavřená) |
| `inventura_polozky` | sklad | Položky inventury |
| `inventura_stavy` | sklad | Vypočtené stavy zásob (při uzavření) |
| `polozky_pohyby` | sklad/vyroba | Pohyby: `inventura`/`vyroba`/`korekce`/`odpis` |
| `rezervace` | rezervace | Blokace zásob |
| `nastaveni_rady`, `nastaveni_ignorovane_polozky`, `nastaveni_global` | nastaveni | Řady dokladů, ignorované SKU vzory, globální parametry |
| `ai_prompts` | analytics | Uložené AI SQL favorit queries |

---

## Routing

Router je v `src/Support/Router.php` — jednoduchý GET/POST match na přesnou cestu. Kompletní seznam routes v `public/index.php`. Každý modul má svou sekci.

---

## Session a autentizace

- `session_start()` v `public/index.php:35` po nastavení cookie params
- Lifetime: 7 dní (`60 * 60 * 24 * 7`)
- Cookie: `HttpOnly`, `SameSite=Lax`, `Secure` když je HTTPS
- Po přihlášení: `session_regenerate_id(true)` (v `AuthController`)
- Role check: v každém controlleru přes vlastní metodu `requireRole([...])`

---

## Bezpečnost — známé nedostatky

⚠️ **Seznam vědomých dluhů** (při refaktorizaci mít v hlavě):

- **Žádná CSRF ochrana** ve formulářích (všechny POST endpointy jsou chráněné jen session + rolí)
- **`display_errors=1`** v produkci → může leakovat stack traces
- **Lokální admin heslo** `dokola` je hardcoded v `AuthController` (pro fallback)
- **Cron token** (`config.cron_token`) musí být v URL parametru — může se logovat v access logu
- **PDO** používá prepared statements všude, SQL injection by neměl být problém
- **XSS**: views většinou používají `htmlspecialchars()`, ale nejsou všude konzistentně

---

## Prostředí

| Parametr | Hodnota |
|----------|---------|
| Doména | gworm.wormup.com |
| Web root | `public/` (případně projekt root + fallback `.htaccess`) |
| DB host | `db.dw164.webglobe.com` |
| DB název | `db_gworm` |
| DB user | `gworm` |
| PHP | 8.x (minimálně 8.0) |
| Timezone | `Europe/Prague` |
| Session lifetime | 7 dní |
| GitHub repo | `libor-sloupensky/Gworm` (privátní) |

### Citlivé údaje
- Google OAuth client_id/secret — `config/config.local.php` nebo env `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`
- OpenAI API key — `config/config.local.php` nebo env `OPENAI_API_KEY`
- Encryption key (pro Shoptet hesla) — env `ENCRYPTION_KEY`, default `'gw0rm-s3cr3t-k3y-ch4ng3-m3'` (⚠️ fallback je slabý, v produkci nastavit silný)
- Cron token — env `CRON_TOKEN`, default `'gworm-auto-import-2025'`

---

## Timestamps

Vygenerováno: 2026-04-20
