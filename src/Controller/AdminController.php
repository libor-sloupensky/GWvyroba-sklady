<?php
namespace App\Controller;

final class AdminController
{
    public function history(): void
    {
        $this->requireSuperAdmin();

        $logFile = dirname(__DIR__, 2) . '/data/access_log.csv';
        $entries = [];

        if (is_file($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
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

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}
