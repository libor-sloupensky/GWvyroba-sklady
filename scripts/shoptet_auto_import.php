<?php
declare(strict_types=1);
/**
 * Automatické stažení faktur ze Shoptetu (XML Pohoda) a import do aplikace.
 * Spouštění: php scripts/shoptet_auto_import.php
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Controller\ImportController;

session_start();

$baseUrl   = 'https://www.wormup.com';
$loginUrl  = $baseUrl . '/admin/login/';
$exportUrl = $baseUrl . '/admin/export-faktur/';
$email     = 'libor@wormup.com';
$password  = 'ozov-uda-jecuv';
$eshop     = 'wormup.com'; // eshop_source pro import
$logFile   = __DIR__ . '/../log/import_xml_shoptet.log';

@mkdir(dirname($logFile), 0775, true);

function logLine(string $msg, string $level = 'INFO'): void
{
    global $logFile;
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $msg);
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

/**
 * Jednoduchý HTTP klient s cookies.
 * @return array{status:int,headers:string,body:string}
 */
function httpRequest(string $url, string $method = 'GET', ?array $data = null, array $headers = [], ?string $cookieFile = null): array
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
        throw new RuntimeException("HTTP request failed: {$err}");
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $rawHeaders = substr($resp, 0, $headerSize);
    $body = substr($resp, $headerSize);
    return ['status' => (int)$status, 'headers' => $rawHeaders, 'body' => $body];
}

function extractCsrf(string $html): ?string
{
    if (preg_match('#shoptet\\.csrf\\.token\\s*=\\s*"([^"]+)"#', $html, $m)) {
        return $m[1];
    }
    if (preg_match('#name="__csrf__"[^>]*value="([^"]+)"#', $html, $m)) {
        return $m[1];
    }
    return null;
}

function ensureDate(string $d): string
{
    // Shoptet akceptuje dd.mm.YYYY
    $dt = new DateTimeImmutable($d);
    return $dt->format('d.m.Y');
}

$cookieFile = tempnam(sys_get_temp_dir(), 'shoptet_cookies_');
try {
    $loginPage = httpRequest($loginUrl, 'GET', null, [], $cookieFile);
    $csrf = extractCsrf($loginPage['body']) ?? '';
    if ($csrf === '') {
        throw new RuntimeException('CSRF token z login stránky nenalezen.');
    }
    $loginHeaders = [
        'Content-Type: application/x-www-form-urlencoded',
        'X-Csrf-Token: ' . $csrf,
        'Referer: ' . $loginUrl,
        'Origin: ' . $baseUrl,
        'Accept-Language: cs,en;q=0.8',
    ];
    $loginResp = httpRequest($loginUrl, 'POST', [
        'action' => 'login',
        'email' => $email,
        'password' => $password,
        '__csrf__' => $csrf, // dle HAR z prohlížeče
    ], $loginHeaders, $cookieFile);
    if ($loginResp['status'] >= 400) {
        $snippet = substr(trim($loginResp['body']), 0, 400);
        throw new RuntimeException('Přihlášení selhalo, HTTP ' . $loginResp['status'] . ' Snippet: ' . $snippet);
    }
    // navštiv přehled dokladů (kvůli cookies jako previousUrl/NOCACHE)
    httpRequest($baseUrl . '/admin/danove-doklady/', 'GET', null, [], $cookieFile);
    // načti export stránku a vytáhni aktuální CSRF pro export
    $exportPage = httpRequest($exportUrl, 'GET', null, [], $cookieFile);
    $exportCsrf = extractCsrf($exportPage['body']) ?? $csrf;
    // zkus obnovit CSRF token
    try {
        $refresh = httpRequest($baseUrl . '/admin/csrf-refresh/', 'GET', null, [], $cookieFile);
        $refToken = extractCsrf($refresh['body']);
        if ($refToken) {
            $exportCsrf = $refToken;
        }
    } catch (\Throwable) {
        // ignoruj, použij exportCsrf
    }

    $from = ensureDate('yesterday');
    $to = ensureDate('today');
    $imports = [
        ['currencyId' => 1, 'label' => 'czk'],
        ['currencyId' => 9, 'label' => 'eur'],
    ];
    $importCtrl = new ImportController();
    foreach ($imports as $imp) {
        $label = $imp['label'];
        $body = [
            'action' => 'export',
            'documentType' => 'invoice',
            'dateFrom' => $from,
            'dateUntil' => $to,
            'currencyId' => $imp['currencyId'],
            'format' => 'xml.stormware.cz',
            'linkProformaInvoicesInit' => '',
            '__csrf__' => $exportCsrf,
            'buttonAction' => 'export',
        ];
        $exportHeaders = [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Csrf-Token: ' . $exportCsrf,
            'Origin: ' . $baseUrl,
            'Referer: ' . $baseUrl . '/admin/danove-doklady/',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: cs,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ];
        logLine("Stahuji export {$label} {$from} - {$to}");
        $exportResp = httpRequest($exportUrl, 'POST', $body, $exportHeaders, $cookieFile);
        if ($exportResp['status'] >= 400) {
            $snippet = substr(trim($exportResp['body']), 0, 400);
            throw new RuntimeException("Export {$label} selhal, HTTP {$exportResp['status']} Snippet: {$snippet}");
        }
        $xmlContent = $exportResp['body'];
        if (stripos($exportResp['headers'], 'application/xml') === false && stripos($exportResp['headers'], 'text/xml') === false) {
            // Shoptet může vrátit HTML s chybou
            if (stripos($xmlContent, '<html') !== false) {
                throw new RuntimeException("Export {$label} vrátil HTML místo XML (pravděpodobně přihlášení/token).");
            }
        }
        $tmpFile = __DIR__ . '/../xml/shoptet_' . $label . '_' . date('Ymd_His') . '.xml';
        file_put_contents($tmpFile, $xmlContent);
        logLine("Staženo do {$tmpFile}");
        $result = $importCtrl->importPohodaFromStringCli($eshop, $xmlContent);
        logLine("Import {$label} OK: doklady={$result['doklady']}, polozky={$result['polozky']}, batch={$result['batch']}");
        if (!empty($result['missingSku'])) {
            logLine("Chybějící SKU: " . implode(', ', $result['missingSku']), 'WARN');
        }
        @unlink($tmpFile);
    }
    logLine('Hotovo.');
} catch (Throwable $e) {
    logLine('CHYBA: ' . $e->getMessage(), 'ERROR');
    exit(1);
} finally {
    if ($cookieFile && file_exists($cookieFile)) {
        @unlink($cookieFile);
    }
}
exit(0);
