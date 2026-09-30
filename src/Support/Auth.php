<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Role a oprávnění na jednom místě.
 *
 *  - superadmin: vše včetně správy uživatelů a mazání produktů
 *  - admin:      běžný provoz – produkty, BOM, import, inventura, rezervace, doklady, nastavení
 *  - user:       ČTENÁŘ – jen prohlížení; jediné, co smí editovat, jsou vlastní dotazy v Analýze
 *
 * Controllery mají historicky vlastní requireAuth()/requireAdmin(); nové kontroly
 * a kontroly zápisu pro čtenáře jdou přes tuto třídu, aby se daly auditovat.
 */
final class Auth
{
    public const ROLE_LABELS = [
        'superadmin' => 'Superadmin',
        'admin' => 'Admin',
        'user' => 'Čtenář',
    ];

    /** @return array{id:int,email:string,role:string}|null */
    public static function user(): ?array
    {
        $u = $_SESSION['user'] ?? null;
        if (!is_array($u)) {
            return null;
        }
        return [
            'id' => (int)($u['id'] ?? 0),
            'email' => (string)($u['email'] ?? ''),
            'role' => (string)($u['role'] ?? 'user'),
        ];
    }

    public static function role(): string
    {
        return (string)($_SESSION['user']['role'] ?? 'user');
    }

    public static function isReader(): bool
    {
        return isset($_SESSION['user']) && self::role() === 'user';
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['user']) && in_array(self::role(), ['admin', 'superadmin'], true);
    }

    public static function isSuperadmin(): bool
    {
        return isset($_SESSION['user']) && self::role() === 'superadmin';
    }

    public static function label(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? $role;
    }

    public static function requireLogin(): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }

    /**
     * Zápis smí jen admin/superadmin. Čtenáři vrátí 403 – JSON pro AJAX, jinak stránku.
     */
    public static function requireAdmin(string $message = 'Tato akce je jen pro administrátory. Role čtenář může data pouze prohlížet.'): void
    {
        self::requireLogin();
        if (self::isAdmin()) {
            return;
        }
        http_response_code(403);
        $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $title = 'Přístup odepřen';
        $view = 'forbidden.php';
        require dirname(__DIR__, 2) . '/views/_layout.php';
        exit;
    }

    // ---------------------------------------------------- poslední návštěva

    private static bool $columnVerified = false;

    public static function ensureLastVisitColumn(): void
    {
        if (self::$columnVerified) {
            return;
        }
        $pdo = DB::pdo();
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_visit_at'");
        if (!$stmt->fetch()) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_visit_at DATETIME NULL AFTER created_at');
        }
        self::$columnVerified = true;
    }

    /** Zapíše čas návštěvy přihlášeného uživatele (volá se nejvýš jednou za hodinu z routeru). */
    public static function touchLastVisit(): void
    {
        $u = self::user();
        if (!$u || $u['id'] <= 0) {
            return; // nouzový admin@local v DB není
        }
        try {
            self::ensureLastVisitColumn();
            DB::pdo()->prepare('UPDATE users SET last_visit_at = NOW() WHERE id = ?')->execute([$u['id']]);
        } catch (\Throwable $e) {
            // návštěva se nezapsala – nesmí to shodit stránku
        }
    }
}
