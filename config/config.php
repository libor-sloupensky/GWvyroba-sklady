<?php
// UTF-8; konfigurace DB podle původního nastavení

// Lokální soubor s credentials (gitignored, přežije restarty serveru)
$local = [];
$localFile = __DIR__ . '/config.local.php';
if (file_exists($localFile)) {
    $local = (array)(include $localFile);
} else {
    // Auto-uložení: pokud env vars existují, uložíme je na disk ať přežijí restart
    $envClientId = getenv('GOOGLE_CLIENT_ID');
    $envSecret   = getenv('GOOGLE_CLIENT_SECRET');
    if ($envClientId && $envSecret) {
        $data = [
            'google_client_id'     => $envClientId,
            'google_client_secret' => $envSecret,
        ];
        $envRedirect = getenv('GOOGLE_REDIRECT_URI');
        if ($envRedirect) $data['google_redirect_uri'] = $envRedirect;
        $envOpenai = getenv('OPENAI_API_KEY');
        if ($envOpenai) $data['openai_api_key'] = $envOpenai;
        $envEnc = getenv('ENCRYPTION_KEY');
        if ($envEnc) $data['encryption_key'] = $envEnc;

        @file_put_contents($localFile, "<?php\nreturn " . var_export($data, true) . ";\n");
        $local = $data;
    }
}

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
    'client_id'     => $local['google_client_id']     ?? getenv('GOOGLE_CLIENT_ID') ?: '',
    'client_secret' => $local['google_client_secret'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'redirect_uri'  => $local['google_redirect_uri']  ?? getenv('GOOGLE_REDIRECT_URI') ?: 'https://gworm.wormup.com/auth/google/callback',
  ],
  'openai' => [
    'api_key' => $local['openai_api_key'] ?? getenv('OPENAI_API_KEY') ?: '',
  ],
  'auth' => [
    'superadmins' => ['sloupensky@grig.cz'],
    'allowed_domain' => '',
  ],
  'encryption_key' => $local['encryption_key'] ?? getenv('ENCRYPTION_KEY') ?: 'gw0rm-s3cr3t-k3y-ch4ng3-m3',
  'cron_token'     => $local['cron_token']     ?? getenv('CRON_TOKEN') ?: 'gworm-auto-import-2025',
];
