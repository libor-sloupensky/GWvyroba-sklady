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
        $dsn = $cfg['db']['dsn'] ?? '';
        $user = $cfg['db']['user'] ?? '';
        $pass = $cfg['db']['pass'] ?? '';
        $opt = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
        try { self::$pdo = new PDO($dsn, $user, $pass, $opt); } catch (PDOException $e) { http_response_code(500); echo 'DB connect error'; exit; }
        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci");
        return self::$pdo;
    }
}

