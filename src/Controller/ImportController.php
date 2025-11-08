<?php
namespace App\Controller;

use App\Support\DB;

final class ImportController
{
    public function form(): void
    {
        $this->requireAdmin();
        $this->render('import_form.php', ['title'=>'Import Pohoda XML']);
    }

    public function importPohoda(): void
    {
        $this->requireAdmin();
        $eshop = trim((string)($_POST['eshop'] ?? ''));
        if (!isset($_FILES['xml']) || $eshop === '') { $this->render('import_form.php', ['title'=>'Import', 'error'=>'Zadejte e-shop a vyberte XML.']); return; }
        $tmp = $_FILES['xml']['tmp_name'];
        if (!is_uploaded_file($tmp)) { $this->render('import_form.php', ['title'=>'Import', 'error'=>'Soubor nebyl nahrán.']); return; }
        $xml = file_get_contents($tmp);
        if ($xml === false) { $this->render('import_form.php', ['title'=>'Import', 'error'=>'Nelze číst soubor.']); return; }
        $xml = $this->ensureUtf8($xml);
        // minimal stub: just store batch marker, do real parsing in next step
        $batch = date('YmdHis');
        $pdo = DB::pdo();
        // Here should be the full Pohoda parser (per MASTER PROMPT). For now, show a stub result.
        $this->render('import_result.php', [
            'title'=>'Import dokončen',
            'batch'=>$batch,
            'summary'=>['doklady'=>0,'polozky'=>0],
            'missingSku'=>[],
            'notice'=>'Parser implementovat dle MASTER PROMPT — validace řad, DUZP, měna/kurz, kontakty, chybějící SKU.'
        ]);
    }

    public function deleteLastBatch(): void
    {
        $this->requireAdmin();
        $eshop = trim((string)($_POST['eshop'] ?? ''));
        if ($eshop === '') { $this->render('import_form.php', ['title'=>'Import', 'error'=>'Zadejte e-shop.']); return; }
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT import_batch_id FROM doklady_eshop WHERE eshop_source=? ORDER BY import_batch_id DESC LIMIT 1");
        $st->execute([$eshop]);
        $row = $st->fetch();
        if (!$row) { $this->render('import_form.php', ['title'=>'Import', 'error'=>'Nenalezen žádný import pro zadaný e-shop.']); return; }
        $batch = $row['import_batch_id'];
        $pdo->prepare("DELETE FROM polozky_eshop WHERE import_batch_id=? AND eshop_source=?")->execute([$batch,$eshop]);
        $pdo->prepare("DELETE FROM doklady_eshop WHERE import_batch_id=? AND eshop_source=?")->execute([$batch,$eshop]);
        $this->render('import_form.php', ['title'=>'Import', 'message'=>"Smazán poslední import batch={$batch}"]);
    }

    public function reportMissingSku(): void
    {
        $this->requireAdmin();
        $days = (int)($_GET['days'] ?? 30);
        $since = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d');
        $pdo = DB::pdo();
        // load ignore patterns
        $pat = array_map(fn($r)=> strtolower((string)$r['vzor']), $pdo->query('SELECT vzor FROM nastaveni_ignorovane_polozky')->fetchAll());
        $rows = $pdo->prepare("SELECT duzp, eshop_source, cislo_dokladu, nazev, mnozstvi, code_raw, stock_ids_raw FROM polozky_eshop WHERE duzp>=? AND code_raw IS NULL AND stock_ids_raw IS NULL ORDER BY duzp DESC");
        $rows->execute([$since]);
        $out = [];
        foreach ($rows as $r) {
            $code = strtolower((string)($r['code_raw'] ?? ''));
            $sku  = '';
            $skip = false;
            foreach ($pat as $p) { if ($p !== '' && fnmatch($p, $code)) { $skip=true; break; } }
            if ($skip) continue;
            $out[] = $r;
        }
        $this->render('report_missing_sku.php', ['title'=>'Chybějící SKU', 'rows'=>$out, 'days'=>$days]);
    }

    private function ensureUtf8(string $s): string
    {
        if (mb_detect_encoding($s, 'UTF-8', true) === false) {
            $s = mb_convert_encoding($s, 'UTF-8');
        }
        return $s;
    }

    private function requireAdmin(): void
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) { header('Location: /login'); exit; }
        if (($u['role'] ?? 'user') !== 'admin') { http_response_code(403); echo 'Přístup jen pro admina.'; exit; }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}

