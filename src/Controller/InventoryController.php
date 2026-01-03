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

        $selectedId = isset($_GET['inventory_id']) ? max(0, (int)$_GET['inventory_id']) : null;
        $inventories = $this->listInventories();
        $activeInventory = $this->getActiveInventory();
        $lastClosed = $this->getLastClosedInventory();

        $inventory = null;
        if ($selectedId) {
            foreach ($inventories as $candidate) {
                if ((int)$candidate['id'] === $selectedId) {
                    $inventory = $candidate;
                    break;
                }
            }
        }
        if (!$inventory) {
            $inventory = $activeInventory ?: ($inventories[0] ?? null);
        }

        $filters = $this->currentFilters();
        $hasSearch = $this->searchTriggered();
        $allowEntries = $inventory && !$inventory['closed_at'] && $activeInventory && $inventory['id'] === $activeInventory['id'];
        $items = ($hasSearch && $inventory)
            ? $this->fetchInventoryProducts($filters, $inventory)
            : [];

        $this->render('inventory.php', [
            'title' => 'Inventura',
            'inventory' => $inventory,
            'lastClosed' => $lastClosed,
            'items' => $items,
            'filters' => $filters,
            'hasSearch' => $hasSearch,
            'brands' => $this->fetchBrands(),
            'groups' => $this->fetchGroups(),
            'types' => $this->productTypes(),
            'message' => $message,
            'error' => $error,
            'allowEntries' => $allowEntries,
            'inventories' => $inventories,
            'selectedInventoryId' => $inventory['id'] ?? null,
            'activeInventoryId' => $activeInventory['id'] ?? null,
            'latestInventoryId' => $inventories[0]['id'] ?? null,
            'isAdmin' => $this->isAdmin(),
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
            $performedAtRaw = $this->normalizeDateTimeInput($_POST['performed_at'] ?? '') ?? date('Y-m-d H:i:s');
            $closedAt = $performedAtRaw;
            $entries = $this->loadInventoryEntries($inventory['id']);
            $entryTotals = [];
            foreach ($entries as $sku => $entryInfo) {
                $entryTotals[$sku] = (float)($entryInfo['total'] ?? 0.0);
            }
            $skuList = $this->fetchInventorySkuList();
            foreach ($entryTotals as $sku => $_total) {
                if (!in_array($sku, $skuList, true)) {
                    $skuList[] = $sku;
                }
            }
            $finalMap = [];
            foreach ($skuList as $sku) {
                $finalMap[$sku] = (float)($entryTotals[$sku] ?? 0.0);
            }

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
        $this->requireAuth();
        if (!$this->isAdmin() && !$this->canEditInventory()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Nemáte oprávnění zapisovat inventuru.']);
            return;
        }
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
            echo json_encode(['ok' => false, 'error' => 'Zadejte platne SKU a mnozstvi.']);
            return;
        }
        $entryQty = (float)$qtyRaw; // entered as current physical count
        $product = $this->loadProduct($sku);
        if (!$product) {
            echo json_encode(['ok' => false, 'error' => 'Produkt nebyl nalezen.']);
            return;
        }
        $entryInfo = $this->loadInventoryEntries($inventory['id'], [$sku])[$sku] ?? ['total' => 0.0, 'parts' => []];
        $desiredTotal = $entryInfo['total'] + $entryQty;
        $expectedQty = $this->calculateExpectedStock($inventory, [$sku], true)[$sku] ?? 0.0;
        $existingMovement = $this->loadInventoryMovementSum($inventory['id'], $sku);
        $deltaTotal = $desiredTotal - $expectedQty;
        $deltaAdjust = $deltaTotal - $existingMovement;

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO inventura_polozky (inventura_id, sku, mnozstvi, created_at) VALUES (?,?,?,NOW())');
            $stmt->execute([$inventory['id'], $sku, $entryQty]);
            $entryId = (int)$pdo->lastInsertId();
            $refId = sprintf('inv:%d:%d', $inventory['id'], $entryId);
            if ($deltaAdjust != 0.0) {
                $pdo->prepare('INSERT INTO polozky_pohyby (datum, sku, mnozstvi, merna_jednotka, typ_pohybu, poznamka, ref_id) VALUES (NOW(), ?, ?, ?, ?, ?, ?)')
                    ->execute([$sku, $deltaAdjust, $product['merna_jednotka'], 'inventura', null, $refId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Uložení selhalo: ' . $e->getMessage()]);
            return;
        }
        $rowData = $this->buildInventoryRow($product, $inventory);
        echo json_encode(['ok' => true, 'row' => $rowData]);
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $inventoryId = (int)($_POST['inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            $_SESSION['inventory_error'] = 'Neplatná inventura.';
            $this->redirect('/inventory');
            return;
        }
        $latest = $this->getLatestInventoryId();
        if ($latest === null || $inventoryId !== $latest) {
            $_SESSION['inventory_error'] = 'Smazat lze pouze poslední inventuru.';
            $this->redirect('/inventory');
            return;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM polozky_pohyby WHERE ref_id LIKE ?')->execute([$this->inventoryRefPattern($inventoryId)]);
            $pdo->prepare('DELETE FROM inventura_polozky WHERE inventura_id=?')->execute([$inventoryId]);
            $pdo->prepare('DELETE FROM inventura_stavy WHERE inventura_id=?')->execute([$inventoryId]);
            $pdo->prepare('DELETE FROM inventury WHERE id=?')->execute([$inventoryId]);
            $pdo->commit();
            $_SESSION['inventory_message'] = 'Inventura byla smazána.';
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['inventory_error'] = 'Smazání inventury selhalo: ' . $e->getMessage();
        }
        $this->redirect('/inventory');
    }

    public function reopen(): void
    {
        $this->requireAdmin();
        $inventoryId = (int)($_POST['inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            $_SESSION['inventory_error'] = 'Neplatná inventura.';
            $this->redirect('/inventory');
            return;
        }
        $latest = $this->getLatestInventoryId();
        if ($latest === null || $inventoryId !== $latest) {
            $_SESSION['inventory_error'] = 'Znovu otevřít lze pouze poslední inventuru.';
            $this->redirect('/inventory');
            return;
        }
        $inventory = $this->loadInventoryById($inventoryId);
        if (!$inventory || empty($inventory['closed_at'])) {
            $_SESSION['inventory_error'] = 'Inventura není uzavřená.';
            $this->redirect('/inventory');
            return;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM inventura_stavy WHERE inventura_id=?')->execute([$inventoryId]);
            $pdo->prepare('UPDATE inventury SET closed_at=NULL WHERE id=?')->execute([$inventoryId]);
            $pdo->commit();
            $_SESSION['inventory_message'] = 'Inventura byla znovu otevřena.';
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['inventory_error'] = 'Znovu otevření inventury selhalo: ' . $e->getMessage();
        }
        $this->redirect('/inventory?inventory_id=' . $inventoryId);
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
            $difference = $entryInfo['total'] - $expected;
            $rows[] = $this->formatInventoryRow($product, $entryInfo, $expected, $difference);
        }
        return $rows;
    }

    private function buildInventoryRow(array $product, array $inventory): array
    {
        $entryInfo = $this->loadInventoryEntries($inventory['id'], [$product['sku']])[$product['sku']] ?? ['total'=>0.0,'parts'=>[]];
        $expected = ($this->calculateExpectedStock($inventory, [$product['sku']], true)[$product['sku']] ?? 0.0);
        $difference = $entryInfo['total'] - $expected;
        return $this->formatInventoryRow($product, $entryInfo, $expected, $difference);
    }

    private function formatInventoryRow(array $product, array $entryInfo, float $expected, float $difference): array
    {
        $expression = $this->formatInventoryExpression($entryInfo['total'], $entryInfo['parts']);
        $expressionHtml = $this->formatInventoryExpressionHtml($entryInfo['total'], $entryInfo['parts']);
        return [
            'sku' => $product['sku'],
            'ean' => $product['ean'],
            'znacka' => $product['znacka'],
            'skupina' => $product['skupina'],
            'typ' => $product['typ'],
            'merna_jednotka' => $product['merna_jednotka'],
            'nazev' => $product['nazev'],
            'inventarizovano' => $expression,
            'inventarizovano_html' => $expressionHtml,
            'inventarizovano_total' => $this->formatQty($entryInfo['total']),
            'rozdil' => $this->formatQty($difference),
            'parts' => $entryInfo['parts'],
            'expected' => $this->formatQty($expected),
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

    private function formatInventoryExpressionHtml(float $sum, array $parts): string
    {
        if (empty($parts)) {
            return '<strong>0</strong>';
        }
        $pieces = [];
        foreach ($parts as $value) {
            $pieces[] = ($value >= 0 ? '+' : '') . $this->formatQty($value);
        }
        $expression = '<strong>' . $this->formatQty($sum) . '</strong>=';
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

    private function loadInventoryMovementSum(int $inventoryId, string $sku): float
    {
        $stmt = DB::pdo()->prepare('SELECT COALESCE(SUM(mnozstvi), 0) FROM polozky_pohyby WHERE sku = ? AND ref_id LIKE ?');
        $stmt->execute([$sku, sprintf('inv:%d:%%', $inventoryId)]);
        return (float)$stmt->fetchColumn();
    }

    private function calculateExpectedStock(array $inventory, array $skuFilter = [], bool $excludeActiveEntries = false): array
    {
        $baselineId = (int)($inventory['baseline_inventory_id'] ?? 0);
        $baselineMap = $this->loadSnapshotMap($baselineId, $skuFilter);
        $baselineClosedAt = $this->getInventoryClosedAt($baselineId);
        $excludeInventoryId = $excludeActiveEntries ? (int)$inventory['id'] : null;
        $until = $inventory['closed_at'] ?? null;
        $movements = $this->loadMovementSums($baselineClosedAt, $skuFilter, $excludeInventoryId, $until);
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

    /**
     * @return array<int,string>
     */
    private function fetchInventorySkuList(): array
    {
        $stmt = DB::pdo()->query(
            'SELECT p.sku FROM produkty p ' .
            'LEFT JOIN product_types pt ON pt.code = p.typ ' .
            'WHERE p.aktivni = 1 AND COALESCE(pt.is_nonstock,0) = 0 ' .
            'ORDER BY p.sku'
        );
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function fetchFilteredProducts(array $filters): array
    {
        $sql = 'SELECT p.sku,p.alt_sku,p.ean,p.nazev AS nazev,p.typ,p.merna_jednotka,' .
            'COALESCE(z.nazev, "") AS znacka, COALESCE(g.nazev, "") AS skupina ' .
            'FROM produkty p ' .
            'LEFT JOIN produkty_znacky z ON z.id = p.znacka_id ' .
            'LEFT JOIN produkty_skupiny g ON g.id = p.skupina_id ' .
            'LEFT JOIN product_types pt ON pt.code = p.typ ';
        $conditions = ['p.aktivni = 1', 'COALESCE(pt.is_nonstock,0) = 0'];
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
        $stmt = DB::pdo()->query('SELECT code FROM product_types ORDER BY name');
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function loadProduct(string $sku): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT p.sku,p.nazev,p.merna_jednotka,p.ean,p.typ,COALESCE(z.nazev,"") AS znacka, COALESCE(g.nazev,"") AS skupina, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN produkty_znacky z ON z.id=p.znacka_id LEFT JOIN produkty_skupiny g ON g.id=p.skupina_id LEFT JOIN product_types pt ON pt.code = p.typ WHERE p.sku=? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch();
        if (!$row || !empty($row['is_nonstock'])) {
            return null;
        }
        return [
            'sku' => (string)$row['sku'],
            'nazev' => (string)$row['nazev'],
            'merna_jednotka' => (string)$row['merna_jednotka'],
            'ean' => (string)($row['ean'] ?? ''),
            'typ' => (string)$row['typ'],
            'znacka' => (string)$row['znacka'],
            'skupina' => (string)$row['skupina'],
        ];
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

    private function normalizeDateTimeInput($value): ?string
    {
        if (!isset($value)) {
            return null;
        }
        $string = trim((string)$value);
        if ($string === '') {
            return null;
        }
        $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $string);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        $timestamp = strtotime($string);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        return null;
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
        if (!$this->isAdmin()) {
            $this->forbidden('Přístup jen pro administrátory.');
        }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function forbidden(string $message): void
    {
        http_response_code(403);
        $this->render('forbidden.php', [
            'title' => 'Přístup odepřen',
            'message' => $message,
        ]);
        exit;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }

    private function isAdmin(): bool
    {
        $role = $_SESSION['user']['role'] ?? 'user';
        return in_array($role, ['admin','superadmin'], true);
    }

    private function canEditInventory(): bool
    {
        return true;
    }

    private function listInventories(): array
    {
        return DB::pdo()->query('SELECT id, opened_at, closed_at, baseline_inventory_id, poznamka FROM inventury ORDER BY opened_at DESC, id DESC')->fetchAll();
    }

    private function loadInventoryById(int $id): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT id, opened_at, closed_at, baseline_inventory_id, poznamka FROM inventury WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getLatestInventoryId(): ?int
    {
        $row = DB::pdo()->query('SELECT id FROM inventury ORDER BY opened_at DESC, id DESC LIMIT 1')->fetch();
        return $row ? (int)$row['id'] : null;
    }

    private function inventoryRefPattern(int $inventoryId): string
    {
        return sprintf('inv:%d:%%', $inventoryId);
    }
}
