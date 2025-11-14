<?php
namespace App\Controller;

use App\Support\DB;

final class ProductionController
{
    public function plans(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $filters = $this->currentFilters();
        $hasSearch = isset($_GET['search']);
        $items = [];

        if ($hasSearch) {
            [$searchCondition, $searchParams] = $this->buildSearchClauses(
                $filters['search'],
                ['sku','nazev','alt_sku','ean']
            );

            $conditions = ['aktivni = 1'];
            $params = [];

            if ($filters['type'] !== '') {
                $conditions[] = 'typ = ?';
                $params[] = $filters['type'];
            }
            if ($filters['brand'] > 0) {
                $conditions[] = 'COALESCE(znacka_id,0) = ?';
                $params[] = $filters['brand'];
            }
            if ($filters['group'] > 0) {
                $conditions[] = 'COALESCE(skupina_id,0) = ?';
                $params[] = $filters['group'];
            }
            if ($searchCondition !== '') {
                $conditions[] = '(' . $searchCondition . ')';
                $params = array_merge($params, $searchParams);
            }

            $where = implode(' AND ', $conditions);
            $sql = 'SELECT sku,typ,nazev,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni FROM produkty WHERE ' .
                $where . ' ORDER BY nazev';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();
        }

        $this->render('production_plans.php', [
            'title' => 'Výroba – návrhy',
            'items' => $items,
            'brands' => $this->fetchBrands(),
            'groups' => $this->fetchGroups(),
            'types' => $this->productTypes(),
            'filters' => $filters,
            'hasSearch' => $hasSearch,
            'resultCount' => count($items),
        ]);
    }

public function produce(): void
    {
        $this->requireAuth();
        $sku = $this->toUtf8((string)($_POST['sku'] ?? ''));
        $qty = (float)($_POST['mnozstvi'] ?? 0);
        $mode = (string)($_POST['modus'] ?? 'odecti_subpotomky'); // odecti_subpotomky | korekce
        if ($sku === '' || $qty <= 0) {
            $this->redirect('/production/plans');
            return;
        }
        $pdo = DB::pdo();
        $ref = 'prod-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $ins = $pdo->prepare('INSERT INTO polozky_pohyby (datum,sku,mnozstvi,merna_jednotka,typ_pohybu,poznamka,ref_id) VALUES (NOW(),?,?,?,?,?,?)');
        $ins->execute([$sku, $qty, null, 'vyroba', null, $ref]);

        if ($mode === 'odecti_subpotomky') {
            $components = $this->loadBomComponents($sku);
            foreach ($components as $component) {
                $required = $qty * $component['koeficient'];
                $ins->execute([
                    $component['sku'],
                    -1 * $required,
                    $component['merna_jednotka'],
                    'vyroba',
                    'odečíst komponenty',
                    $ref
                ]);
            }
        } else {
            $ins->execute([$sku . '*', 0, null, 'korekce', 'Komponenty k odečtu – řešit ručně', $ref]);
        }

        $this->redirect('/production/plans');
    }

    public function check(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');
        $data = $this->collectJsonRequest();
        $sku = $this->toUtf8((string)($data['sku'] ?? ''));
        $qty = (float)($data['mnozstvi'] ?? 0);
        if ($sku === '' || $qty <= 0) {
            echo json_encode(['ok'=>false,'error'=>'Zadejte platné SKU i množství.']);
            return;
        }
        $deficits = $this->calculateDeficits($sku, $qty);
        echo json_encode(['ok'=>true,'deficits'=>$deficits]);
    }

    public function deleteRecord(): void
    {
        $this->requireAuth();
        $ref = (string)($_POST['ref_id'] ?? '');
        if ($ref !== '') {
            DB::pdo()->prepare('DELETE FROM polozky_pohyby WHERE ref_id=?')->execute([$ref]);
        }
        $this->redirect('/production/plans');
    }

