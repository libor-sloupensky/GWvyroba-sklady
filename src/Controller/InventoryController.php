<?php
namespace App\Controller;

use App\Support\DB;

final class InventoryController
{
    public function index(): void
    {
        $this->requireAuth();
        $message = $_SESSION['inventory_message'] ?? null;
        $error = $_SESSION['inventory_error'] ?? null;
        unset($_SESSION['inventory_message'], $_SESSION['inventory_error']);

        $activeInventory = $this->getActiveInventory();
        $lastClosed = $this->getLastClosedInventory();
        $filters = $this->currentFilters();
        $hasSearch = $this->searchTriggered();
        $items = ($hasSearch && $activeInventory)
            ? $this->fetchInventoryProducts($filters, $activeInventory)
            : [];

        $this->render('inventory.php', [
            'title' => 'Inventura',
            'inventory' => $activeInventory,
            'lastClosed' => $lastClosed,
            'items' => $items,
            'filters' => $filters,
            'hasSearch' => $hasSearch,
            'brands' => $this->fetchBrands(),
            'groups' => $this->fetchGroups(),
            'types' => $this->productTypes(),
            'message' => $message,
            'error' => $error,
        ]);
    }

    public function start(): void
    {
        $this->requireAdmin();
        if ($this->getActiveInventory()) {
            $_SESSION['inventory_error'] = 'Inventura už probíhá.';
            $this->redirect('/inventory');
            return;
        }
        $baseline = $this->getLastClosedInventory();
        $note = trim((string)($_POST['poznamka'] ?? ''));
        DB::pdo()->prepare('INSERT INTO inventury (baseline_inventory_id, poznamka) VALUES (?, ?)')
            ->execute([$baseline['id'] ?? null, $note === '' ? null : $note]);
        $_SESSION['inventory_message'] = 'Inventura byla zahájena.';
        $this->redirect('/inventory');
    }

    public function close(): void
    {
        $this->requireAdmin();
        $inventory = $this->getActiveInventory();
        if (!$inventory) {
            $_SESSION['inventory_error'] = 'Neprobíhá žádná inventura.';
            $this->redirect('/inventory');
            return;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $closedAt = date('Y-m-d H:i:s');
            $baselineId = (int)($inventory['baseline_inventory_id'] ?? 0);
            $baselineMap = $this->loadSnapshotMap($baselineId);
            $baselineClosedAt = $this->getInventoryClosedAt($baselineId);
            $movements = $this->loadMovementSums($baselineClosedAt, null, null, $closedAt);
            $finalMap = $this->mergeQuantities($baselineMap, $movements);

            $pdo->prepare('DELETE FROM inventura_stavy WHERE inventura_id=?')->execute([$inventory['id']]);
            if (!empty($finalMap)) {
                $ins = $pdo->prepare('INSERT INTO inventura_stavy (inventura_id, sku, stav) VALUES (?,?,?)');
                foreach ($finalMap as $sku => $qty) {
                    $ins->execute([$inventory['id'], $sku, $qty]);
                }
            }
            $pdo->prepare('UPDATE inventury SET closed_at=? WHERE id=?')->execute([$closedAt, $inventory['id']]);
            $pdo->commit();
            $_SESSION['inventory_message'] = 'Inventura byla uzavřena.';
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['inventory_error'] = 'Uzavření inventury selhalo: ' . $e->getMessage();
        }
        $this->redirect('/inventory');
    }

