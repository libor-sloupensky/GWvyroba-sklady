<?php
/** @var string $title */
?>
<h1>Analýza v2 (katalog dotazů)</h1>

<p>Tato nová záložka bude používat ověřené šablony dotazů (katalog) a router, který podle zadání vybere šablonu, doplní parametry a vrátí výstup (text/tabulka/graf). Stávající analýza zůstává beze změny.</p>

<div class="notice">
  <strong>Stav:</strong> UI pro katalog a router se připravuje. Prozatím slouží tato stránka jako placeholder.
</div>

<p>Požadavky z předchozí diskuse:</p>
<ul>
  <li>Šablony nejsou vázané na typ výstupu; AI/router může zvolit tabulku, text nebo graf.</li>
  <li>Filtry/dimenze: čas (default posledních 18 měsíců), IČ (kontakty), kanál/eshop_source, SKU, typ produktu, skupina, značka.</li>
  <li>Metriky: tržby bez DPH, počet dokladů/řádků, průměrná cena, skladová hodnota (inventura+pohyby−rezervace), stav skladu, výroba; rezervace samotné aktuálně ne.</li>
  <li>Uživatel může kombinovat víc výstupů (např. tabulka + graf) a přidat vlastní groupování (měsíc/týden/den).</li>
  <li>Pouze role admin/superadmin.</li>
</ul>

<p>Jakmile bude katalog a router hotový, přibude formulář pro zadání dotazu, výběr/úpravu parametrů a volbu výstupu.</p>
<p class="muted">Pozn.: Pokud stránku nevidíte v menu, zkuste aktualizovat cache prohlížeče.</p>
