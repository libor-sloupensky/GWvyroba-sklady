<?php
namespace App\Controller;

use App\Support\DB;

final class InventoryController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->render('inventory.php', ['title'=>'Inventura']);
    }

    public function addMove(): void
    {
        $this->requireAuth();
        $sku = trim((string)($_POST['sku'] ?? ''));
        $qty = (float)($_POST['mnozstvi'] ?? 0);
        $mj  = trim((string)($_POST['merna_jednotka'] ?? ''));
        $note= trim((string)($_POST['poznamka'] ?? ''));
        if ($sku === '' || $qty === 0) { http_response_code(400); echo 'Chybí sku nebo množství'; return; }
        $pdo = DB::pdo();
        $ins = $pdo->prepare('INSERT INTO polozky_pohyby (datum,sku,mnozstvi,merna_jednotka,typ_pohybu,poznamka,ref_id) VALUES (NOW(),?,?,?,?,?,NULL)');
        $ins->execute([$sku,$qty,$mj,'inventura',$note]);
        header('Location: /inventory');
    }

    private function requireAuth(): void { if (!isset($_SESSION['user'])) { header('Location: /login'); exit; } }
    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
}

