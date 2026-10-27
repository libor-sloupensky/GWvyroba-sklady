<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\VanoceService;

/** Vánoce 2026 – sledování vánoční výroby proti plánu (jen čtení, pro všechny přihlášené). */
final class VanoceController
{
    public function index(): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        $report = (new VanoceService())->report();
        $this->render('vanoce.php', ['title' => VanoceService::SEZONA['nazev'], 'report' => $report]);
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}
