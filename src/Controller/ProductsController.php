<?php
namespace App\Controller;

use App\Support\DB;

final class ProductsController
{
    public function index(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query(
            'SELECT p.sku,p.nazev,p.typ,p.merna_jednotka,p.ean,p.min_zasoba,p.min_davka,' .
            'p.krok_vyroby,p.vyrobni_doba_dni,p.aktivni,zb.nazev AS znacka,p.poznamka,sg.nazev AS skupina ' .
            'FROM produkty p ' .
            'LEFT JOIN produkty_znacky zb ON p.znacka_id = zb.id ' .
            'LEFT JOIN produkty_skupiny sg ON p.skupina_id = sg.id ' .
            'ORDER BY p.nazev LIMIT 500'
        )->fetchAll();
        $this->render('products_index.php', ['title'=>'Produkty','items'=>$rows]);
    }

    public function exportCsv(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query(
            'SELECT p.sku,p.nazev,p.typ,p.merna_jednotka,p.ean,p.min_zasoba,p.min_davka,' .
            'p.krok_vyroby,p.vyrobni_doba_dni,p.aktivni,zb.nazev AS znacka,p.poznamka,sg.nazev AS skupina ' .
            'FROM produkty p ' .
            'LEFT JOIN produkty_znacky zb ON p.znacka_id = zb.id ' .
            'LEFT JOIN produkty_skupiny sg ON p.skupina_id = sg.id ' .
            'ORDER BY p.nazev'
        )->fetchAll();
        $fh = fopen('php://output','wb');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="produkty.csv"');
        $delimiter = ',';
        $enclosure = '"';
        $escape = '\\';
        fputcsv($fh, ['sku','nazev','typ','merna_jednotka','ean','min_zasoba','min_davka','krok_vyroby','vyrobni_doba_dni','aktivni','znacka','poznamka','skupina'], $delimiter, $enclosure, $escape);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['sku'],
                $r['nazev'],
                $r['typ'],
                $r['merna_jednotka'],
                $r['ean'],
                $r['min_zasoba'],
                $r['min_davka'],
                $r['krok_vyroby'],
                $r['vyrobni_doba_dni'],
                $r['aktivni'],
                $r['znacka'],
                $r['poznamka'],
                $r['skupina'],
            ], $delimiter, $enclosure, $escape);
        }
        exit;
    }

    public function importCsv(): void
    {
        $this->requireAdmin();
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->render('products_index.php',['title'=>'Produkty','error'=>'Soubor nebyl nahrán.']);
            return;
        }
        $fh = fopen($_FILES['csv']['tmp_name'],'rb');
        if (!$fh){
            $this->render('products_index.php',['title'=>'Produkty','error'=>'Nelze číst soubor.']);
            return;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $header = fgetcsv($fh);
            $expected = ['sku','nazev','typ','merna_jednotka','ean','min_zasoba','min_davka','krok_vyroby','vyrobni_doba_dni','aktivni','znacka','poznamka','skupina'];
            if (!$header || array_map('strtolower',$header)!==$expected) {
                throw new \RuntimeException('Neplatná hlavička CSV.');
            }
            $brands = $this->loadDictionary('produkty_znacky');
            $groups = $this->loadDictionary('produkty_skupiny');
            $ins = $pdo->prepare(
                'INSERT INTO produkty (sku,nazev,typ,merna_jednotka,ean,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni,aktivni,znacka_id,poznamka,skupina_id) ' .
                'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ' .
                'ON DUPLICATE KEY UPDATE nazev=VALUES(nazev),typ=VALUES(typ),merna_jednotka=VALUES(merna_jednotka),ean=VALUES(ean),min_zasoba=VALUES(min_zasoba),min_davka=VALUES(min_davka),krok_vyroby=VALUES(krok_vyroby),vyrobni_doba_dni=VALUES(vyrobni_doba_dni),aktivni=VALUES(aktivni),znacka_id=VALUES(znacka_id),poznamka=VALUES(poznamka),skupina_id=VALUES(skupina_id)'
            );
            $ok=0;$err=[];$i=1;
            while(($row=fgetcsv($fh))!==false){
                $i++;
                if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;
                $row = array_pad($row, 13, '');
                [$sku,$nazev,$typ,$mj,$ean,$min,$md,$krok,$vdd,$act,$znackaName,$poznamka,$skupinaName]=$row;
                $sku=trim((string)$sku);
                $nazev=trim((string)$nazev);
                $typ=trim((string)$typ);
                $mj=trim((string)$mj);
                $ean=trim((string)$ean);
                $znackaName=trim((string)$znackaName);
                $poznamka=trim((string)$poznamka);
                $skupinaName=trim((string)$skupinaName);
                if($sku===''){ $err[]="Řádek $i: chybí sku"; continue; }
                if($nazev===''){ $err[]="Řádek $i: chybí nazev"; continue; }
                if($typ===''||!in_array($typ,['produkt','obal','etiketa','surovina','baleni','karton'],true)){ $err[]="Řádek $i: neplatný typ"; continue; }
                if($mj===''){ $err[]="Řádek $i: chybí merna_jednotka"; continue; }
                if($act===''){ $err[]="Řádek $i: aktivni je povinné (0/1)"; continue; }
                $aktivni=(int)$act;
                $brandId=null;
                if($znackaName!==''){
                    $key=mb_strtolower($znackaName,'UTF-8');
                    if(!isset($brands[$key])){ $err[]="Řádek $i: značka '{$znackaName}' není definována"; continue; }
                    $brandId=$brands[$key];
                }
                $groupId=null;
                if($skupinaName!==''){
                    $key=mb_strtolower($skupinaName,'UTF-8');
                    if(!isset($groups[$key])){ $err[]="Řádek $i: skupina '{$skupinaName}' není definována"; continue; }
                    $groupId=$groups[$key];
                }
                if($ean===''){ $ean=null; }
                $ins->execute([
                    $sku,
                    $nazev,
                    $typ,
                    $mj,
                    $ean,
                    $min===''?0:$min,
                    $md===''?0:$md,
                    $krok===''?0:$krok,
                    $vdd===''?0:$vdd,
                    $aktivni,
                    $brandId,
                    ($poznamka === '' ? null : $poznamka),
                    $groupId,
                ]);
                $ok++;
            }
            $pdo->commit();
            $items = $pdo->query(
                'SELECT p.sku,p.nazev,p.typ,p.merna_jednotka,p.ean,p.min_zasoba,p.min_davka,' .
                'p.krok_vyroby,p.vyrobni_doba_dni,p.aktivni,zb.nazev AS znacka,p.poznamka,sg.nazev AS skupina ' .
                'FROM produkty p ' .
                'LEFT JOIN produkty_znacky zb ON p.znacka_id = zb.id ' .
                'LEFT JOIN produkty_skupiny sg ON p.skupina_id = sg.id ' .
                'ORDER BY p.nazev LIMIT 500'
            )->fetchAll();
            $this->render('products_index.php',[ 'title'=>'Produkty','items'=>$items,'message'=>"Import OK: $ok", 'errors'=>$err ]);
        } catch (\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $this->render('products_index.php',['title'=>'Produkty','error'=>$e->getMessage()]);
        }
    }

    private function loadDictionary(string $table): array
    {
        $map = [];
        foreach (DB::pdo()->query("SELECT id,nazev FROM {$table}") as $row) {
            $map[mb_strtolower((string)$row['nazev'],'UTF-8')] = (int)$row['id'];
        }
        return $map;
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
