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

// Product types: table, seed, enums -> varchar, drop bom.druh_vazby
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_types (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(128) NOT NULL, is_nonstock TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_czech_ci");
    $defaults = [
        ['produkt','Produkt',0],
        ['obal','Obal',0],
        ['etiketa','Etiketa',0],
        ['surovina','Surovina',0],
        ['baleni','Baleni',1],
        ['karton','Karton',1],
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO product_types (code,name,is_nonstock) VALUES (?,?,?)');
    foreach ($defaults as $row) {
        $ins->execute($row);
    }
} catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE produkty MODIFY typ VARCHAR(32) NOT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE rezervace MODIFY typ VARCHAR(32) NOT NULL DEFAULT 'produkt'"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE produkty ADD CONSTRAINT fk_produkty_typ FOREIGN KEY (typ) REFERENCES product_types(code)"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE rezervace ADD CONSTRAINT fk_rez_typ FOREIGN KEY (typ) REFERENCES product_types(code)"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE bom DROP COLUMN druh_vazby"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE produkty ADD COLUMN dovyrobit DECIMAL(18,3) NOT NULL DEFAULT 0 AFTER skl_hodnota"); } catch (Throwable $e) {}

try {
    if (!columnExists($pdo, 'produkty', 'alt_sku')) {
        addColumn($pdo, 'produkty', "COLUMN `alt_sku` VARCHAR(128) NULL AFTER `sku`");
        try { $pdo->exec('ALTER TABLE `produkty` ADD UNIQUE KEY uniq_produkty_alt_sku (alt_sku)'); } catch (Throwable $e) {}
    }
    if (!columnExists($pdo, 'produkty', 'skl_hodnota')) {
        addColumn($pdo, 'produkty', "COLUMN `skl_hodnota` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `merna_jednotka`");
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
    // Auto-import credentials pro nastaveni_rady
    if (!columnExists($pdo, 'nastaveni_rady', 'admin_url')) {
        addColumn($pdo, 'nastaveni_rady', 'COLUMN `admin_url` VARCHAR(255) NULL AFTER `cislo_do`');
    }
    if (!columnExists($pdo, 'nastaveni_rady', 'admin_email')) {
        addColumn($pdo, 'nastaveni_rady', 'COLUMN `admin_email` VARCHAR(255) NULL AFTER `admin_url`');
    }
    if (!columnExists($pdo, 'nastaveni_rady', 'admin_password_enc')) {
        addColumn($pdo, 'nastaveni_rady', 'COLUMN `admin_password_enc` TEXT NULL AFTER `admin_email`');
    }
    // Stav ověření přihlášení (0=neověřeno, 1=ok, -1=selhalo)
    if (!columnExists($pdo, 'nastaveni_rady', 'login_ok')) {
        addColumn($pdo, 'nastaveni_rady', 'COLUMN `login_ok` TINYINT NOT NULL DEFAULT 0 AFTER `admin_password_enc`');
    }
    // Import history tabulka
    if (!tableExists($pdo, 'import_history')) {
        $pdo->exec("CREATE TABLE import_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            eshop_source VARCHAR(128) NOT NULL,
            mena VARCHAR(8) NOT NULL DEFAULT '',
            datum_od DATE NULL,
            datum_do DATE NULL,
            batch_id VARCHAR(32) NULL,
            doklady INT NOT NULL DEFAULT 0,
            polozky INT NOT NULL DEFAULT 0,
            status ENUM('ok','error') NOT NULL DEFAULT 'ok',
            message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ih_eshop (eshop_source),
            KEY idx_ih_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
    }
    // Rozšíření ENUM status o 'warning'
    try {
        $pdo->exec("ALTER TABLE import_history MODIFY COLUMN status ENUM('ok','error','warning') NOT NULL DEFAULT 'ok'");
    } catch (Throwable $e) {
        // Už může být rozšířeno
    }
    echo "Migrace OK\n";
} catch (Throwable $e) {
    echo "Migrace selhala: " . $e->getMessage() . "\n";
    exit(2);
}
