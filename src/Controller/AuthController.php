<?php
namespace App\Controller;

use App\Support\DB;

final class AuthController
{
    public function loginForm(): void
    {
        $this->render('auth_login.php', ['title'=>'Přihlášení']);
    }

    public function loginSubmit(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if ($email === '' || $pass === '') { $this->redirect('/login'); return; }
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT id,email,role,active,password_hash FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || (int)$u['active'] !== 1) { $this->redirect('/login'); return; }
        $ok = false;
        if (!empty($u['password_hash'])) {
            $ok = password_verify($pass, (string)$u['password_hash']);
        } else {
            // dočasný fallback: admin/dokola
            $ok = ($email === 'admin' || $email === 'admin@local') && $pass === 'dokola';
        }
        if ($ok) {
            $_SESSION['user'] = ['id'=>$u['id'], 'email'=>$u['email'], 'role'=>$u['role']];
            $this->redirect('/');
            return;
        }
        $this->redirect('/login');
    }

    public function googleStart(): void
    {
        http_response_code(501); echo 'Google OAuth stub (implementovat s klientskými údaji)';
    }

    public function googleCallback(): void
    {
        http_response_code(501); echo 'Google OAuth callback stub';
    }

    public function logout(): void
    {
        $_SESSION = []; session_destroy();
        $this->redirect('/login');
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

