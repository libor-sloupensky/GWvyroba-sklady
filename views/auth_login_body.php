<!-- Google-only login screen -->
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
  <a href="/auth/google" class="btn"
     style="display:inline-flex;align-items:center;gap:0.6rem;padding:0.55rem 0.95rem;border:1px solid #dadce0;border-radius:6px;background:#fff;color:#202124;text-decoration:none;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,0.07);">
    <img src="https://developers.google.com/identity/images/g-logo.png" alt="" width="20" height="20" />
    Přihlásit se přes Google
  </a>
<?php else: ?>
  <p class="muted">Integrace Google OAuth zatím není nakonfigurovaná. Kontaktujte správce systému.</p>
<?php endif; ?>
