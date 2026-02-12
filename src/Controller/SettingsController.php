<?php

namespace App\Controller;

use App\Service\CryptoService;
use App\Service\ShoptetImportService;
use App\Service\StockService;
use App\Support\DB;

final class SettingsController
{
    public function index(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $series = $pdo->query("SELECT nr.id,nr.eshop_source,nr.prefix,nr.cislo_od,nr.cislo_do,nr.admin_url,nr.admin_email,nr.admin_password_enc, EXISTS(SELECT 1 FROM doklady_eshop de WHERE de.eshop_source = nr.eshop_source LIMIT 1) AS has_imports FROM nastaveni_rady nr ORDER BY nr.eshop_source")->fetchAll();
        $ignores = $pdo->query('SELECT id,vzor FROM nastaveni_ignorovane_polozky ORDER BY id DESC')->fetchAll();
        $glob = $pdo->query('SELECT okno_pro_prumer_dni,spotreba_prumer_dni,zasoba_cil_dni,mena_zakladni,zaokrouhleni,timezone FROM nastaveni_global WHERE id=1')->fetch() ?: [];
        $brands = $pdo->query('SELECT z.id,z.nazev,(SELECT COUNT(*) FROM produkty p WHERE p.znacka_id=z.id) AS used_count FROM produkty_znacky z ORDER BY z.nazev')->fetchAll();
        $groups = $pdo->query('SELECT g.id,g.nazev,(SELECT COUNT(*) FROM produkty p WHERE p.skupina_id=g.id) AS used_count FROM produkty_skupiny g ORDER BY g.nazev')->fetchAll();
        $units = $pdo->query('SELECT u.id,u.kod,(SELECT COUNT(*) FROM produkty p WHERE p.merna_jednotka=u.kod) AS used_count FROM produkty_merne_jednotky u ORDER BY u.kod')->fetchAll();
        $types = $pdo->query('SELECT pt.id,pt.code,pt.name,pt.is_nonstock,(SELECT COUNT(*) FROM produkty p WHERE p.typ=pt.code) AS used_products,(SELECT COUNT(*) FROM rezervace r WHERE r.typ=pt.code) AS used_reservations FROM product_types pt ORDER BY pt.name')->fetchAll();
        $users = $this->isSuperAdmin() ? $this->fetchUsers() : [];
        $flashError = $_SESSION['settings_error'] ?? null;
        $flashMessage = $_SESSION['settings_message'] ?? null;
        unset($_SESSION['settings_error'], $_SESSION['settings_message']);

        $this->render('settings.php', [
            'title' => 'Nastavení',
            'series' => $series,
            'ignores' => $ignores,
            'glob' => $glob,
            'brands' => $brands,
            'groups' => $groups,
            'units' => $units,
            'types' => $types,
            'users' => $users,
            'canManageUsers' => $this->isSuperAdmin(),
            'flashError' => $flashError,
            'flashMessage' => $flashMessage,
        ]);
    }

