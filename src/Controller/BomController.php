<?php
namespace App\Controller;

use App\Support\DB;

final class BomController
{
    public function index(): void
    {
        $this->requireAuth();
        header('Location: /products#bom-import');
        exit;
    }

    public function exportCsv(): void
    {
        $this->requireAuth();
        $rows = DB::pdo()->query('SELECT rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka FROM bom ORDER BY rodic_sku,potomek_sku')->fetchAll();
        $fh = fopen('php://output','wb');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bom.csv"');
        $delimiter = ';';
        $enclosure = '"';
        $escape = '\\';
        fputcsv($fh, ['rodic_sku','potomek_sku','koeficient','merna_jednotka_potomka'], $delimiter, $enclosure, $escape);
        foreach ($rows as $r) {
            fputcsv($fh, $r, $delimiter, $enclosure, $escape);
        }
        exit;
    }

    public function importCsv(): void
    {
        $this->requireAdmin();
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->flashBomError('Soubor nebyl nahrán.');
        }
        $fh = fopen($_FILES['csv']['tmp_name'], 'rb');
        if (!$fh) {
            $this->flashBomError('Nelze číst soubor.');
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        $err = [];
        $created = 0;
        $updated = 0;
        try {
            $header = $this->readCsvRow($fh);
            $expected = ['rodic_sku','potomek_sku','koeficient','merna_jednotka_potomka'];
            if (!$header || array_map('strtolower',$header)!==$expected) {
                throw new \RuntimeException('Neplatná hlavička CSV.');
            }
            $deletePair = $pdo->prepare('DELETE FROM bom WHERE rodic_sku=? AND potomek_sku=?');
            $insert = $pdo->prepare('INSERT INTO bom (rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka) VALUES (?,?,?,?)');
            $ok=0;$line=1;
            while(($row=$this->readCsvRow($fh))!==false){
                $line++;
                if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;
                $row = array_pad($row,4,'');
                [$parent,$child,$coef,$unit]=$row;
                $parent=$this->toUtf8($parent);
                $child=$this->toUtf8($child);
                $unit=$this->toUtf8($unit);
                if($parent===''||$child===''){ $err[]="Řádek {$line}: chybí rodič/potomek"; continue;}
                if($coef===''|| !is_numeric($coef) || (float)$coef<=0){ $err[]="Řádek {$line}: neplatný koeficient"; continue;}
                $parentInfo = $this->getProductInfo($parent);
                if ($parentInfo === null) {
                    $err[] = "Řádek {$line}: rodič '{$parent}' neexistuje";
                    continue;
                }
                $childInfo = $this->getProductInfo($child);
                if ($childInfo === null) {
                    $err[] = "Řádek {$line}: potomek '{$child}' neexistuje";
                    continue;
                }
                if($unit===''){
                    $unit = $childInfo['merna_jednotka'] ?? null;
                }
                $deletePair->execute([$parent,$child]);
                $wasUpdate = $deletePair->rowCount() > 0;
                $insert->execute([$parent,$child,$coef,$unit]);
                if ($wasUpdate) {
                    $updated++;
                } else {
                    $created++;
                }
                $ok++;
            }
            $pdo->commit();
            $this->flashBomSuccess("Import OK: {$ok}", $err, [
                'created' => $created,
                'updated' => $updated,
                'errors' => count($err),
            ]);
        } catch (\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $this->flashBomError('Import selhal: ' . $e->getMessage());
        } finally {
            fclose($fh);
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

    private function flashBomSuccess(string $message, array $errors, array $stats = []): void
    {
        $_SESSION['products_bom_message'] = $message;
        $_SESSION['products_bom_errors'] = $errors;
        $_SESSION['products_bom_stats'] = $stats;
        unset($_SESSION['products_bom_error']);
        header('Location: /products#bom-import');
        exit;
    }

    private function flashBomError(string $message, array $errors = []): void
    {
        $_SESSION['products_bom_error'] = $message;
        $_SESSION['products_bom_errors'] = $errors;
        unset($_SESSION['products_bom_message']);
        unset($_SESSION['products_bom_stats']);
        header('Location: /products#bom-import');
        exit;
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
        $role = $_SESSION['user']['role'] ?? 'user';
        if (!in_array($role, ['admin','superadmin'], true)) {
            http_response_code(403);
            $this->render('forbidden.php', [
                'title' => 'Přístup odepřen',
                'message' => 'Přístup jen pro administrátory.',
            ]);
            exit;
        }
    }




}
