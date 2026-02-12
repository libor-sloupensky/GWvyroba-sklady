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
 *   php cron.php
 */

require __DIR__ . '/src/bootstrap.php';

use App\Service\ShoptetImportService;

// -- Autorizace --------------------------------------------------------
$cfg = include __DIR__ . '/config/config.php';
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

// -- Detekce prohlížeče vs. cron/CLI ------------------------------------
$isBrowser = PHP_SAPI !== 'cli'
    && isset($_SERVER['HTTP_ACCEPT'])
    && stripos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false;

// -- Příprava výstupu ---------------------------------------------------
if ($isBrowser) {
    header('Content-Type: text/html; charset=utf-8');
} else {
    header('Content-Type: text/plain; charset=utf-8');
}
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
if (function_exists('ob_implicit_flush')) {
    ob_implicit_flush(true);
}

// -- Import -------------------------------------------------------------
$service = new ShoptetImportService();
$results = $service->runAll();

// -- Sestavení výstupu --------------------------------------------------
$lines = []; // [text, type] - type: 'heading', 'ok', 'fail', 'warn', 'skip', 'lock', 'info', 'sep'

$lines[] = ['Shoptet auto-import', 'heading'];
$lines[] = [str_repeat('=', 50), 'sep'];
$lines[] = ['', 'sep'];

foreach ($service->getLogBuffer() as $logLine) {
    if ($logLine === '') {
        $lines[] = ['', 'sep'];
    } elseif (str_contains($logLine, '[ERROR]')) {
        $lines[] = [$logLine, 'fail'];
    } elseif (str_contains($logLine, '[WARN]')) {
        $lines[] = [$logLine, 'warn'];
    } else {
        $lines[] = [$logLine, 'info'];
    }
}

$lines[] = ['', 'sep'];
$lines[] = [str_repeat('=', 50), 'sep'];
$lines[] = ['SOUHRN:', 'heading'];
$lines[] = [str_repeat('-', 50), 'sep'];
$lines[] = ['', 'sep'];

$hasError = false;

if (isset($results['_locked'])) {
    $lines[] = ['LOCKED  Jiný import právě běží, zkuste za chvíli.', 'lock'];
    $lines[] = ['', 'sep'];
    $lines[] = ['STATUS: OK', 'ok'];
} else {
    foreach ($results as $eshop => $result) {
        if (!empty($result['skipped'])) {
            $lines[] = ["  SKIP  {$eshop}  (už naimportován dnes)", 'skip'];
        } elseif (isset($result['error'])) {
            $hasError = true;
            $lines[] = ["  FAIL  {$eshop}  {$result['error']}", 'fail'];
        } elseif (isset($result['currencies'])) {
            foreach ($result['currencies'] as $cur => $curResult) {
                if (isset($curResult['error'])) {
                    $hasError = true;
                    $lines[] = ["  FAIL  {$eshop}/{$cur}  {$curResult['error']}", 'fail'];
                } elseif (((int)($curResult['doklady'] ?? 0)) === 0) {
                    $lines[] = ["  WARN  {$eshop}/{$cur}  žádné doklady", 'warn'];
                } else {
                    $lines[] = ["  OK    {$eshop}/{$cur}  doklady={$curResult['doklady']}  polozky={$curResult['polozky']}", 'ok'];
                }
            }
        }
        $lines[] = ['', 'sep'];
    }
    $lines[] = [str_repeat('-', 50), 'sep'];
    $lines[] = [$hasError ? 'STATUS: ERROR' : 'STATUS: OK', $hasError ? 'fail' : 'ok'];
}

// -- Výpis --------------------------------------------------------------
if ($isBrowser) {
    $colors = [
        'heading' => '#1565c0',
        'ok'      => '#2e7d32',
        'fail'    => '#c62828',
        'warn'    => '#e65100',
        'skip'    => '#78909c',
        'lock'    => '#e65100',
        'info'    => '#37474f',
        'sep'     => '#90a4ae',
    ];
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Shoptet auto-import</title></head><body style='margin:2rem;font-family:monospace;font-size:14px;background:#fafafa;'>\n";
    echo "<div style='max-width:900px;background:#fff;border:1px solid #cfd8dc;border-radius:8px;padding:1.5rem 2rem;box-shadow:0 1px 3px rgba(0,0,0,0.08);'>\n";
    foreach ($lines as [$text, $type]) {
        $c = $colors[$type] ?? '#37474f';
        $esc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        if ($text === '') {
            echo "<br>\n";
        } elseif ($type === 'heading') {
            echo "<div style='color:{$c};font-weight:bold;font-size:16px;'>{$esc}</div>\n";
        } elseif ($type === 'sep') {
            echo "<div style='color:{$c};'>{$esc}</div>\n";
        } else {
            echo "<div style='color:{$c};padding:2px 0;'>{$esc}</div>\n";
        }
    }
    echo "</div>\n";
    echo "<p style='color:#90a4ae;font-size:12px;margin-top:1rem;'>Vygenerováno: " . date('d.m.Y H:i:s') . "</p>\n";
    echo "</body></html>";
} else {
    foreach ($lines as [$text, $type]) {
        echo $text . "\n";
    }
}

exit($hasError ? 1 : 0);
