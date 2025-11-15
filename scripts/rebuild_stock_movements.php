<?php
declare(strict_types=1);

use App\Service\MovementRebuildService;

if (PHP_SAPI !== 'cli') {
    echo "Tento skript spouštějte z CLI.\n";
    exit(1);
}

require __DIR__ . '/../src/bootstrap.php';

try {
    $result = MovementRebuildService::rebuild();
    echo sprintf(
        "Hotovo – doklady: %d, položky: %d, pohyby: %d, chybějící produkty: %d\n",
        $result['documents'],
        $result['items'],
        $result['movements'],
        $result['missing']
    );
} catch (Throwable $e) {
    echo "Chyba: " . $e->getMessage() . "\n";
    exit(1);
}
