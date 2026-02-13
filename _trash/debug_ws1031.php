<?php
require __DIR__ . '/src/bootstrap.php';

use App\Support\DB;

$pdo = DB::pdo();

echo "=== KONTROLA WS1031 ===\n\n";

// 1. Zkontroluj produkt a jeho typ
echo "1. Informace o produktu WS1031:\n";
$stmt = $pdo->prepare('
    SELECT p.sku, p.typ, pt.is_nonstock, p.merna_jednotka, pt.nazev as typ_nazev
    FROM produkty p
    LEFT JOIN product_types pt ON pt.code = p.typ
    WHERE p.sku = ?
');
$stmt->execute(['WS1031']);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if ($product) {
    echo "  SKU: " . $product['sku'] . "\n";
    echo "  Typ: " . ($product['typ'] ?? 'NULL') . "\n";
    echo "  Typ název: " . ($product['typ_nazev'] ?? 'NULL') . "\n";
    echo "  is_nonstock: " . ($product['is_nonstock'] ? 'ANO' : 'NE') . "\n";
    echo "  Měrná jednotka: " . ($product['merna_jednotka'] ?? 'NULL') . "\n";
} else {
    echo "  PRODUKT NENALEZEN!\n";
}

echo "\n2. BOM potomci pro WS1031:\n";
$stmt = $pdo->prepare('
    SELECT b.potomek_sku, b.koeficient,
           COALESCE(NULLIF(b.merna_jednotka_potomka, \'\'), NULL) AS edge_unit,
           p.merna_jednotka
    FROM bom b
    LEFT JOIN produkty p ON p.sku = b.potomek_sku
    WHERE b.rodic_sku = ?
');
$stmt->execute(['WS1031']);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($children)) {
    echo "  ŽÁDNÍ POTOMCI!\n";
} else {
    foreach ($children as $child) {
        echo "  - " . $child['potomek_sku'] .
             " (koef: " . $child['koeficient'] .
             ", edge_unit: " . ($child['edge_unit'] ?? 'NULL') .
             ", jednotka: " . ($child['merna_jednotka'] ?? 'NULL') . ")\n";
    }
}

echo "\n=== PRO SROVNÁNÍ: WS0232 (funguje správně) ===\n\n";

// Pro srovnání totéž pro WS0232
echo "1. Informace o produktu WS0232:\n";
$stmt = $pdo->prepare('
    SELECT p.sku, p.typ, pt.is_nonstock, p.merna_jednotka, pt.nazev as typ_nazev
    FROM produkty p
    LEFT JOIN product_types pt ON pt.code = p.typ
    WHERE p.sku = ?
');
$stmt->execute(['WS0232']);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if ($product) {
    echo "  SKU: " . $product['sku'] . "\n";
    echo "  Typ: " . ($product['typ'] ?? 'NULL') . "\n";
    echo "  Typ název: " . ($product['typ_nazev'] ?? 'NULL') . "\n";
    echo "  is_nonstock: " . ($product['is_nonstock'] ? 'ANO' : 'NE') . "\n";
    echo "  Měrná jednotka: " . ($product['merna_jednotka'] ?? 'NULL') . "\n";
}

echo "\n2. BOM potomci pro WS0232:\n";
$stmt = $pdo->prepare('
    SELECT b.potomek_sku, b.koeficient,
           COALESCE(NULLIF(b.merna_jednotka_potomka, \'\'), NULL) AS edge_unit,
           p.merna_jednotka
    FROM bom b
    LEFT JOIN produkty p ON p.sku = b.potomek_sku
    WHERE b.rodic_sku = ?
');
$stmt->execute(['WS0232']);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($children)) {
    echo "  ŽÁDNÍ POTOMCI!\n";
} else {
    foreach ($children as $child) {
        echo "  - " . $child['potomek_sku'] .
             " (koef: " . $child['koeficient'] .
             ", edge_unit: " . ($child['edge_unit'] ?? 'NULL') .
             ", jednotka: " . ($child['merna_jednotka'] ?? 'NULL') . ")\n";
    }
}
