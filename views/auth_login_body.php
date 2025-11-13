<h1>Přihlášení</h1>
<?php if (!empty($error ?? null)): ?>
  <div class="notice" style="border-color:#ffcdd2;background:#ffebee;color:#b71c1c;">
    <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>
<?php if (!empty($info ?? null)): ?>
  <div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;">
    <?= htmlspecialchars((string)$info, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>
<p class="muted">
  Přístup je povolen pouze pomocí firemního Google Workspace účtu. Superadministrátor spravuje,
  kteří uživatelé mají práva administrátora.
</p>
<?php if (!empty($googleReady)): ?>
  <p><a href="/auth/google" class="btn">Přihlásit se přes Google</a></p>
<?php else: ?>
  <p class="muted">Integrace Google OAuth zatím není nakonfigurovaná. Kontaktujte správce systému.</p>
<?php endif; ?>