    public function saveSeries(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $id = max(0, (int)($_POST['id'] ?? 0));
        $eshop = trim((string)($_POST['eshop_source'] ?? ''));
        $prefix = trim((string)($_POST['prefix'] ?? ''));
        $from = trim((string)($_POST['cislo_od'] ?? ''));
        $to = trim((string)($_POST['cislo_do'] ?? ''));
        $adminUrl = trim((string)($_POST['admin_url'] ?? ''));
        $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
        $adminPassword = (string)($_POST['admin_password'] ?? '');

        if ($eshop === '') {
            $_SESSION['settings_error'] = 'Zadejte název e-shopu.';
            header('Location: /settings');
            return;
        }

        // Encrypt password if provided
        $passwordEnc = null;
        if ($adminPassword !== '') {
            $passwordEnc = CryptoService::encrypt($adminPassword);
        }

        $existingStmt = $pdo->prepare('SELECT id, admin_password_enc FROM nastaveni_rady WHERE eshop_source = ? LIMIT 1');
        $existingStmt->execute([$eshop]);
        $existingRow = $existingStmt->fetch();
        $existingId = $existingRow ? (int)$existingRow['id'] : 0;
        $targetId = ($existingId > 0 && $existingId !== $id) ? $existingId : $id;

        if ($targetId > 0) {
            if ($passwordEnc !== null) {
                $st = $pdo->prepare('UPDATE nastaveni_rady SET eshop_source=?,prefix=?,cislo_od=?,cislo_do=?,admin_url=?,admin_email=?,admin_password_enc=? WHERE id=?');
                $st->execute([$eshop, $prefix, $from, $to, $adminUrl ?: null, $adminEmail ?: null, $passwordEnc, $targetId]);
            } else {
                // Keep existing password
                $st = $pdo->prepare('UPDATE nastaveni_rady SET eshop_source=?,prefix=?,cislo_od=?,cislo_do=?,admin_url=?,admin_email=? WHERE id=?');
                $st->execute([$eshop, $prefix, $from, $to, $adminUrl ?: null, $adminEmail ?: null, $targetId]);
            }
            $_SESSION['settings_message'] = "E-shop {$eshop} byl upraven.";
        } else {
            $st = $pdo->prepare('INSERT INTO nastaveni_rady (eshop_source,prefix,cislo_od,cislo_do,admin_url,admin_email,admin_password_enc) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$eshop, $prefix, $from, $to, $adminUrl ?: null, $adminEmail ?: null, $passwordEnc]);
            $_SESSION['settings_message'] = "E-shop {$eshop} byl přidán.";
        }

        // Ověřit přihlašovací údaje, pokud jsou vyplněny
        if ($adminUrl !== '' && $adminEmail !== '') {
            // Zjistit aktuální heslo pro test (nové nebo existující)
            $testPassword = $adminPassword;
            if ($testPassword === '' && $targetId > 0) {
                // Heslo nebylo změněno, načíst existující šifrované a dešifrovat
                $encStmt = $pdo->prepare('SELECT admin_password_enc FROM nastaveni_rady WHERE id = ?');
                $encStmt->execute([$targetId]);
                $encVal = $encStmt->fetchColumn();
                if ($encVal) {
                    try {
                        $testPassword = CryptoService::decrypt((string)$encVal);
                    } catch (\Throwable $e) {
                        $testPassword = '';
                    }
                }
            }

            if ($testPassword !== '') {
                try {
                    $service = new ShoptetImportService();
                    $loginResult = $service->testLogin($adminUrl, $adminEmail, $testPassword);
                    if ($loginResult['ok']) {
                        $_SESSION['settings_message'] .= ' Přihlášení ověřeno ✓';
                    } else {
                        $_SESSION['settings_error'] = 'E-shop uložen, ale přihlášení selhalo: ' . $loginResult['message'];
                    }
                } catch (\Throwable $e) {
                    $_SESSION['settings_error'] = 'E-shop uložen, ale test přihlášení selhal: ' . $e->getMessage();
                }
            }
        }

        header('Location: /settings');
    }

    public function deleteSeries(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['settings_error'] = 'Neplatný požadavek na smazání.';
            header('Location: /settings');
            return;
        }

        $st = $pdo->prepare('SELECT eshop_source FROM nastaveni_rady WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            $_SESSION['settings_error'] = 'Zadaný e-shop neexistuje.';
            header('Location: /settings');
            return;
        }

        $eshop = (string)$row['eshop_source'];
        if ($this->seriesHasImports($eshop)) {
            $_SESSION['settings_error'] = "E-shop {$eshop} má importovaná data a nelze ho smazat.";
            header('Location: /settings');
            return;
        }

        $del = $pdo->prepare('DELETE FROM nastaveni_rady WHERE id=?');
        $del->execute([$id]);
        $_SESSION['settings_message'] = "E-shop {$eshop} byl smazán.";
        header('Location: /settings');
    }

    public function saveIgnore(): void
    {
        $this->requireAdmin();
        $vzor = trim((string)($_POST['vzor'] ?? ''));
        if ($vzor !== '') {
            DB::pdo()->prepare('INSERT INTO nastaveni_ignorovane_polozky (vzor) VALUES (?)')->execute([$vzor]);
        }
        header('Location: /settings');
    }

