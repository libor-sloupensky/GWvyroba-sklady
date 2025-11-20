<?php
// UTF-8; konfigurace DB podle původního nastavení
return [
  'db' => [
    'host'    => 'db.dw164.webglobe.com',
    'name'    => 'db_gworm',
    'user'    => 'gworm',
    'pass'    => 'jwbmgzXr1',
    'charset' => 'utf8mb4',
  ],
  'app' => [
    'version' => '0.1.0',
  ],
  'google' => [
    // Vyplňte přes prostředí (nastavení hostingu) nebo lokálně mimo git.
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'https://gworm.wormup.com/auth/google/callback',
  ],
  'auth' => [
    'superadmins' => ['sloupensky@grig.cz'],
    'allowed_domain' => '', // pokud chcete omezit na doménu, nastavte např. wormup.com
  ],
];
