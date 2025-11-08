<?php
namespace App\Support;

use PDO; use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        $cfg = include dirname(__DIR__,2) . '/config/config.php';
        // Support either full DSN or host/name/charset
        if (!empty($cfg['db']['dsn'] ?? '')) {
            $dsn = (string)$cfg['db']['dsn'];
            $user = (string)($cfg['db']['user'] ?? '');
            $pass = (string)($cfg['db']['pass'] ?? '');
        } else {
            $host = (string)($cfg['db']['host'] ?? '127.0.0.1');
            $name = (string)($cfg['db']['name'] ?? 'app');
            $charset = (string)($cfg['db']['charset'] ?? 'utf8mb4');
            $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;
            $user = (string)($cfg['db']['user'] ?? 'root');
            $pass = (string)($cfg['db']['pass'] ?? '');
        }
        $opt = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
        try { self::$pdo = new PDO($dsn, $user, $pass, $opt); } catch (PDOException $e) { http_response_code(500); echo 'DB connect error'; exit; }
        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci");
        return self::$pdo;
    }
}