    public function deleteIgnore(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::pdo()->prepare('DELETE FROM nastaveni_ignorovane_polozky WHERE id=?')->execute([$id]);
        }
        header('Location: /settings');
    }

    public function saveBrand(): void
    {
        $this->requireAdmin();
        $nazev = trim((string)($_POST['nazev'] ?? ''));
        if ($nazev === '') {
            $_SESSION['settings_error'] = 'Zadejte název značky.';
            header('Location: /settings');
            return;
        }

        DB::pdo()->prepare('INSERT INTO produkty_znacky (nazev) VALUES (?)')->execute([$nazev]);
        $_SESSION['settings_message'] = 'Značka byla přidána.';
        header('Location: /settings');
    }

    public function deleteBrand(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $count = DB::pdo()->prepare('SELECT COUNT(*) FROM produkty WHERE znacka_id=?');
            $count->execute([$id]);
            if ($count->fetchColumn()) {
                $_SESSION['settings_error'] = 'Značku nelze smazat, protože je přiřazena k produktům.';
            } else {
                DB::pdo()->prepare('DELETE FROM produkty_znacky WHERE id=?')->execute([$id]);
                $_SESSION['settings_message'] = 'Značka byla smazána.';
            }
        }
        header('Location: /settings');
    }

    public function saveGroup(): void
    {
        $this->requireAdmin();
        $nazev = trim((string)($_POST['nazev'] ?? ''));
        if ($nazev === '') {
            $_SESSION['settings_error'] = 'Zadejte název skupiny.';
            header('Location: /settings');
            return;
        }

        DB::pdo()->prepare('INSERT INTO produkty_skupiny (nazev) VALUES (?)')->execute([$nazev]);
        $_SESSION['settings_message'] = 'Skupina byla přidána.';
        header('Location: /settings');
    }

    public function deleteGroup(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $count = DB::pdo()->prepare('SELECT COUNT(*) FROM produkty WHERE skupina_id=?');
            $count->execute([$id]);
            if ($count->fetchColumn()) {
                $_SESSION['settings_error'] = 'Skupinu nelze smazat, protože je přiřazena k produktům.';
            } else {
                DB::pdo()->prepare('DELETE FROM produkty_skupiny WHERE id=?')->execute([$id]);
                $_SESSION['settings_message'] = 'Skupina byla smazána.';
            }
        }
        header('Location: /settings');
    }

    public function saveUnit(): void
    {
        $this->requireAdmin();
        $kod = trim((string)($_POST['kod'] ?? ''));
        if ($kod === '') {
            $_SESSION['settings_error'] = 'Zadejte kód jednotky.';
            header('Location: /settings');
            return;
        }

        DB::pdo()->prepare('INSERT INTO produkty_merne_jednotky (kod) VALUES (?)')->execute([$kod]);
        $_SESSION['settings_message'] = 'Jednotka byla přidána.';
        header('Location: /settings');
    }

    public function deleteUnit(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $count = DB::pdo()->prepare('SELECT COUNT(*) FROM produkty WHERE merna_jednotka=(SELECT kod FROM produkty_merne_jednotky WHERE id=? LIMIT 1)');
            $count->execute([$id]);
            if ($count->fetchColumn()) {
                $_SESSION['settings_error'] = 'Jednotku nelze smazat, protože je používána v produktech.';
            } else {
                DB::pdo()->prepare('DELETE FROM produkty_merne_jednotky WHERE id=?')->execute([$id]);
                $_SESSION['settings_message'] = 'Jednotka byla smazána.';
            }
        }
        header('Location: /settings');
    }

    public function saveProductType(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $id = max(0, (int)($_POST['id'] ?? 0));
        $codeInput = strtolower(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $isNonstock = isset($_POST['is_nonstock']) ? 1 : 0;

        $row = null;
        if ($id > 0) {
            $st = $pdo->prepare('SELECT id, code FROM product_types WHERE id=? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) {
                $_SESSION['settings_error'] = 'Typ nenalezen.';
                header('Location: /settings');
                return;
            }
        }

        $code = $id > 0 ? (string)$row['code'] : $codeInput;
        if ($name === '') {
            $_SESSION['settings_error'] = 'Zadejte název typu.';
            header('Location: /settings');
            return;
        }
        if ($code === '') {
            $_SESSION['settings_error'] = 'Zadejte kód typu (bez diakritiky).';
            header('Location: /settings');
            return;
        }
        if (!preg_match('/^[a-z0-9_-]+$/i', $code)) {
            $_SESSION['settings_error'] = 'Kód smí obsahovat jen a-z, 0-9, _ a -.';
            header('Location: /settings');
            return;
        }

        if ($id > 0) {
            $upd = $pdo->prepare('UPDATE product_types SET name=?, is_nonstock=? WHERE id=?');
            $upd->execute([$name, $isNonstock, $id]);
            $_SESSION['settings_message'] = 'Typ byl upraven.';
        } else {
            $existing = $pdo->prepare('SELECT id FROM product_types WHERE code=? LIMIT 1');
            $existing->execute([$code]);
            $existingId = (int)($existing->fetchColumn() ?: 0);
            if ($existingId > 0) {
                $upd = $pdo->prepare('UPDATE product_types SET name=?, is_nonstock=? WHERE id=?');
                $upd->execute([$name, $isNonstock, $existingId]);
                $_SESSION['settings_message'] = 'Typ byl upraven.';
            } else {
                $ins = $pdo->prepare('INSERT INTO product_types (code,name,is_nonstock) VALUES (?,?,?)');
                $ins->execute([$code, $name, $isNonstock]);
                $_SESSION['settings_message'] = 'Typ byl přidán.';
            }
        }

        header('Location: /settings');
    }

    public function deleteProductType(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['settings_error'] = 'Neplatný požadavek na smazání typu.';
            header('Location: /settings');
            return;
        }

        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT code FROM product_types WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            $_SESSION['settings_error'] = 'Typ neexistuje.';
            header('Location: /settings');
            return;
        }

        $code = (string)$row['code'];
        $usage = $pdo->prepare('SELECT (SELECT COUNT(*) FROM produkty WHERE typ=?) + (SELECT COUNT(*) FROM rezervace WHERE typ=?) AS total');
        $usage->execute([$code, $code]);
        if ((int)($usage->fetchColumn() ?: 0) > 0) {
            $_SESSION['settings_error'] = 'Typ je použit v produktech nebo rezervacích a nelze ho smazat.';
            header('Location: /settings');
            return;
        }

        $pdo->prepare('DELETE FROM product_types WHERE id=?')->execute([$id]);
        $_SESSION['settings_message'] = 'Typ byl smazán.';
        header('Location: /settings');
    }

    public function saveGlobal(): void
    {
        $this->requireAdmin();
        $okno = max(1, (int)($_POST['okno_pro_prumer_dni'] ?? 30));
        $spotreba = max(1, (int)($_POST['spotreba_prumer_dni'] ?? 90));
        $zasoba = max(1, (int)($_POST['zasoba_cil_dni'] ?? 30));

        DB::pdo()->prepare('UPDATE nastaveni_global SET okno_pro_prumer_dni=?, spotreba_prumer_dni=?, zasoba_cil_dni=? WHERE id=1')->execute([$okno, $spotreba, $zasoba]);
        StockService::recalcAutoSafetyStock();
        $_SESSION['settings_message'] = 'Globální nastavení bylo upraveno.';
        header('Location: /settings');
    }

    public function saveUser(): void
    {
        $this->requireSuperAdmin();
        $pdo = DB::pdo();
        $id = max(0, (int)($_POST['id'] ?? 0));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role = (string)($_POST['role'] ?? 'admin');
        $active = isset($_POST['active']) ? 1 : 0;
        $allowedRoles = ['superadmin', 'admin', 'employee'];

        // Vlastní účet může upravit jen jiný superadmin
        if ($id > 0 && $this->currentUserId() === $id) {
            $_SESSION['settings_error'] = 'Nelze upravit vlastní účet. Požádejte jiného superadmina.';
            header('Location: /settings');
            return;
        }

        if (!in_array($role, $allowedRoles, true)) {
            $_SESSION['settings_error'] = 'Neznámá role.';
            header('Location: /settings');
            return;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE users SET role=?, active=? WHERE id=?');
            $stmt->execute([$role, $active, $id]);
            $_SESSION['settings_message'] = 'Uživatel byl upraven.';
        } else {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['settings_error'] = 'Zadejte platný e-mail.';
                header('Location: /settings');
                return;
            }

            $exists = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $exists->execute([$email]);
            if ($exists->fetchColumn()) {
                $_SESSION['settings_error'] = 'Uživatel s tímto e-mailem již existuje.';
                header('Location: /settings');
                return;
            }

            $stmt = $pdo->prepare('INSERT INTO users (email, role, active) VALUES (?,?,?)');
            $stmt->execute([$email, $role, $active ?: 1]);
            $_SESSION['settings_message'] = 'Uživatel byl přidán.';
        }

        header('Location: /settings');
    }

    private function requireAdmin(): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        $role = $_SESSION['user']['role'] ?? 'user';
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            $this->forbidden('Přístup jen pro administrátory.');
        }
    }

    private function forbidden(string $message): void
    {
        http_response_code(403);
        $this->render('forbidden.php', [
            'title' => 'Přístup odepřen',
            'message' => $message,
        ]);
        exit;
    }

    private function requireSuperAdmin(): void
    {
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo 'Akce je povolena pouze superadministrátorům.';
            exit;
        }
    }

    private function isSuperAdmin(): bool
    {
        return (($this->currentUser()['role'] ?? 'user') === 'superadmin');
    }

    private function currentUser(): array
    {
        return $_SESSION['user'] ?? [];
    }

    private function currentUserId(): int
    {
        return (int)($this->currentUser()['id'] ?? 0);
    }

    private function fetchUsers(): array
    {
        return DB::pdo()->query('SELECT id,email,role,active,created_at FROM users ORDER BY email')->fetchAll();
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function seriesHasImports(string $eshop): bool
    {
        if ($eshop === '') {
            return false;
        }
        $pdo = DB::pdo();
        $check = $pdo->prepare('SELECT 1 FROM doklady_eshop WHERE eshop_source=? LIMIT 1');
        $check->execute([$eshop]);
        return (bool)$check->fetchColumn();
    }
    // noop change: deployment refresh marker
}
