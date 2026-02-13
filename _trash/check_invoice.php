<?php
require 'config/database.php';

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT eshop_source, cislo_dokladu, duzp, typ_dokladu FROM doklady_eshop WHERE cislo_dokladu = ?');
$stmt->execute(['2026900018']);

echo "Doklad 2026900018 v databázi:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  eshop_source: " . $row['eshop_source'] . "\n";
    echo "  cislo_dokladu: " . $row['cislo_dokladu'] . "\n";
    echo "  duzp: " . $row['duzp'] . "\n";
    echo "  typ_dokladu: " . $row['typ_dokladu'] . "\n";
    echo "  ---\n";
}
