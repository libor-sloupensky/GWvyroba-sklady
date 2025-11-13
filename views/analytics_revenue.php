<?php
$openAiStatus = 'Připravujeme OpenAI integrační vrstvu (text-davinci/GPT-4o-mini).';
$upcomingSteps = [
  'Dokončit read-only přístup k databázi pomocí uživatele ai_analytics (pouze SELECT).',
  'Definovat role a přiřadit jim viditelné pohledy/výstupy (např. Finanční, Výroba).',
  'Vytvořit administrátorský prompt popisující databázi (tabulky, vazby, omezení).',
  'Přidat možnost uložit vlastní prompt pouze po označení hvězdičkou.',
];
$inspiration = [
  ['title' => 'Top 20 objednávek', 'summary' => 'Sestupné seřazení podle částky bez DPH, zobrazí datum + zákazníka.'],
  ['title' => 'Obraty e-shopů', 'summary' => 'Spojnicový graf obratu všech e-shopů za posledních 12 měsíců.'],
  ['title' => 'Výrobní kapacita dnes', 'summary' => 'Textové doporučení, jaké suroviny vyrábět podle rezervací.'],
];
?>
<h1>Analýza (AI)</h1>
<p class="muted">Sekce je nově navržena pro dotazy řízené AI. Koncová integrace s OpenAI bude aktivována po doplnění zabezpečení.</p>

<style>
.analysis-layout { display:grid; grid-template-columns: minmax(0,2fr) minmax(0,1fr); gap:1.2rem; }
.analysis-panel { border:1px solid #e0e0e0; border-radius:10px; padding:1rem; background:#fff; }
.analysis-panel h2 { margin-top:0; }
.analysis-form label { display:block; font-weight:600; margin-bottom:0.4rem; }
.analysis-form textarea { width:100%; min-height:160px; border:1px solid #ccc; border-radius:6px; padding:0.75rem; resize:vertical; font-family:inherit; }
.analysis-form .outputs { display:flex; flex-wrap:wrap; gap:0.8rem; margin:0.8rem 0 1rem 0; }
.analysis-form .outputs label { font-weight:400; }
.analysis-form button { padding:0.55rem 1.1rem; border:none; border-radius:6px; background:#1e88e5; color:#fff; cursor:pointer; font-size:1rem; }
.analysis-form button:disabled { background:#90caf9; cursor:not-allowed; }
.analysis-note { margin-top:0.6rem; font-size:0.9rem; color:#546e7a; }
.favorite-list { list-style:none; padding:0; margin:0; }
.favorite-list li { border:1px solid #eceff1; border-radius:8px; padding:0.65rem 0.8rem; margin-bottom:0.6rem; display:flex; justify-content:space-between; align-items:flex-start; gap:0.6rem; }
.favorite-title { font-weight:600; }
.favorite-actions button { background:none; border:0; cursor:pointer; font-size:1rem; }
.favorite-actions button.starred { color:#fbc02d; }
.badge { display:inline-block; padding:0.1rem 0.5rem; border-radius:999px; font-size:0.8rem; background:#e0f2f1; color:#00695c; margin-left:0.5rem; }
.todo-list { list-style:disc; margin:0.4rem 0 0 1.2rem; color:#37474f; }
.info-block { border-left:4px solid #90a4ae; padding-left:0.8rem; margin-top:1rem; color:#455a64; }
.muted { color:#607d8b; }
@media (max-width: 960px) {
  .analysis-layout { grid-template-columns: 1fr; }
}
</style>

<div class="analysis-layout">
  <section class="analysis-panel">
    <h2>AI dotaz</h2>
    <form class="analysis-form">
      <label for="prompt">Prompt pro AI</label>
      <textarea id="prompt" placeholder="Např.: Vypiš tabulku 20 největších objednávek v roce 2025 podle částky bez DPH."></textarea>
      <div class="outputs">
        <label><input type="checkbox" checked disabled /> Textový výstup (vždy v češtině)</label>
        <label><input type="checkbox" disabled /> Tabulkový výstup</label>
        <label><input type="checkbox" disabled /> Spojnicový graf</label>
      </div>
      <button type="button" disabled>Odeslat dotaz (OpenAI sandbox)</button>
      <div class="analysis-note">
        - AI používá pouze SELECT dotazy, nikdy nemůže upravit databázi ani zdrojový kód.<br>
        - Vstupní jazyk je libovolný, odpověď se vrací česky.<br>
        - Přístup k osobním údajům se řídí úrovní práv; některé role uvidí plné údaje, jiné anonymizované pohledy.
      </div>
    </form>
    <div class="info-block">
      <strong>Aktuální stav:</strong> <?= htmlspecialchars($openAiStatus, ENT_QUOTES, 'UTF-8') ?><br>
      <strong>Další kroky:</strong>
      <ul class="todo-list">
        <?php foreach ($upcomingSteps as $item): ?>
          <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="analysis-note">Informace o přihlášení/rolích budou zapsány i v sekci „Plány“, jakmile bude hotová autentizace.</p>
    </div>
  </section>

  <section class="analysis-panel">
    <h2>Oblíbené prompty</h2>
    <p class="muted">
      Prompty se ukládají pouze ve chvíli, kdy je označíte hvězdičkou. Bez hvězdičky se po odeslání zapomenou a do historie se nic nepíše.
      Kliknutím na cizí prompt se obsah pouze načte do editoru, teprve následně jej můžete upravit a označit jako svůj.
    </p>

    <h3>Moje</h3>
    <ul class="favorite-list">
      <li>
        <div>
          <span class="favorite-title">Návrh výroby pro dnešek</span>
          <p class="muted">Text + tabulka: ukáže, které suroviny chybí na základě rezervací z e-shopu.</p>
        </div>
        <div class="favorite-actions">
          <button type="button" class="starred" title="Odebrat z oblíbených">★</button>
        </div>
      </li>
      <li>
        <div>
          <span class="favorite-title">Stavy skladů vůči poslední inventuře</span>
          <p class="muted">Graf rozdílů oproti uzavřené inventuře, omezeno na produkty role Výroba.</p>
        </div>
        <div class="favorite-actions">
          <button type="button" class="starred" title="Odebrat z oblíbených">★</button>
        </div>
      </li>
    </ul>

    <h3>Inspirace ostatních</h3>
    <ul class="favorite-list">
      <?php foreach ($inspiration as $prompt): ?>
      <li>
        <div>
          <span class="favorite-title"><?= htmlspecialchars($prompt['title'], ENT_QUOTES, 'UTF-8') ?></span>
          <p class="muted"><?= htmlspecialchars($prompt['summary'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="favorite-actions">
          <button type="button" title="Otevřít v editoru">&#10140;</button>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
