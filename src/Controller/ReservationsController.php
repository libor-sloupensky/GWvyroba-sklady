<?php
namespace App\Controller;

use App\Support\DB;

final class ReservationsController
{
    public function index(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT id,sku,mnozstvi,platna_do,poznamka FROM rezervace ORDER BY platna_do DESC, id DESC')->fetchAll();
        $this->render('reservations.php', ['title'=>'Rezervace','rows'=>$rows]);
    }

    public function save(): void
    {
        $this->requireAuth();
        $id = (int)($_POST['id'] ?? 0);
        $sku = trim((string)($_POST['sku'] ?? ''));
        $qty = (float)($_POST['mnozstvi'] ?? 0);
        $to  = trim((string)($_POST['platna_do'] ?? ''));
        $note= trim((string)($_POST['poznamka'] ?? ''));
        if ($sku === '' || $qty <= 0 || $to === '') { $this->redirect('/reservations'); return; }
        $pdo = DB::pdo();
        if ($id > 0) {
            $st = $pdo->prepare('UPDATE rezervace SET sku=?, mnozstvi=?, platna_do=?, poznamka=? WHERE id=?');
            $st->execute([$sku,$qty,$to,$note,$id]);
        } else {
            $st = $pdo->prepare('INSERT INTO rezervace (sku,mnozstvi,platna_do,poznamka) VALUES (?,?,?,?)');
            $st->execute([$sku,$qty,$to,$note]);
        }
        $this->redirect('/reservations');
    }

    public function delete(): void
    {
        $this->requireAuth();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { DB::pdo()->prepare('DELETE FROM rezervace WHERE id=?')->execute([$id]); }
        $this->redirect('/reservations');
    }

    private function requireAuth(): void { if (!isset($_SESSION['user'])) { header('Location: /login'); exit; } }
    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
    private function redirect(string $p): void { header('Location: '.$p, true, 302); exit; }
}

