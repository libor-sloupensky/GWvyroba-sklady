<?php
namespace App\Controller;

use App\Support\DB;

final class AnalyticsController
{
    public function revenue(): void
    {
        $this->requireAuth();
        $from = (string)($_GET['from'] ?? '');
        $to   = (string)($_GET['to'] ?? '');
        $pdo = DB::pdo();
        $sql = 'SELECT duzp, eshop_source, sku, nazev, mnozstvi, cena_jedn_czk FROM polozky_eshop';
        $conds=[];$p=[]; if($from!==''){ $conds[]='duzp>=?'; $p[]=$from; } if($to!==''){ $conds[]='duzp<=?'; $p[]=$to; }
        if($conds){ $sql.=' WHERE '.implode(' AND ',$conds); }
        $sql.=' ORDER BY duzp DESC LIMIT 1000';
        $st = $pdo->prepare($sql); $st->execute($p); $rows=$st->fetchAll();
        $this->render('analytics_revenue.php', ['title'=>'Analýza obratu','rows'=>$rows,'from'=>$from,'to'=>$to]);
    }
    private function requireAuth(): void {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }
    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
}
