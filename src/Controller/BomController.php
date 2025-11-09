<?php
namespace App\Controller;

use App\Support\DB;

final class BomController
{
    public function index(): void
    {
        $this->requireAuth();
        $rows = DB::pdo()->query('SELECT rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby FROM bom ORDER BY rodic_sku,potomek_sku LIMIT 1000')->fetchAll();
        $this->render('bom_index.php', ['title'=>'BOM','items'=>$rows,'total'=>$this->bomCount()]);
    }

    public function exportCsv(): void
    {
        $this->requireAuth();
        $rows = DB::pdo()->query('SELECT rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby FROM bom ORDER BY rodic_sku,potomek_sku')->fetchAll();
        $fh = fopen('php://output','wb');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bom.csv"');
        $delimiter = ';';
        $enclosure = '"';
        $escape = '\\';
        fputcsv($fh, ['rodic_sku','potomek_sku','koeficient','merna_jednotka_potomka','druh_vazby'], $delimiter, $enclosure, $escape);
        foreach ($rows as $r) {
            fputcsv($fh, $r, $delimiter, $enclosure, $escape);
        }
        exit;
    }

    public function importCsv(): void
    {
        $this->requireAdmin();
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->render('bom_index.php',['title'=>'BOM','error'=>'Soubor nebyl nahrán.']);
            return;
        }
        $fh = fopen($_FILES['csv']['tmp_name'],'rb');
        if (!$fh){
            $this->render('bom_index.php',['title'=>'BOM','error'=>'Nelze číst soubor.']);
            return;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $header = $this->readCsvRow($fh);
            $expected = ['rodic_sku','potomek_sku','koeficient','merna_jednotka_potomka','druh_vazby'];
            if (!$header || array_map('strtolower',$header)!==$expected) {
                throw new \RuntimeException('Neplatná hlavička CSV.');
            }
            $deletePair = $pdo->prepare('DELETE FROM bom WHERE rodic_sku=? AND potomek_sku=?');
            $insert = $pdo->prepare('INSERT INTO bom (rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby) VALUES (?,?,?,?,?)');
            $ok=0;$err=[];$line=1;
            while(($row=$this->readCsvRow($fh))!==false){
                $line++;
                if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;
                $row = array_pad($row,5,'');
                [$parent,$child,$coef,$unit,$bond]=$row;
                $parent=$this->toUtf8($parent);
                $child=$this->toUtf8($child);
                $unit=$this->toUtf8($unit);
                $bond=$this->toUtf8($bond);
                if($parent===''||$child===''){ $err[]="Řádek $line: chybí rodič/potomek"; continue;}
                if($coef===''|| !is_numeric($coef) || (float)$coef<=0){ $err[]="Řádek $line: neplatný koeficient"; continue;}
                if($unit===''){
                    $childInfo = $this->getProductInfo($child);
                    $unit = $childInfo['merna_jednotka'] ?? null;
                }
                if($bond===''){
                    $parentInfo = $this->getProductInfo($parent);
                    $bond = $this->deriveBondType($parentInfo['typ'] ?? null);
                }
                if(!in_array($bond,['karton','sada'],true)){
                    $err[]="Řádek $line: neplatný druh_vazby";
                    continue;
                }
                $deletePair->execute([$parent,$child]);
                $insert->execute([$parent,$child,$coef,$unit,$bond]);
                $ok++;
            }
            $pdo->commit();
            $items = DB::pdo()->query('SELECT rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby FROM bom ORDER BY rodic_sku,potomek_sku LIMIT 1000')->fetchAll();
            $this->render('bom_index.php',['title'=>'BOM','items'=>$items,'message'=>"Import OK: $ok", 'errors'=>$err,'total'=>$this->bomCount()]);
        } catch (\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $this->render('bom_index.php',['title'=>'BOM','error'=>$e->getMessage(),'total'=>$this->bomCount()]);
        }
    }

    private function getProductInfo(string $sku): ?array
    {
        static $cache = [];
        $key = mb_strtolower($sku,'UTF-8');
        if (array_key_exists($key,$cache)) {
            return $cache[$key];
        }
        $stmt = DB::pdo()->prepare('SELECT typ, merna_jednotka FROM produkty WHERE sku=? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch() ?: null;
        $cache[$key] = $row ?: null;
        return $cache[$key];
    }

    private function deriveBondType(?string $parentType): string
    {
        return $parentType === 'karton' ? 'karton' : 'sada';
    }

    private function readCsvRow($handle)
    {
        return fgetcsv($handle, 0, ';', '"', '\\');
    }

    private function toUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (mb_detect_encoding($value, 'UTF-8', true) === false) {
            $value = mb_convert_encoding($value, 'UTF-8', 'WINDOWS-1250,ISO-8859-2,ISO-8859-1');
        }
        return trim($value);
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

    private function render(string $view, array $vars=[]): void {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function bomCount(): int
    {
        $count = DB::pdo()->query('SELECT COUNT(*) FROM bom')->fetchColumn();
        return (int)$count;
    }
}
