<?php
declare(strict_types=1);

namespace App\Service;

use App\Support\DB;
use PDO;

/**
 * Vánoce 2026 – plán a sledování vánoční výroby (stránka /vanoce, jen čtení).
 *
 * Plán odbytu = loňský odbyt ve stejných ISO týdnech × koeficient e-shopu; stánky (grigsupply)
 * rovnoměrně podle odhadu kolegyně. Plán výroby = pevný týdenní cíl. Skutečnost = faktury,
 * výrobní doklady a stav hotových sáčků na konci týdne. Objem je v sáčcích (spotřebitelská balení):
 * kartony a sady se rozpadají přes kusovník, položky mimo katalog podle názvu („15 ks - …“).
 */
final class VanoceService
{
    /** Parametry sezóny – měnit zde. */
    public const SEZONA = [
        'nazev' => 'Vánoce 2026',
        'rok' => 2026,
        'tyden_od' => 44,                 // 26. 10. 2026
        'tyden_do' => 52,                 // 21.–27. 12. 2026
        'zakladni_rok' => 2025,           // loňské stejné ISO týdny
        'koeficienty' => [                // násobek loňského odbytu
            'grig.cz' => 1.5,
            'grig.sk' => 1.5,
            'WormUP.com' => 1.5,
            'gogrig.com' => 1.0,
        ],
        'skupiny' => [
            'grig.cz' => 'maloobchod',
            'grig.sk' => 'maloobchod',
            'WormUP.com' => 'maloobchod',
            'gogrig.com' => 'velkoobchod',
            'grigsupply.cz' => 'stanky',
        ],
        // Stánky: odhad kolegyně (realistický scénář) v sáčcích, rovnoměrně po týdnech
        'stanky' => ['sacku' => 15517, 'tyden_od' => 45, 'tyden_do' => 51],
        // Plán výroby: sáčků za týden, poslední výrobní týden
        'vyroba_tydne' => 5000,
        'vyroba_tyden_do' => 51,
        // Plánované dodávky suroviny (kg natural)
        'dodavky' => [
            ['datum' => '2026-11-02', 'druh' => 'cervici', 'kg' => 297, 'poznamka' => 'paleta červíků'],
            ['datum' => '2026-11-02', 'druh' => 'cvrcci', 'kg' => 240, 'poznamka' => 'paleta cvrčků'],
        ],
    ];

    public const SKUPINY_NAZVY = ['maloobchod' => 'Maloobchod', 'velkoobchod' => 'Velkoobchod gogrig', 'stanky' => 'Stánky'];
    public const DRUHY = ['cervici' => 'Červíci', 'cvrcci' => 'Cvrčci', 'sarancata' => 'Sarančata'];
    private const KORENY = ['SX9001' => 'cervici', 'SX9002' => 'cvrcci', 'SX9003' => 'sarancata'];
    private const NEVYROBNI = '/label|kupon|sleva|balné|balne|kotou|tabulková|billa|duplicit|objednávk|stornov|propaga|stojan|pronájem|raben|logist|termo|doprava|zkumavka|letáky|poštovné|poukaz/iu';

    /** @var array<string,array<string,mixed>> */
    private array $prod = [];
    /** @var array<string,string> */
    private array $alt = [];
    /** @var array<string,array<int,array{0:string,1:float}>> */
    private array $bom = [];
    /** @var array<string,array<string,float>> */
    private array $naturalCache = [];

