# Sklad + Výroba (čistá implementace)

UTF‑8, PHP (bez frameworku), MySQL (utf8mb4_czech_ci). Viz `db/schema.sql`.

## Rychlý start
1) Nakonfiguruj `config/config.php` (DSN, user, pass).
2) Spusť migraci: `php scripts/migrate.php`
3) Seed admina: `php scripts/seed_admin.php admin@local` (heslo: `dokola`)
4) Nastav web root na `public/` (viz `public/.htaccess`) a otevři `/login`.

## Funkce (stav)
- Import Pohoda XML: formulář `/import` (parser – TODO dle MASTER_PROMPT; zatím stub)
- Produkty: CSV import/export dle hlaviček v UI
- BOM: CSV import/export (`karton`/`sada`, replace‑per‑parent)
- Inventura: zápis pohybu „inventura“
- Rezervace: CRUD (jen pro produkt)
- Výroba: návrhy – náhled; zápis „vyrobeno“ (korekce/odečet subpotomků), reverze dle ref_id
- Analýza: přehled položek dokladů s filtry
- Nastavení: řady, ignor vzory, globální parametry
- Plány: `/plany` čte `docs/PLANS.json`

## CSV hlavičky
Produkty: `sku,nazev,typ,merna_jednotka,ean,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni,aktivni`

BOM: `rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby`

## Poznámky
- Všude UTF‑8.
- DB collation: `utf8mb4_czech_ci`.
- Footer zobrazuje „Poslední úprava“ (mtime) a „Verze/Deploy“ z configu.

