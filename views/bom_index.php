<h1>BOM (karton / sada)</h1>
<?php $total = isset($total) ? (int)$total : 0; ?>
<style>
.csv-help {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 0.75rem;
  margin: 0 0 1rem;
}
.csv-help summary {
  cursor: pointer;
  font-weight: bold;
  list-style: none;
  display: flex;
  align-items: center;
}
.csv-help summary::-webkit-details-marker {
  display: none;
}
.csv-help summary::after {
  content: '\25BC';
  font-size: 1.4rem;
  margin-left: 0.5rem;
  color: #455a64;
}
.csv-help[open] summary::after {
  content: '\25B2';
}
.csv-help-body {
  margin-top: 0.5rem;
}
.muted-note { color:#607d8b; margin-top:0.4rem; }
.bom-tree-panel {
  border:1px solid #dfe3e8;
  border-radius:6px;
  padding:0.75rem;
  margin:1.2rem 0;
}
.bom-tree-panel summary {
  cursor:pointer;
  font-weight:600;
}
.bom-tree-view { list-style:none; padding-left:1.2rem; margin:0.5rem 0 0 0; }
.bom-tree-node { margin:0.15rem 0; padding:0.2rem 0.4rem; border-radius:4px; display:inline-block; }
.bom-tree-node[data-type="produkt"] { background:#e8f5e9; }
.bom-tree-node[data-type="obal"] { background:#fff8e1; }
.bom-tree-node[data-type="etiketa"] { background:#f3e5f5; }
.bom-tree-node[data-type="surovina"] { background:#ffebee; }
.bom-tree-node[data-type="baleni"] { background:#e3f2fd; }
.bom-tree-node[data-type="karton"] { background:#ede7f6; }
.bom-tree-node[data-type=""] { background:#eceff1; }
</style>
<details class="csv-help" id="bom-help">
  <summary>Nápověda – BOM import</summary>
  <div class="csv-help-body">
    <p><strong>Popis sloupců (oddělovač ;):</strong></p>
    <ul>
      <li><code>rodic_sku</code> – finální produkt nebo karton, pro který skládáte recepturu.</li>
      <li><code>potomek_sku</code> – komponenta, která do rodiče vstupuje.</li>
      <li><code>koeficient</code> – množství potomka na 1 jednotku rodiče (ve stejné MJ jako má potomek).</li>
      <li><code>merna_jednotka_potomka</code> – volitelné; pokud ponecháte prázdné, převezme se MJ potomka z kmenových produktů.</li>
      <li><code>druh_vazby</code> – <code>karton</code> pouze pro rodiče typu karton; ve všech ostatních případech zvolte <em>sada</em>. Prázdné pole systém dopočítá stejně.</li>
    </ul>
    <p>Desetinné hodnoty zadávejte s tečkou. Každou vazbu lze nahrát kdykoliv – rodič i potomek jen musí existovat v tabulce produktů.</p>
  </div>
</details>
<?php if (!empty($error)): ?>
  <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;"><?= htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8') ?></div>
<?php endif; ?>
<?php if (!empty($message)): ?>
  <div class="notice" style="border-color:#c8e6c9;background:#f1f8f1;color:#2e7d32;"><?= htmlspecialchars((string)$message,ENT_QUOTES,'UTF-8') ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="notice" style="border-color:#ffbdbd;background:#fff5f5;color:#b00020;">
    <strong>Chyby importu:</strong>
    <ul style="margin:0.4rem 0 0 1rem;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string)$e,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<p><a href="/bom/export">Stáhnout CSV (aktuální)</a></p>
<form method="post" action="/bom/import" enctype="multipart/form-data">
  <label>Nahrát CSV</label><br>
  <input type="file" name="csv" accept=".csv" required />
  <br>
  <button type="submit">Importovat</button>
  <span class="muted">Tip: používejte UTF‑8; oddělovač je středník.</span>
</form>

<hr>
<p class="muted-note">Celkem vazeb v tabulce BOM: <strong><?= number_format($total, 0, ',', ' ') ?></strong></p>
<?php
  $renderBomTree = function(array $nodes) use (&$renderBomTree) {
      if (empty($nodes)) return;
      echo '<ul class="bom-tree-view">';
      foreach ($nodes as $node) {
          $sku = htmlspecialchars((string)$node['sku'], ENT_QUOTES, 'UTF-8');
          $nazev = htmlspecialchars((string)($node['nazev'] ?? ''), ENT_QUOTES, 'UTF-8');
          $type = htmlspecialchars((string)($node['typ'] ?? ''), ENT_QUOTES, 'UTF-8');
          echo '<li>';
          echo '<span class="bom-tree-node" data-type="' . $type . '">' . $sku;
          if ($nazev !== '') {
              echo ' – ' . $nazev;
          }
          echo '</span>';
          if (!empty($node['children'])) {
              $renderBomTree($node['children']);
          }
          echo '</li>';
      }
      echo '</ul>';
  };
?>
<?php if (!empty($tree)): ?>
  <link rel="stylesheet" href="https://unpkg.com/vis-network@9.1.2/styles/vis-network.min.css" />
  <script src="https://unpkg.com/vis-network@9.1.2/standalone/umd/vis-network.min.js"></script>
  <details class="bom-tree-panel">
    <summary>Strom vazeb produktů</summary>
    <p class="muted">Každý produkt je ve stromu uveden pouze jednou. Barvy rozlišují typy produktů.</p>
    <div id="bom-network" style="height:480px;"></div>
    <noscript><?php $renderBomTree($tree); ?></noscript>
  </details>
  <script>
  (function(){
    const container = document.getElementById('bom-network');
    if (!container || typeof vis === 'undefined') return;
    const rawTree = <?= json_encode($tree, JSON_UNESCAPED_UNICODE) ?>;
    const typeColors = {
      'produkt': '#81c784',
      'obal': '#ffe082',
      'etiketa': '#ce93d8',
      'surovina': '#ef9a9a',
      'baleni': '#90caf9',
      'karton': '#b39ddb',
      '': '#cfd8dc'
    };
    const nodes = [];
    const edges = [];
    const seen = new Set();
    function traverse(node, parent) {
      if (!node) return;
      if (!seen.has(node.sku)) {
        seen.add(node.sku);
        nodes.push({
          id: node.sku,
          label: node.sku + (node.nazev ? ' – ' + node.nazev : ''),
          color: typeColors[node.typ] || typeColors[''],
          shape: 'box',
          font: { multi: 'html', color: '#263238' }
        });
      }
      if (parent) {
        edges.push({ from: parent, to: node.sku, arrows: 'to' });
      }
      (node.children || []).forEach(child => traverse(child, node.sku));
    }
    rawTree.forEach(root => traverse(root, null));
    const network = new vis.Network(container, {
      nodes: new vis.DataSet(nodes),
      edges: new vis.DataSet(edges)
    }, {
      layout: { hierarchical: { direction: 'UD', nodeSpacing: 180, levelSeparation: 150, sortMethod: 'directed' } },
      physics: false,
      interaction: { hover: true }
    });
  })();
  </script>
<?php endif; ?>
<table>
  <tr>
    <th>Rodič (SKU)</th>
    <th>Potomek (SKU)</th>
    <th>Koeficient</th>
    <th>MJ potomka</th>
    <th>Druh vazby</th>
  </tr>
  <?php foreach (($items ?? []) as $it): ?>
  <tr>
    <td><?= htmlspecialchars((string)$it['rodic_sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['potomek_sku'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['koeficient'],ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)($it['merna_jednotka_potomka'] ?? ''),ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars((string)$it['druh_vazby'],ENT_QUOTES,'UTF-8') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
