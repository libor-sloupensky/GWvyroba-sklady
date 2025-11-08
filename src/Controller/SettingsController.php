<?php
namespace App\Controller;

use App\Support\DB;

final class SettingsController
{
    public function index(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $series = $pdo->query('SELECT id,eshop_source,prefix,cislo_od,cislo_do FROM nastaveni_rady ORDER BY eshop_source')->fetchAll();
        $ignores= $pdo->query('SELECT id,vzor FROM nastaveni_ignorovane_polozky ORDER BY id DESC')->fetchAll();
        $glob   = $pdo->query('SELECT okno_pro_prumer_dni,mena_zakladni,zaokrouhleni,timezone FROM nastaveni_global WHERE id=1')->fetch() ?: [];
        $this->render('settings.php', ['title'=>'Nastavení','series'=>$series,'ignores'=>$ignores,'glob'=>$glob]);
    }

    public function saveSeries(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim((string)$_POST['eshop_source'] ?? ''), trim((string)$_POST['prefix'] ?? ''), trim((string)$_POST['cislo_od'] ?? ''), trim((string)$_POST['cislo_do'] ?? '')];
        if ($id>0) { $st=$pdo->prepare('UPDATE nastaveni_rady SET eshop_source=?,prefix=?,cislo_od=?,cislo_do=? WHERE id=?'); $st->execute([...$data,$id]); }
        else { $st=$pdo->prepare('INSERT INTO nastaveni_rady (eshop_source,prefix,cislo_od,cislo_do) VALUES (?,?,?,?)'); $st->execute($data); }
        header('Location: /settings');
    }

    public function saveIgnore(): void
    {
        $this->requireAdmin();
        $vzor = trim((string)($_POST['vzor'] ?? ''));
        if ($vzor!=='') { DB::pdo()->prepare('INSERT INTO nastaveni_ignorovane_polozky (vzor) VALUES (?)')->execute([$vzor]); }
        header('Location: /settings');
    }

    public function saveGlobal(): void
    {
        $this->requireAdmin();
        $days = (int)($_POST['okno_pro_prumer_dni'] ?? 30);
        $cur  = trim((string)($_POST['mena_zakladni'] ?? 'CZK'));
        $round= trim((string)($_POST['zaokrouhleni'] ?? 'half_up'));
        $tz   = trim((string)($_POST['timezone'] ?? 'Europe/Prague'));
        DB::pdo()->prepare('UPDATE nastaveni_global SET okno_pro_prumer_dni=?, mena_zakladni=?, zaokrouhleni=?, timezone=? WHERE id=1')->execute([$days,$cur,$round,$tz]);
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
}
