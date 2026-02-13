<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

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

$count = StockService::recalcDovyrobit();

if (!$embedRun) {
    echo "dovyrobit recalculated for {$count} SKUs\n";
}
