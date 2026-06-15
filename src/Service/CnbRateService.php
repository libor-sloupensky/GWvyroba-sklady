<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Denní kurzy ČNB (devizový trh) pro přepočet cizí měny na CZK.
 *
 * Zdroj: https://www.cnb.cz/.../denni_kurz.txt?date=DD.MM.YYYY
 * Formát (text, oddělovač "|"):
 *   14.05.2026 #92
 *   země|měna|množství|kód|kurz
 *   EMU|euro|1|EUR|24,305
 *
 * Kurz = "kolik CZK za 1 jednotku měny" = sloupec kurz / množství.
 * ČNB má kurzy jen pro pracovní dny; pro nepracovní den endpoint vrací
 * poslední platný (vyhlášený) kurz. Výsledky cachujeme do log/cnb/.
 */
final class CnbRateService
{
    private const URL = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';

    /** @var array<string,array<string,float>> cache po datu: [Y-m-d => [EUR => 24.305]] */
    private array $memo = [];
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = __DIR__ . '/../../log/cnb';
        @mkdir($this->cacheDir, 0775, true);
    }

    /**
     * Vrátí kurz (CZK za 1 jednotku měny) pro danou měnu a datum (Y-m-d),
     * nebo null pokud se nepodařilo zjistit.
     */
    public function getRate(string $currency, string $date): ?float
    {
        $currency = strtoupper(trim($currency));
        if ($currency === '' || $currency === 'CZK') {
            return $currency === 'CZK' ? 1.0 : null;
        }
        $table = $this->loadTable($date);
        return $table[$currency] ?? null;
    }

    /**
     * Načte (a nacachuje) tabulku kurzů pro datum. Vrací mapu kód→kurz.
     * @return array<string,float>
     */
    private function loadTable(string $date): array
    {
        if (isset($this->memo[$date])) {
            return $this->memo[$date];
        }
        $raw = $this->fetchRaw($date);
        $table = $raw !== null ? $this->parse($raw) : [];
        $this->memo[$date] = $table;
        return $table;
    }

    private function fetchRaw(string $date): ?string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return null;
        }
        $cacheFile = $this->cacheDir . '/' . date('Y-m-d', $ts) . '.txt';
        if (is_file($cacheFile)) {
            $cached = @file_get_contents($cacheFile);
            if (is_string($cached) && trim($cached) !== '') {
                return $cached;
            }
        }
        $url = self::URL . '?date=' . date('d.m.Y', $ts);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code !== 200 || stripos($body, '|') === false) {
            return null;
        }
        @file_put_contents($cacheFile, $body);
        return $body;
    }

    /**
     * @return array<string,float>
     */
    private function parse(string $raw): array
    {
        $table = [];
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        foreach ($lines as $i => $line) {
            if ($i < 2) {
                continue; // 1. řádek datum, 2. řádek hlavička
            }
            $cols = explode('|', $line);
            if (count($cols) < 5) {
                continue;
            }
            $amount = (float)str_replace([' ', ','], ['', '.'], $cols[2]);
            $code = strtoupper(trim($cols[3]));
            $rate = (float)str_replace([' ', ','], ['', '.'], $cols[4]);
            if ($code === '' || $amount <= 0 || $rate <= 0) {
                continue;
            }
            $table[$code] = $rate / $amount;
        }
        return $table;
    }
}
