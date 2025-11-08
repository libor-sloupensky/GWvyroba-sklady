<?php
namespace App\Controller;

use App\Support\DB;

final class SettingsController
{
    public function index(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $series = $pdo->query('SELECT nr.id,nr.eshop_source,nr.prefix,nr.cislo_od,nr.cislo_do, EXISTS(SELECT 1 FROM doklady_eshop de WHERE de.eshop_source = nr.eshop_source LIMIT 1) AS has_imports FROM nastaveni_rady nr ORDER BY nr.eshop_source')->fetchAll();
        $ignores= $pdo->query('SELECT id,vzor FROM nastaveni_ignorovane_polozky ORDER BY id DESC')->fetchAll();
        $glob   = $pdo->query('SELECT okno_pro_prumer_dni,mena_zakladni,zaokrouhleni,timezone FROM nastaveni_global WHERE id=1')->fetch() ?: [];
        $brands = $pdo->query('SELECT z.id,z.nazev,(SELECT COUNT(*) FROM produkty p WHERE p.znacka_id=z.id) AS used_count FROM produkty_znacky z ORDER BY z.nazev')->fetchAll();
        $groups = $pdo->query('SELECT g.id,g.nazev,(SELECT COUNT(*) FROM produkty p WHERE p.skupina_id=g.id) AS used_count FROM produkty_skupiny g ORDER BY g.nazev')->fetchAll();
        $flashError = $_SESSION['settings_error'] ?? null;
        $flashMessage = $_SESSION['settings_message'] ?? null;
        unset($_SESSION['settings_error'], $_SESSION['settings_message']);
        $this->render('settings.php', [
            'title'=>'Nastavení',
            'series'=>$series,
            'ignores'=>$ignores,
            'glob'=>$glob,
            'brands'=>$brands,
            'groups'=>$groups,
            'flashError'=>$flashError,
            'flashMessage'=>$flashMessage
        ]);
    }

    public function saveSeries(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $id = max(0, (int)($_POST['id'] ?? 0));
        $eshop = trim((string)($_POST['eshop_source'] ?? ''));
        $prefix = trim((string)($_POST['prefix'] ?? ''));
        $from   = trim((string)($_POST['cislo_od'] ?? ''));
        $to     = trim((string)($_POST['cislo_do'] ?? ''));

        if ($eshop === '') {
            $_SESSION['settings_error'] = 'Zadejte název e-shopu.';
            header('Location: /settings');
            return;
        }

        $existingStmt = $pdo->prepare('SELECT id FROM nastaveni_rady WHERE eshop_source = ? LIMIT 1');
        $existingStmt->execute([$eshop]);
        $existingId = (int)($existingStmt->fetchColumn() ?: 0);

        if ($existingId > 0 && $existingId !== $id) {
            $id = 0;
            $targetId = $existingId;
        } else {
            $targetId = $id;
        }

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
        if ($vzor!=='') {
            DB::pdo()->prepare('INSERT INTO nastaveni_ignorovane_polozky (vzor) VALUES (?)')->execute([$vzor]);
            $_SESSION['settings_message'] = 'Ignorovaná položka přidána.';
        }
        header('Location: /settings');
    }

    public function deleteIgnore(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['settings_error'] = 'Neplatný požadavek na odstranění ignorované položky.';
            header('Location: /settings');
            return;
        }
        $pdo = DB::pdo();
        $st = $pdo->prepare('DELETE FROM nastaveni_ignorovane_polozky WHERE id=?');
        $st->execute([$id]);
        if ($st->rowCount() > 0) {
            $_SESSION['settings_message'] = 'Ignorovaná položka odstraněna.';
        } else {
            $_SESSION['settings_error'] = 'Ignorovaná položka nenalezena.';
        }
        header('Location: /settings');
    }

    public function saveBrand(): void
    {
        $this->requireAdmin();
        $name = trim((string)($_POST['nazev'] ?? ''));
        if ($name === '') {
            $_SESSION['settings_error'] = 'Zadejte název značky.';
        } else {
            try {
                DB::pdo()->prepare('INSERT INTO produkty_znacky (nazev) VALUES (?)')->execute([$name]);
                $_SESSION['settings_message'] = 'Značka přidána.';
            } catch (\PDOException $e) {
                $_SESSION['settings_error'] = 'Značka již existuje.';
            }
        }
        header('Location: /settings');
    }

    public function deleteBrand(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['settings_error'] = 'Neplatný požadavek na odstranění značky.';
            header('Location: /settings');
            return;
        }
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM produkty WHERE znacka_id=?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $_SESSION['settings_error'] = 'Značku nelze smazat, je použita u produktů.';
            header('Location: /settings');
            return;
        }
        $del = $pdo->prepare('DELETE FROM produkty_znacky WHERE id=?');
        $del->execute([$id]);
        $_SESSION['settings_message'] = 'Značka odstraněna.';
        header('Location: /settings');
    }

    public function saveGroup(): void
    {
        $this->requireAdmin();
        $name = trim((string)($_POST['nazev'] ?? ''));
        if ($name === '') {
            $_SESSION['settings_error'] = 'Zadejte název skupiny.';
        } else {
            try {
                DB::pdo()->prepare('INSERT INTO produkty_skupiny (nazev) VALUES (?)')->execute([$name]);
                $_SESSION['settings_message'] = 'Skupina přidána.';
            } catch (\PDOException $e) {
                $_SESSION['settings_error'] = 'Skupina již existuje.';
            }
        }
        header('Location: /settings');
    }

    public function deleteGroup(): void
    {
        $this->requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['settings_error'] = 'Neplatný požadavek na odstranění skupiny.';
            header('Location: /settings');
            return;
        }
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM produkty WHERE skupina_id=?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $_SESSION['settings_error'] = 'Skupinu nelze smazat, je použitá u produktů.';
            header('Location: /settings');
            return;
        }
        $del = $pdo->prepare('DELETE FROM produkty_skupiny WHERE id=?');
        $del->execute([$id]);
        $_SESSION['settings_message'] = 'Skupina odstraněna.';
        header('Location: /settings');
    }

    public function saveGlobal(): void
    {
        $this->requireAdmin();
        $days = (int)($_POST['okno_pro_prumer_dni'] ?? 30);
        DB::pdo()->prepare('UPDATE nastaveni_global SET okno_pro_prumer_dni=? WHERE id=1')->execute([$days]);
        header('Location: /settings');
    }

    private function requireAdmin(): void {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        if (($_SESSION['user']['role'] ?? 'user') !== 'admin'){
            http_response_code(403);
            echo 'Přístup jen pro admina.';
            exit;
        }
    }

    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }

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
