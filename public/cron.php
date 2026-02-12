<?php
declare(strict_types=1);
/**
 * Cron endpoint pro Webglobe hosting.
 *
 * Webglobe vyžaduje fyzický soubor na disku.
 * Tento soubor slouží jako tenký wrapper pro automatický Shoptet import.
 *
 * Nastavení v Webglobe administraci (HOSTING -> WEB -> CRON):
 *   Script: https://vase-domena.cz/cron.php?token=VAS-CRON-TOKEN
 *
 * Alternativně lze spustit z CLI:
 *   php public/cron.php
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Service\ShoptetImportService;

// -- Autorizace --------------------------------------------------------
$cfg = include __DIR__ . '/../config/config.php';
$cronToken = (string)($cfg['cron_token'] ?? '');
$providedToken = trim((string)($_GET['token'] ?? ''));

$authorized = false;

// Token z URL parametru
if ($cronToken !== '' && $providedToken !== '' && hash_equals($cronToken, $providedToken)) {
    $authorized = true;
}

// CLI (přímo z příkazové řádky) - vždy povoleno
if (PHP_SAPI === 'cli') {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden: Invalid token.\n";
    exit(1);
}

// -- Příprava výstupu ---------------------------------------------------
header('Content-Type: text/plain; charset=utf-8');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
if (function_exists('ob_implicit_flush')) {
    ob_implicit_flush(true);
}

echo "Shoptet auto-import\n";
echo str_repeat('=', 40) . "\n\n";

// -- Import -------------------------------------------------------------
$service = new ShoptetImportService();
$results = $service->runAll();

// Výpis logů
foreach ($service->getLogBuffer() as $line) {
    echo $line . "\n";
}

echo "\n" . str_repeat('=', 40) . "\n";
echo "SOUHRN:\n" . str_repeat('-', 40) . "\n\n";

// Pokud běží jiný import
if (isset($results['_locked'])) {
    echo "LOCKED: Jiný import právě běží, zkuste za chvíli.\n\nSTATUS: OK\n";
    exit(0);
}

$hasError = false;
foreach ($results as $eshop => $result) {
    if (!empty($result['skipped'])) {
        echo "  SKIP  {$eshop}  (už naimportován dnes)\n";
    } elseif (isset($result['error'])) {
        $hasError = true;
        echo "  FAIL  {$eshop}  {$result['error']}\n";
    } elseif (isset($result['currencies'])) {
        foreach ($result['currencies'] as $cur => $curResult) {
            if (isset($curResult['error'])) {
                $hasError = true;
                echo "  FAIL  {$eshop}/{$cur}  {$curResult['error']}\n";
            } elseif (((int)($curResult['doklady'] ?? 0)) === 0) {
                echo "  WARN  {$eshop}/{$cur}  žádné doklady\n";
            } else {
                echo "  OK    {$eshop}/{$cur}  doklady={$curResult['doklady']}  polozky={$curResult['polozky']}\n";
            }
        }
    }
    echo "\n";
}
echo str_repeat('-', 40) . "\n";
echo $hasError ? "STATUS: ERROR\n" : "STATUS: OK\n";

exit($hasError ? 1 : 0);
