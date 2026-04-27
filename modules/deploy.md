# Modul: deploy

## Co modul dělá

Nasazení aplikace na produkci **gworm.wormup.com** (Webglobe shared hosting). Průřezový modul — není součástí business logiky, ale průběžně ho potřebujeme pro všechny ostatní.

Deploy je **automatizovaný přes GitHub Actions**: push do `main` → FTPS sync na server. Inkrementální — jen změněné soubory (state file `.ftp-deploy-sync-state.json`). Žádné CI testy (lint/test/migration) se nespouští.

## Kam sahá v kódu

- `.github/workflows/deploy.yml` — GitHub Actions workflow
- `public/.htaccess` — rewrite pravidla pro web root = `public/`
- `.htaccess` v root — fallback, pokud web root = projekt root (přesměruje vše na `public/index.php`)
- `scripts/migrate.php` — inicializace DB schema (spouští se ručně na serveru)
- `scripts/seed_admin.php` — seed superadmina (ručně)
- `cron.php` — endpoint pro cron (volaný z Webglobe cron systému)

## Deploy mechanika

**CI/CD:** GitHub Actions
- Workflow: `.github/workflows/deploy.yml`
- Trigger: `push` na `main` branch **nebo** `workflow_dispatch` (ruční spuštění)
- Concurrency group: `deploy-gworm`, `cancel-in-progress: true` (rozjezd nového zruší probíhající)

**Transport:** FTPS (port 21) přes `SamKirkland/FTP-Deploy-Action@v4.3.5`
- Cílová cesta: `/public_html/gworm/`
- State file: `.ftp-deploy-sync-state.json` (server-side, pro incremental sync)
- `dangerous-clean-slate: false` — soubory neodstraňuje, jen nahradí změněné

**Secrets v GitHub:**
- `FTP_SERVER` (pravděpodobně `ftp.wormup.com`)
- `FTP_USERNAME`
- `FTP_PASSWORD`

**Exclude:**
```
**/.git*
**/.github/**
**/node_modules/**
**/.DS_Store
**/Thumbs.db
```

## Závislosti

- Nezávisí na ničem v aplikaci
- Žádný jiný modul na něm nezávisí (je to infra)

## Prostředí

| Parametr | Hodnota |
|----------|---------|
| Doména | gworm.wormup.com |
| Server | Webglobe (shared hosting) |
| FTPS host | `ftp.wormup.com` (v GitHub secret) |
| Web root | `/public_html/gworm/` (musí ukazovat na `public/` nebo použít root `.htaccess`) |
| DB host | `db.dw164.webglobe.com` |
| DB název | `db_gworm` |
| Cron | přes Webglobe cron panel → `GET https://gworm.wormup.com/cron.php?token=...` |

## Aktuální stav

✅ **Hotovo**
- GitHub Actions workflow funguje (push → deploy v řádu minut)
- Inkrementální sync (nepřenáší nezměněné soubory)
- Gitignored: `config/config.local.php`, `data/`, `log/`, `_trash/`, `.ftp-deploy-sync-state.json`

⚠️ **Známé dluhy / gotchy**
- **Žádné CI testy** — push kódu, který se nezkompiluje (PHP parse error), skončí na produkci chybovou stránkou. Nutno spouštět `php -l` lokálně před commitem (zmíněno v root `CLAUDE.md`).
- **Žádné automatické migrace** — pokud změním `db/schema.sql`, musím DDL provést ručně na serveru (přes phpMyAdmin nebo scripts/migrate.php). Preferujeme `ADD COLUMN IF NOT EXISTS` / `CREATE TABLE IF NOT EXISTS`.
- **Žádný rollback mechanismus** — vrátit změnu = revert commit + push
- **State file `.ftp-deploy-sync-state.json`** — při problémech se sync stavem (např. se soubor poškodí) se musí ručně smazat přes FTP, aby se deploy znovu inicializoval z nuly
- **FTP protokol** (FTPS na port 21) — už je to dnes retro, ale Webglobe SSH nepodporuje na shared
- **Citlivá data** (DB heslo, Google secret, OpenAI klíč) jsou v `config/config.php` zahrnutém v repozitáři — **na veřejný push pozor!**. Lepší přesunout do `config/config.local.php` (gitignored) a používat fallback `getenv()`.

❌ **Nezačato**
- Staging / preview prostředí (dev.gworm.wormup.com)
- Automatické migrace při deployi (např. post-deploy hook `?run=migrate&token=...`)
- Health check po deployi (ping na `/` → 200 OK)
- Notifikace o úspěšném/neúspěšném deployi (Slack, email)
- PHP lint v CI před deployem
