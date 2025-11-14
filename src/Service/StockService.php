<?php
namespace App\Service;

use App\Support\DB;
use DateTimeImmutable;
use PDO;

final class StockService
{
    private static array $demandCache = [];
    private static ?array $bomCache = null;
    private static bool $settingsColumnsVerified = false;

    public static function getSettings(): array
    {
        $pdo = DB::pdo();
        if (!self::$settingsColumnsVerified) {
            self::ensureSettingsColumns($pdo);
            self::$settingsColumnsVerified = true;
        }
        $row = $pdo->query('SELECT okno_pro_prumer_dni, spotreba_prumer_dni, zasoba_cil_dni FROM nastaveni_global WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'xml_window_days' => max(1, (int)($row['okno_pro_prumer_dni'] ?? 30)),
            'consumption_days' => max(1, (int)($row['spotreba_prumer_dni'] ?? 90)),
            'stock_days' => max(1, (int)($row['zasoba_cil_dni'] ?? 30)),
        ];
    }

    private static function ensureSettingsColumns(PDO $pdo): void
    {
        self::ensureColumn($pdo, 'spotreba_prumer_dni', "INT NOT NULL DEFAULT 90 AFTER `okno_pro_prumer_dni`");
        self::ensureColumn($pdo, 'zasoba_cil_dni', "INT NOT NULL DEFAULT 30 AFTER `spotreba_prumer_dni`");
    }

    private static function ensureColumn(PDO $pdo, string $column, string $definition): void
    {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `nastaveni_global` LIKE ?");
        $stmt->execute([$column]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `nastaveni_global` ADD COLUMN `{$column}` {$definition}");
        }
    }

