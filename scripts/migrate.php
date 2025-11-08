<?php
declare(strict_types=1);
// Simple migration runner: applies db/schema.sql and incremental alters

$cfg = include __DIR__ . '/../config/config.php';
if (!empty($cfg['db']['dsn'] ?? '')) {
    $dsn = (string)$cfg['db']['dsn'];
    $user = (string)($cfg['db']['user'] ?? '');
    $pass = (string)($cfg['db']['pass'] ?? '');
} else {
    $host = (string)($cfg['db']['host'] ?? '127.0.0.1');
    $name = (string)($cfg['db']['name'] ?? 'app');
    $charset = (string)($cfg['db']['charset'] ?? 'utf8mb4');
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;
    $user = (string)($cfg['db']['user'] ?? 'root');
    $pass = (string)($cfg['db']['pass'] ?? '');
}
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    echo "DB připojení selhalo: " . $e->getMessage() . "\n";
    exit(1);
}
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci");
$sql = file_get_contents(__DIR__ . '/../db/schema.sql');
if ($sql === false) {
    echo "Nelze načíst db/schema.sql\n";
    exit(1);
}
try {
    $pdo->exec($sql);
} catch (Throwable $e) {
    echo "Migrace selhala: " . $e->getMessage() . "\n";
    exit(2);
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function addColumn(PDO $pdo, string $table, string $definition): void {
    $pdo->exec("ALTER TABLE `{$table}` ADD {$definition}");
}

try {
    if (!columnExists($pdo, 'produkty', 'znacka_id')) {
        addColumn($pdo, 'produkty', 'COLUMN `znacka_id` INT NULL AFTER `aktivni`');
        $pdo->exec('ALTER TABLE `produkty` ADD KEY idx_produkty_znacka (znacka_id)');
    }
    if (!columnExists($pdo, 'produkty', 'skupina_id')) {
        addColumn($pdo, 'produkty', 'COLUMN `skupina_id` INT NULL AFTER `znacka_id`');
        $pdo->exec('ALTER TABLE `produkty` ADD KEY idx_produkty_skupina (skupina_id)');
    }
    if (!columnExists($pdo, 'produkty', 'poznamka')) {
        addColumn($pdo, 'produkty', 'COLUMN `poznamka` VARCHAR(1024) NULL AFTER `skupina_id`');
    }
    try {
        $pdo->exec('ALTER TABLE produkty ADD CONSTRAINT fk_produkty_znacka FOREIGN KEY (znacka_id) REFERENCES produkty_znacky(id) ON DELETE SET NULL');
    } catch (Throwable $e) {}
    try {
        $pdo->exec('ALTER TABLE produkty ADD CONSTRAINT fk_produkty_skupina FOREIGN KEY (skupina_id) REFERENCES produkty_skupiny(id) ON DELETE SET NULL');
    } catch (Throwable $e) {}
    echo "Migrace OK\n";
} catch (Throwable $e) {
    echo "Migrace selhala: " . $e->getMessage() . "\n";
    exit(2);
}