    /** @return array<string,mixed> */
    public function report(): array
    {
        $this->loadCatalog();
        $c = self::SEZONA;
        $now = new \DateTimeImmutable('now');
        $tydny = [];
        for ($w = $c['tyden_od']; $w <= $c['tyden_do']; $w++) {
            $od = (new \DateTimeImmutable())->setISODate($c['rok'], $w, 1)->setTime(0, 0);
            $tydny[$w] = ['tyden' => $w, 'od' => $od->format('Y-m-d'), 'do' => $od->modify('+6 days')->format('Y-m-d')];
        }
        $prvni = reset($tydny); $posledni = end($tydny);

        // Loňský odbyt ve stejných ISO týdnech
        $lOd = (new \DateTimeImmutable())->setISODate($c['zakladni_rok'], $c['tyden_od'], 1)->format('Y-m-d');
        $lDo = (new \DateTimeImmutable())->setISODate($c['zakladni_rok'], $c['tyden_do'], 7)->format('Y-m-d');
        $loni = $this->odbytPoTydnech($lOd, $lDo);

        // Skladba suroviny stánků podle loňska (kg natural na sáček)
        $gs = ['sacky' => 0.0, 'kg' => []];
        foreach ($loni['grigsupply.cz'] ?? [] as $t) { $gs['sacky'] += $t['sacky']; foreach ($t['kg'] as $d => $v) $gs['kg'][$d] = ($gs['kg'][$d] ?? 0) + $v; }
        $stankyTydnu = $c['stanky']['tyden_do'] - $c['stanky']['tyden_od'] + 1;

        // Skutečnost letos
        $letos = $this->odbytPoTydnech($prvni['od'], min($posledni['do'], $now->format('Y-m-d')));
        $vyroba = $this->vyrobaPoTydnech($prvni['od'], $posledni['do']);
        $start = $this->sackuSkladem($prvni['od'] . ' 00:00:00')['sacky'];

        $planKumOdbyt = 0.0; $planKumVyroba = 0.0; $skKumOdbyt = 0.0; $skKumVyroba = 0.0;
        $rows = [];
        foreach ($tydny as $w => $t) {
            $plan = ['maloobchod' => 0.0, 'velkoobchod' => 0.0, 'stanky' => 0.0];
            $kg = array_fill_keys(array_keys(self::DRUHY), 0.0);
            foreach ($c['koeficienty'] as $e => $k) {
                $l = $loni[$e][$w] ?? null;
                if (!$l) continue;
                $plan[$c['skupiny'][$e]] += $l['sacky'] * $k;
                foreach ($l['kg'] as $d => $v) $kg[$d] += $v * $k;
                foreach ($l['volne'] as $d => $v) $kg[$d] += $v * $k;
            }
            if ($w >= $c['stanky']['tyden_od'] && $w <= $c['stanky']['tyden_do']) {
                $s = $c['stanky']['sacku'] / $stankyTydnu;
                $plan['stanky'] += $s;
                if ($gs['sacky'] > 0) foreach ($gs['kg'] as $d => $v) $kg[$d] += $s * $v / $gs['sacky'];
            }
            $planOdbyt = array_sum($plan);
            $planVyroba = $w <= $c['vyroba_tyden_do'] ? (float)$c['vyroba_tydne'] : 0.0;
            $planKumOdbyt += $planOdbyt; $planKumVyroba += $planVyroba;

            $probehl = $t['od'] <= $now->format('Y-m-d');
            $sk = null;
            if ($probehl) {
                $odb = ['maloobchod' => 0.0, 'velkoobchod' => 0.0, 'stanky' => 0.0];
                foreach ($c['skupiny'] as $e => $g) $odb[$g] += $letos[$e][$w]['sacky'] ?? 0.0;
                $skKumOdbyt += array_sum($odb);
                $skKumVyroba += $vyroba[$w] ?? 0.0;
                $konec = min($t['do'] . ' 23:59:59', $now->format('Y-m-d H:i:s'));
                $sk = [
                    'odbyt' => $odb, 'odbyt_celkem' => array_sum($odb), 'odbyt_kum' => $skKumOdbyt,
                    'vyroba' => $vyroba[$w] ?? 0.0, 'vyroba_kum' => $skKumVyroba,
                    'zasoba' => $this->sackuSkladem($konec)['sacky'],
                    'probiha' => $t['do'] >= $now->format('Y-m-d'),
                ];
            }
            $rows[] = $t + [
                'plan' => $plan, 'plan_odbyt' => $planOdbyt, 'plan_odbyt_kum' => $planKumOdbyt,
                'plan_kg' => $kg,
                'plan_vyroba' => $planVyroba, 'plan_vyroba_kum' => $planKumVyroba,
                'plan_zasoba' => $start + $planKumVyroba - $planKumOdbyt,
                'skutecnost' => $sk,
            ];
        }

        // Suroviny: sklad teď, plánované dodávky, potřeba na zbývající plán výroby
        $dnes = $now->format('Y-m-d');
        $sklad = $this->sackuSkladem(null);
        $sur = $this->surovinySkladem();
        $zbyvaVyroba = 0.0; $zbyvaKg = array_fill_keys(array_keys(self::DRUHY), 0.0); $zbyvaOdbyt = 0.0;
        foreach ($rows as $r) {
            if ($r['do'] < $dnes) continue;
            $zbyvaVyroba += $r['plan_vyroba'];
            $zbyvaOdbyt += $r['plan_odbyt'];
            foreach ($r['plan_kg'] as $d => $v) $zbyvaKg[$d] += $v;
        }
        // kg na vyrobené sáčky: náplň zbývajícího odbytu bez náplně v hotových sáčcích skladem, škálovaná na plán výroby
        $suroviny = [];
        foreach (self::DRUHY as $d => $nazev) {
            $potrebaOdbyt = max(0.0, $zbyvaKg[$d] - ($sklad['kg'][$d] ?? 0.0));
            $dodavky = 0.0;
            foreach ($c['dodavky'] as $x) if ($x['druh'] === $d && $x['datum'] >= $prvni['od'] && $x['datum'] >= $dnes) $dodavky += $x['kg'];
            $suroviny[] = ['druh' => $d, 'nazev' => $nazev, 'sklad' => $sur[$d] ?? 0.0, 'dodavky' => $dodavky, 'potreba' => $potrebaOdbyt, 'bilance' => ($sur[$d] ?? 0.0) + $dodavky - $potrebaOdbyt];
        }

        return [
            'sezona' => ['nazev' => $c['nazev'], 'vyroba_tydne' => $c['vyroba_tydne'], 'stanky' => $c['stanky'], 'koeficienty' => $c['koeficienty'], 'dodavky' => $c['dodavky'], 'zakladni_rok' => $c['zakladni_rok']],
            'tydny' => $rows,
            'start_zasoba' => $start,
            'sklad' => ['sacky' => $sklad['sacky'], 'obaly' => $this->obalySkladem()],
            'zbyva' => ['vyroba' => $zbyvaVyroba, 'odbyt' => $zbyvaOdbyt],
            'suroviny' => $suroviny,
            'vygenerovano' => $now->format('Y-m-d H:i'),
        ];
    }