    /**
     * @param array<int,string>|null $skuFilter
     */
    public static function recalcAutoSafetyStock(?array $skuFilter = null): void
    {
        $settings = self::getSettings();
        $consumptionDays = $settings['consumption_days'];
        $stockDays = $settings['stock_days'];
        if ($stockDays <= 0 || $consumptionDays <= 0) {
            return;
        }

        $pdo = DB::pdo();
        $filterSql = '';
        $filterParams = [];
        if ($skuFilter && count($skuFilter) > 0) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $filterSql = " AND sku IN ({$placeholders})";
            $filterParams = array_values($skuFilter);
        }
        $stmt = $pdo->prepare('SELECT sku, vyrobni_doba_dni, min_davka FROM produkty WHERE nast_zasob = \'auto\'' . $filterSql);
        $stmt->execute($filterParams);
        $autos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$autos) {
            return;
        }
        $demandMap = self::buildDemandMap($consumptionDays);
        $updates = [];
        foreach ($autos as $row) {
            $sku = (string)$row['sku'];
            $daily = (float)($demandMap[$sku] ?? 0.0);
            $effectiveDays = $stockDays + max(0, (int)$row['vyrobni_doba_dni']);
            $target = $daily * $effectiveDays;
            $minBatch = (float)$row['min_davka'];
            if ($minBatch > 0.0 && $target > 0.0) {
                $target = max($target, $minBatch);
            }
            $updates[] = [round($target, 3), $sku];
        }
        if ($updates) {
            $updateStmt = $pdo->prepare('UPDATE produkty SET min_zasoba=? WHERE sku=? AND nast_zasob = \'auto\'');
            foreach ($updates as $payload) {
                $updateStmt->execute($payload);
            }
        }
    }

    /**
     * Returns average daily demand per SKU, cascaded through BOM.
     */
    public static function buildDemandMap(int $days): array
    {
        $days = max(1, $days);
        if (isset(self::$demandCache[$days])) {
            return self::$demandCache[$days];
        }
        $since = (new DateTimeImmutable("-{$days} days"))->format('Y-m-d 00:00:00');
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT sku, SUM(CASE WHEN mnozstvi < 0 THEN -mnozstvi ELSE 0 END) AS demand FROM polozky_pohyby WHERE datum >= ? GROUP BY sku');
        $stmt->execute([$since]);
        $base = [];
        foreach ($stmt as $row) {
            $sku = trim((string)$row['sku']);
            if ($sku === '') {
                continue;
            }
            $base[$sku] = ((float)$row['demand']) / $days;
        }

        $graph = self::getBomGraph();
        $nodes = array_unique(array_merge(array_keys($graph['children']), array_keys($graph['indegree']), array_keys($base)));
        $total = [];
        foreach ($nodes as $node) {
            $total[$node] = $base[$node] ?? 0.0;
        }
        $order = self::topologicalOrder($graph['children'], $graph['indegree'], $nodes);
        foreach ($order as $sku) {
            $demand = $total[$sku] ?? 0.0;
            if ($demand <= 0) {
                continue;
            }
            foreach ($graph['children'][$sku] ?? [] as $child) {
                $childSku = $child['sku'];
                $total[$childSku] = ($total[$childSku] ?? 0.0) + ($demand * $child['koeficient']);
            }
        }
        self::$demandCache[$days] = $total;
        return $total;
    }

    /**
     * @return array{children:array<string,array<int,array{sku:string,koeficient:float}>>,indegree:array<string,int>}
     */
    public static function getBomGraph(): array
    {
        if (self::$bomCache !== null) {
            return self::$bomCache;
        }
        $children = [];
        $indegree = [];
        foreach (DB::pdo()->query('SELECT rodic_sku, potomek_sku, koeficient FROM bom') as $row) {
            $parent = (string)$row['rodic_sku'];
            $child = (string)$row['potomek_sku'];
            if ($parent === '' || $child === '') {
                continue;
            }
            $children[$parent][] = [
                'sku' => $child,
                'koeficient' => (float)$row['koeficient'],
            ];
            $indegree[$child] = ($indegree[$child] ?? 0) + 1;
            $indegree[$parent] = $indegree[$parent] ?? 0;
        }
        self::$bomCache = ['children' => $children, 'indegree' => $indegree];
        return self::$bomCache;
    }

    /**
     * @param array<string,array<int,array{sku:string,koeficient:float}>> $children
     * @param array<string,int> $indegree
     * @param array<int,string> $nodes
     * @return array<int,string>
     */
    private static function topologicalOrder(array $children, array $indegree, array $nodes): array
    {
        $queue = [];
        $order = [];
        foreach ($nodes as $node) {
            if (($indegree[$node] ?? 0) === 0) {
                $queue[] = $node;
            }
        }
        $seen = [];
        while ($queue) {
            $sku = array_shift($queue);
            if (isset($seen[$sku])) {
                continue;
            }
            $seen[$sku] = true;
            $order[] = $sku;
            foreach ($children[$sku] ?? [] as $edge) {
                $child = $edge['sku'];
                $indegree[$child] = ($indegree[$child] ?? 1) - 1;
                if ($indegree[$child] <= 0) {
                    $queue[] = $child;
                }
            }
        }
        // Append remaining nodes (cycle fallback)
        foreach ($nodes as $node) {
            if (!isset($seen[$node])) {
                $order[] = $node;
            }
        }
        return $order;
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array<string,float>
     */
    public static function buildStockMap(?array $skuFilter = null): array
    {
        $pdo = DB::pdo();
        $latest = $pdo->query('SELECT id, closed_at FROM inventury WHERE closed_at IS NOT NULL ORDER BY closed_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $snapshot = [];
        $params = [];
        if ($latest) {
            $sql = 'SELECT sku, stav FROM inventura_stavy WHERE inventura_id = ?';
            $params[] = (int)$latest['id'];
            if ($skuFilter && count($skuFilter) > 0) {
                $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
                $sql .= " AND sku IN ({$placeholders})";
                $params = array_merge($params, $skuFilter);
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt as $row) {
                $snapshot[(string)$row['sku']] = (float)$row['stav'];
            }
        }
        $stock = $snapshot;
        $movSql = 'SELECT sku, SUM(mnozstvi) AS qty FROM polozky_pohyby WHERE 1=1';
        $movParams = [];
        if ($latest && !empty($latest['closed_at'])) {
            $movSql .= ' AND datum > ?';
            $movParams[] = $latest['closed_at'];
        }
        if ($skuFilter && count($skuFilter) > 0) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $movSql .= " AND sku IN ({$placeholders})";
            $movParams = array_merge($movParams, $skuFilter);
        }
        $movSql .= ' GROUP BY sku';
        $stmt = $pdo->prepare($movSql);
        $stmt->execute($movParams);
        foreach ($stmt as $row) {
            $sku = (string)$row['sku'];
            if ($sku === '') {
                continue;
            }
            $stock[$sku] = ($stock[$sku] ?? 0.0) + (float)$row['qty'];
        }
        return $stock;
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array<string,float>
     */
    public static function buildReservationMap(?array $skuFilter = null): array
    {
        $sql = 'SELECT sku, SUM(mnozstvi) AS qty FROM rezervace WHERE 1=1';
        $params = [];
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $sql .= ' AND platna_do >= ?';
        $params[] = $today;
        if ($skuFilter && count($skuFilter) > 0) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql .= " AND sku IN ({$placeholders})";
            $params = array_merge($params, $skuFilter);
        }
        $sql .= ' GROUP BY sku';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt as $row) {
            $sku = (string)$row['sku'];
            $map[$sku] = (float)$row['qty'];
        }
        return $map;
    }

    /**
     * @param array<int,string> $skus
     * @return array<string,array{mode:string,daily:float,target:float,available:float,reservations:float,deficit:float,ratio:float}>
     */
    public static function getStatusForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map('trim', $skus))));
        if (!$skus) {
            return [];
        }
        $settings = self::getSettings();
        $stockDays = $settings['stock_days'];
        $consumptionDays = $settings['consumption_days'];
        $demandMap = self::buildDemandMap($consumptionDays);
        $stockMap = self::buildStockMap($skus);
        $reservationMap = self::buildReservationMap($skus);
        $meta = self::fetchProductsMeta($skus);
        $status = [];
        foreach ($skus as $sku) {
            $row = $meta[$sku] ?? null;
            $mode = $row['nast_zasob'] ?? 'manual';
            $daily = max(0.0, (float)($demandMap[$sku] ?? 0.0));
            if ($mode === 'auto') {
                $effectiveDays = $stockDays + max(0, (int)($row['vyrobni_doba_dni'] ?? 0));
                $target = $daily * $effectiveDays;
                $minBatch = max(0.0, (float)($row['min_davka'] ?? 0.0));
                if ($minBatch > 0.0 && $target > 0.0) {
                    $target = max($target, $minBatch);
                }
            } else {
                $target = max(0.0, (float)($row['min_zasoba'] ?? 0.0));
            }
            $stockQty = (float)($stockMap[$sku] ?? 0.0);
            $reservations = max(0.0, (float)($reservationMap[$sku] ?? 0.0));
            $available = $stockQty - $reservations;
            $deficit = max(0.0, $target - $available);
            $ratio = $target > 0 ? min(1.0, $deficit / $target) : ($deficit > 0 ? 1.0 : 0.0);
            $status[$sku] = [
                'mode' => $mode,
                'daily' => $daily,
                'target' => $target,
                'stock' => $stockQty,
                'available' => $available,
                'reservations' => $reservations,
                'deficit' => $deficit,
                'ratio' => $ratio,
            ];
        }
        return $status;
    }

    /**
     * @param array<int,string> $skus
     * @return array<string,array{nast_zasob:string,min_zasoba:float,min_davka:float,vyrobni_doba_dni:int}>
     */
    private static function fetchProductsMeta(array $skus): array
    {
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $stmt = DB::pdo()->prepare("SELECT sku, nast_zasob, min_zasoba, min_davka, vyrobni_doba_dni FROM produkty WHERE sku IN ({$placeholders})");
        $stmt->execute($skus);
        $map = [];
        foreach ($stmt as $row) {
            $sku = (string)$row['sku'];
            $map[$sku] = [
                'nast_zasob' => (string)($row['nast_zasob'] ?? 'manual'),
                'min_zasoba' => (float)($row['min_zasoba'] ?? 0.0),
                'min_davka' => (float)($row['min_davka'] ?? 0.0),
                'vyrobni_doba_dni' => (int)($row['vyrobni_doba_dni'] ?? 0),
            ];
        }
        return $map;
    }
}
