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
    'version' => '1.1.0',
  ],
  'google' => [
    // Vyplňte přes prostředí (nastavení hostingu) nebo lokálně mimo git.
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'https://gworm.wormup.com/auth/google/callback',
  ],
  'openai' => [
    'api_key' => getenv('OPENAI_API_KEY') ?: '',
  ],
  'auth' => [
    'superadmins' => ['sloupensky@grig.cz'],
    'allowed_domain' => '', // pokud chcete omezit na doménu, nastavte např. wormup.com
  ],
  'encryption_key' => getenv('ENCRYPTION_KEY') ?: 'gw0rm-s3cr3t-k3y-ch4ng3-m3',
  'cron_token' => getenv('CRON_TOKEN') ?: 'gworm-auto-import-2025',
];
