<?php
$title = $title ?? 'App';
$config = include __DIR__ . '/../config/config.php';
$version = $config['app']['version'] ?? 'dev';

function app_footer_info(): array {
    $root = dirname(__DIR__, 1);
    $max = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        if (strpos($path, '/vendor/') !== false) {
            continue;
        }
        $mtime = @filemtime($path);
        if ($mtime && $mtime > $max) {
            $max = $mtime;
        }
    }
    if ($max <= 0) {
        $max = time();
    }
    return ['mtime' => $max];
}

$fi = app_footer_info();
$currentUser = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#f6f7f9; }
    header { background:#263238; color:#fff; padding:10px 16px; }
    nav { display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
    nav a { color:#fff; text-decoration:none; }
    .container { max-width: 1200px; margin: 1rem auto; background:#fff; border:1px solid #e5e5e5; border-radius: 12px; padding: 14px 16px; }
    .footer { position: fixed; right: 1rem; bottom: 1rem; background:#fff; border: 1px solid #ddd; border-radius: 8px; padding: 8px 10px; font-size: 13px; color:#333; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .upload-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-left:6px; background:#cfd8dc; box-shadow:0 0 0 1px #ccc inset; vertical-align:middle; }
    .upload-dot[data-state="uploading"] { background:#d50000; box-shadow:0 0 0 1px #b71c1c inset; }
    .upload-dot[data-state="done"] { background:#2e7d32; box-shadow:0 0 0 1px #1b5e20 inset; }
    .notice { padding:8px 10px; border:1px solid #ddd; background:#f9f9f9; border-radius:8px; }
    table { border-collapse: collapse; width: 100%; }
    th,td { padding: 6px 8px; border-bottom:1px solid #eee; text-align:left; }
    th { background:#f1f5f9; }
    .nav-user { margin-left:auto; color:#cfd8dc; font-size:13px; display:flex; gap:6px; align-items:center; }
    .nav-user a { color:#fff; text-decoration:underline; }
    .print-hide {}
    @media print {
      header, .footer, .print-hide { display:none !important; }
      body { background:#fff; }
      .container { border:none; border-radius:0; margin:0; padding:0.5rem; }
    }
  </style>
</head>
<body>
  <header class="print-hide">
    <nav>
      <a href="/">Domů</a>
      <a href="/import" title="Nahrát XML a spustit import">Import</a>
      <a href="/products" title="Kmenová karta produktů, CSV import/export">Produkty</a>
      <a href="/inventory" title="Záznam inventury a korekcí">Inventura</a>
      <a href="/reservations" title="Rezervace hotových produktů">Rezervace</a>
      <a href="/production/plans" title="Návrhy výroby a zápis vyrobeného">Výroba</a>
      <a href="/analytics/revenue" title="Analýza">Analýza</a>
      <a href="/settings" title="Řady, ignorované vzory, globální nastavení">Nastavení</a>
      <span class="nav-user">
        <?php if ($currentUser): ?>
          <?= htmlspecialchars((string)$currentUser['email'], ENT_QUOTES, 'UTF-8') ?>
          (<?= htmlspecialchars((string)$currentUser['role'], ENT_QUOTES, 'UTF-8') ?>)
          • <a href="/logout">Odhlásit</a>
        <?php else: ?>
          Nepřihlášen • <a href="/login">Přihlásit</a>
        <?php endif; ?>
      </span>
    </nav>
  </header>
  <main class="container">
    <?php require __DIR__ . '/' . basename($view ?? 'home.php'); ?>
  </main>
  <div class="footer print-hide">
    <div><strong>Poslední úprava:</strong> <?= date('Y-m-d H:i:s', (int)$fi['mtime']) ?> <span class="upload-dot" id="upload-status-dot" title="Stav nahrávání"></span></div>
    <div><strong>Verze/Deploy:</strong> <?= htmlspecialchars((string)$version, ENT_QUOTES, 'UTF-8') ?></div>
    <script>
    (function () {
      const dot = document.getElementById('upload-status-dot');
      if (!dot) return;
      const setState = (state) => {
        dot.dataset.state = state;
        dot.title = state === 'uploading' ? 'Nahravani probiha' : 'Nahravani dokonceno';
      };
      setState('idle');
      window.addEventListener('load', () => setState('done'));
      document.querySelectorAll('form').forEach((form) => {
        if (!form.querySelector('input[type="file"]')) return;
        form.addEventListener('submit', () => setState('uploading'));
      });
      document.addEventListener('upload:started', () => setState('uploading'));
      document.addEventListener('upload:finished', () => setState('done'));
    }());
  </script>
</div>
</body>
</html>

