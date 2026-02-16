<?php
/**
 * Jednorázový setup - uloží Google OAuth credentials do config/config.local.php.
 * Po úspěšném nastavení tento soubor SMAŽTE (nebo ho smažeme přes git).
 */
$localFile = __DIR__ . '/config/config.local.php';

// Pokud už soubor existuje, jen to oznámíme
if (file_exists($localFile)) {
    echo '<h2 style="color:green;">config.local.php už existuje. Credentials jsou uloženy.</h2>';
    echo '<p>Tento soubor (setup-credentials.php) můžete smazat.</p>';
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');

    if ($clientId === '' || $clientSecret === '') {
        $error = 'Vyplňte obě pole.';
    } else {
        $data = [
            'google_client_id' => $clientId,
            'google_client_secret' => $clientSecret,
        ];
        $content = "<?php\nreturn " . var_export($data, true) . ";\n";
        if (@file_put_contents($localFile, $content)) {
            $success = true;
        } else {
            $error = 'Nepodařilo se zapsat soubor. Zkontrolujte oprávnění složky config/.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"><title>Setup Google OAuth</title></head>
<body style="font-family:sans-serif;max-width:500px;margin:3rem auto;padding:0 1rem;">
<?php if ($success): ?>
    <h2 style="color:green;">Credentials uloženy do config/config.local.php</h2>
    <p>Google přihlášení by nyní mělo fungovat. <a href="/login">Vyzkoušejte</a>.</p>
    <p style="color:#999;">Nezapomeňte smazat soubor <code>setup-credentials.php</code>.</p>
<?php else: ?>
    <h2>Nastavení Google OAuth</h2>
    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST" style="display:flex;flex-direction:column;gap:0.75rem;">
        <label>Client ID:
            <input type="text" name="client_id" style="width:100%;padding:0.4rem;" placeholder="...apps.googleusercontent.com">
        </label>
        <label>Client Secret:
            <input type="text" name="client_secret" style="width:100%;padding:0.4rem;" placeholder="GOCSPX-...">
        </label>
        <button type="submit" style="padding:0.5rem 1rem;background:#1976d2;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:1rem;">
            Uložit
        </button>
    </form>
<?php endif; ?>
</body>
</html>
