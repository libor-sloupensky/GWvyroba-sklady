<h1>Plány</h1>
<p class="muted">Seznam potvrzených, ale dosud neimplementovaných bodů. Udržováno v <code>docs/PLANS.json</code>.</p>
<table>
  <tr><th>Položka</th><th>Stav</th><th>Pozn.</th></tr>
  <?php foreach (($plans ?? []) as $p): ?>
    <tr>
      <td><?= htmlspecialchars((string)($p['item'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars((string)($p['status'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
      <td><?= htmlspecialchars((string)($p['note'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    </tr>
  <?php endforeach; ?>
</table>

