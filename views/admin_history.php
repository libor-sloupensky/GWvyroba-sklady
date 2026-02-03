<h1>Historie přihlášení</h1>

<?php if (empty($entries)): ?>
  <p style="color:#78909c;">Zatím nejsou zaznamenána žádná přihlášení.</p>
<?php else: ?>
  <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
    <thead>
      <tr style="background:#eceff1; text-align:left;">
        <th style="padding:0.5rem 0.75rem; border-bottom:2px solid #cfd8dc;">#</th>
        <th style="padding:0.5rem 0.75rem; border-bottom:2px solid #cfd8dc;">Datum a čas</th>
        <th style="padding:0.5rem 0.75rem; border-bottom:2px solid #cfd8dc;">E-mail</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $i => $entry): ?>
        <tr style="border-bottom:1px solid #eceff1;">
          <td style="padding:0.4rem 0.75rem; color:#90a4ae;"><?= $i + 1 ?></td>
          <td style="padding:0.4rem 0.75rem;"><?= htmlspecialchars($entry['datetime'], ENT_QUOTES, 'UTF-8') ?></td>
          <td style="padding:0.4rem 0.75rem;"><?= htmlspecialchars($entry['email'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
