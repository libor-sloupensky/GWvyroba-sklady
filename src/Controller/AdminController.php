<?php
namespace App\Controller;

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
            $this->render('admin_seed.php', ['title'=>'Admin – Seed admin účtu', 'error'=>'Zadejte e-mail.']);
            return;
        }
        $pdo = DB::pdo();
        $hash = password_hash('dokola', PASSWORD_DEFAULT);
        $sql = 'INSERT INTO users (email,role,active,password_hash) VALUES (:email,\'admin\',1,:hash)
                ON DUPLICATE KEY UPDATE role=VALUES(role), active=VALUES(active), password_hash=VALUES(password_hash)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email'=>$email, ':hash'=>$hash]);
        $this->render('admin_seed.php', ['title'=>'Admin – Seed admin účtu', 'message'=>'Admin účet vytvořen/aktualizován: '.$email]);
    }

    private function requireAdmin(): void
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        if (($u['role'] ?? 'user') !== 'admin') { http_response_code(403); echo 'Přístup jen pro admina.'; exit; }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}
