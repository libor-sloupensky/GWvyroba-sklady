<?php
namespace App\Controller;

use App\Support\DB;

final class BomController
{
    public function index(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby FROM bom ORDER BY rodic_sku,potomek_sku LIMIT 1000')->fetchAll();
        $this->render('bom_index.php', ['title'=>'BOM','items'=>$rows]);
    }

    public function exportCsv(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby FROM bom ORDER BY rodic_sku,potomek_sku')->fetchAll();
        $fh = fopen('php://output','wb');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bom.csv"');
        fputcsv($fh, ['rodic_sku','potomek_sku','koeficient','merna_jednotka_potomka','druh_vazby']);
        foreach ($rows as $r) { fputcsv($fh, $r); }
        exit;
    }

    public function importCsv(): void
    {
        $this->requireAdmin();
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) { $this->render('bom_index.php',['title'=>'BOM','error'=>'Soubor nebyl nahrán.']); return; }
        $fh = fopen($_FILES['csv']['tmp_name'],'rb'); if (!$fh){ $this->render('bom_index.php',['title'=>'BOM','error'=>'Nelze číst soubor.']); return; }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $header = fgetcsv($fh);
            $expected = ['rodic_sku','potomek_sku','koeficient','merna_jednotka_potomka','druh_vazby'];
            if (!$header || array_map('strtolower',$header)!==$expected) throw new \RuntimeException('Neplatná hlavička CSV.');
            $seenParents = [];
            $del = $pdo->prepare('DELETE FROM bom WHERE rodic_sku=?');
            $ins = $pdo->prepare('INSERT INTO bom (rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby) VALUES (?,?,?,?,?)');
            $ok=0;$err=[];$i=1;
            while(($row=fgetcsv($fh))!==false){$i++; if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue; [$p,$c,$k,$u,$t]=$row; if($p===''||$c===''){ $err[]="Řádek $i: chybí rodic/potomek"; continue;} if($k===''|| (float)$k<=0){ $err[]="Řádek $i: neplatný koeficient"; continue;} if(!in_array($t,['karton','sada'],true)){ $err[]="Řádek $i: neplatný druh_vazby"; continue;} if(!isset($seenParents[$p])){$seenParents[$p]=true;$del->execute([$p]);} $ins->execute([$p,$c,$k,$u,$t]); $ok++;}
            $pdo->commit();
            $this->render('bom_index.php',['title'=>'BOM','items'=>$pdo->query('SELECT rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby FROM bom ORDER BY rodic_sku,potomek_sku LIMIT 1000')->fetchAll(),'message'=>"Import OK: $ok", 'errors'=>$err]);
        } catch (\Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $this->render('bom_index.php',['title'=>'BOM','error'=>$e->getMessage()]); }
    }

    private function requireAuth(): void { if (!isset($_SESSION['user'])) { header('Location: /login'); exit; } }
    private function requireAdmin(): void { $this->requireAuth(); if (($_SESSION['user']['role'] ?? 'user') !== 'admin'){ http_response_code(403); echo 'Přístup jen pro admina.'; exit; } }
    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
}

