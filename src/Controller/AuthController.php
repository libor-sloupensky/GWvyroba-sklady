<?php
namespace App\Controller;

use App\Support\DB;

final class AuthController
{
    public function loginForm(): void
    {
        // Login dočasně vypnutý – uživatel je automaticky admin
        $this->redirect('/');
    }

    public function loginSubmit(): void
    {
        $this->redirect('/');
    }

    public function googleStart(): void
    {
        $this->redirect('/');
    }

    public function googleCallback(): void
    {
        $this->redirect('/');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->redirect('/');
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302); exit;
    }
}
