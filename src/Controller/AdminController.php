<?php
namespace App\Controller;

use App\Service\MovementRebuildService;
use App\Support\DB;

final class AdminController
{
    public function migrateForm(): void
    {
        $this->requireAdmin();
        $this->render('admin_migrate.php', ['title'=>'Admin – Migrace DB']);
    }

    public function migrateRun(): void
    {
        $this->requireAdmin();
        $pdo = DB::pdo();
        $sqlFile = dirname(__DIR__, 2) . '/db/schema.sql';
        if (!is_file($sqlFile)) { $this->render('admin_migrate.php', ['title'=>'Admin – Migrace DB', 'error'=>'Soubor db/schema.sql nenalezen.']); return; }
        $sql = file_get_contents($sqlFile);
        if ($sql === false) { $this->render('admin_migrate.php', ['title'=>'Admin – Migrace DB', 'error'=>'Nelze načíst obsah db/schema.sql.']); return; }
        try {
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci");
            $pdo->exec($sql);
            $this->ensureProductSchema($pdo);
            $this->ensureReservationsSchema($pdo);
            $this->render('admin_migrate.php', ['title'=>'Admin – Migrace DB', 'message'=>'Migrace proběhla úspěšně.']);
        } catch (\Throwable $e) {
            $this->render('admin_migrate.php', ['title'=>'Admin – Migrace DB', 'error'=>'Migrace selhala: '.$e->getMessage()]);
        }
    }

    public function seedForm(): void
    {
        $this->requireAdmin();
        $this->render('admin_seed.php', ['title'=>'Admin – Seed admin účtu']);
    }

    public function seedRun(): void
    {
        $this->requireAdmin();
        $email = trim((string)($_POST['email'] ?? 'admin@local'));
        if ($email === '') {
            $this->render('admin_seed.php', ['title'=>'Admin - Seed admin účtu', 'error'=>'Zadejte e-mail.']);
            return;
        }
        $pdo = DB::pdo();
        $hash = password_hash('dokola', PASSWORD_DEFAULT);
        $sql = 'INSERT INTO users (email,role,active,password_hash) VALUES (:email,\'admin\',1,:hash)
                ON DUPLICATE KEY UPDATE role=VALUES(role), active=VALUES(active), password_hash=VALUES(password_hash)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email'=>$email, ':hash'=>$hash]);
        $this->render('admin_seed.php', ['title'=>'Admin - Seed admin účtu', 'message'=>'Admin účet vytvořen/aktualizován: '.$email]);
    }

    public function rebuildMovements(): void
    {
        $this->requireAdmin();
        header('Content-Type: text/plain; charset=utf-8');
        try {
            $result = MovementRebuildService::rebuild();
            echo sprintf(
                "Rebuild dokončen.\nDoklady: %d\nPoložky: %d\nPohyby: %d\nChybějící produkty: %d\n",
                $result['documents'],
                $result['items'],
                $result['movements'],
                $result['missing']
            );
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Chyba: ' . $e->getMessage();
        }
    }

    public function history(): void
    {
        $this->requireSuperAdmin();

        $logFile = dirname(__DIR__, 2) . '/data/access_log.csv';
        $entries = [];

        if (is_file($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                // Obrátit pořadí - nejnovější první
                $lines = array_reverse($lines);
                foreach ($lines as $line) {
                    $parts = explode(',', $line, 2);
                    if (count($parts) === 2) {
                        $entries[] = [
                            'datetime' => $parts[0],
                            'email' => $parts[1],
                        ];
                    }
                }
            }
        }

        $this->render('admin_history.php', [
            'title' => 'Historie přihlášení',
            'entries' => $entries,
        ]);
    }

    private function requireSuperAdmin(): void
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        if (($u['role'] ?? 'user') !== 'superadmin') {
            http_response_code(403);
            $this->render('forbidden.php', [
                'title' => 'Přístup odepřen',
                'message' => 'Přístup jen pro superadministrátory.',
            ]);
            exit;
        }
    }

    private function requireAdmin(): void
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        $role = $u['role'] ?? 'user';
        if (!in_array($role, ['admin','superadmin'], true)) {
            http_response_code(403);
            $this->render('forbidden.php', [
                'title' => 'Přístup odepřen',
                'message' => 'Přístup jen pro administrátory.',
            ]);
            exit;
        }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function ensureProductSchema(\PDO $pdo): void
    {
        $this->addColumnIfMissing($pdo, 'produkty', 'alt_sku VARCHAR(128) NULL', 'sku');
        try { $pdo->exec('ALTER TABLE `produkty` ADD UNIQUE KEY uniq_produkty_alt_sku (alt_sku)'); } catch (\Throwable $e) {}
        $this->addColumnIfMissing($pdo, 'produkty', 'znacka_id INT NULL', 'alt_sku');
        $this->addColumnIfMissing($pdo, 'produkty', 'skupina_id INT NULL', 'znacka_id');
        $this->addColumnIfMissing($pdo, 'produkty', 'poznamka VARCHAR(1024) NULL', 'skupina_id');

        try { $pdo->exec('ALTER TABLE `produkty` ADD KEY idx_produkty_znacka (znacka_id)'); } catch (\Throwable $e) {}
        try { $pdo->exec('ALTER TABLE `produkty` ADD KEY idx_produkty_skupina (skupina_id)'); } catch (\Throwable $e) {}
        try { $pdo->exec('ALTER TABLE `produkty` ADD CONSTRAINT fk_produkty_znacka FOREIGN KEY (znacka_id) REFERENCES produkty_znacky(id) ON DELETE SET NULL'); } catch (\Throwable $e) {}
        try { $pdo->exec('ALTER TABLE `produkty` ADD CONSTRAINT fk_produkty_skupina FOREIGN KEY (skupina_id) REFERENCES produkty_skupiny(id) ON DELETE SET NULL'); } catch (\Throwable $e) {}
    }
    private function ensureReservationsSchema(\PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'rezervace', 'typ')) {
            $pdo->exec("ALTER TABLE `rezervace` ADD COLUMN `typ` ENUM('produkt','obal','etiketa','surovina','baleni','karton') NOT NULL DEFAULT 'produkt' AFTER `sku`");
            try { $pdo->exec('ALTER TABLE `rezervace` ADD KEY idx_rez_typ (typ)'); } catch (\Throwable $e) {}
        }
    }



    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function addColumnIfMissing(\PDO $pdo, string $table, string $definition, string $afterColumn): void
    {
        preg_match('/`?([a-zA-Z0-9_]+)`?/', $definition, $matches);
        $column = $matches[1] ?? '';
        if ($column === '' || $this->columnExists($pdo, $table, $column)) {
            return;
        }
        $pdo->exec("ALTER TABLE `{$table}` ADD {$definition} AFTER `{$afterColumn}`");
    }
}