    private function calculateDeficits(string $sku, float $qty): array
    {
        $components = $this->loadBomComponents($sku);
        if (empty($components)) {
            return [];
        }
        $childSkus = array_column($components, 'sku');
        $stock = $this->loadCurrentStock($childSkus);
        $names = $this->loadProductNames($childSkus);
        $deficits = [];
        foreach ($components as $component) {
            $required = $qty * $component['koeficient'];
            $available = $stock[$component['sku']] ?? 0.0;
            $missing = $available - $required;
            if ($missing < 0) {
                $deficits[] = [
                    'sku' => $component['sku'],
                    'nazev' => $names[$component['sku']] ?? '',
                    'required' => $this->formatQty($required),
                    'available' => $this->formatQty($available),
                    'missing' => $this->formatQty(abs($missing)),
                ];
            }
        }
        return $deficits;
    }

    private function loadBomComponents(string $sku): array
    {
        $stmt = DB::pdo()->prepare("SELECT potomek_sku AS sku, koeficient, merna_jednotka_potomka FROM bom WHERE rodic_sku=? AND druh_vazby='sada'");
        $stmt->execute([$sku]);
        $list = [];
        foreach ($stmt as $row) {
            $list[] = [
                'sku' => (string)$row['sku'],
                'koeficient' => (float)$row['koeficient'],
                'merna_jednotka' => $row['merna_jednotka_potomka'] !== '' ? (string)$row['merna_jednotka_potomka'] : null,
            ];
        }
        return $list;
    }

    private function loadCurrentStock(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $stmt = DB::pdo()->prepare("SELECT sku, SUM(mnozstvi) AS qty FROM polozky_pohyby WHERE sku IN ({$placeholders}) GROUP BY sku");
        $stmt->execute($skus);
        $stock = [];
        foreach ($stmt as $row) {
            $stock[(string)$row['sku']] = (float)$row['qty'];
        }
        return $stock;
    }

    private function loadProductNames(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $stmt = DB::pdo()->prepare("SELECT sku, nazev FROM produkty WHERE sku IN ({$placeholders})");
        $stmt->execute($skus);
        $names = [];
        foreach ($stmt as $row) {
            $names[(string)$row['sku']] = (string)$row['nazev'];
        }
        return $names;
    }

    private function collectJsonRequest(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function toUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (mb_detect_encoding($value, 'UTF-8', true) === false) {
            $value = mb_convert_encoding($value, 'UTF-8');
        }
        return trim($value);
    }

    private function formatQty(float $value): string
    {
        $formatted = number_format($value, 6, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }

    private function render(string $view, array $vars=[]): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function redirect(string $path): void
    {
        header('Location: '.$path, true, 302);
        exit;
    }

    private function currentFilters(): array
    {
        $brand = (int)($_GET['znacka_id'] ?? 0);
        $group = (int)($_GET['skupina_id'] ?? 0);
        $typeRaw = $this->toUtf8((string)($_GET['typ'] ?? ''));
        $type = in_array($typeRaw, $this->productTypes(), true) ? $typeRaw : '';
        $search = $this->toUtf8((string)($_GET['q'] ?? ''));
        return [
            'brand' => $brand > 0 ? $brand : 0,
            'group' => $group > 0 ? $group : 0,
            'type'  => $type,
            'search'=> $search,
        ];
    }

    private function fetchBrands(): array
    {
        return DB::pdo()->query('SELECT id,nazev FROM produkty_znacky ORDER BY nazev')->fetchAll();
    }

    private function fetchGroups(): array
    {
        return DB::pdo()->query('SELECT id,nazev FROM produkty_skupiny ORDER BY nazev')->fetchAll();
    }

    private function productTypes(): array
    {
        return ['produkt','obal','etiketa','surovina','baleni','karton'];
    }

    private function buildSearchClauses(string $search, array $fields): array
    {
        $terms = preg_split('/\s+/u', trim($search)) ?: [];
        $terms = array_values(array_filter($terms, static fn($t) => $t !== ''));
        if (empty($terms)) {
            return ['', []];
        }
        $clauses = [];
        $params = [];
        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $inner = [];
            foreach ($fields as $field) {
                $inner[] = "{$field} LIKE ?";
                $params[] = $like;
            }
            $clauses[] = '(' . implode(' OR ', $inner) . ')';
        }
        return [implode(' AND ', $clauses), $params];
    }
}
