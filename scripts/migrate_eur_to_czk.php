<?php
declare(strict_types=1);
/**
 * Fáze 2 — přepočet EUR dokladů na CZK přes ČNB kurz dle DUZP.
 *
 * Týká se dokladů, kde je cizí měna a NENÍ uložený kurz (typicky grig.sk /
 * Shoptet.sk, kde se cena_jedn_czk uložila v EUR a kurz_na_czk = NULL).
 * Doklady z Shoptet.cz (gogrig.com aj.) mají EUR už převedené na CZK + kurz → vynechají se.
 *
 * Pro každý dotčený doklad:
 *   - kurz = ČNB(měna, DUZP)
 *   - položky: cena_jedn_mena := původní cena_jedn_czk (cizí měna),
 *              cena_jedn_czk  := původní × kurz
 *   - doklad: kurz_na_czk := kurz,
 *             castka_celkem := Σ(cena_jedn_czk × mnozstvi × (1 − sleva/100))  [už bez DPH]
 *
 * Spuštění:
 *   php scripts/migrate_eur_to_czk.php            (dry-run, jen vypíše)
 *   php scripts/migrate_eur_to_czk.php --apply    (zapíše změny)
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Support\DB;
use App\Service\CnbRateService;

$apply = in_array('--apply', $argv, true);
$pdo = DB::pdo();
$cnb = new CnbRateService();

echo "Migrace EUR → CZK (" . ($apply ? 'APPLY' : 'DRY-RUN') . ")\n";
echo str_repeat('-', 60) . "\n";

$docs = $pdo->query("
    SELECT eshop_source, cislo_dokladu, mena_puvodni, duzp, castka_celkem
    FROM doklady_eshop
    WHERE COALESCE(mena_puvodni, 'CZK') <> 'CZK'
      AND (kurz_na_czk IS NULL OR kurz_na_czk = 0)
    ORDER BY duzp
")->fetchAll(PDO::FETCH_ASSOC);

echo 'Dokladů k přepočtu: ' . count($docs) . "\n\n";

$itemSel = $pdo->prepare("SELECT id, cena_jedn_czk, mnozstvi, COALESCE(sleva_procento,0) AS sleva
                          FROM polozky_eshop WHERE eshop_source=? AND cislo_dokladu=?");
$itemUpd = $pdo->prepare("UPDATE polozky_eshop SET cena_jedn_mena=?, cena_jedn_czk=? WHERE id=?");
$docUpd  = $pdo->prepare("UPDATE doklady_eshop SET kurz_na_czk=?, castka_celkem=? WHERE eshop_source=? AND cislo_dokladu=?");

$ok = 0; $skipNoRate = 0;
if ($apply) { $pdo->beginTransaction(); }
foreach ($docs as $d) {
    $mena = (string)$d['mena_puvodni'];
    $duzp = (string)$d['duzp'];
    $rate = $cnb->getRate($mena, $duzp);
    if ($rate === null || $rate <= 0) {
        $skipNoRate++;
        echo sprintf("  ! %s/%s  %s %s  — kurz NENALEZEN, přeskakuji\n", $d['eshop_source'], $d['cislo_dokladu'], $mena, $duzp);
        continue;
    }
    $itemSel->execute([$d['eshop_source'], $d['cislo_dokladu']]);
    $items = $itemSel->fetchAll(PDO::FETCH_ASSOC);
    $castka = 0.0;
    foreach ($items as $it) {
        $foreign = (float)$it['cena_jedn_czk'];           // původní hodnota je v cizí měně
        $czk = round($foreign * $rate, 4);
        $qty = (float)$it['mnozstvi'];
        $sleva = (float)$it['sleva'];
        $castka += $czk * $qty * (1 - $sleva / 100);
        if ($apply) {
            $itemUpd->execute([$foreign, $czk, $it['id']]);
        }
    }
    $castka = round($castka, 2);
    if ($apply) {
        $docUpd->execute([$rate, $castka, $d['eshop_source'], $d['cislo_dokladu']]);
    }
    $ok++;
    echo sprintf("  %s/%s  %s kurz %.3f  castka %s → %s CZK\n",
        $d['eshop_source'], $d['cislo_dokladu'], $mena, $rate, $d['castka_celkem'], number_format($castka, 2, '.', ''));
}
if ($apply) { $pdo->commit(); }

echo "\n" . str_repeat('-', 60) . "\n";
echo "Přepočteno: {$ok}  |  bez kurzu (přeskočeno): {$skipNoRate}\n";
echo $apply ? "ZAPSÁNO.\n" : "DRY-RUN — nic nezapsáno. Spusť s --apply.\n";