    public function addEntry(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $inventory = $this->getActiveInventory();
        if (!$inventory) {
            echo json_encode(['ok' => false, 'error' => 'Neprobíhá inventura.']);
            return;
        }
        $payload = $this->collectRequestData();
        $sku = $this->toUtf8((string)($payload['sku'] ?? ''));
        $qtyRaw = (string)($payload['quantity'] ?? '');
        $qtyRaw = str_replace(',', '.', $qtyRaw);
        if ($sku === '' || $qtyRaw === '' || !is_numeric($qtyRaw)) {
            echo json_encode(['ok' => false, 'error' => 'Zadejte platné SKU a množství.']);
            return;
        }
        $qty = (float)$qtyRaw;
        if ($qty == 0.0) {
            echo json_encode(['ok' => false, 'error' => 'Množství nesmí být 0.']);
            return;
        }
        $product = $this->loadProduct($sku);
        if (!$product) {
            echo json_encode(['ok' => false, 'error' => 'Produkt nebyl nalezen.']);
            return;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO inventura_polozky (inventura_id, sku, mnozstvi, created_at) VALUES (?,?,?,NOW())');
            $stmt->execute([$inventory['id'], $sku, $qty]);
            $entryId = (int)$pdo->lastInsertId();
            $refId = sprintf('inv:%d:%d', $inventory['id'], $entryId);
            $pdo->prepare('INSERT INTO polozky_pohyby (datum, sku, mnozstvi, merna_jednotka, typ_pohybu, poznamka, ref_id) VALUES (NOW(), ?, ?, ?, ?, ?, ?)')
                ->execute([$sku, $qty, $product['merna_jednotka'], 'inventura', null, $refId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Uložení selhalo.']);
            return;
        }
        $rowData = $this->buildInventoryRow($product, $inventory);
        echo json_encode(['ok' => true, 'row' => $rowData]);
    }

    // ----- Helpers -----

    private function fetchInventoryProducts(array $filters, array $inventory): array
    {
        $products = $this->fetchFilteredProducts($filters);
        if (empty($products)) {
            return [];
        }
        $skuList = array_column($products, 'sku');
        $entries = $this->loadInventoryEntries($inventory['id'], $skuList);
        $expectedMap = $this->calculateExpectedStock($inventory, $skuList, true);
        $rows = [];
        foreach ($products as $product) {
            $sku = $product['sku'];
            $entryInfo = $entries[$sku] ?? ['total' => 0.0, 'parts' => []];
            $expected = $expectedMap[$sku] ?? 0.0;
            $difference = $expected - $entryInfo['total'];
            $rows[] = $this->formatInventoryRow($product, $entryInfo, $difference);
        }
        return $rows;
    }

    private function buildInventoryRow(array $product, array $inventory): array
    {
        $entryInfo = $this->loadInventoryEntries($inventory['id'], [$product['sku']])[$product['sku']] ?? ['total'=>0.0,'parts'=>[]];
        $expected = ($this->calculateExpectedStock($inventory, [$product['sku']], true)[$product['sku']] ?? 0.0);
        $difference = $expected - $entryInfo['total'];
        return $this->formatInventoryRow($product, $entryInfo, $difference);
    }

    private function formatInventoryRow(array $product, array $entryInfo, float $difference): array
    {
        $expression = $this->formatInventoryExpression($entryInfo['total'], $entryInfo['parts']);
        return [
            'sku' => $product['sku'],
            'ean' => $product['ean'],
            'znacka' => $product['znacka'],
            'skupina' => $product['skupina'],
            'typ' => $product['typ'],
            'merna_jednotka' => $product['merna_jednotka'],
            'nazev' => $product['nazev'],
            'inventarizovano' => $expression,
            'inventarizovano_total' => $this->formatQty($entryInfo['total']),
            'rozdil' => $this->formatQty($difference),
            'parts' => $entryInfo['parts'],
        ];
    }

    private function formatInventoryExpression(float $sum, array $parts): string
    {
        if (empty($parts)) {
            return '0';
        }
        $pieces = [];
        foreach ($parts as $value) {
            $pieces[] = ($value >= 0 ? '+' : '') . $this->formatQty($value);
        }
        $expression = $this->formatQty($sum) . '=';
        $expression .= ltrim(implode('', $pieces), '+');
        return $expression;
    }

    private function loadInventoryEntries(int $inventoryId, array $skuFilter = []): array
    {
        $sql = 'SELECT sku, mnozstvi, created_at FROM inventura_polozky WHERE inventura_id=?';
        $params = [$inventoryId];
        if (!empty($skuFilter)) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql .= " AND sku IN ({$placeholders})";
            $params = array_merge($params, $skuFilter);
        }
        $sql .= ' ORDER BY created_at, id';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $list = [];
        foreach ($stmt as $row) {
            $sku = (string)$row['sku'];
            $qty = (float)$row['mnozstvi'];
            if (!isset($list[$sku])) {
                $list[$sku] = ['total' => 0.0, 'parts' => []];
            }
            $list[$sku]['parts'][] = $qty;
            $list[$sku]['total'] += $qty;
        }
        return $list;
    }

    private function calculateExpectedStock(array $inventory, array $skuFilter = [], bool $excludeActiveEntries = false): array
    {
        $baselineId = (int)($inventory['baseline_inventory_id'] ?? 0);
        $baselineMap = $this->loadSnapshotMap($baselineId, $skuFilter);
        $baselineClosedAt = $this->getInventoryClosedAt($baselineId);
        $excludeInventoryId = $excludeActiveEntries ? (int)$inventory['id'] : null;
        $movements = $this->loadMovementSums($baselineClosedAt, $skuFilter, $excludeInventoryId, null);
        return $this->mergeQuantities($baselineMap, $movements);
    }

    private function mergeQuantities(array $base, array $delta): array
    {
        foreach ($delta as $sku => $value) {
            if (isset($base[$sku])) {
                $base[$sku] += $value;
            } else {
                $base[$sku] = $value;
            }
        }
        return $base;
    }

    private function loadSnapshotMap(?int $inventoryId, array $skuFilter = []): array
    {
        if (!$inventoryId) {
            return [];
        }
        $sql = 'SELECT sku, stav FROM inventura_stavy WHERE inventura_id=?';
        $params = [$inventoryId];
        if (!empty($skuFilter)) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql .= " AND sku IN ({$placeholders})";
            $params = array_merge($params, $skuFilter);
        }
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt as $row) {
            $map[(string)$row['sku']] = (float)$row['stav'];
        }
        return $map;
    }

    private function loadMovementSums(?string $since, ?array $skuFilter, ?int $excludeInventoryId, ?string $until): array
    {
        $sql = 'SELECT sku, SUM(mnozstvi) AS qty FROM polozky_pohyby WHERE 1=1';
        $params = [];
        if ($since !== null) {
            $sql .= ' AND datum > ?';
            $params[] = $since;
        }
        if ($until !== null) {
            $sql .= ' AND datum <= ?';
            $params[] = $until;
        }
        if ($excludeInventoryId) {
            $sql .= ' AND (ref_id IS NULL OR ref_id NOT LIKE ?)';
            $params[] = sprintf('inv:%d:%%', $excludeInventoryId);
        }
        if (!empty($skuFilter)) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql .= " AND sku IN ({$placeholders})";
            $params = array_merge($params, $skuFilter);
        }
        $sql .= ' GROUP BY sku';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt as $row) {
            $out[(string)$row['sku']] = (float)$row['qty'];
        }
        return $out;
    }

    private function fetchFilteredProducts(array $filters): array
    {
        $sql = 'SELECT p.sku,p.alt_sku,p.ean,p.nazev,p.typ,p.merna_jednotka,' .
            'COALESCE(z.nazev, "") AS znacka, COALESCE(g.nazev, "") AS skupina ' .
            'FROM produkty p ' .
            'LEFT JOIN produkty_znacky z ON z.id = p.znacka_id ' .
            'LEFT JOIN produkty_skupiny g ON g.id = p.skupina_id ';
        $conditions = ['p.aktivni = 1'];
        $params = [];
        $brand = (int)($filters['brand'] ?? 0);
        if ($brand > 0) {
            $conditions[] = 'p.znacka_id = ?';
            $params[] = $brand;
        }
        $group = (int)($filters['group'] ?? 0);
        if ($group > 0) {
            $conditions[] = 'p.skupina_id = ?';
            $params[] = $group;
        }
        $type = (string)($filters['type'] ?? '');
        if ($type !== '' && in_array($type, $this->productTypes(), true)) {
            $conditions[] = 'p.typ = ?';
            $params[] = $type;
        }
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            [$clause, $searchParams] = $this->buildSearchClauses($search, ['p.sku','p.alt_sku','p.nazev','p.ean']);
            if ($clause !== '') {
                $conditions[] = $clause;
                $params = array_merge($params, $searchParams);
            }
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.nazev LIMIT 500';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt as $row) {
            $rows[] = [
                'sku' => (string)$row['sku'],
                'alt_sku' => (string)($row['alt_sku'] ?? ''),
                'ean' => (string)($row['ean'] ?? ''),
                'nazev' => (string)$row['nazev'],
                'typ' => (string)$row['typ'],
                'merna_jednotka' => (string)$row['merna_jednotka'],
                'znacka' => (string)$row['znacka'],
                'skupina' => (string)$row['skupina'],
            ];
        }
        return $rows;
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

    private function loadProduct(string $sku): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT sku,nazev,merna_jednotka,ean,typ,COALESCE(z.nazev,"") AS znacka, COALESCE(g.nazev,"") AS skupina FROM produkty p LEFT JOIN produkty_znacky z ON z.id=p.znacka_id LEFT JOIN produkty_skupiny g ON g.id=p.skupina_id WHERE sku=? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch();
        return $row ? [
            'sku' => (string)$row['sku'],
            'nazev' => (string)$row['nazev'],
            'merna_jednotka' => (string)$row['merna_jednotka'],
            'ean' => (string)($row['ean'] ?? ''),
            'typ' => (string)$row['typ'],
            'znacka' => (string)$row['znacka'],
            'skupina' => (string)$row['skupina'],
        ] : null;
    }

    private function getActiveInventory(): ?array
    {
        $stmt = DB::pdo()->query('SELECT id, opened_at, closed_at, baseline_inventory_id, poznamka FROM inventury WHERE closed_at IS NULL ORDER BY opened_at DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    private function getLastClosedInventory(): ?array
    {
        $stmt = DB::pdo()->query('SELECT id, opened_at, closed_at FROM inventury WHERE closed_at IS NOT NULL ORDER BY closed_at DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    private function getInventoryClosedAt(?int $inventoryId): ?string
    {
        if (!$inventoryId) {
            return null;
        }
        $stmt = DB::pdo()->prepare('SELECT closed_at FROM inventury WHERE id=? LIMIT 1');
        $stmt->execute([$inventoryId]);
        $val = $stmt->fetchColumn();
        return $val ? (string)$val : null;
    }

    private function currentFilters(): array
    {
        return [
            'brand' => (int)($_GET['znacka_id'] ?? 0),
            'group' => (int)($_GET['skupina_id'] ?? 0),
            'type' => (string)($_GET['typ'] ?? ''),
            'search' => $this->toUtf8((string)($_GET['q'] ?? '')),
        ];
    }

    private function searchTriggered(): bool
    {
        return isset($_GET['search']);
    }

    private function collectRequestData(): array
    {
        $data = $_POST;
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return $data ?? [];
    }

    private function formatQty(float $value): string
    {
        $s = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
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

    private function requireAuth(): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }

    private function requireAdmin(): void
    {
        $this->requireAuth();
        if (($_SESSION['user']['role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            echo 'Přístup jen pro admina.';
            exit;
        }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }
}
