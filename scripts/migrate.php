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

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch(PDO::FETCH_NUM);
}

try {
    if (!columnExists($pdo, 'produkty', 'alt_sku')) {
        addColumn($pdo, 'produkty', "COLUMN `alt_sku` VARCHAR(128) NULL AFTER `sku`");
        try { $pdo->exec('ALTER TABLE `produkty` ADD UNIQUE KEY uniq_produkty_alt_sku (alt_sku)'); } catch (Throwable $e) {}
    }
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
    if (!columnExists($pdo, 'produkty', 'nast_zasob')) {
        addColumn($pdo, 'produkty', "COLUMN `nast_zasob` ENUM('auto','manual') NOT NULL DEFAULT 'auto' AFTER `min_zasoba`");
    }
    try {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user'");
    } catch (Throwable $e) {}
    $superEmail = 'sloupensky@grig.cz';
    $checkSuper = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $checkSuper->execute([$superEmail]);
    $superRow = $checkSuper->fetch(PDO::FETCH_ASSOC);
    if ($superRow) {
        if ($superRow['role'] !== 'superadmin') {
            $upd = $pdo->prepare('UPDATE users SET role = ?, active = 1 WHERE id = ?');
            $upd->execute(['superadmin', (int)$superRow['id']]);
        }
    } else {
        $insertSuper = $pdo->prepare('INSERT INTO users (email, role, active) VALUES (?, ?, 1)');
        $insertSuper->execute([$superEmail, 'superadmin']);
    }
    if (!tableExists($pdo, 'ai_prompts')) {
        $pdo->exec("CREATE TABLE ai_prompts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            prompt TEXT NOT NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ai_prompts_user (user_id),
            KEY idx_ai_prompts_public (is_public, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
    }
    try {
        $pdo->exec('ALTER TABLE produkty ADD CONSTRAINT fk_produkty_znacka FOREIGN KEY (znacka_id) REFERENCES produkty_znacky(id) ON DELETE SET NULL');
    } catch (Throwable $e) {}
    try {
        $pdo->exec('ALTER TABLE produkty ADD CONSTRAINT fk_produkty_skupina FOREIGN KEY (skupina_id) REFERENCES produkty_skupiny(id) ON DELETE SET NULL');
    } catch (Throwable $e) {}
    if (!columnExists($pdo, 'nastaveni_global', 'spotreba_prumer_dni')) {
        addColumn($pdo, 'nastaveni_global', 'COLUMN `spotreba_prumer_dni` INT NOT NULL DEFAULT 90 AFTER `okno_pro_prumer_dni`');
    }
    if (!columnExists($pdo, 'nastaveni_global', 'zasoba_cil_dni')) {
        addColumn($pdo, 'nastaveni_global', 'COLUMN `zasoba_cil_dni` INT NOT NULL DEFAULT 30 AFTER `spotreba_prumer_dni`');
    }
    if (!columnExists($pdo, 'rezervace', 'typ')) {
        addColumn($pdo, 'rezervace', "COLUMN `typ` ENUM('produkt','obal','etiketa','surovina','baleni','karton') NOT NULL DEFAULT 'produkt' AFTER `sku`");
        try { $pdo->exec('ALTER TABLE `rezervace` ADD KEY idx_rez_typ (typ)'); } catch (Throwable $e) {}
    }
    echo "Migrace OK\n";
} catch (Throwable $e) {
    echo "Migrace selhala: " . $e->getMessage() . "\n";
    exit(2);
}
