<?php
declare(strict_types=1);
/**
 * JEDNORÁZOVÝ migrační spouštěč (po doběhu SMAZAT!).
 * Oprava tržeb: EUR→CZK (ČNB) + castka_celkem bez DPH po slevě.
 *
 *   ?token=...&mode=dry     (default) – jen ukáže, co by se změnilo (rollback)
 *   ?token=...&mode=apply   – zapíše změny (commit)
 *
 * Idempotentní: EUR přepočet jen u řádků bez kurzu; castka se přepočítává z položek.
 */
require __DIR__ . '/../src/bootstrap.php';

use App\Support\DB;
use App\Service\CnbRateService;

header('Content-Type: text/plain; charset=utf-8');

// Autorizace stejně jako cron.php — token z config (žádný secret v repu)
$cfg = include __DIR__ . '/../config/config.php';
$cronToken = (string)($cfg['cron_token'] ?? '');
$providedToken = trim((string)($_GET['token'] ?? ''));
if ($cronToken === '' || $providedToken === '' || !hash_equals($cronToken, $providedToken)) {
    http_response_code(403);
    echo "403 Forbidden: Invalid token.\n";
    exit;
}
$apply = (($_GET['mode'] ?? 'dry') === 'apply');

$pdo = DB::pdo();
$cnb = new CnbRateService();
$out = [];
$out[] = 'Migrace tržeb — režim: ' . ($apply ? 'APPLY (zápis)' : 'DRY-RUN (bez zápisu)');
$out[] = str_repeat('-', 64);

$pdo->beginTransaction();
try {
    // === Fáze 2: EUR → CZK (řádky bez uloženého kurzu, typicky grig.sk) ===
    $eurDocs = $pdo->query("
        SELECT eshop_source, cislo_dokladu, mena_puvodni, duzp, castka_celkem
        FROM doklady_eshop
        WHERE COALESCE(mena_puvodni,'CZK') <> 'CZK'
          AND (kurz_na_czk IS NULL OR kurz_na_czk = 0)
        ORDER BY duzp
    ")->fetchAll(PDO::FETCH_ASSOC);

    $itemSel = $pdo->prepare("SELECT id, cena_jedn_czk FROM polozky_eshop WHERE eshop_source=? AND cislo_dokladu=?");
    $itemUpd = $pdo->prepare("UPDATE polozky_eshop SET cena_jedn_mena=?, cena_jedn_czk=? WHERE id=?");
    $docKurz = $pdo->prepare("UPDATE doklady_eshop SET kurz_na_czk=? WHERE eshop_source=? AND cislo_dokladu=?");

    $eurOk = 0; $eurSkip = 0;
    foreach ($eurDocs as $d) {
        $rate = $cnb->getRate((string)$d['mena_puvodni'], (string)$d['duzp']);
        if ($rate === null || $rate <= 0) {
            $eurSkip++;
            $out[] = sprintf('  ! EUR bez kurzu (přeskočeno): %s/%s %s %s',
                $d['eshop_source'], $d['cislo_dokladu'], $d['mena_puvodni'], $d['duzp']);
            continue;
        }
        $itemSel->execute([$d['eshop_source'], $d['cislo_dokladu']]);
        foreach ($itemSel->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $foreign = (float)$it['cena_jedn_czk'];      // původní hodnota je v cizí měně
            $czk = round($foreign * $rate, 4);
            $itemUpd->execute([$foreign, $czk, $it['id']]);
        }
        $docKurz->execute([$rate, $d['eshop_source'], $d['cislo_dokladu']]);
        $eurOk++;
        $out[] = sprintf('  EUR→CZK %s/%s %s kurz %.3f', $d['eshop_source'], $d['cislo_dokladu'], $d['mena_puvodni'], $rate);
    }
    $out[] = sprintf('Fáze 2: přepočteno %d EUR dokladů, bez kurzu %d', $eurOk, $eurSkip);
    $out[] = '';

    // === Fáze 1: castka_celkem = Σ(cena_jedn_czk × mn. × (1−sleva)) bez DPH, po slevě ===
    $changed = $pdo->exec("
        UPDATE doklady_eshop de
        SET de.castka_celkem = (
            SELECT ROUND(SUM(COALESCE(pe.cena_jedn_czk,0) * COALESCE(pe.mnozstvi,0)
                             * (1 - COALESCE(pe.sleva_procento,0)/100)), 2)
            FROM polozky_eshop pe
            WHERE pe.eshop_source = de.eshop_source
              AND pe.cislo_dokladu = de.cislo_dokladu
        )
    ");
    $out[] = sprintf('Fáze 1: přepočteno castka_celkem u %d dokladů (bez DPH, po slevě).', (int)$changed);
    $out[] = '';

    // === Kontrola na vzorových dokladech ===
    $sample = $pdo->query("
        SELECT eshop_source, cislo_dokladu, mena_puvodni, kurz_na_czk, castka_celkem
        FROM doklady_eshop
        WHERE cislo_dokladu IN ('2026900227','2026900009','2026000044','2026000046',
                                '3260146','7770000302','1126000008','2026900108')
        ORDER BY cislo_dokladu
    ")->fetchAll(PDO::FETCH_ASSOC);
    $out[] = 'Kontrola vzorových dokladů (po migraci):';
    foreach ($sample as $s) {
        $out[] = sprintf('  %-12s %-12s %-3s kurz=%-8s castka=%s',
            $s['eshop_source'], $s['cislo_dokladu'], $s['mena_puvodni'],
            $s['kurz_na_czk'] ?? 'null', $s['castka_celkem']);
    }

    if ($apply) {
        $pdo->commit();
        $out[] = '';
        $out[] = '>>> ZAPSÁNO (commit).';
    } else {
        $pdo->rollBack();
        $out[] = '';
        $out[] = '>>> DRY-RUN — nic nezapsáno (rollback). Pro zápis přidej &mode=apply.';
    }
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
    exit;
}

echo implode("\n", $out) . "\n";
