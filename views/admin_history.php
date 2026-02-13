<h1>Historie přihlášení</h1>

<?php if (empty($entries)): ?>
  <p style="color:#78909c;">Zatím nejsou zaznamenána žádná přihlášení.</p>
<?php else:
  // Seskupit podle e-mailu, zachovat pořadí (nejnovější první)
  $grouped = [];
  foreach ($entries as $entry) {
      $email = $entry['email'];
      $grouped[$email][] = $entry['datetime'];
  }
?>
  <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
    <thead>
      <tr style="background:#eceff1; text-align:left;">
        <th style="padding:0.5rem 0.75rem; border-bottom:2px solid #cfd8dc; width:2rem;"></th>
        <th style="padding:0.5rem 0.75rem; border-bottom:2px solid #cfd8dc;">E-mail</th>
        <th style="padding:0.5rem 0.75rem; border-bottom:2px solid #cfd8dc;">Poslední přihlášení</th>
        <th style="padding:0.5rem 0.75rem; border-bottom:2px solid #cfd8dc; text-align:right;">Celkem</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($grouped as $email => $dates): ?>
        <tr style="border-bottom:1px solid #eceff1; cursor:pointer;" onclick="var d=document.getElementById('hist-<?= md5($email) ?>');d.style.display=d.style.display==='none'?'':'none'; var t=this.querySelector('.tri');t.textContent=d.style.display===''?'\u25BC':'\u25B6';">
          <td style="padding:0.4rem 0.75rem; color:#90a4ae;"><span class="tri"><?= count($dates) > 1 ? '&#9654;' : '' ?></span></td>
          <td style="padding:0.4rem 0.75rem; font-weight:600;"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></td>
          <td style="padding:0.4rem 0.75rem;"><?= htmlspecialchars($dates[0], ENT_QUOTES, 'UTF-8') ?></td>
          <td style="padding:0.4rem 0.75rem; text-align:right; color:#78909c;"><?= count($dates) ?>&times;</td>
        </tr>
        <?php if (count($dates) > 1): ?>
        <tr id="hist-<?= md5($email) ?>" style="display:none;">
          <td colspan="4" style="padding:0 0.75rem 0.5rem 2.5rem;">
            <table style="width:100%; border-collapse:collapse;">
              <?php foreach (array_slice($dates, 1) as $dt): ?>
                <tr><td style="padding:0.2rem 0.5rem; color:#78909c; border-bottom:1px solid #f5f5f5;"><?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?></td></tr>
              <?php endforeach; ?>
            </table>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
