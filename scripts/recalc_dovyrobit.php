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

// status a meta pro všechny produkty
$allProducts = [];
$prodStmt = $pdo->query('SELECT sku FROM produkty');
foreach ($prodStmt as $row) {
    $sku = trim((string)$row['sku']);
    if ($sku !== '') {
        $allProducts[] = $sku;
    }
}
if (!$allProducts) {
    $pdo->commit();
    return;
}
$status = StockService::getStatusForSkus($allProducts);
$metaStmt = $pdo->query('SELECT p.sku, p.min_zasoba, p.min_davka, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ');
$meta = [];
foreach ($metaStmt as $m) {
    $meta[(string)$m['sku']] = [
        'is_nonstock' => (int)$m['is_nonstock'] === 1,
        'min_zasoba' => (float)($m['min_zasoba'] ?? 0.0),
        'min_davka' => (float)($m['min_davka'] ?? 0.0),
    ];
}

// kořeny: skladové položky bez skladového rodiče
$roots = [];
foreach ($allProducts as $sku) {
    $metaRow = $meta[$sku] ?? [];
    $isNonstock = (bool)($metaRow['is_nonstock'] ?? false);
    if ($isNonstock) {
        continue; // nonstock není kořen
    }
    $hasStockParent = false;
    foreach ($parents[$sku] ?? [] as $edge) {
        $parentMeta = $meta[$edge['sku']] ?? [];
        $parentNonstock = (bool)($parentMeta['is_nonstock'] ?? false);
        if (!$parentNonstock) {
            $hasStockParent = true;
            break;
        }
    }
    if (!$hasStockParent) {
        $roots[] = $sku;
    }
}


$updateRows = [];
$incomingSum = [];

// Prepare indegree for topological propagation (parent -> child edges)
$indegree = [];
foreach ($children as $parent => $edges) {
    foreach ($edges as $edge) {
        $childSku = (string)$edge['sku'];
        $indegree[$childSku] = ($indegree[$childSku] ?? 0) + 1;
    }
}

$queue = $roots;
$processed = [];
while ($queue) {
    $sku = array_shift($queue);
    if (isset($processed[$sku])) {
        continue;
    }
    $processed[$sku] = true;

    $st = $status[$sku] ?? [];
    $metaRow = $meta[$sku] ?? ['is_nonstock' => false];
    $isNonstock = (bool)($metaRow['is_nonstock'] ?? false);
    $available = (float)($st['available'] ?? 0.0); // stock - reservations
    $baseNeed = $isNonstock ? 0.0 : max(0.0, (float)($st['deficit'] ?? 0.0)); // vlastní deficit z targetu

    $incoming = max(0.0, (float)($incomingSum[$sku] ?? 0.0));
    $missingForParents = $isNonstock ? $incoming : max(0.0, $incoming - $available);
    $needHere = max($baseNeed, $missingForParents);

    $updateRows[$sku] = $needHere;

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

// Fallback for any nodes not reached (cycles or disconnected): compute need from incomingSum without propagation
foreach ($incomingSum as $sku => $inc) {
    if (isset($processed[$sku])) {
        continue;
    }
    $st = $status[$sku] ?? [];
    $metaRow = $meta[$sku] ?? ['is_nonstock' => false];
    $isNonstock = (bool)($metaRow['is_nonstock'] ?? false);
    $available = (float)($st['available'] ?? 0.0);
    $baseNeed = $isNonstock ? 0.0 : max(0.0, (float)($st['deficit'] ?? 0.0));
    $incoming = max(0.0, (float)$inc);
    $missingForParents = $isNonstock ? $incoming : max(0.0, $incoming - $available);
    $needHere = max($baseNeed, $missingForParents);
    $updateRows[$sku] = $needHere;
}

$upd = $pdo->prepare('UPDATE produkty SET dovyrobit=? WHERE sku=?');
foreach ($updateRows as $sku => $need) {
    $upd->execute([round($need, 0), $sku]);
}

$pdo->commit();

if (!$embedRun) {
    echo "dovyrobit recalculated for " . count($updateRows) . " SKUs\n";
}
