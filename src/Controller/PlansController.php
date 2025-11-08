<?php
namespace App\Controller;

final class PlansController
{
    public function index(): void
    {
        $this->requireAuth();
        // Simple placeholder – reads from docs/PLANS.json if exists
        $plansFile = dirname(__DIR__,2) . '/docs/PLANS.json';
        $plans = [];
        if (is_file($plansFile)) { $plans = json_decode((string)file_get_contents($plansFile), true) ?: []; }
        $this->render('plans.php', ['title'=>'Plány','plans'=>$plans]);
    }
    private function requireAuth(): void { if (!isset($_SESSION['user'])) { header('Location: /login'); exit; } }
    private function render(string $view, array $vars=[]): void { extract($vars); require __DIR__ . '/../../views/_layout.php'; }
}

