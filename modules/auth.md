# Modul: auth

## Co modul dělá

Autentizace uživatelů — kombinace lokálního hesla a Google OAuth (Google Identity Services). Podporuje tři role: `superadmin` (plné oprávnění včetně správy uživatelů), `admin` (většina operací), `user` (read-only / omezené).

Session je perzistentní (7 dní, HttpOnly cookies, SameSite=Lax). Logování přístupů (jednou za hodinu na uživatele) do `data/access_log.csv` — čte to modul `admin`.

## Kam sahá v kódu

- `src/Controller/AuthController.php` — hlavní logika
- `views/auth_login.php`, `views/auth_login_body.php` — přihlašovací stránka
- `public/index.php:35-52` — session setup + access logging
- `config/config.php` — sekce `auth.superadmins` (hardcoded seznam), `auth.allowed_domain`, `google.*`

## Routes

| Metoda | URL | Akce |
|--------|-----|------|
| GET | `/login` | `AuthController::loginForm` |
| POST | `/login` | `AuthController::loginSubmit` |
| GET | `/logout` | `AuthController::logout` |
| GET | `/auth/google` | `AuthController::googleStart` |
| GET | `/auth/google/callback` | `AuthController::googleCallback` |

## Tabulky

- `users` (id, email, role ENUM, active, password_hash, created_at)
- `data/access_log.csv` — NE tabulka, CSV soubor (timestamp, email)

## Závislosti

- Konzumuje: `nastaveni` (správa uživatelů, viz `nastaveni.md` — `POST /settings/users/save`)
- Používají: všechny ostatní moduly (přes `requireRole()` v každém controlleru)

## Aktuální stav

✅ **Hotovo**
- Lokální login (email + heslo, bcrypt hash v `users.password_hash`)
- Fallback `admin@local` / `dokola` (viz gotchy)
- Google OAuth flow (start → callback → session)
- Persistentní Shoptet session — login jen při vypršení (commit a10ae6e)
- `session_regenerate_id(true)` po úspěšném loginu
- Access logging do CSV (max 1× za hodinu na session)

⚠️ **Známé dluhy / gotchy**
- **Hardcoded fallback heslo** `admin@local` / `dokola` — pro emergency access, odstranit až bude Google OAuth 100 % spolehlivý
- **Žádná CSRF ochrana** na `POST /login`
- **Superadmins jsou v `config.php`** — `['sloupensky@grig.cz']` — změna vyžaduje redeploy (viz [config/config.php:50](config/config.php#L50))
- Role-based access control je ad-hoc — každý controller volá `$this->requireRole([...])`, nelze centrálně auditovat

❌ **Nezačato**
- 2FA
- Password reset flow (superadmin musí heslo nastavit ručně)
- Session revoke pro konkrétního uživatele
