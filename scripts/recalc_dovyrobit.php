<?php
declare(strict_types=1);

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

// status a meta
$allSkus = array_values(array_unique(array_merge(array_keys($children), array_keys($parents))));
if (!$allSkus) {
    $pdo->commit();
    return;
}
$status = StockService::getStatusForSkus($allSkus);
$metaStmt = $pdo->prepare('SELECT p.sku, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ WHERE p.sku IN (' . implode(',', array_fill(0, count($allSkus), '?')) . ')');
$metaStmt->execute($allSkus);
$meta = [];
foreach ($metaStmt as $m) {
    $meta[(string)$m['sku']] = (int)$m['is_nonstock'] === 1;
}

// kořeny: skladové položky bez skladového rodiče
$roots = [];
foreach ($allSkus as $sku) {
    if ($meta[$sku] ?? false) continue;
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

$updateRows = [];

$computeNeed = function (string $sku, float $incoming) use (&$computeNeed, &$children, &$status, &$meta, &$updateRows): void {
    $st = $status[$sku] ?? [];
    $isNonstock = (bool)($meta[$sku] ?? false);
    $target = max(0.0, (float)($st['target'] ?? 0.0));
    $available = (float)($st['available'] ?? 0.0);
    $ownNeed = $isNonstock ? 0.0 : max(0.0, $target - $available);
    $coverage = $isNonstock ? 0.0 : max(0.0, $available - $target);
    $parentNeed = max(0.0, $incoming - $coverage);
    $needHere = $isNonstock ? $parentNeed : max($ownNeed, $parentNeed);

    $updateRows[$sku] = ($updateRows[$sku] ?? 0.0) + $needHere;

    foreach ($children[$sku] ?? [] as $edge) {
        $coef = (float)$edge['coef'];
        if ($coef <= 0) {
            continue;
        }
        $computeNeed((string)$edge['sku'], $needHere * $coef);
    }
};

foreach ($roots as $root) {
    $computeNeed($root, 0.0);
}

$upd = $pdo->prepare('UPDATE produkty SET dovyrobit=? WHERE sku=?');
foreach ($updateRows as $sku => $need) {
    $upd->execute([round($need, 0), $sku]);
}

$pdo->commit();

if (!$embedRun) {
    echo "dovyrobit recalculated for " . count($updateRows) . " SKUs\n";
}
