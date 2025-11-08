<?php
// base layout with footer: last modified (mtime) + version
$title = $title ?? 'App';
$version = (include __DIR__ . '/../config/config.php')['app']['version'] ?? 'dev';
function app_footer_info(): array {
  $root = dirname(__DIR__,1);
  $files = [ $root.'/public/index.php', $root.'/src', $root.'/views', $root.'/db/schema.sql' ];
  $max = 0; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) { $p = $f->getPathname(); if (strpos($p,'/vendor/')!==false) continue; $t = @filemtime($p); if ($t && $t>$max) $max=$t; }
  if ($max<=0) $max = time();
  return ['mtime'=>$max];
}
$fi = app_footer_info();
?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#f6f7f9; }
    header { background:#263238; color:#fff; padding:10px 16px; }
    nav a { color:#fff; margin-right:12px; text-decoration:none; }
    .container { max-width: 1200px; margin: 1rem auto; background:#fff; border:1px solid #e5e5e5; border-radius: 12px; padding: 14px 16px; }
    .footer { position: fixed; right: 1rem; bottom: 1rem; background:#fff; border: 1px solid #ddd; border-radius: 8px; padding: 8px 10px; font-size: 13px; color:#333; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .notice { padding:8px 10px; border:1px solid #ddd; background:#f9f9f9; border-radius:8px; }
    table { border-collapse: collapse; width: 100%; }
    th,td { padding: 6px 8px; border-bottom:1px solid #eee; text-align:left; }
    th { background:#f1f5f9; }
    .muted { color:#607d8b; font-size: 13px; }
    .help { cursor: help; }
  </style>
</head>
<body>
  <header>
    <nav>
      <a href="/">Domů</a>
      <a href="/import" title="Nahrát Pohoda XML a spustit import">Import</a>
      <a href="/products" title="Kmenová karta produktů, CSV import/export">Produkty</a>
      <a href="/bom" title="Vazby BOM: karton/sada">BOM</a>
      <a href="/inventory" title="Záznam inventury a korekcí">Inventura</a>
      <a href="/reservations" title="Rezervace hotových produktů">Rezervace</a>
      <a href="/production/plans" title="Návrhy výroby a zápis vyrobeného">Výroba</a>
      <a href="/analytics/revenue" title="Přehled obratu (všechny položky)">Analýza</a>
      <a href="/settings" title="Řady, ignor vzory, globální nastavení">Nastavení</a>
      <a href="/plany" title="Seznam naplánovaných funkcí">Plány</a>
      <span style="float:right;" class="muted">Režim: otevřený (login vypnut)</span>
    </nav>
  </header>
  <main class="container">
    <?php require __DIR__ . '/' . basename($view ?? ($view ?? 'home.php')); ?>
  </main>
  <div class="footer">
    <div><strong>Poslední úprava:</strong> <?= date('Y-m-d H:i:s', (int)$fi['mtime']) ?></div>
    <div><strong>Verze/Deploy:</strong> <?= htmlspecialchars((string)$version, ENT_QUOTES, 'UTF-8') ?></div>
  </div>
</body>
</html>
