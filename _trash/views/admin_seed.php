<h1>Admin – Seed admin účtu</h1>
<p class="muted">Tento formulář vloží/aktualizuje admin účet s heslem <strong>dokola</strong>. Používej pouze pro prvotní nastavení.</p>
<?php if (!empty($error)): ?><div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<form method="post" action="/admin/seed" onsubmit="return confirm('Vytvořit/aktualizovat admin účet?');">
  <label>E‑mail admina</label><br>
  <input type="email" name="email" value="admin@local" required />
  <br>
  <button type="submit">Seed admina</button>
  <span class="muted">Účet dostane heslo <code>dokola</code> a roli admin.</span>
</form>

