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
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$navIsActive = static function (string $href) use ($currentPath): bool {
    if ($href === '/') {
        return $currentPath === '' || $currentPath === '/';
    }
    return strncmp($currentPath, $href, strlen($href)) === 0;
};
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?> | Gworm</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Vizuální identita – tokeny převzaté z Kalkulia / Projektovny (viz CLAUDE.md, sekce Vzhled) */
    :root {
      --c-primary: #dd5500;            /* oranžová na tmavém podkladu + dekorace, linky, ikony */
      --c-primary-text: #c14a00;       /* oranžová pro text a odkazy na světlém (kontrast 4,9:1) */
      --c-primary-akcent: #c14a00;     /* plocha tlačítek s bílým textem */
      --c-primary-akcent-hover: #aa4400;
      --c-primary-tint: #fbeee6;       /* jemný oranžový podklad (badge, aktivní řádek) */
      --c-bg: #ffffff;
      --c-surface: #f6f5f2;            /* podklad stránky – teplá, ne modrošedá */
      --c-surface-2: #faf9f7;          /* hlavičky tabulek, panely */
      --c-text: #111111;
      --c-text-secondary: #5f5e5a;
      --c-text-muted: #8a8985;
      --c-border: #e5e5e5;
      --c-border-strong: #d6d4cf;
      --c-danger: #c62828;
      --c-ok: #2e7d32;
      --radius-s: 4px; --radius-m: 8px; --radius-l: 12px;
      --shadow-s: 0 4px 12px rgba(0,0,0,0.10), 0 1px 3px rgba(0,0,0,0.06);
    }
    body { margin:0; font-family: 'Nunito', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; font-weight:600; font-size:15px; color:var(--c-text); background:var(--c-surface); }
    h1, h2, h3 { font-weight:800; color:var(--c-text); letter-spacing:-0.01em; }
    h1 { font-size:1.6rem; margin:.2rem 0 1rem; }
    h2 { font-size:1.2rem; margin:1.4rem 0 .7rem; }
    a { color:var(--c-primary-text); text-decoration:underline; text-decoration-color:color-mix(in srgb, var(--c-primary-text) 35%, transparent); text-underline-offset:2px; }
    a:hover { color:var(--c-primary-akcent-hover); text-decoration-color:currentColor; }
    /* Hlavička: bílá, menu podtržené oranžovou linkou */
    header { background:var(--c-bg); color:var(--c-text); padding:0 16px; border-bottom:3px solid var(--c-primary); box-shadow:0 1px 0 var(--c-border); }
    nav { display:flex; flex-wrap:wrap; gap:2px; align-items:center; max-width:1200px; margin:0 auto; }
    nav .brand { display:inline-flex; align-items:center; gap:.4rem; font-weight:800; font-size:1.15rem; color:var(--c-text); text-decoration:none; padding:10px 14px 10px 0; margin-right:.4rem; border-bottom:3px solid transparent; }
    nav .brand::before { content:''; width:.7em; height:.7em; border-radius:2px; background:var(--c-primary); display:inline-block; }
    nav a:not(.brand) { display:inline-block; color:var(--c-text); text-decoration:none; font-weight:700; padding:12px 12px 9px; margin-bottom:-3px; border-bottom:3px solid transparent; border-radius:0; }
    nav a:not(.brand):hover { color:var(--c-primary-text); }
    nav a:not(.brand).active { color:var(--c-primary-text); border-bottom-color:var(--c-primary); }
    .container { max-width: 1200px; margin: 1rem auto; background:var(--c-bg); border:1px solid var(--c-border); border-radius: var(--radius-l); padding: 16px 18px; }
    .footer { position: fixed; right: 1rem; bottom: 1rem; background:var(--c-bg); border: 1px solid var(--c-border); border-radius: var(--radius-m); padding: 8px 10px; font-size: 12px; color:var(--c-text-secondary); box-shadow: var(--shadow-s); }
    .notice { padding:8px 10px; border:1px solid var(--c-border); background:var(--c-surface-2); border-radius:var(--radius-m); }
    table { border-collapse: collapse; width: 100%; }
    th,td { padding: 7px 8px; border-bottom:1px solid var(--c-border); text-align:left; }
    th { background:var(--c-surface-2); font-size:.8rem; font-weight:800; text-transform:uppercase; letter-spacing:.03em; color:var(--c-text-secondary); }
    tbody tr:hover > td { background:color-mix(in srgb, var(--c-primary) 4%, transparent); }
    .muted { color:var(--c-text-secondary); }
    /* Tlačítka a pole – oranžová jen pro akce, zbytek neutrální */
    button, input[type="submit"], .btn { font-family:inherit; font-weight:700; font-size:.92rem; border-radius:var(--radius-m); border:1px solid var(--c-border-strong); background:var(--c-bg); color:var(--c-text); padding:.42rem .85rem; cursor:pointer; line-height:1.2; }
    button:hover, input[type="submit"]:hover, .btn:hover { border-color:var(--c-primary-text); color:var(--c-primary-text); }
    button[type="submit"], .btn-primary { background:var(--c-primary-akcent); border-color:var(--c-primary-akcent); color:#fff; }
    button[type="submit"]:hover, .btn-primary:hover { background:var(--c-primary-akcent-hover); border-color:var(--c-primary-akcent-hover); color:#fff; }
    button:disabled, input[type="submit"]:disabled { opacity:.45; cursor:not-allowed; }
    input[type="text"], input[type="number"], input[type="email"], input[type="date"], input[type="datetime-local"], input[type="password"], input[type="search"], select, textarea { font-family:inherit; font-weight:600; font-size:.95rem; color:var(--c-text); border:1px solid var(--c-border-strong); border-radius:var(--radius-s); background:var(--c-bg); padding:.38rem .5rem; }
    input:focus, select:focus, textarea:focus, button:focus-visible, a:focus-visible { outline:2px solid color-mix(in srgb, var(--c-primary) 55%, transparent); outline-offset:1px; }
    .nav-user { margin-left:auto; color:var(--c-text-secondary); font-size:13px; display:flex; gap:6px; align-items:center; }
    .nav-user a { color:var(--c-primary-text); }
    /* Jednotný styl pro rozbalovací trojúhelníčky */
    .row-toggle {
      display:inline-block;
      width:1.2rem;
      text-align:center;
      font-size:1rem;
      color:#5f5e5a;
      cursor:pointer;
      user-select:none;
      transition: transform 0.15s ease;
    }
    .row-toggle:hover { color:#111111; }
    /* Ikony Lucide (App\Support\Lucide, helper ikona()) – dědí barvu textu, zarovnané na účaří */
    svg.ikona { display:inline-block; vertical-align:-0.15em; flex-shrink:0; }
    .ikona-btn { display:inline-flex; align-items:center; gap:.35rem; }
    .ikona-muted { color:#90a4ae; }
    .ikona-danger { color:#c62828; }
    @media print {
      header, .footer, .print-hide { display:none !important; }
      body { background:#fff; }
      .container { border:none; border-radius:0; margin:0; padding:0.5rem; }
    }
  </style>
  <script>
    // Ikony pro JavaScript ve view (stejná sada Lucide jako ikona() v PHP): LUCIDE.x, LUCIDE['chevron-down'] …
    window.LUCIDE = <?= json_encode(array_combine(
        ['x', 'check', 'chevron-right', 'chevron-down', 'chevron-up', 'trash-2', 'plus', 'info', 'circle-help', 'search', 'loader-circle', 'triangle-alert', 'arrow-right'],
        array_map(static fn(string $n): string => \App\Support\Lucide::svg($n, 16), ['x', 'check', 'chevron-right', 'chevron-down', 'chevron-up', 'trash-2', 'plus', 'info', 'circle-help', 'search', 'loader-circle', 'triangle-alert', 'arrow-right'])
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
  </script>
</head>
<body>
  <header class="print-hide">
    <nav>
      <a href="/" class="brand" title="Gworm – výroba a sklady">Gworm</a>
      <a href="/"<?= $navIsActive('/') ? ' class="active" aria-current="page"' : '' ?>>Domů</a>
      <?php $navIsReader = (($currentUser['role'] ?? '') === 'user'); ?>
      <?php if (!$navIsReader): ?>
      <a href="/import" title="Nahrát XML a spustit import"<?= $navIsActive('/import') ? ' class="active" aria-current="page"' : '' ?>>Import</a>
      <?php endif; ?>
      <a href="/products" title="Kmenová karta produktů, CSV import/export"<?= $navIsActive('/products') ? ' class="active" aria-current="page"' : '' ?>>Produkty</a>
      <a href="/inventory" title="Záznam inventury a korekcí"<?= $navIsActive('/inventory') ? ' class="active" aria-current="page"' : '' ?>>Inventura</a>
      <a href="/reservations" title="Rezervace hotových produktů"<?= $navIsActive('/reservations') ? ' class="active" aria-current="page"' : '' ?>>Rezervace</a>
      <a href="/production/plans" title="Návrhy výroby a zápis vyrobeného"<?= $navIsActive('/production') ? ' class="active" aria-current="page"' : '' ?>>Výroba</a>
      <a href="/movements" title="Skladové doklady – výroba a korekce"<?= $navIsActive('/movements') ? ' class="active" aria-current="page"' : '' ?>>Pohyby</a>
      <a href="/analytics/revenue" title="Analýza"<?= $navIsActive('/analytics') ? ' class="active" aria-current="page"' : '' ?>>Analýza</a>
      <?php if (!$navIsReader): ?>
      <a href="/settings" title="Řady, ignorované vzory, globální nastavení"<?= $navIsActive('/settings') ? ' class="active" aria-current="page"' : '' ?>>Nastavení</a>
      <?php endif; ?>
      <?php if (($currentUser['role'] ?? '') === 'superadmin'): ?>
        <a href="/admin/history" title="Historie přihlášení"<?= $navIsActive('/admin/history') ? ' class="active" aria-current="page"' : '' ?>>Historie</a>
      <?php endif; ?>
      <span class="nav-user">
        <?php if ($currentUser): ?>
          <?= htmlspecialchars((string)$currentUser['email'], ENT_QUOTES, 'UTF-8') ?>
          (<?= htmlspecialchars(mb_strtolower(\App\Support\Auth::label((string)$currentUser['role'])), ENT_QUOTES, 'UTF-8') ?>)
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
    <div><strong>Poslední úprava:</strong> <?= date('Y-m-d H:i:s', (int)$fi['mtime']) ?></div>
    <div><strong>Verze/Deploy:</strong> <?= htmlspecialchars((string)$version, ENT_QUOTES, 'UTF-8') ?></div>
  </div>
</body>
</html>

