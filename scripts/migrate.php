<?php
declare(strict_types=1);
// Simple migration runner: applies db/schema.sql

$cfg = include __DIR__ . '/../config/config.php';
$dsn = $cfg['db']['dsn']; $user = $cfg['db']['user']; $pass = $cfg['db']['pass'];
try { $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) { echo "DB připojení selhalo: ".$e->getMessage()."\n"; exit(1);} 
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci");
$sql = file_get_contents(__DIR__ . '/../db/schema.sql');
if ($sql === false) { echo "Nelze načíst db/schema.sql\n"; exit(1);} 
try { $pdo->exec($sql); echo "Migrace OK\n"; } catch (Throwable $e) { echo "Migrace selhala: ".$e->getMessage()."\n"; exit(2);} 

