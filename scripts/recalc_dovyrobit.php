<?php
declare(strict_types=1);

// Recount "dovyrobit" top-down across BOM. Run via CLI/cron.

require __DIR__ . '/../src/bootstrap.php';

use App\Support\DB;
use App\Service\StockService;

$embedRun = $GLOBALS['__RUN_RECALC_INLINE'] ?? false;

if (php_sapi_name() !== 'cli') {
    if (!$embedRun && !isset($_GET['run'])) {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Add ?run=1 to execute recalculation.\n";
        return;
    }
}

if (!isset($GLOBALS['__RECALC_ALREADY_RUNNING'])) {
    $GLOBALS['__RECALC_ALREADY_RUNNING'] = true;
}

$pdo = DB::pdo();
$pdo->beginTransaction();

// reset
$pdo->exec('UPDATE produkty SET dovyrobit = 0');

// load bom
$children = [];
$parents = [];
$stmt = $pdo->query('SELECT rodic_sku, potomek_sku, koeficient FROM bom');
foreach ($stmt as $row) {
    $parent = (string)$row['rodic_sku'];
    $child = (string)$row['potomek_sku'];
    $coef = (float)$row['koeficient'];
    if ($parent === '' || $child === '' || $coef <= 0) {
        continue;
    }
    $children[$parent][] = ['sku' => $child, 'coef' => $coef];
    $parents[$child][] = ['sku' => $parent, 'coef' => $coef];
}

// load meta + status for all skus in graph
$allSkus = array_values(array_unique(array_merge(array_keys($children), array_keys($parents))));
if (!$allSkus) {
    echo "No BOM data.\n";
    exit(0);
}
$status = StockService::getStatusForSkus($allSkus);
$metaStmt = $pdo->prepare('SELECT p.sku, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ WHERE p.sku IN (' . implode(',', array_fill(0, count($allSkus), '?')) . ')');
$metaStmt->execute($allSkus);
$meta = [];
foreach ($metaStmt as $m) {
    $meta[(string)$m['sku']] = (int)$m['is_nonstock'] === 1;
}

// find roots: skladové položky bez skladového rodiče
$roots = [];
foreach ($allSkus as $sku) {
    $isNon = $meta[$sku] ?? false;
    if ($isNon) continue;
    $hasStockParent = false;
    foreach ($parents[$sku] ?? [] as $edge) {
        if (!($meta[$edge['sku']] ?? false)) {
            $hasStockParent = true;
            break;
        }
    }
    if (!$hasStockParent) {
        $roots[] = $sku;
    }
}

$processed = [];
$updateRows = [];

$computeNeed = function (string $sku, float $incoming) use (&$computeNeed, &$children, &$status, &$meta, &$processed, &$updateRows): void {
    $st = $status[$sku] ?? [];
    $isNonstock = $meta[$sku] ?? false;
    $available = (float)($st['available'] ?? 0.0);
    $target = 0.0;
    $daily = (float)($st['daily'] ?? 0.0);
    $mode = (string)($st['mode'] ?? 'manual');
    $reservations = (float)($st['reservations'] ?? 0.0);
    $hasDirect = ($daily > 0.0) || ($reservations > 0.0);
    if ($isNonstock) {
        $target = 0.0;
    } elseif ($mode === 'auto' && $hasDirect) {
        $target = max(0.0, (float)($st['target'] ?? 0.0));
    } elseif ($mode !== 'auto') {
        $target = max(0.0, (float)($st['target'] ?? 0.0));
    }

    $ownNeed = max(0.0, $target - $available);
    $coverage = $isNonstock ? 0.0 : max(0.0, $available - $target);
    $parentNeed = max(0.0, $incoming - $coverage);
    $needHere = $isNonstock ? $parentNeed : max($ownNeed, $parentNeed);

    $updateRows[$sku] = ($updateRows[$sku] ?? 0.0) + $needHere;
    $processed[$sku] = true;

    if (empty($children[$sku])) {
        return;
    }
    foreach ($children[$sku] as $edge) {
        $childSku = (string)$edge['sku'];
        $coef = (float)$edge['coef'];
        if ($coef <= 0) continue;
        $childIncoming = max(0.0, $needHere * $coef);
        $computeNeed($childSku, $childIncoming);
    }
};

foreach ($roots as $root) {
    $computeNeed($root, 0.0);
}

// persist
$upd = $pdo->prepare('UPDATE produkty SET dovyrobit=? WHERE sku=?');
foreach ($updateRows as $sku => $need) {
    $upd->execute([round($need, 3), $sku]);
}

$pdo->commit();

if (!$embedRun) {
    echo "dovyrobit recalculated for " . count($updateRows) . " SKUs\n";
}
