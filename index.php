<?php
// Kořenový index pro hostingy, které očekávají index v rootu
// Přesměruje/načte skutečný vstup v public/index.php

$public = __DIR__ . '/public/index.php';
if (is_file($public)) {
    require $public;
    exit;
}
http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Chybí public/index.php";

