<?php
/** @var string $message */
// noop spacing tweak v2
?>
<h1><?= htmlspecialchars((string)($title ?? 'Přístup odepřen'), ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?></p>
<p><a href="/">Zpět na úvod</a></p>
