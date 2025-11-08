<?php
namespace App\Controller;

use App\Support\DB;

final class ImportController
{
    public function form(): void
    {
        $this->requireAdmin();
        $this->renderImportForm();
    }

    public function importPohoda(): void
    {
        $this->requireAdmin();
        $eshop = trim((string)($_POST['eshop'] ?? ''));
        if (!isset($_FILES['xml'])) {
            $this->renderImportForm(['error'=>'Vyberte XML soubor.', 'selectedEshop'=>$eshop]);
            return;
        }
        if ($eshop === '' || !$this->isKnownEshop($eshop)) {
            $this->renderImportForm(['error'=>'Zvolte e-shop z nastaveného seznamu.', 'selectedEshop'=>$eshop]);
            return;
        }
        $tmp = $_FILES['xml']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            $this->renderImportForm(['error'=>'Soubor nebyl nahrán.', 'selectedEshop'=>$eshop]);
            return;
        }
        $xml = file_get_contents($tmp);
        if ($xml === false) {
            $this->renderImportForm(['error'=>'Nelze číst soubor.', 'selectedEshop'=>$eshop]);
            return;
        }
        $xml = $this->ensureUtf8($xml);
        try {
            $series = $this->loadSeries($eshop);
            $documents = $this->parsePohodaXml($xml);
            if (empty($documents)) {
                throw new \RuntimeException('XML neobsahuje žádné doklady.');
            }
            $batch = date('YmdHis');
            [$docCount, $itemCount, $missingSku] = $this->persistInvoices($eshop, $series, $documents, $batch);
            $eshops = $this->loadEshops();
            $outstanding = $this->collectOutstandingMissingSku();
            $this->render('import_result.php', [
                'title'=>'Import dokončen',
                'batch'=>$batch,
                'summary'=>['doklady'=>$docCount,'polozky'=>$itemCount],
                'missingSku'=>$missingSku,
                'notice'=>null,
                'eshops'=>$eshops,
                'selectedEshop'=>$eshop,
                'outstandingMissing'=>$outstanding['groups'],
                'outstandingDays'=>$outstanding['days']
            ]);
        } catch (\Throwable $e) {
            $this->renderImportForm(['error'=>$e->getMessage(), 'selectedEshop'=>$eshop]);
        }
    }

    public function deleteLastBatch(): void
    {
        $this->requireAdmin();
        $eshop = trim((string)($_POST['eshop'] ?? ''));
        if ($eshop === '' || !$this->isKnownEshop($eshop)) {
            $this->renderImportForm(['error'=>'Vyberte e-shop z nabídky.', 'selectedEshop'=>$eshop]);
            return;
        }
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT import_batch_id FROM doklady_eshop WHERE eshop_source=? ORDER BY import_batch_id DESC LIMIT 1");
        $st->execute([$eshop]);
        $row = $st->fetch();
        if (!$row) {
            $this->renderImportForm(['error'=>'Pro tento e-shop není co mazat.', 'selectedEshop'=>$eshop]);
            return;
        }
        $batch = $row['import_batch_id'];
        $pdo->prepare("DELETE FROM polozky_eshop WHERE import_batch_id=? AND eshop_source=?")->execute([$batch,$eshop]);
        $pdo->prepare("DELETE FROM doklady_eshop WHERE import_batch_id=? AND eshop_source=?")->execute([$batch,$eshop]);
        $this->renderImportForm(['message'=>"Smazán poslední import batch={$batch}"]);
    }

    public function reportMissingSku(): void
    {
        $this->requireAdmin();
        $data = $this->collectOutstandingMissingSku();
        $this->render('report_missing_sku.php', [
            'title'=>'Chybějící SKU',
            'rows'=>$data['rows'],
            'grouped'=>$data['groups'],
            'days'=>$data['days']
        ]);
    }

    private function loadSeries(string $eshop): array
    {
        $st = DB::pdo()->prepare('SELECT prefix,cislo_od,cislo_do FROM nastaveni_rady WHERE eshop_source=? LIMIT 1');
        $st->execute([$eshop]);
        $row = $st->fetch();
        if (!$row) {
            throw new \RuntimeException('Pro tento e-shop není nastavena fakturační řada.');
        }
        return [
            'prefix' => trim((string)$row['prefix']),
            'od' => trim((string)$row['cislo_od']),
            'do' => trim((string)$row['cislo_do']),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $documents
     * @return array{0:int,1:int,2:array<int,array<string,string>>}
     */
    private function persistInvoices(string $eshop, array $series, array $documents, string $batch): array
    {
        $pdo = DB::pdo();
        $importTs = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ignorePatterns = $this->loadIgnorePatterns();
        $docInsert = $pdo->prepare('INSERT INTO doklady_eshop (eshop_source,cislo_dokladu,typ_dokladu,platba_typ,dopravce_ids,cislo_objednavky,sym_var,datum_vystaveni,duzp,splatnost,mena_puvodni,kurz_na_czk,kontakt_id,import_batch_id,import_ts) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL,?,?)');
        $itemInsert = $pdo->prepare('INSERT INTO polozky_eshop (eshop_source,cislo_dokladu,code_raw,stock_ids_raw,sku,ean,nazev,mnozstvi,merna_jednotka,cena_jedn_mena,cena_jedn_czk,mena_puvodni,sazba_dph_hint,plati_dph,sleva_procento,duzp,import_batch_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $deleteItems = $pdo->prepare('DELETE FROM polozky_eshop WHERE eshop_source=? AND cislo_dokladu=?');
        $deleteDoc = $pdo->prepare('DELETE FROM doklady_eshop WHERE eshop_source=? AND cislo_dokladu=?');
        $docCount = 0;
        $itemCount = 0;
        $missingSku = [];
        $pdo->beginTransaction();
        try {
            foreach ($documents as $doc) {
                $docNumber = (string)$doc['cislo_dokladu'];
                $this->validateSeriesNumber($series, $docNumber);
                $duzp = $this->normalizeDate($doc['duzp'] ?? null);
                if ($duzp === null) {
                    throw new \RuntimeException("Doklad {$docNumber} nemá platné DUZP.");
                }
                $dateIssue = $this->normalizeDate($doc['datum_vystaveni'] ?? null);
                $dueDate = $this->normalizeDate($doc['splatnost'] ?? null);
                $deleteItems->execute([$eshop, $docNumber]);
                $deleteDoc->execute([$eshop, $docNumber]);
                $docInsert->execute([
                    $eshop,
                    $docNumber,
                    $this->emptyToNull($doc['typ_dokladu'] ?? null),
                    $this->emptyToNull($doc['platba_typ'] ?? null),
                    $this->emptyToNull($doc['dopravce_ids'] ?? null),
                    $this->emptyToNull($doc['cislo_objednavky'] ?? null),
                    $this->emptyToNull($doc['sym_var'] ?? null),
                    $dateIssue,
                    $duzp,
                    $dueDate,
                    $this->emptyToNull($doc['mena'] ?? null),
                    $this->normalizeDecimal($doc['kurz'] ?? null),
                    $batch,
                    $importTs,
                ]);
                $docCount++;
                foreach ($doc['items'] as $item) {
                    $code = (string)($item['code'] ?? '');
                    $stock = (string)($item['stock'] ?? '');
                    $sku = $item['sku'] ?? $stock;
                    $quantity = $this->normalizeDecimal($item['quantity'] ?? null);
                    if ($quantity === null) {
                        $quantity = '0';
                    }
                    $itemInsert->execute([
                        $eshop,
                        $docNumber,
                        $code !== '' ? $code : null,
                        $stock !== '' ? $stock : null,
                        $sku !== '' ? $sku : null,
                        $this->emptyToNull($item['ean'] ?? null),
                        $this->emptyToNull($item['text'] ?? null),
                        $quantity,
                        $this->emptyToNull($item['unit'] ?? null),
                        $this->normalizeDecimal($item['unit_price_foreign'] ?? null),
                        $this->normalizeDecimal($item['unit_price_home'] ?? null),
                        $this->emptyToNull($doc['mena'] ?? null),
                        $this->emptyToNull($item['rate_vat'] ?? null),
                        $item['pay_vat'] ?? null,
                        $this->normalizeDecimal($item['discount'] ?? null),
                        $duzp,
                        $batch,
                    ]);
                    $itemCount++;
                    if ($code === '' && $stock === '') {
                        if ($this->matchesIgnorePatterns($ignorePatterns, [
                            (string)($item['text'] ?? ''),
                            (string)$docNumber,
                        ])) {
                            continue;
                        }
                        $missingSku[] = [
                            'duzp' => $duzp,
                            'eshop_source' => $eshop,
                            'cislo_dokladu' => $docNumber,
                            'nazev' => (string)($item['text'] ?? ''),
                            'mnozstvi' => (string)($item['quantity'] ?? ''),
                            'code_raw' => $code,
                        ];
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return [$docCount, $itemCount, $missingSku];
    }

    /**
     * @return array{days:int,groups:array<string,array<int,array<string,mixed>>>,rows:array<int,array<string,mixed>>}
     */
    private function collectOutstandingMissingSku(): array
    {
        $pdo = DB::pdo();
        $glob = $pdo->query('SELECT okno_pro_prumer_dni FROM nastaveni_global WHERE id=1')->fetch() ?: [];
        $days = max(1, (int)($glob['okno_pro_prumer_dni'] ?? 30));
        $since = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d');
        $patterns = $this->loadIgnorePatterns();
        $stmt = $pdo->prepare("SELECT duzp, eshop_source, cislo_dokladu, nazev, mnozstvi, code_raw, stock_ids_raw FROM polozky_eshop WHERE duzp>=? AND ((code_raw IS NULL OR code_raw='') AND (stock_ids_raw IS NULL OR stock_ids_raw='')) ORDER BY eshop_source, duzp DESC");
        $stmt->execute([$since]);
        $groups = [];
        $flat = [];
        $seen = [];
        foreach ($stmt as $r) {
            if ($this->matchesIgnorePatterns($patterns, [
                (string)($r['code_raw'] ?? ''),
                (string)($r['nazev'] ?? ''),
                (string)($r['cislo_dokladu'] ?? ''),
            ])) {
                continue;
            }
            $nameKey = trim((string)$r['nazev']);
            if ($nameKey === '') {
                $nameKey = trim((string)$r['code_raw']);
            }
            if ($nameKey === '') {
                $nameKey = trim((string)$r['cislo_dokladu'] . '|' . (string)$r['mnozstvi']);
            }
            $key = mb_strtolower((string)$r['eshop_source'] . '|' . $nameKey, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $eshop = (string)$r['eshop_source'];
            if (!isset($groups[$eshop])) {
                $groups[$eshop] = [];
            }
            $groups[$eshop][] = $r;
            $flat[] = $r;
        }
        return ['days'=>$days,'groups'=>$groups,'rows'=>$flat];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parsePohodaXml(string $xml): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException('Soubor není platné Pohoda XML.');
        }
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('dat', 'http://www.stormware.cz/schema/version_2/data.xsd');
        $xpath->registerNamespace('inv', 'http://www.stormware.cz/schema/version_2/invoice.xsd');
        $xpath->registerNamespace('typ', 'http://www.stormware.cz/schema/version_2/type.xsd');
        $docs = [];
        foreach ($xpath->query('//inv:invoice') as $invoiceNode) {
            $header = $this->firstNode($xpath, './inv:invoiceHeader', $invoiceNode);
            if (!$header) {
                continue;
            }
            $number = $this->xpathValue($xpath, './inv:number/typ:numberRequested', $header);
            if ($number === '') {
                continue;
            }
            $doc = [
                'cislo_dokladu' => $number,
                'typ_dokladu' => $this->xpathValue($xpath, './inv:invoiceType', $header),
                'platba_typ' => $this->xpathValue($xpath, './inv:paymentType/typ:paymentType', $header),
                'dopravce_ids' => $this->collectList($xpath, './inv:carrier/typ:ids', $header),
                'cislo_objednavky' => $this->xpathValue($xpath, './inv:numberOrder', $header),
                'sym_var' => $this->xpathValue($xpath, './inv:symVar', $header),
                'datum_vystaveni' => $this->xpathValue($xpath, './inv:date', $header),
                'duzp' => $this->xpathValue($xpath, './inv:dateTax', $header),
                'splatnost' => $this->xpathValue($xpath, './inv:dateDue', $header),
                'mena' => $this->xpathValue($xpath, './inv:homeCurrency/typ:currency', $header),
                'kurz' => $this->xpathValue($xpath, './inv:homeCurrency/typ:rate', $header),
                'items' => [],
            ];
            if ($doc['mena'] === '') {
                $doc['mena'] = $this->xpathValue($xpath, './inv:foreignCurrency/typ:currency', $header);
            }
            foreach ($xpath->query('./inv:invoiceDetail/inv:invoiceItem', $invoiceNode) as $itemNode) {
                $payVatRaw = $this->xpathValue($xpath, './inv:payVAT', $itemNode);
                $doc['items'][] = [
                    'text' => $this->xpathValue($xpath, './inv:text', $itemNode),
                    'code' => $this->xpathValue($xpath, './inv:code', $itemNode),
                    'stock' => $this->xpathValue($xpath, './inv:stockItem/typ:stockItem/typ:ids', $itemNode),
                    'sku' => $this->xpathValue($xpath, './inv:stockItem/typ:stockItem/typ:ids', $itemNode),
                    'ean' => $this->xpathValue($xpath, './inv:stockItem/typ:stockItem/typ:ean', $itemNode),
                    'quantity' => $this->xpathValue($xpath, './inv:quantity', $itemNode),
                    'unit' => $this->xpathValue($xpath, './inv:unit', $itemNode),
                    'unit_price_foreign' => $this->xpathValue($xpath, './inv:foreignCurrency/typ:unitPrice', $itemNode),
                    'unit_price_home' => $this->xpathValue($xpath, './inv:homeCurrency/typ:unitPrice', $itemNode),
                    'rate_vat' => $this->xpathValue($xpath, './inv:rateVAT', $itemNode),
                    'pay_vat' => $payVatRaw === '' ? null : ($payVatRaw === 'true' ? 1 : 0),
                    'discount' => $this->xpathValue($xpath, './inv:discountPercentage', $itemNode),
                ];
            }
            $docs[] = $doc;
        }
        libxml_clear_errors();
        return $docs;
    }

    private function firstNode(\DOMXPath $xpath, string $expression, \DOMNode $context): ?\DOMNode
    {
        $nodes = $xpath->query($expression, $context);
        return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function xpathValue(\DOMXPath $xpath, string $expression, \DOMNode $context): string
    {
        $nodes = $xpath->query($expression, $context);
        if (!$nodes || $nodes->length === 0) {
            return '';
        }
        $value = trim((string)$nodes->item(0)?->nodeValue);
        return $value;
    }

    private function collectList(\DOMXPath $xpath, string $expression, \DOMNode $context): ?string
    {
        $nodes = $xpath->query($expression, $context);
        if (!$nodes || $nodes->length === 0) {
            return null;
        }
        $values = [];
        foreach ($nodes as $node) {
            $val = trim((string)$node->nodeValue);
            if ($val !== '') {
                $values[] = $val;
            }
        }
        return empty($values) ? null : implode(',', array_unique($values));
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDecimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = str_replace(' ', '', str_replace(',', '.', trim($value)));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        return $value;
    }

    private function emptyToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function validateSeriesNumber(array $series, string $number): void
    {
        $prefix = $series['prefix'] ?? '';
        if ($prefix !== '' && strpos($number, $prefix) !== 0) {
            throw new \RuntimeException("Doklad {$number} neodpovídá prefixu {$prefix}.");
        }
        $numericPart = $number;
        if ($prefix !== '' && strpos($numericPart, $prefix) === 0) {
            $numericPart = substr($numericPart, strlen($prefix));
        }
        $from = $series['od'] ?? '';
        $to = $series['do'] ?? '';
        if ($from !== '' && $this->compareDocumentNumbers($numericPart, $from) < 0) {
            throw new \RuntimeException("Doklad {$number} je mimo nastavený rozsah (od {$from}).");
        }
        if ($to !== '' && $this->compareDocumentNumbers($numericPart, $to) > 0) {
            throw new \RuntimeException("Doklad {$number} je mimo nastavený rozsah (do {$to}).");
        }
    }

    private function compareDocumentNumbers(string $a, string $b): int
    {
        $digitsA = preg_replace('/\D+/', '', $a);
        $digitsB = preg_replace('/\D+/', '', $b);
        if ($digitsA !== '' && $digitsB !== '') {
            $digitsA = ltrim($digitsA, '0');
            $digitsB = ltrim($digitsB, '0');
            $digitsA = $digitsA === '' ? '0' : $digitsA;
            $digitsB = $digitsB === '' ? '0' : $digitsB;
            $lenCompare = strlen($digitsA) <=> strlen($digitsB);
            if ($lenCompare !== 0) {
                return $lenCompare;
            }
            return strcmp($digitsA, $digitsB);
        }
        return strcmp($a, $b);
    }

    private function renderImportForm(array $vars = []): void
    {
        $pdo = DB::pdo();
        $eshops = $this->loadEshops();
        $outstanding = $this->collectOutstandingMissingSku();
        if (!isset($vars['title'])) {
            $vars['title'] = 'Import Pohoda XML';
        }
        $vars['eshops'] = $eshops;
        $vars['outstandingMissing'] = $outstanding['groups'];
        $vars['outstandingDays'] = $outstanding['days'];
        $this->render('import_form.php', $vars);
    }

    private function loadEshops(): array
    {
        return DB::pdo()->query('SELECT nr.id,nr.eshop_source,MAX(de.import_batch_id) AS last_batch FROM nastaveni_rady nr LEFT JOIN doklady_eshop de ON de.eshop_source = nr.eshop_source GROUP BY nr.id,nr.eshop_source ORDER BY nr.eshop_source')->fetchAll();
    }

    private function loadIgnorePatterns(): array
    {
        return array_map(
            fn($r) => (string)$r['vzor'],
            DB::pdo()->query('SELECT vzor FROM nastaveni_ignorovane_polozky')->fetchAll()
        );
    }

    private function matchesIgnorePatterns(array $patterns, array $values): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = trim((string)$pattern);
            if ($pattern === '') {
                continue;
            }
            $regex = $this->wildcardToRegex($pattern);
            foreach ($values as $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                if (preg_match($regex, mb_strtolower($value, 'UTF-8'))) {
                    return true;
                }
            }
        }
        return false;
    }

    private function wildcardToRegex(string $pattern): string
    {
        $lower = mb_strtolower($pattern, 'UTF-8');
        $quoted = preg_quote($lower, '/');
        $quoted = str_replace(['\*', '\?'], ['.*', '.'], $quoted);
        return '/^' . $quoted . '$/u';
    }

    private function isKnownEshop(string $eshop): bool
    {
        if ($eshop === '') {
            return false;
        }
        $st = DB::pdo()->prepare('SELECT 1 FROM nastaveni_rady WHERE eshop_source=? LIMIT 1');
        $st->execute([$eshop]);
        return (bool)$st->fetchColumn();
    }

    private function ensureUtf8(string $s): string
    {
        if (mb_detect_encoding($s, 'UTF-8', true) === false) {
            $s = mb_convert_encoding($s, 'UTF-8');
        }
        return $s;
    }

    private function requireAdmin(): void
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        if (($u['role'] ?? 'user') !== 'admin') { http_response_code(403); echo 'Přístup jen pro admina.'; exit; }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}
