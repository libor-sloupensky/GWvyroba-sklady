<?php
declare(strict_types=1);

use App\Support\DB;

if (PHP_SAPI !== 'cli') {
    echo "Tento skript spouštějte z CLI.\n";
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    $docs = $pdo->query('SELECT id, eshop_source, cislo_dokladu, duzp, import_batch_id FROM doklady_eshop ORDER BY duzp')->fetchAll(PDO::FETCH_ASSOC);
    $itemsStmt = $pdo->prepare('SELECT code_raw, stock_ids_raw, sku, ean, nazev, mnozstvi, merna_jednotka FROM polozky_eshop WHERE eshop_source=? AND cislo_dokladu=?');
    $productStmt = $pdo->prepare('SELECT sku, merna_jednotka, typ FROM produkty WHERE sku=? OR alt_sku=? LIMIT 1');
    $bomStmt = $pdo->prepare('SELECT potomek_sku, koeficient, merna_jednotka_potomka, druh_vazby FROM bom WHERE rodic_sku=?');
    $movementDelete = $pdo->prepare('DELETE FROM polozky_pohyby WHERE ref_id=?');
    $movementInsert = $pdo->prepare('INSERT INTO polozky_pohyby (datum, sku, mnozstvi, merna_jednotka, typ_pohybu, poznamka, ref_id) VALUES (?,?,?,?,?,?,?)');

    foreach ($docs as $doc) {
        $eshop = (string)$doc['eshop_source'];
        $docNumber = (string)$doc['cislo_dokladu'];
        $duzp = (string)$doc['duzp'];
        $itemsStmt->execute([$eshop, $docNumber]);
        foreach ($itemsStmt as $item) {
            $sku = (string)($item['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $qty = (float)$item['mnozstvi'];
            if ($qty === 0.0) {
                continue;
            }
            $ref = sprintf('doc:%s:%s:%s', strtolower($eshop), $docNumber, $sku);
            $movementDelete->execute([$ref]);
            $productStmt->execute([$sku, $sku]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                continue;
            }
            $type = (string)$product['typ'];
            $unit = (string)$product['merna_jednotka'];
            if (in_array($type, ['karton', 'baleni'], true)) {
                $bomStmt->execute([$product['sku']]);
                foreach ($bomStmt as $bomRow) {
                    $childSku = (string)$bomRow['potomek_sku'];
                    if ($childSku === '') {
                        continue;
                    }
                    $componentQty = -1 * $qty * (float)$bomRow['koeficient'];
                    $movementInsert->execute([
                        $duzp,
                        $childSku,
                        $componentQty,
                        $bomRow['merna_jednotka_potomka'] !== '' ? $bomRow['merna_jednotka_potomka'] : null,
                        'odpis',
                        sprintf('Rebuild %s/%s (rozpad %s)', $eshop, $docNumber, $product['sku']),
                        $ref . ':' . $childSku,
                    ]);
                }
            } else {
                $movementInsert->execute([
                    $duzp,
                    $product['sku'],
                    -1 * $qty,
                    $unit,
                    'odpis',
                    sprintf('Rebuild %s/%s', $eshop, $docNumber),
                    $ref,
                ]);
            }
        }
    }
    $pdo->commit();
    echo "Hotovo.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Chyba: " . $e->getMessage() . "\n";
    exit(1);
}
