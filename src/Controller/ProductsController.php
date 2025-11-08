<?php
namespace App\Controller;

use App\Support\DB;

final class ProductsController
{
    public function index(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT sku,nazev,typ,merna_jednotka,ean,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni,aktivni FROM produkty ORDER BY nazev LIMIT 500')->fetchAll();
        $this->render('products_index.php', ['title'=>'Produkty','items'=>$rows]);
    }

    public function exportCsv(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT sku,nazev,typ,merna_jednotka,ean,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni,aktivni FROM produkty ORDER BY nazev')->fetchAll();
        $fh = fopen('php://output','wb');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="produkty.csv"');
        $delimiter = ',';
        $enclosure = '"';
        $escape = '\\';
        fputcsv($fh, ['sku','nazev','typ','merna_jednotka','ean','min_zasoba','min_davka','krok_vyroby','vyrobni_doba_dni','aktivni'], $delimiter, $enclosure, $escape);
        foreach ($rows as $r) {
            fputcsv($fh, $r, $delimiter, $enclosure, $escape);
        }
        exit;
    }

    public function importCsv(): void
    {
        $this->requireAdmin();
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) { $this->render('products_index.php',['title'=>'Produkty','error'=>'Soubor nebyl nahrán.']); return; }
        $fh = fopen($_FILES['csv']['tmp_name'],'rb'); if (!$fh){ $this->render('products_index.php',['title'=>'Produkty','error'=>'Nelze číst soubor.']); return; }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $header = fgetcsv($fh);
            $expected = ['sku','nazev','typ','merna_jednotka','ean','min_zasoba','min_davka','krok_vyroby','vyrobni_doba_dni','aktivni'];
            if (!$header || array_map('strtolower',$header)!==$expected) throw new \RuntimeException('Neplatná hlavička CSV.');
            $ins = $pdo->prepare('INSERT INTO produkty (sku,nazev,typ,merna_jednotka,ean,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni,aktivni) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nazev=VALUES(nazev),typ=VALUES(typ),merna_jednotka=VALUES(merna_jednotka),ean=VALUES(ean),min_zasoba=VALUES(min_zasoba),min_davka=VALUES(min_davka),krok_vyroby=VALUES(krok_vyroby),vyrobni_doba_dni=VALUES(vyrobni_doba_dni),aktivni=VALUES(aktivni)');
            $ok=0;$err=[];$i=1;
            while(($row=fgetcsv($fh))!==false){$i++; if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue; [$sku,$nazev,$typ,$mj,$ean,$min,$md,$krok,$vdd,$act]=$row; if($sku===''){ $err[]="Řádek $i: chybí sku"; continue;} if(!in_array($typ,['produkt','obal','etiketa','surovina','baleni','karton'],true)){ $err[]="Řádek $i: neplatný typ"; continue;} if(!in_array($mj,['ks','kg'],true)){ $err[]="Řádek $i: neplatná mj"; continue;} $ins->execute([$sku,$nazev,$typ,$mj,$ean!==''?$ean:null, $min===''?0:$min, $md===''?0:$md, $krok===''?0:$krok, $vdd===''?0:$vdd, $act===''?1:$act]); $ok++;}
            $pdo->commit();
            $this->render('products_index.php',['title'=>'Produkty','items'=>$pdo->query('SELECT sku,nazev,typ,merna_jednotka,ean,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni,aktivni FROM produkty ORDER BY nazev LIMIT 500')->fetchAll(),'message'=>"Import OK: $ok", 'errors'=>$err]);
        } catch (\Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $this->render('products_index.php',['title'=>'Produkty','error'=>$e->getMessage()]); }
    }

    private function requireAuth(): void {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }
    private function requireAdmin(): void {
        $this->requireAuth();
        if (($_SESSION['user']['role'] ?? 'user') !== 'admin'){
            http_response_code(403);
            echo 'Přístup jen pro admina.';
            exit;
        }
    }
    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
}
