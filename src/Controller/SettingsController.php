<?php
namespace App\Controller;

use App\Service\StockService;
use App\Support\DB;

final class SettingsController
{
    public function index(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $series = $pdo->query("SELECT nr.id,nr.eshop_source,nr.prefix,nr.cislo_od,nr.cislo_do, EXISTS(SELECT 1 FROM doklady_eshop de WHERE de.eshop_source = nr.eshop_source LIMIT 1) AS has_imports FROM nastaveni_rady nr ORDER BY nr.eshop_source")
            ->fetchAll();
        $ignores = $pdo->query('SELECT id,vzor FROM nastaveni_ignorovane_polozky ORDER BY id DESC')->fetchAll();
        $glob = $pdo->query('SELECT okno_pro_prumer_dni,spotreba_prumer_dni,zasoba_cil_dni,mena_zakladni,zaokrouhleni,timezone FROM nastaveni_global WHERE id=1')->fetch() ?: [];
        $brands = $pdo->query('SELECT z.id,z.nazev,(SELECT COUNT(*) FROM produkty p WHERE p.znacka_id=z.id) AS used_count FROM produkty_znacky z ORDER BY z.nazev')->fetchAll();
        $groups = $pdo->query('SELECT g.id,g.nazev,(SELECT COUNT(*) FROM produkty p WHERE p.skupina_id=g.id) AS used_count FROM produkty_skupiny g ORDER BY g.nazev')->fetchAll();
        $units = $pdo->query('SELECT u.id,u.kod,(SELECT COUNT(*) FROM produkty p WHERE p.merna_jednotka=u.kod) AS used_count FROM produkty_merne_jednotky u ORDER BY u.kod')->fetchAll();
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
        if ($eshop === '') {
            $_SESSION['settings_error'] = 'Zadejte název e-shopu.';
            header('Location: /settings');
            return;
        }
        $existingStmt = $pdo->prepare('SELECT id FROM nastaveni_rady WHERE eshop_source = ? LIMIT 1');
        $existingStmt->execute([$eshop]);
        $existingId = (int)($existingStmt->fetchColumn() ?: 0);
        $targetId = ($existingId > 0 && $existingId !== $id) ? $existingId : $id;
        if ($targetId > 0) {
            $st = $pdo->prepare('UPDATE nastaveni_rady SET eshop_source=?,prefix=?,cislo_od=?,cislo_do=? WHERE id=?');
            $st->execute([$eshop, $prefix, $from, $to, $targetId]);
            $_SESSION['settings_message'] = "E-shop {$eshop} byl upraven.";
        } else {
            $st = $pdo->prepare('INSERT INTO nastaveni_rady (eshop_source,prefix,cislo_od,cislo_do) VALUES (?,?,?,?)');
            $st->execute([$eshop, $prefix, $from, $to]);
            $_SESSION['settings_message'] = "E-shop {$eshop} byl přidán.";
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

    public function saveGlobal(): void
    {
        $this->requireAdmin();
        $okno = max(1, (int)($_POST['okno_pro_prumer_dni'] ?? 30));
        $spotreba = max(1, (int)($_POST['spotreba_prumer_dni'] ?? 90));
        $zasoba = max(1, (int)($_POST['zasoba_cil_dni'] ?? 30));
        DB::pdo()->prepare('UPDATE nastaveni_global SET okno_pro_prumer_dni=?, spotreba_prumer_dni=?, zasoba_cil_dni=? WHERE id=1')
            ->execute([$okno, $spotreba, $zasoba]);
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
        $allowedRoles = ['superadmin','admin','employee'];
        if (!in_array($role, $allowedRoles, true)) {
            $_SESSION['settings_error'] = 'Neznámá role.';
            header('Location: /settings');
            return;
        }
        if ($id > 0) {
            if ($this->currentUserId() === $id && $active === 0) {
                $_SESSION['settings_error'] = 'Nemůžete deaktivovat vlastní účet.';
                header('Location: /settings');
                return;
            }
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
            $stmt = $pdo->prepare('INSERT INTO users (email, role, active) VALUES (?,?,1)');
            $stmt->execute([$email, $role]);
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
        if (!in_array($role, ['admin','superadmin'], true)) {
            http_response_code(403);
            echo 'Přístup jen pro administrátory.';
            exit;
        }
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
}
