<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Controller\ImportController;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Pouze pro CLI.\n");
    exit(1);
}

$eshop = $argv[1] ?? 'wormup.com';
$file = $argv[2] ?? '';

if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Použití: php scripts/import_pohoda_cli.php <eshop_source> <soubor.xml>\n");
    exit(1);
}

$xml = file_get_contents($file);
if ($xml === false) {
    fwrite(STDERR, "Nelze načíst soubor {$file}\n");
    exit(1);
}

$ctrl = new ImportController();
try {
    [$docs, $items, $missing, $skipped] = $ctrl->importPohodaFromStringCli($eshop, $xml);
    echo "OK: doklady={$docs}, polozky={$items}\n";
    if (!empty($skipped)) {
        echo "Preskocene doklady:\n";
        foreach ($skipped as $s) {
            echo "  {$s['cislo_dokladu']}: {$s['duvod']}\n";
        }
    }
    if (!empty($missing)) {
        echo "Chybejici SKU (pocet): " . count($missing) . "\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Chyba importu: " . $e->getMessage() . "\n");
    exit(2);
}
