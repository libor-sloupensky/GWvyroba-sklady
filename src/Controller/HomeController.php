<?php
namespace App\Controller;

final class HomeController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->render('home.php', ['title'=>'Přehled']);
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['user'])) { header('Location: /login'); exit; }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}

