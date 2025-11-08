<?php
declare(strict_types=1);
// Seed admin allowlist user and set password 'dokola'

$cfg = include __DIR__ . '/../config/config.php';
$dsn = $cfg['db']['dsn']; $user = $cfg['db']['user']; $pass = $cfg['db']['pass'];
try { $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) { echo "DB připojení selhalo: ".$e->getMessage()."\n"; exit(1);} 
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci");
$email = $argv[1] ?? 'admin@local';
$hash  = password_hash('dokola', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (email,role,active,password_hash) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role), active=VALUES(active), password_hash=VALUES(password_hash)');
$stmt->execute([$email,'admin',1,$hash]);
echo "Admin seed OK: $email\n";

