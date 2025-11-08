<?php
declare(strict_types=1);
// UTF-8 bootstrap & autoload

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Prague');
ini_set('default_charset', 'UTF-8');
header_register_callback(function(){
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
});

// error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// simple PSR-4 autoload for App namespace
spl_autoload_register(function(string $class){
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $rel = str_replace('App\\', 'src/', $class) . '.php';
    $rel = str_replace('\\', '/', $rel);
    $path = dirname(__DIR__) . '/' . $rel;
    if (is_file($path)) require_once $path;
});

// load config
$cfgPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($cfgPath)) {
    @mkdir(dirname($cfgPath), 0775, true);
    file_put_contents($cfgPath, "<?php\nreturn [\n  'db'=>[\n    'dsn'=>'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',\n    'user'=>'root',\n    'pass'=>'',\n  ],\n  'app'=>[\n    'version'=>'0.1.0',\n  ],\n];\n");
}