    // ------------------------------------------------------------------ data

    private function loadCatalog(): void
    {
        $pdo = DB::pdo();
        foreach ($pdo->query('SELECT p.sku, p.alt_sku, p.typ, p.aktivni, COALESCE(pt.is_nonstock,0) AS ns FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ') as $r) {
            $this->prod[(string)$r['sku']] = $r;
            if (($r['alt_sku'] ?? '') !== '') $this->alt[(string)$r['alt_sku']] = (string)$r['sku'];
        }
        foreach ($pdo->query('SELECT rodic_sku, potomek_sku, koeficient FROM bom') as $r) {
            $this->bom[(string)$r['rodic_sku']][] = [(string)$r['potomek_sku'], (float)$r['koeficient']];
        }
    }

    /**
     * Odbyt v sáčcích a kg natural suroviny po e-shopech a ISO týdnech.
     * @return array<string,array<int,array{sacky:float,kg:array<string,float>,volne:array<string,float>}>>
     */
    private function odbytPoTydnech(string $od, string $do): array
    {
        $st = DB::pdo()->prepare("SELECT eshop_source, duzp, sku, nazev, mnozstvi FROM polozky_eshop
            WHERE duzp BETWEEN ? AND ? AND mnozstvi > 0
              AND (code_raw IS NULL OR (code_raw NOT LIKE 'SHIPPING%' AND code_raw NOT LIKE 'BILLING%'))");
        $st->execute([$od, $do]);
        $out = [];
        foreach ($st as $r) {
            $w = (int)date('W', strtotime((string)$r['duzp']));
            $sku = (string)$r['sku'];
            $main = isset($this->prod[$sku]) ? $sku : ($this->alt[$sku] ?? null);
            [$s, $kg, $vol] = $main !== null ? $this->rozpad($main, (float)$r['mnozstvi']) : $this->mimoKatalog((string)$r['nazev'], (float)$r['mnozstvi']);
            $t = &$out[(string)$r['eshop_source']][$w];
            $t['sacky'] = ($t['sacky'] ?? 0.0) + $s;
            $t['kg'] = $t['kg'] ?? []; $t['volne'] = $t['volne'] ?? [];
            foreach ($kg as $d => $v) $t['kg'][$d] = ($t['kg'][$d] ?? 0.0) + $v;
            foreach ($vol as $d => $v) $t['volne'][$d] = ($t['volne'][$d] ?? 0.0) + $v;
            unset($t);
        }
        return $out;
    }

    /** Čistá výroba hotových sáčků (výrobní pohyby produktů) po ISO týdnech. @return array<int,float> */
    private function vyrobaPoTydnech(string $od, string $do): array
    {
        $st = DB::pdo()->prepare("SELECT DATE(pp.datum) AS d, SUM(pp.mnozstvi) AS ks FROM polozky_pohyby pp
            JOIN produkty p ON p.sku = pp.sku
            WHERE pp.typ_pohybu = 'vyroba' AND p.typ = 'produkt' AND pp.datum BETWEEN ? AND ?
            GROUP BY DATE(pp.datum)");
        $st->execute([$od . ' 00:00:00', $do . ' 23:59:59']);
        $out = [];
        foreach ($st as $r) { $w = (int)date('W', strtotime((string)$r['d'])); $out[$w] = ($out[$w] ?? 0.0) + (float)$r['ks']; }
        return $out;
    }

    /** Hotové sáčky skladem (kladné stavy produktů) a jejich náplň podle druhu. @return array{sacky:float,kg:array<string,float>} */
    private function sackuSkladem(?string $asOf): array
    {
        $stock = StockService::buildStockMap(null, $asOf);
        $s = 0.0; $kg = [];
        foreach ($stock as $sku => $q) {
            if ($q <= 0 || ($this->prod[$sku]['typ'] ?? '') !== 'produkt') continue;
            $s += $q;
            foreach ($this->natural($sku) as $d => $k) $kg[$d] = ($kg[$d] ?? 0.0) + $q * $k;
        }
        return ['sacky' => $s, 'kg' => $kg];
    }

    /** Suroviny skladem jako kg natural podle druhu (dochucené přes kusovník). @return array<string,float> */
    private function surovinySkladem(): array
    {
        $out = [];
        foreach (StockService::buildStockMap() as $sku => $q) {
            if ($q <= 0 || ($this->prod[$sku]['typ'] ?? '') !== 'surovina') continue;
            foreach ($this->natural($sku) as $d => $k) $out[$d] = ($out[$d] ?? 0.0) + $q * $k;
        }
        return $out;
    }

    /** Prázdné obaly skladem (kladné stavy, typ obal). */
    private function obalySkladem(): float
    {
        $s = 0.0;
        foreach (StockService::buildStockMap() as $sku => $q) {
            if ($q > 0 && ($this->prod[$sku]['typ'] ?? '') === 'obal') $s += $q;
        }
        return $s;
    }

    // ------------------------------------------------------------------ rozpad

    /** kg natural suroviny podle druhu v 1 jednotce položky. @return array<string,float> */
    private function natural(string $sku, int $hloubka = 0): array
    {
        if (isset(self::KORENY[$sku])) return [self::KORENY[$sku] => 1.0];
        if (isset($this->naturalCache[$sku])) return $this->naturalCache[$sku];
        $res = [];
        if ($hloubka < 6) {
            foreach ($this->bom[$sku] ?? [] as [$ch, $k]) {
                $t = $this->prod[$ch]['typ'] ?? '';
                if ($t === 'obal' || $t === 'etiketa') continue;
                foreach ($this->natural($ch, $hloubka + 1) as $d => $kg) $res[$d] = ($res[$d] ?? 0.0) + $k * $kg;
            }
        }
        return $this->naturalCache[$sku] = $res;
    }

    /** Položka z katalogu → [sáčky, kg náplně podle druhu, kg prodané volně]. @return array{0:float,1:array<string,float>,2:array<string,float>} */
    private function rozpad(string $sku, float $qty, int $hloubka = 0): array
    {
        $p = $this->prod[$sku] ?? null;
        if (!$p || $hloubka > 5) return [0.0, [], []];
        $mul = static fn(array $m, float $q): array => array_map(static fn($v) => $v * $q, $m);
        if ($p['typ'] === 'produkt') return [$qty, $mul($this->natural($sku), $qty), []];
        if ($p['typ'] === 'surovina') return [0.0, [], $mul($this->natural($sku), $qty)];
        if (!empty($p['ns'])) {
            $s = 0.0; $kg = []; $vol = [];
            foreach ($this->bom[$sku] ?? [] as [$ch, $k]) {
                [$a, $b, $cc] = $this->rozpad($ch, $qty * $k, $hloubka + 1);
                $s += $a;
                foreach ($b as $d => $v) $kg[$d] = ($kg[$d] ?? 0.0) + $v;
                foreach ($cc as $d => $v) $vol[$d] = ($vol[$d] ?? 0.0) + $v;
            }
            return [$s, $kg, $vol];
        }
        return [0.0, [], []];
    }

    /** Položka mimo katalog podle názvu. @return array{0:float,1:array<string,float>,2:array<string,float>} */
    private function mimoKatalog(string $nazev, float $qty): array
    {
        if (preg_match(self::NEVYROBNI, $nazev)) return [0.0, [], []];
        $d = preg_match('/kobyl|saran|locust/iu', $nazev) ? 'sarancata' : (preg_match('/cvrč|cvrc|cricket|chips/iu', $nazev) ? 'cvrcci' : 'cervici');
        if (preg_match('/\b1\s*kg\b|\(kg\)|moučk|mouka/iu', $nazev)) return [0.0, [], [$d => $qty]];
        $g = preg_match('/(\d+(?:[.,]\d+)?)\s*g\b/u', $nazev, $m) ? (float)str_replace(',', '.', $m[1]) : 20.0;
        $n = 1;
        if (preg_match('/^\s*(\d+)\s*ks\s*-/u', $nazev, $m)) $n = (int)$m[1];
        elseif (preg_match('/(\d+)\s*x\s*\d+\s*g/iu', $nazev, $m) && (int)$m[1] <= 6) $n = (int)$m[1];
        return [$qty * $n, [$d => $qty * $n * $g / 1000], []];
    }
}
