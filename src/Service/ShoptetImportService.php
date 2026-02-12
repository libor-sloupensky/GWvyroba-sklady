<?php
declare(strict_types=1);

namespace App\Service;

use App\Controller\ImportController;
use App\Support\DB;

/**
 * Automatické stažení XML faktur z Shoptet adminu a import do aplikace.
 * Podporuje automatickou detekci měn z export stránky.
 */
final class ShoptetImportService
{
    private string $logFile;
    /** @var string[] Buffered log lines for web output */
    private array $logBuffer = [];

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../../log/import_xml_shoptet.log';
        @mkdir(dirname($this->logFile), 0775, true);
    }

    /**
     * @return string[] Log lines from this run
     */
    public function getLogBuffer(): array
    {
        return $this->logBuffer;
    }

    /**
     * Spustí auto-import. Zpracuje JEDEN eshop na jedno spuštění.
     * Používá file lock proti souběžným běhům.
     * @return array<string,array<string,mixed>> Výsledky per eshop
     */
    public function runAll(): array
    {
        // File lock - zabránit souběžným běhům
        $lockFile = sys_get_temp_dir() . '/gworm_shoptet_import.lock';
        $lockFp = @fopen($lockFile, 'c');
        if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
            $this->log('Jiný import právě běží, přeskakuji.', 'WARN');
            if ($lockFp) { fclose($lockFp); }
            return ['_locked' => ['skipped' => true]];
        }

        // Zapsat PID do lock souboru pro diagnostiku
        ftruncate($lockFp, 0);
        fwrite($lockFp, (string)getmypid() . ' ' . date('Y-m-d H:i:s'));
        fflush($lockFp);

        try {
            return $this->runAllInternal();
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    private function runAllInternal(): array
    {
        $eshops = $this->loadEshopsWithCredentials();
        if (empty($eshops)) {
            $this->log('Žádné eshopy s přihlašovacími údaji.', 'WARN');
            return [];
        }

        $results = [];
        $processed = false;

        foreach ($eshops as $row) {
            $eshopSource = (string)$row['eshop_source'];

            // Přeskočit eshopy, které už dnes byly úspěšně naimportovány
            if ($this->wasImportedToday($eshopSource)) {
                $this->log("=== Eshop {$eshopSource}: už byl dnes naimportován, přeskakuji ===");
                $results[$eshopSource] = ['skipped' => true];
                continue;
            }

            // Zpracovat pouze JEDEN eshop a skončit
            $this->log("=== Zpracovávám eshop: {$eshopSource} ===");
            @set_time_limit(300);
            try {
                $results[$eshopSource] = $this->runForEshop($row);
            } catch (\Throwable $e) {
                $this->log("CHYBA pro {$eshopSource}: " . $e->getMessage(), 'ERROR');
                $results[$eshopSource] = ['error' => $e->getMessage()];
                $this->saveHistory($eshopSource, '', null, null, null, 0, 0, 'error', $e->getMessage());
            }
            $processed = true;
            break; // Jeden eshop na jedno spuštění
        }

        if (!$processed) {
            $this->log('Všechny eshopy jsou dnes hotové.');
        }
        $this->log('=== Auto-import dokončen ===');
        return $results;
    }

    /**
     * Ověří přihlašovací údaje k Shoptet adminu.
     * @return array{ok:bool,message:string}
     */
    public function testLogin(string $adminUrl, string $email, string $password): array
    {
        $baseUrl = rtrim($adminUrl, '/');
        $loginUrl = $baseUrl . '/admin/login/';
        $cookieFile = tempnam(sys_get_temp_dir(), 'shoptet_test_');

        try {
            $loginPage = $this->httpRequest($loginUrl, 'GET', null, [], $cookieFile);
            $csrf = $this->extractCsrf($loginPage['body']) ?? '';
            if ($csrf === '') {
                return ['ok' => false, 'message' => 'CSRF token z login stránky nenalezen. Zkontrolujte Admin URL.'];
            }

            $loginHeaders = [
                'Content-Type: application/x-www-form-urlencoded',
                'X-Csrf-Token: ' . $csrf,
                'Referer: ' . $loginUrl,
                'Origin: ' . $baseUrl,
                'Accept-Language: cs,en;q=0.8',
            ];
            $loginResp = $this->httpRequest($loginUrl, 'POST', [
                'action' => 'login',
                'email' => $email,
                'password' => $password,
                '__csrf__' => $csrf,
            ], $loginHeaders, $cookieFile);

            if ($loginResp['status'] >= 400) {
                return ['ok' => false, 'message' => 'Přihlášení selhalo (HTTP ' . $loginResp['status'] . ').'];
            }

            // Po úspěšném loginu musí být v odpovědi session CSRF token
            $sessionCsrf = $this->extractCsrf($loginResp['body']);
            if (!$sessionCsrf) {
                // Zkusit ještě dashboard
                $dash = $this->httpRequest($baseUrl . '/admin/danove-doklady/', 'GET', null, [], $cookieFile);
                $sessionCsrf = $this->extractCsrf($dash['body']);
            }

            if (!$sessionCsrf) {
                // Přihlášení pravděpodobně selhalo (špatné heslo), Shoptet vrátí login stránku znovu
                return ['ok' => false, 'message' => 'Přihlášení nebylo úspěšné. Zkontrolujte e-mail a heslo.'];
            }

            return ['ok' => true, 'message' => 'Přihlášení úspěšné.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            if ($cookieFile && file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    /**
     * Spustí import pro jeden eshop. Automaticky detekuje měny.
     * @param array<string,mixed> $eshopRow Řádek z nastaveni_rady s credentials
     * @return array<string,mixed>
     */
    public function runForEshop(array $eshopRow): array
    {
        $eshopSource = (string)$eshopRow['eshop_source'];
        $baseUrl = rtrim((string)$eshopRow['admin_url'], '/');
        $email = (string)$eshopRow['admin_email'];
        $passwordEnc = (string)$eshopRow['admin_password_enc'];

        if ($baseUrl === '' || $email === '' || $passwordEnc === '') {
            throw new \RuntimeException('Neúplné přihlašovací údaje.');
        }

        $password = CryptoService::decrypt($passwordEnc);
        $loginUrl = $baseUrl . '/admin/login/';
        $exportUrl = $baseUrl . '/admin/export-faktur/';

        $cookieFile = tempnam(sys_get_temp_dir(), 'shoptet_cookies_');
        try {
            // 1. Login
            $this->log('');
            $this->log("Přihlašuji se na {$loginUrl}...");
            $loginPage = $this->httpRequest($loginUrl, 'GET', null, [], $cookieFile);
            $csrf = $this->extractCsrf($loginPage['body']) ?? '';
            if ($csrf === '') {
                throw new \RuntimeException('CSRF token z login stránky nenalezen.');
            }

            $loginHeaders = [
                'Content-Type: application/x-www-form-urlencoded',
                'X-Csrf-Token: ' . $csrf,
                'Referer: ' . $loginUrl,
                'Origin: ' . $baseUrl,
                'Accept-Language: cs,en;q=0.8',
            ];
            $loginResp = $this->httpRequest($loginUrl, 'POST', [
                'action' => 'login',
                'email' => $email,
                'password' => $password,
                '__csrf__' => $csrf,
            ], $loginHeaders, $cookieFile);

            if ($loginResp['status'] >= 400) {
                throw new \RuntimeException('Přihlášení selhalo, HTTP ' . $loginResp['status']);
            }

            // 2. Získat session CSRF
            $sessionCsrf = $this->extractCsrf($loginResp['body']);
            if (!$sessionCsrf) {
                $doklady = $this->httpRequest($baseUrl . '/admin/danove-doklady/', 'GET', null, [], $cookieFile);
                $sessionCsrf = $this->extractCsrf($doklady['body']);
            }
            if (!$sessionCsrf) {
                throw new \RuntimeException('Session CSRF token nenalezen po přihlášení.');
            }
            $this->log('Session CSRF OK');

            // 3. Navštívit export stránku a detekovat měny
            $exportPage = $this->httpRequest($exportUrl, 'GET', null, [], $cookieFile);
            $exportCsrf = $this->extractCsrf($exportPage['body']) ?: $sessionCsrf;

            $currencies = $this->detectCurrencies($exportPage['body']);
            if (empty($currencies)) {
                $this->log('Měny nebyly nalezeny, použiji výchozí CZK+EUR.', 'WARN');
                $currencies = [
                    ['id' => 1, 'label' => 'czk'],
                    ['id' => 9, 'label' => 'eur'],
                ];
            }
            $this->log('Detekované měny: ' . implode(', ', array_column($currencies, 'label')));

            // 4. Období: od 1. dne minulého měsíce do dneška
            $now = new \DateTimeImmutable();
            $firstOfLastMonth = $now->modify('first day of last month');
            $from = $firstOfLastMonth->format('d.m.Y');
            $to = $now->format('d.m.Y');
            $this->log("Období: {$from} - {$to}");
            $this->log('');

            // 5. Stáhnout a importovat pro každou měnu
            $importCtrl = new ImportController();
            $totalResults = ['currencies' => []];

            foreach ($currencies as $idx => $cur) {
                $label = $cur['label'];
                $currencyId = $cur['id'];
                if ($idx > 0) { $this->log(''); }
                $this->log("Stahuji export {$label} (currencyId={$currencyId})...");

                try {
                    $xmlContent = $this->downloadExport(
                        $exportUrl,
                        $baseUrl,
                        $exportCsrf,
                        $cookieFile,
                        $from,
                        $to,
                        $currencyId
                    );

                    if (empty(trim($xmlContent))) {
                        $this->log("Export {$label}: prázdná odpověď, přeskakuji.", 'WARN');
                        $this->saveHistory($eshopSource, strtoupper($label), $firstOfLastMonth->format('Y-m-d'), $now->format('Y-m-d'), null, 0, 0, 'warning', 'Prázdná odpověď (žádné faktury)');
                        continue;
                    }

                    // Ověřit, že odpověď je XML
                    if (stripos($xmlContent, '<?xml') === false && stripos($xmlContent, '<dat:dataPack') === false) {
                        if (stripos($xmlContent, '<html') !== false) {
                            // Extrahovat <title> pro diagnostiku
                            $htmlTitle = '';
                            if (preg_match('#<title[^>]*>(.*?)</title>#si', $xmlContent, $tm)) {
                                $htmlTitle = ' Title: ' . trim(strip_tags($tm[1]));
                            }
                            $snippet = substr(strip_tags($xmlContent), 0, 200);
                            $this->log("HTML odpověď ({$label}):{$htmlTitle} Snippet: {$snippet}", 'DEBUG');
                            throw new \RuntimeException("Export {$label} vrátil HTML místo XML.{$htmlTitle}");
                        }
                    }

                    $result = $importCtrl->importPohodaFromStringCli($eshopSource, $xmlContent, $label);
                    $this->log("Import {$label} OK: doklady={$result['doklady']}, polozky={$result['polozky']}, batch={$result['batch']}");

                    $this->saveHistory(
                        $eshopSource,
                        strtoupper($label),
                        $firstOfLastMonth->format('Y-m-d'),
                        $now->format('Y-m-d'),
                        $result['batch'],
                        $result['doklady'],
                        $result['polozky'],
                        'ok',
                        null
                    );

                    $totalResults['currencies'][$label] = $result;
                } catch (\Throwable $e) {
                    $isEmptyExport = str_contains($e->getMessage(), 'neobsahuje žádné doklady');
                    if ($isEmptyExport) {
                        $this->log("Export {$label}: žádné doklady pro toto období, přeskakuji.", 'WARN');
                        $this->saveHistory(
                            $eshopSource,
                            strtoupper($label),
                            $firstOfLastMonth->format('Y-m-d'),
                            $now->format('Y-m-d'),
                            null,
                            0,
                            0,
                            'warning',
                            'Žádné doklady pro toto období.'
                        );
                        $totalResults['currencies'][$label] = ['doklady' => 0, 'polozky' => 0, 'batch' => null];
                    } else {
                        $msg = "Import {$label} selhal: " . $e->getMessage();
                        $this->log($msg, 'ERROR');
                        $this->saveHistory(
                            $eshopSource,
                            strtoupper($label),
                            $firstOfLastMonth->format('Y-m-d'),
                            $now->format('Y-m-d'),
                            null,
                            0,
                            0,
                            'error',
                            $e->getMessage()
                        );
                        $totalResults['currencies'][$label] = ['error' => $e->getMessage()];
                    }
                }
            }

            return $totalResults;
        } finally {
            if ($cookieFile && file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    /**
     * Detekuje dostupné měny z HTML export stránky.
     * Parsuje <select name="currencyId"> a vrátí pole měn.
     * @return array<int,array{id:int,label:string}>
     */
    public function detectCurrencies(string $html): array
    {
        // Najdi <select name="currencyId"> ... </select>
        if (!preg_match('#<select[^>]*name=["\']currencyId["\'][^>]*>(.*?)</select>#si', $html, $selectMatch)) {
            return [];
        }
        $selectHtml = $selectMatch[1];

        // Najdi všechny <option value="X">Label</option>
        $currencies = [];
        if (preg_match_all('#<option[^>]*value=["\'](\d+)["\'][^>]*>(.*?)</option>#si', $selectHtml, $optionMatches, PREG_SET_ORDER)) {
            // Mapování známých Shoptet currency ID na ISO kódy
            $knownCurrencies = [
                1 => 'czk',
                2 => 'usd',
                3 => 'gbp',
                9 => 'eur',
                10 => 'pln',
                11 => 'huf',
                12 => 'ron',
                13 => 'hrk',
                14 => 'chf',
            ];

            foreach ($optionMatches as $m) {
                $id = (int)$m[1];
                $text = trim(strip_tags($m[2]));
                if ($id <= 0) {
                    continue;
                }

                // Určit label z textu option nebo ze známé mapy
                $label = $knownCurrencies[$id] ?? '';
                if ($label === '' && $text !== '') {
                    // Zkusit z textu (Shoptet typicky zobrazuje "CZK - Česká koruna")
                    if (preg_match('/^([A-Z]{3})/i', $text, $codeMatch)) {
                        $label = strtolower($codeMatch[1]);
                    } else {
                        $label = strtolower(preg_replace('/[^a-z0-9]/i', '', $text));
                    }
                }

                if ($label !== '') {
                    $currencies[] = ['id' => $id, 'label' => $label];
                }
            }
        }

        return $currencies;
    }

    /**
     * Stáhne XML export z Shoptetu pro danou měnu a období.
     */
    private function downloadExport(
        string $exportUrl,
        string $baseUrl,
        string $csrf,
        string $cookieFile,
        string $from,
        string $to,
        int $currencyId
    ): string {
        $body = [
            'action'                   => 'export',
            'documentType'             => 'invoice',
            'codeFrom'                 => '',
            'codeUntil'                => '',
            'orderCodeFrom'            => '',
            'orderCodeUntil'           => '',
            'dateFrom'                 => $from,
            'dateUntil'                => $to,
            'taxDateFrom'              => '',
            'taxDateUntil'             => '',
            'currencyId'               => $currencyId,
            'exportAsForeignCurrency'  => '',
            'exportWithHistoricalVat'  => '',
            'format'                   => 'xml.stormware.cz',
            'linkProformaInvoices'     => '',
            'linkProformaInvoicesInit' => '',
            'order'                    => '',
            '__csrf__'                 => $csrf,
        ];
        $exportHeaders = [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Csrf-Token: ' . $csrf,
            'Origin: ' . $baseUrl,
            'Referer: ' . $exportUrl,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];

        $resp = $this->httpRequest($exportUrl, 'POST', $body, $exportHeaders, $cookieFile);

        if ($resp['status'] >= 400) {
            $snippet = substr(trim($resp['body']), 0, 400);
            throw new \RuntimeException("Export selhal, HTTP {$resp['status']} Snippet: {$snippet}");
        }

        return $resp['body'];
    }

    /**
     * Uloží záznam do import_history.
     */
    private function saveHistory(
        string $eshopSource,
        string $mena,
        ?string $datumOd,
        ?string $datumDo,
        ?string $batchId,
        int $doklady,
        int $polozky,
        string $status,
        ?string $message
    ): void {
        try {
            $pdo = DB::pdo();
            $st = $pdo->prepare('INSERT INTO import_history (eshop_source,mena,datum_od,datum_do,batch_id,doklady,polozky,status,message) VALUES (?,?,?,?,?,?,?,?,?)');
            $st->execute([$eshopSource, $mena, $datumOd, $datumDo, $batchId, $doklady, $polozky, $status, $message]);
        } catch (\Throwable $e) {
            $this->log('Zápis do import_history selhal: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Načte eshopy s vyplněnými přihlašovacími údaji.
     * @return array<int,array<string,mixed>>
     */
    private function loadEshopsWithCredentials(): array
    {
        $pdo = DB::pdo();
        return $pdo->query("SELECT * FROM nastaveni_rady WHERE admin_url IS NOT NULL AND admin_url != '' AND admin_email IS NOT NULL AND admin_email != '' AND admin_password_enc IS NOT NULL AND admin_password_enc != '' ORDER BY eshop_source")->fetchAll();
    }

    /**
     * Zjistí, zda eshop už byl dnes úspěšně naimportován,
     * nebo zda překročil max. počet pokusů (3 chyby = stop na dnes).
     */
    private function wasImportedToday(string $eshopSource): bool
    {
        $pdo = DB::pdo();
        // Úspěšný import dnes?
        $ok = $pdo->prepare("SELECT COUNT(*) FROM import_history WHERE eshop_source = ? AND status = 'ok' AND DATE(created_at) = CURDATE() AND doklady > 0");
        $ok->execute([$eshopSource]);
        if ((int)$ok->fetchColumn() > 0) {
            return true;
        }
        // Příliš mnoho chyb dnes? (max 3 pokusy)
        $err = $pdo->prepare("SELECT COUNT(*) FROM import_history WHERE eshop_source = ? AND status = 'error' AND DATE(created_at) = CURDATE()");
        $err->execute([$eshopSource]);
        if ((int)$err->fetchColumn() >= 3) {
            $this->log("Eshop {$eshopSource}: příliš mnoho chyb dnes (3+), přeskakuji do zítřka.", 'WARN');
            return true;
        }
        return false;
    }

    private function log(string $msg, string $level = 'INFO'): void
    {
        if ($msg === '') {
            file_put_contents($this->logFile, "\n", FILE_APPEND);
            $this->logBuffer[] = '';
            if (PHP_SAPI === 'cli') { echo "\n"; }
            return;
        }
        $line = sprintf("[%s] [%s] %s", date('Y-m-d H:i:s'), $level, $msg);
        file_put_contents($this->logFile, $line . "\n", FILE_APPEND);
        $this->logBuffer[] = $line;
        if (PHP_SAPI === 'cli') {
            echo $line . "\n";
        }
    }

    private function extractCsrf(string $html): ?string
    {
        if (preg_match('#shoptet\\.csrf\\.token\\s*=\\s*"([^"]+)"#', $html, $m)) {
            return $m[1];
        }
        if (preg_match('#name="__csrf__"[^>]*value="([^"]+)"#', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @return array{status:int,headers:string,body:string}
     */
    private function httpRequest(string $url, string $method = 'GET', ?array $data = null, array $headers = [], ?string $cookieFile = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ShoptetAutoImport/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => 120,
        ];
        if ($cookieFile) {
            $opts[CURLOPT_COOKIEJAR] = $cookieFile;
            $opts[CURLOPT_COOKIEFILE] = $cookieFile;
        }
        $method = strtoupper($method);
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $data ? http_build_query($data) : '';
        } elseif ($method !== 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($data) {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
            }
        }
        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP request failed: {$err}");
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $rawHeaders = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);
        return ['status' => (int)$status, 'headers' => $rawHeaders, 'body' => $body];
    }
}
