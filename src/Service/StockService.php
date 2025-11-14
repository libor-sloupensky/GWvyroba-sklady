<?php
namespace App\Service;

use App\Support\DB;
use DateTimeImmutable;
use PDO;

final class StockService
{
    private static array $demandCache = [];
    private static ?array $bomCache = null;

    public static function getSettings(): array
    {
        $row = DB::pdo()->query('SELECT okno_pro_prumer_dni, spotreba_prumer_dni, zasoba_cil_dni FROM nastaveni_global WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'xml_window_days' => max(1, (int)($row['okno_pro_prumer_dni'] ?? 30)),
            'consumption_days' => max(1, (int)($row['spotreba_prumer_dni'] ?? 90)),
            'stock_days' => max(1, (int)($row['zasoba_cil_dni'] ?? 30)),
        ];
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
}
