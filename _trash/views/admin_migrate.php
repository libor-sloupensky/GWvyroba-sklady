<h1>Admin – Migrace DB</h1>
<p class="muted">Tento nástroj provede aplikaci schématu z <code>db/schema.sql</code> na připojenou databázi. Spouštěj pouze jako administrátor.</p>
<?php if (!empty($error)): ?><div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<form method="post" action="/admin/migrate" onsubmit="return confirm('Spustit migraci schématu?');">
  <button type="submit">Spustit migraci</button>
  <span class="muted">Použij pouze pokud víš, co děláš. Vždy měj zálohu DB.</span>
  </form>

