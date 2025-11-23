<?php
namespace App\Controller;

use App\Support\DB;

final class ReservationsController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureReservationSchema();
        $pdo = DB::pdo();
        $rows = $pdo->query('SELECT id,sku,typ,mnozstvi,platna_do,poznamka FROM rezervace ORDER BY platna_do DESC, id DESC')->fetchAll();
        $this->render('reservations.php', [
            'title' => 'Rezervace',
            'rows'  => $rows,
            'types' => $this->productTypes(),
        ]);
    }

    public function save(): void
    {
        $this->requireAuth();
        $id = (int)($_POST['id'] ?? 0);
        $sku = trim((string)($_POST['sku'] ?? ''));
        $type = trim((string)($_POST['typ'] ?? 'produkt'));
        if (!in_array($type, $this->productTypes(), true)) {
            $type = 'produkt';
        }
        $qty = (float)($_POST['mnozstvi'] ?? 0);
        $to  = trim((string)($_POST['platna_do'] ?? ''));
        $note= trim((string)($_POST['poznamka'] ?? ''));
        if ($sku === '' || $qty <= 0 || $to === '') { $this->redirect('/reservations'); return; }
        $this->ensureReservationSchema();
        $pdo = DB::pdo();
        if ($id > 0) {
            $st = $pdo->prepare('UPDATE rezervace SET sku=?, typ=?, mnozstvi=?, platna_do=?, poznamka=? WHERE id=?');
            $st->execute([$sku,$type,$qty,$to,$note,$id]);
        } else {
            $st = $pdo->prepare('INSERT INTO rezervace (sku,typ,mnozstvi,platna_do,poznamka) VALUES (?,?,?,?,?)');
            $st->execute([$sku,$type,$qty,$to,$note]);
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

    public function searchProducts(): void
    {
        $this->requireAuth();
        $term = trim((string)($_GET['q'] ?? ''));
        header('Content-Type: application/json');
        if ($term === '') {
            echo json_encode(['items' => []]);
            return;
        }
        [$searchCondition, $params] = $this->buildSearchClauses(
            $term,
            ['sku','alt_sku','nazev','ean']
        );
        $sql = 'SELECT sku, alt_sku, nazev, ean, merna_jednotka FROM produkty';
        if ($searchCondition !== '') {
            $sql .= ' WHERE ' . $searchCondition;
        }
        $sql .= ' ORDER BY nazev LIMIT 20';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $items = [];
        foreach ($stmt as $row) {
            $items[] = [
                'sku' => (string)$row['sku'],
                'alt_sku' => (string)($row['alt_sku'] ?? ''),
                'nazev' => (string)$row['nazev'],
                'ean' => (string)($row['ean'] ?? ''),
                'merna_jednotka' => (string)($row['merna_jednotka'] ?? ''),
            ];
        }
        echo json_encode(['items' => $items]);
    }

    private function requireAuth(): void {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }

    private function productTypes(): array
    {
        $stmt = DB::pdo()->query('SELECT code FROM product_types ORDER BY name');
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function ensureReservationSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        $pdo = DB::pdo();
        $stmt = $pdo->query("SHOW COLUMNS FROM rezervace LIKE 'typ'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `rezervace` ADD COLUMN `typ` ENUM('produkt','obal','etiketa','surovina','baleni','karton') NOT NULL DEFAULT 'produkt' AFTER `sku`");
            try { $pdo->exec('ALTER TABLE `rezervace` ADD KEY idx_rez_typ (typ)'); } catch (\Throwable $e) {}
        }
    }

    /**
     * @param array<int,string> $fields
     * @return array{0:string,1:array<int,string>}
     */
    private function buildSearchClauses(string $term, array $fields): array
    {
        $words = preg_split('/\s+/u', trim($term)) ?: [];
        $words = array_values(array_filter($words, static fn($w) => $w !== ''));
        if (empty($words)) {
            return ['', []];
        }
        $clauses = [];
        $params = [];
        foreach ($words as $word) {
            $like = '%' . $word . '%';
            $inner = [];
            foreach ($fields as $field) {
                $inner[] = "{$field} LIKE ?";
                $params[] = $like;
            }
            $clauses[] = '(' . implode(' OR ', $inner) . ')';
        }
        return [implode(' AND ', $clauses), $params];
    }

    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
    private function redirect(string $p): void { header('Location: '.$p, true, 302); exit; }
}
