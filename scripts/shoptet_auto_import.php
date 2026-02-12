<?php
declare(strict_types=1);
/**
 * Automatické stažení faktur ze Shoptet eshopů a import do aplikace.
 * Credentials se načítají z DB (nastaveni_rady).
 * Spouštění: php scripts/shoptet_auto_import.php
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Service\ShoptetImportService;

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    ob_implicit_flush(true);
}

echo "Shoptet auto-import\n";
echo str_repeat('-', 40) . "\n";

$service = new ShoptetImportService();
$results = $service->runAll();

$hasError = false;
foreach ($results as $eshop => $result) {
    if (isset($result['error'])) {
        $hasError = true;
    }
    if (isset($result['currencies'])) {
        foreach ($result['currencies'] as $cur => $curResult) {
            if (isset($curResult['error'])) {
                $hasError = true;
            }
        }
    }
}

exit($hasError ? 1 : 0);
