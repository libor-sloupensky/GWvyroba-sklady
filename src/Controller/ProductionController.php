<?php
namespace App\Controller;

use App\Support\DB;

final class ProductionController
{
    public function plans(): void
    {
        $this->requireAuth();
        // Minimal stub: fetch products and render with placeholder columns; implement full calc later.
        $pdo = DB::pdo();
        $items = $pdo->query("SELECT sku,nazev,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni FROM produkty WHERE typ='produkt' AND aktivni=1 ORDER BY nazev")->fetchAll();
        $this->render('production_plans.php', ['title'=>'Výroba – návrhy','items'=>$items,'notice'=>'Výpočet doplnit dle MASTER PROMPT (forecast, rezervace, dostupné, návrh).']);
    }

    public function produce(): void
    {
        $this->requireAuth();
        $sku = trim((string)($_POST['sku'] ?? ''));
        $qty = (float)($_POST['mnozstvi'] ?? 0);
        $mode = (string)($_POST['modus'] ?? 'korekce'); // korekce|odecti_subpotomky
        if ($sku===''||$qty<=0){ $this->redirect('/production/plans'); return; }
        $pdo = DB::pdo();
        $ref = 'prod-'.date('YmdHis').'-'.bin2hex(random_bytes(3));
        // 1) naskladni hotový produkt
        $ins = $pdo->prepare('INSERT INTO polozky_pohyby (datum,sku,mnozstvi,merna_jednotka,typ_pohybu,poznamka,ref_id) VALUES (NOW(),?,?,?,?,?,?)');
        $ins->execute([$sku, $qty, null, 'vyroba', null, $ref]);
        // 2) odečet přímých potomků dle BOM (sada) -> pro jednoduchost zde jen zaznamenáme korekci nebo odečet; plná logika viz MASTER PROMPT
        if ($mode === 'odecti_subpotomky') {
            $st = $pdo->prepare("SELECT potomek_sku, koeficient, merna_jednotka_potomka FROM bom WHERE rodic_sku=? AND druh_vazby='sada'");
            $st->execute([$sku]);
            foreach ($st as $r) {
                $csku = (string)$r['potomek_sku']; $k=(float)$r['koeficient']; $u=$r['merna_jednotka_potomka'];
                $ins->execute([$csku, -1*($qty*$k), $u, 'vyroba', 'odečet komponenty', $ref]);
            }
        } else {
            // default korekce – jen záznam pro přehled
            $ins->execute([$sku.'*', 0, null, 'korekce', 'Komponenty k odečtu – řešit dle zásob (default: korekce)', $ref]);
        }
        $this->redirect('/production/plans');
    }

    public function deleteRecord(): void
    {
        $this->requireAuth();
        $ref = (string)($_POST['ref_id'] ?? '');
        if ($ref !== '') { DB::pdo()->prepare('DELETE FROM polozky_pohyby WHERE ref_id=?')->execute([$ref]); }
        $this->redirect('/production/plans');
    }

    private function requireAuth(): void { if (!isset($_SESSION['user'])) { header('Location: /login'); exit; } }
    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
    private function redirect(string $p): void { header('Location: '.$p, true, 302); exit; }
}

