<?php
namespace App\Controller;

use App\Support\DB;
use PDO;

final class AuthController
{
    public function loginForm(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $error = $_SESSION['auth_error'] ?? null;
        $info = $_SESSION['auth_message'] ?? null;
        unset($_SESSION['auth_error'], $_SESSION['auth_message']);
        $googleReady = $this->hasGoogleConfig();
        $this->render('auth_login.php', [
            'title' => 'Přihlášení',
            'error' => $error,
            'info' => $info,
            'googleReady' => $googleReady,
            'localEnabled' => !$googleReady,
        ]);
    }

    public function loginSubmit(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        if ($this->hasGoogleConfig()) {
            $this->flashError('Použijte přihlášení přes Google.');
            $this->redirect('/login');
            return;
        }
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === 'admin' && $password === 'dokola') {
            $_SESSION['user'] = [
                'id' => 0,
                'email' => 'admin@local',
                'role' => 'superadmin',
                'name' => 'Lokální administrátor',
            ];
            session_regenerate_id(true);
            $target = $_SESSION['redirect_after_login'] ?? '/';
            unset($_SESSION['redirect_after_login']);
            $this->redirect($target ?: '/');
            return;
        }
        $this->flashError('Neplatné přihlašovací údaje.');
        $this->redirect('/login');
    }

    public function googleStart(): void
    {
        if (!$this->hasGoogleConfig()) {
            $this->flashError('Integrace Google Workspace není nakonfigurovaná.');
            $this->redirect('/login');
            return;
        }
        $config = $this->googleConfig();
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->callbackUrl($config),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'select_account',
            'state' => $state,
        ];
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        $this->redirect($authUrl);
    }

    public function googleCallback(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        if (!$this->hasGoogleConfig()) {
            $this->flashError('Integrace Google Workspace není nakonfigurovaná.');
            $this->redirect('/login');
            return;
        }
        $expectedState = $_SESSION['google_oauth_state'] ?? null;
        unset($_SESSION['google_oauth_state']);
        $state = $_GET['state'] ?? null;
        if (!$state || !$expectedState || !hash_equals($expectedState, $state)) {
            $this->flashError('Neplatný stav relace při přihlášení.');
            $this->redirect('/login');
            return;
        }
        if (!empty($_GET['error'])) {
            $this->flashError('Přihlášení bylo zrušeno.');
            $this->redirect('/login');
            return;
        }
        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            $this->flashError('Chybí autorizační kód z Google.');
            $this->redirect('/login');
            return;
        }
        try {
            $config = $this->googleConfig();
            $token = $this->exchangeCodeForToken($code, $config, $this->callbackUrl($config));
            $profile = $this->fetchUserInfo($token['access_token'] ?? '');
        } catch (\Throwable $e) {
            $this->flashError('Nelze ověřit účet přes Google: ' . $e->getMessage());
            $this->redirect('/login');
            return;
        }
        $email = strtolower((string)($profile['email'] ?? ''));
        if ($email === '' || empty($profile['email_verified'])) {
            $this->flashError('Google účet nemá ověřený e-mail.');
            $this->redirect('/login');
            return;
        }
        $authCfg = $this->authConfig();
        $allowedDomain = trim((string)($authCfg['allowed_domain'] ?? ''));
        if ($allowedDomain !== '' && !str_ends_with($email, '@' . $allowedDomain)) {
            $this->flashError('Tento e-mail není povolen pro přihlášení.');
            $this->redirect('/login');
            return;
        }
        $user = $this->resolveUser($email);
        if (!$user || !(int)$user['active']) {
            $this->flashError('Nemáte oprávnění ke vstupu. Kontaktujte superadministrátora.');
            $this->redirect('/login');
            return;
        }
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'email' => $email,
            'role' => (string)$user['role'],
            'name' => (string)($profile['name'] ?? ''),
        ];
        session_regenerate_id(true);
        $target = $_SESSION['redirect_after_login'] ?? '/';
        unset($_SESSION['redirect_after_login']);
        $this->redirect($target ?: '/');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['auth_message'] = 'Byli jste odhlášeni.';
        $this->redirect('/login');
    }

    private function resolveUser(string $email): ?array
    {
        $superadmins = $this->authConfig()['superadmins'] ?? [];

        $user = $this->findUser($email);
        if ($user) {
            // Pokud je na whitelistu superadminů, povýšíme roli a aktivujeme.
            if (in_array($email, $superadmins, true) && $user['role'] !== 'superadmin') {
                $stmt = DB::pdo()->prepare('UPDATE users SET role=?, active=1 WHERE id=?');
                $stmt->execute(['superadmin', (int)$user['id']]);
                $user['role'] = 'superadmin';
                $user['active'] = 1;
            }
            return $user;
        }

        if (in_array($email, $superadmins, true)) {
            // Pokud ještě neexistuje, vložíme jako superadmin.
            $stmt = DB::pdo()->prepare('INSERT INTO users (email, role, active) VALUES (?, ?, 1)');
            $stmt->execute([$email, 'superadmin']);
            return $this->findUser($email);
        }

        return null;
    }

    private function findUser(string $email): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT id, email, role, active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function exchangeCodeForToken(string $code, array $config, string $redirectUri): array
    {
        $payload = [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $result = curl_exec($ch);
        if ($result === false) {
            throw new \RuntimeException('Chyba komunikace s Google token endpointem.');
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 400) {
            throw new \RuntimeException('Google token endpoint vrátil chybu.');
        }
        $data = json_decode($result, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Neplatná odpověď token endpointu.');
        }
        return $data;
    }

    private function fetchUserInfo(string $accessToken): array
    {
        if ($accessToken === '') {
            throw new \RuntimeException('Chybí access token.');
        }
        $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        $result = curl_exec($ch);
        if ($result === false) {
            throw new \RuntimeException('Chyba komunikace se službou Google UserInfo.');
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 400) {
            throw new \RuntimeException('Google UserInfo vrátil chybu.');
        }
        $data = json_decode($result, true);
        if (!is_array($data) || empty($data['email'])) {
            throw new \RuntimeException('Neplatná odpověď služby UserInfo.');
        }
        return $data;
    }

    private function googleConfig(): array
    {
        $cfg = $this->appConfig();
        return $cfg['google'] ?? ['client_id' => '', 'client_secret' => '', 'redirect_uri' => ''];
    }

    private function authConfig(): array
    {
        $cfg = $this->appConfig();
        return $cfg['auth'] ?? ['superadmins' => []];
    }

    private function hasGoogleConfig(): bool
    {
        $cfg = $this->googleConfig();
        return !empty($cfg['client_id']) && !empty($cfg['client_secret']);
    }

    private function callbackUrl(array $config): string
    {
        if (!empty($config['redirect_uri'])) {
            return $config['redirect_uri'];
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/auth/google/callback';
    }

    private function flashError(string $message): void
    {
        $_SESSION['auth_error'] = $message;
    }

    private function isLoggedIn(): bool
    {
        return isset($_SESSION['user']);
    }

    private function appConfig(): array
    {
        static $cfg;
        if ($cfg === null) {
            $cfg = include __DIR__ . '/../../config/config.php';
        }
        return $cfg;
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }
}
