<?php
namespace App\Service;

use App\Support\DB;
use DateTimeImmutable;
use PDO;

final class StockService
{
    // noop refresh marker
    private static array $demandCache = [];
    private static ?array $bomCache = null;
    private static bool $settingsColumnsVerified = false;
    private static array $baselineCache = [];

    /**
     * Vypočítá cílový stav (totalDemand) a množství k výrobě (dovyrobit) pro produkt.
     *
     * @param float $incoming Příchozí poptávka od rodičovských produktů
     * @param float $baseTarget Vlastní cílový stav (pouze pro root produkty)
     * @param float $available Dostupné zásoby (stav - rezervace)
     * @param bool $isNonstock Zda je produkt nonstock typ
     * @return array{cilovy_stav: float, dovyrobit: float}
     */
    public static function calculateProductionNeeds(float $incoming, float $baseTarget, float $available, bool $isNonstock): array
    {
        $totalDemand = max(0.0, $incoming) + $baseTarget;
        $needHere = $isNonstock ? $totalDemand : max(0.0, $totalDemand - $available);
        return [
            'cilovy_stav' => $totalDemand,
            'dovyrobit' => $needHere,
        ];
    }

    /**
     * Určí baseTarget pro produkt na základě jeho pozice v BOM stromu.
     *
     * @param bool $isNonstock Zda je produkt nonstock typ
     * @param bool $isRootNode Zda je produkt root (nemá skladové rodiče)
     * @param float $target Cílový stav z getStatusForSkus (daily * stockDays)
     * @return float
     */
    public static function calculateBaseTarget(bool $isNonstock, bool $isRootNode, float $target): float
    {
        return ($isNonstock || !$isRootNode) ? 0.0 : max(0.0, $target);
    }

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
        $directDemand = self::buildDirectDemandMap($consumptionDays);
        $parentsMap = self::getBomGraph()['parents'] ?? [];
        $filterSql = '';
        $filterParams = [];
        if ($skuFilter && count($skuFilter) > 0) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $filterSql = " AND sku IN ({$placeholders})";
            $filterParams = array_values($skuFilter);
        }
        $stmt = $pdo->prepare('SELECT p.sku, p.vyrobni_doba_dni, p.min_davka, p.min_zasoba, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ WHERE p.nast_zasob = \'auto\'' . $filterSql);
        $stmt->execute($filterParams);
        $autos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$autos) {
            return;
        }
        $updates = [];
        foreach ($autos as $row) {
            $sku = (string)$row['sku'];
            $daily = (float)($directDemand[$sku] ?? 0.0);
            $hasParent = !empty($parentsMap[$sku]);
            $isNonstock = ((int)($row['is_nonstock'] ?? 0) === 1);
            if ($isNonstock) {
                $updates[] = [0.0, $sku];
                continue;
            }
            $effectiveDays = $stockDays + max(0, (int)$row['vyrobni_doba_dni']);
            $target = $daily * $effectiveDays;
            $target = max($target, (float)($row['min_zasoba'] ?? 0.0));
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
        $stmt = $pdo->prepare(
            'SELECT sku, SUM(mnozstvi) AS demand
             FROM polozky_eshop
             WHERE duzp >= ? AND sku IS NOT NULL AND sku <> \'\'
             GROUP BY sku'
        );
        $stmt->execute([$since]);
        $base = [];
        foreach ($stmt as $row) {
            $sku = trim((string)$row['sku']);
            if ($sku === '') {
                continue;
            }
            $base[$sku] = ((float)$row['demand']) / $days;
        }
        if (empty($base)) {
            $fallback = $pdo->prepare('SELECT sku, SUM(CASE WHEN mnozstvi < 0 THEN -mnozstvi ELSE 0 END) AS demand FROM polozky_pohyby WHERE datum >= ? GROUP BY sku');
            $fallback->execute([$since]);
            foreach ($fallback as $row) {
                $sku = trim((string)$row['sku']);
                if ($sku === '') {
                    continue;
                }
                $base[$sku] = ((float)$row['demand']) / $days;
            }
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
     * Returns average daily direct demand per SKU (bez kaskády).
     */
    public static function buildDirectDemandMap(int $days): array
    {
        $days = max(1, $days);
        if (isset(self::$demandCache['direct'][$days])) {
            return self::$demandCache['direct'][$days];
        }
        $since = (new DateTimeImmutable("-{$days} days"))->format('Y-m-d 00:00:00');
        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            'SELECT sku, SUM(mnozstvi) AS demand
             FROM polozky_eshop
             WHERE duzp >= ? AND sku IS NOT NULL AND sku <> \'\'
             GROUP BY sku'
        );
        $stmt->execute([$since]);
        $base = [];
        foreach ($stmt as $row) {
            $sku = trim((string)$row['sku']);
            if ($sku === '') {
                continue;
            }
            $base[$sku] = ((float)$row['demand']) / $days;
        }
        if (empty($base)) {
            $fallback = $pdo->prepare('SELECT sku, SUM(CASE WHEN mnozstvi < 0 THEN -mnozstvi ELSE 0 END) AS demand FROM polozky_pohyby WHERE datum >= ? GROUP BY sku');
            $fallback->execute([$since]);
            foreach ($fallback as $row) {
                $sku = trim((string)$row['sku']);
                if ($sku === '') {
                    continue;
                }
                $base[$sku] = ((float)$row['demand']) / $days;
            }
        }
        self::$demandCache['direct'][$days] = $base;
        return $base;
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
        $parents = [];
        $indegree = [];
        $stmt = DB::pdo()->query('SELECT rodic_sku, potomek_sku, koeficient, COALESCE(NULLIF(merna_jednotka_potomka, \'\'), NULL) AS edge_mj FROM bom');
        foreach ($stmt as $row) {
            $parent = (string)$row['rodic_sku'];
            $child = (string)$row['potomek_sku'];
            if ($parent === '' || $child === '') {
                continue;
            }
            $coef = (float)$row['koeficient'];
            $edgeUnit = $row['edge_mj'] === null ? null : (string)$row['edge_mj'];
            $children[$parent][] = [
                'sku' => $child,
                'koeficient' => $coef,
                'edge_mj' => $edgeUnit,
            ];
            $parents[$child][] = [
                'sku' => $parent,
                'koeficient' => $coef,
                'merna_jednotka' => $edgeUnit,
            ];
            $indegree[$child] = ($indegree[$child] ?? 0) + 1;
            $indegree[$parent] = $indegree[$parent] ?? 0;
        }
        self::$bomCache = ['children' => $children, 'parents' => $parents, 'indegree' => $indegree];
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
    public static function buildStockMap(?array $skuFilter = null, ?string $asOf = null): array
    {
        $asOf = self::normalizeAsOf($asOf);
        $baseline = self::findBaselineInventory($asOf);
        $snapshot = self::loadSnapshotMap($baseline['id'] ?? null, $skuFilter);
        $movements = self::loadMovementSums($baseline['closed_at'] ?? null, $asOf, $skuFilter);
        return self::mergeQuantities($snapshot, $movements);
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array<string,float>
     */
    public static function buildReservationMap(?array $skuFilter = null, ?string $asOf = null): array
    {
        $asOf = self::normalizeAsOf($asOf);
        return self::loadReservationMap($skuFilter, $asOf);
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array{stock:array<string,float>,reservations:array<string,float>,available:array<string,float>}
     */
    public static function getStockState(?array $skuFilter = null, ?string $asOf = null): array
    {
        $asOf = self::normalizeAsOf($asOf);
        $stock = self::buildStockMap($skuFilter, $asOf);
        $reservations = self::loadReservationMap($skuFilter, $asOf);
        $available = [];
        $skuKeys = array_unique(array_merge(array_keys($stock), array_keys($reservations)));
        foreach ($skuKeys as $sku) {
            $available[$sku] = ($stock[$sku] ?? 0.0) - ($reservations[$sku] ?? 0.0);
        }
        return [
            'stock' => $stock,
            'reservations' => $reservations,
            'available' => $available,
        ];
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
        $directDemandMap = self::buildDirectDemandMap($consumptionDays);
        $state = self::getStockState($skus);
        $stockMap = $state['stock'];
        $reservationMap = $state['reservations'];
        $meta = self::fetchProductsMeta($skus);
        $parentsMap = self::getBomGraph()['parents'] ?? [];
        $status = [];
        foreach ($skus as $sku) {
            $row = $meta[$sku] ?? null;
            $mode = $row['nast_zasob'] ?? 'manual';
            $daily = max(0.0, (float)($demandMap[$sku] ?? 0.0));
            $directDaily = max(0.0, (float)($directDemandMap[$sku] ?? 0.0));
            $hasParent = !empty($parentsMap[$sku]);
            $hasDirectDemand = ($directDaily > 0.0) || !empty($reservationMap[$sku]);
            $isNonstock = (bool)($row['is_nonstock'] ?? false);

            if ($isNonstock) {
                $target = 0.0;
            } elseif ($mode === 'auto') {
                $target = $daily * $stockDays;
            } else {
                $target = 0.0;
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
     * @param array<int,string>|null $skuFilter
     */
    public static function recalcDovyrobit(?array $skuFilter = null): int
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            if ($skuFilter && count($skuFilter) > 0) {
                $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
                $stmt = $pdo->prepare("UPDATE produkty SET dovyrobit = 0, cilovy_stav_calc = 0 WHERE sku IN ({$placeholders})");
                $stmt->execute(array_values($skuFilter));
            } else {
                $pdo->exec('UPDATE produkty SET dovyrobit = 0, cilovy_stav_calc = 0');
            }

            $meta = self::loadDovyrobitMeta($skuFilter);
            if (empty($meta)) {
                $pdo->commit();
                return 0;
            }
            $allProducts = array_keys($meta);
            $status = self::getStatusForSkus($allProducts);

            $graph = self::buildActiveBomGraph($meta);
            $children = $graph['children'];
            $parents = $graph['parents'];
            $indegree = $graph['indegree'];
            $roots = self::findDovyrobitRoots($allProducts, $parents, $meta);

            $updateRows = [];
            $incomingSum = [];
            $queue = $roots;
            $processed = [];

            while ($queue) {
                $sku = array_shift($queue);
                if (isset($processed[$sku])) {
                    continue;
                }
                $processed[$sku] = true;
                $metaRow = $meta[$sku] ?? ['aktivni' => false, 'is_nonstock' => false];
                if (empty($metaRow['aktivni'])) {
                    continue;
                }
                $st = $status[$sku] ?? [];
                $isNonstock = !empty($metaRow['is_nonstock']);
                $available = (float)($st['available'] ?? 0.0);
                $isRootNode = in_array($sku, $roots, true);
                $baseTarget = self::calculateBaseTarget($isNonstock, $isRootNode, (float)($st['target'] ?? 0.0));

                $incoming = (float)($incomingSum[$sku] ?? 0.0);
                $needs = self::calculateProductionNeeds($incoming, $baseTarget, $available, $isNonstock);
                $needHere = $needs['dovyrobit'];
                $updateRows[$sku] = [
                    'dovyrobit' => $needs['dovyrobit'],
                    'cilovy_stav' => $needs['cilovy_stav'],
                ];

                foreach ($children[$sku] ?? [] as $edge) {
                    $coef = (float)$edge['coef'];
                    if ($coef <= 0) {
                        continue;
                    }
                    $childSku = (string)$edge['sku'];
                    $incomingSum[$childSku] = ($incomingSum[$childSku] ?? 0.0) + ($needHere * $coef);
                    $indegree[$childSku] = ($indegree[$childSku] ?? 1) - 1;
                    if ($indegree[$childSku] <= 0) {
                        $queue[] = $childSku;
                    }
                }
            }

            foreach ($incomingSum as $sku => $inc) {
                if (isset($processed[$sku])) {
                    continue;
                }
                $metaRow = $meta[$sku] ?? ['aktivni' => false, 'is_nonstock' => false];
                if (empty($metaRow['aktivni'])) {
                    continue;
                }
                $st = $status[$sku] ?? [];
                $isNonstock = !empty($metaRow['is_nonstock']);
                $available = (float)($st['available'] ?? 0.0);
                $isRootNode = in_array($sku, $roots, true);
                $baseTarget = self::calculateBaseTarget($isNonstock, $isRootNode, (float)($st['target'] ?? 0.0));
                $incoming = (float)$inc;
                $needs = self::calculateProductionNeeds($incoming, $baseTarget, $available, $isNonstock);
                $updateRows[$sku] = [
                    'dovyrobit' => $needs['dovyrobit'],
                    'cilovy_stav' => $needs['cilovy_stav'],
                ];
            }

            $upd = $pdo->prepare('UPDATE produkty SET dovyrobit=?, cilovy_stav_calc=? WHERE sku=?');
            foreach ($updateRows as $sku => $row) {
                $upd->execute([round($row['dovyrobit'], 0), round($row['cilovy_stav'], 0), $sku]);
            }
            $pdo->commit();
            return count($updateRows);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<int,string> $skus
     * @return array<string,array{nast_zasob:string,min_zasoba:float,min_davka:float,vyrobni_doba_dni:int,is_nonstock:int}>
     */
    private static function fetchProductsMeta(array $skus): array
    {
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $stmt = DB::pdo()->prepare("SELECT p.sku, p.nast_zasob, p.min_zasoba, p.min_davka, p.vyrobni_doba_dni, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ WHERE p.sku IN ({$placeholders})");
        $stmt->execute($skus);
        $map = [];
        foreach ($stmt as $row) {
            $sku = (string)$row['sku'];
            $map[$sku] = [
                'nast_zasob' => (string)($row['nast_zasob'] ?? 'manual'),
                'min_zasoba' => (float)($row['min_zasoba'] ?? 0.0),
                'min_davka' => (float)($row['min_davka'] ?? 0.0),
                'vyrobni_doba_dni' => (int)($row['vyrobni_doba_dni'] ?? 0),
                'is_nonstock' => (int)($row['is_nonstock'] ?? 0),
            ];
        }
        return $map;
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array<string,array{aktivni:bool,is_nonstock:bool,min_zasoba:float,min_davka:float}>
     */
    private static function loadDovyrobitMeta(?array $skuFilter = null): array
    {
        $sql = 'SELECT p.sku, p.aktivni, p.min_zasoba, p.min_davka, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ';
        $params = [];
        if ($skuFilter && count($skuFilter) > 0) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql .= " WHERE p.sku IN ({$placeholders})";
            $params = array_values($skuFilter);
        }
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $meta = [];
        foreach ($stmt as $row) {
            $sku = (string)$row['sku'];
            if ($sku === '') {
                continue;
            }
            $meta[$sku] = [
                'aktivni' => ((int)($row['aktivni'] ?? 0) === 1),
                'is_nonstock' => ((int)($row['is_nonstock'] ?? 0) === 1),
                'min_zasoba' => (float)($row['min_zasoba'] ?? 0.0),
                'min_davka' => (float)($row['min_davka'] ?? 0.0),
            ];
        }
        return $meta;
    }

    /**
     * @param array<string,array{aktivni:bool,is_nonstock:bool}> $meta
     * @return array{children:array<string,array<int,array{sku:string,coef:float}>>,parents:array<string,array<int,array{sku:string,coef:float}>>,indegree:array<string,int>}
     */
    private static function buildActiveBomGraph(array $meta): array
    {
        $children = [];
        $parents = [];
        $indegree = [];
        $stmt = DB::pdo()->query('SELECT rodic_sku, potomek_sku, koeficient FROM bom');
        foreach ($stmt as $row) {
            $parent = (string)$row['rodic_sku'];
            $child = (string)$row['potomek_sku'];
            $coef = (float)$row['koeficient'];
            if ($parent === '' || $child === '' || $coef <= 0) {
                continue;
            }
            if (empty($meta[$parent]['aktivni'])) {
                continue;
            }
            if (!isset($meta[$parent]) || !isset($meta[$child])) {
                continue;
            }
            $children[$parent][] = ['sku' => $child, 'coef' => $coef];
            $parents[$child][] = ['sku' => $parent, 'coef' => $coef];
            $indegree[$child] = ($indegree[$child] ?? 0) + 1;
            $indegree[$parent] = $indegree[$parent] ?? 0;
        }
        return ['children' => $children, 'parents' => $parents, 'indegree' => $indegree];
    }

    /**
     * @param array<int,string> $allProducts
     * @param array<string,array<int,array{sku:string,coef:float}>> $parents
     * @param array<string,array{aktivni:bool,is_nonstock:bool}> $meta
     * @return array<int,string>
     */
    private static function findDovyrobitRoots(array $allProducts, array $parents, array $meta): array
    {
        $roots = [];
        foreach ($allProducts as $sku) {
            $metaRow = $meta[$sku] ?? [];
            if (empty($metaRow['aktivni']) || !empty($metaRow['is_nonstock'])) {
                continue;
            }
            $hasStockParent = false;
            foreach ($parents[$sku] ?? [] as $edge) {
                $parentMeta = $meta[$edge['sku']] ?? [];
                if (!empty($parentMeta['is_nonstock'])) {
                    continue;
                }
                $hasStockParent = true;
                break;
            }
            if (!$hasStockParent) {
                $roots[] = $sku;
            }
        }
        return $roots;
    }

    private static function normalizeAsOf(?string $asOf): ?string
    {
        if ($asOf === null) {
            return null;
        }
        $asOf = trim($asOf);
        if ($asOf === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
            return $asOf . ' 23:59:59';
        }
        return $asOf;
    }

    /**
     * @return array{id:int,closed_at:string}|null
     */
    private static function findBaselineInventory(?string $asOf = null): ?array
    {
        $cacheKey = $asOf ?? 'latest';
        if (array_key_exists($cacheKey, self::$baselineCache)) {
            return self::$baselineCache[$cacheKey];
        }
        $pdo = DB::pdo();
        if ($asOf !== null) {
            $stmt = $pdo->prepare('SELECT id, closed_at FROM inventury WHERE closed_at IS NOT NULL AND closed_at <= ? ORDER BY closed_at DESC, id DESC LIMIT 1');
            $stmt->execute([$asOf]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $row = $pdo->query('SELECT id, closed_at FROM inventury WHERE closed_at IS NOT NULL ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $baseline = $row ? ['id' => (int)$row['id'], 'closed_at' => (string)$row['closed_at']] : null;
        self::$baselineCache[$cacheKey] = $baseline;
        return $baseline;
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array<string,float>
     */
    private static function loadSnapshotMap(?int $inventoryId, ?array $skuFilter): array
    {
        if (!$inventoryId) {
            return [];
        }
        $sql = 'SELECT sku, stav FROM inventura_stavy WHERE inventura_id = ?';
        $params = [$inventoryId];
        if ($skuFilter && count($skuFilter) > 0) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql .= " AND sku IN ({$placeholders})";
            $params = array_merge($params, $skuFilter);
        }
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $snapshot = [];
        foreach ($stmt as $row) {
            $snapshot[(string)$row['sku']] = (float)$row['stav'];
        }
        return $snapshot;
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array<string,float>
     */
    private static function loadMovementSums(?string $since, ?string $until, ?array $skuFilter): array
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
        if ($skuFilter && count($skuFilter) > 0) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql .= " AND sku IN ({$placeholders})";
            $params = array_merge($params, $skuFilter);
        }
        $sql .= ' GROUP BY sku';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $movements = [];
        foreach ($stmt as $row) {
            $sku = (string)$row['sku'];
            if ($sku === '') {
                continue;
            }
            $movements[$sku] = (float)$row['qty'];
        }
        return $movements;
    }

    /**
     * @param array<int,string>|null $skuFilter
     * @return array<string,float>
     */
    private static function loadReservationMap(?array $skuFilter, ?string $asOf): array
    {
        $asOfDate = $asOf ? substr($asOf, 0, 10) : (new DateTimeImmutable('today'))->format('Y-m-d');
        $sql = 'SELECT sku, SUM(mnozstvi) AS qty FROM rezervace WHERE 1=1 AND platna_do >= ?';
        $params = [$asOfDate];
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
     * @param array<string,float> $base
     * @param array<string,float> $delta
     * @return array<string,float>
     */
    private static function mergeQuantities(array $base, array $delta): array
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
}
