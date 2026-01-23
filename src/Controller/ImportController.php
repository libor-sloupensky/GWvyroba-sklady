<?php
namespace App\Controller;

use App\Support\DB;

final class ImportController
{
    /**
     * Jednoduchý cache kontaktů v rámci jednoho importu (klíč ic/email -> id).
     * @var array<string,int>
     */
    private array $contactCache = [];
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
            [$docCount, $itemCount, $missingSku, $skipped] = $this->persistInvoices($eshop, $series, $documents, $batch);
            $eshops = $this->loadEshops();
            $outstanding = $this->collectOutstandingMissingSku();
            $viewMode = $this->currentViewMode();
            $invoiceLimit = $this->currentInvoiceLimit();
            $invoiceRows = $viewMode === 'invoices' ? $this->loadImportedInvoices($invoiceLimit) : [];
            $displayRows = $this->filterOutstandingRows($outstanding['rows'], $viewMode);
            $this->render('import_result.php', [
                'title'=>'Import dokončen',
                'batch'=>$batch,
                'summary'=>['doklady'=>$docCount,'polozky'=>$itemCount],
                'missingSku'=>$missingSku,
                'notice'=>null,
                'eshops'=>$eshops,
                'selectedEshop'=>$eshop,
                'outstandingMissing'=>$this->groupRowsByEshop($displayRows),
                'outstandingDays'=>$outstanding['days'],
                'viewMode'=>$viewMode,
                'viewModes'=>$this->viewModes(),
                'invoiceRows'=>$invoiceRows,
                'invoiceLimit'=>$invoiceLimit,
                'skipped'=>$skipped,
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


    public function deleteInvoice(): void
    {
        $this->requireAdmin();
        $eshop = trim((string)($_POST['eshop'] ?? ''));
        $docNumber = trim((string)($_POST['cislo_dokladu'] ?? ''));
        if ($eshop === '' || $docNumber === '') {
            $this->renderImportForm(['error' => 'Chyb? e-shop nebo ??slo faktury.', 'viewMode' => 'invoices']);
            return;
        }
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT 1 FROM doklady_eshop WHERE eshop_source=? AND cislo_dokladu=? LIMIT 1');
        $st->execute([$eshop, $docNumber]);
        if (!$st->fetchColumn()) {
            $this->renderImportForm(['error' => 'Faktura nebyla nalezena.', 'viewMode' => 'invoices']);
            return;
        }
        $refPrefix = sprintf('doc:%s:%s', mb_strtolower($eshop, 'UTF-8'), $docNumber);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM polozky_pohyby WHERE ref_id LIKE ?')->execute([$refPrefix . ':%']);
            $pdo->prepare('DELETE FROM polozky_eshop WHERE eshop_source=? AND cislo_dokladu=?')->execute([$eshop, $docNumber]);
            $pdo->prepare('DELETE FROM doklady_eshop WHERE eshop_source=? AND cislo_dokladu=?')->execute([$eshop, $docNumber]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->renderImportForm(['error' => 'Smaz?n? faktury selhalo: ' . $e->getMessage(), 'viewMode' => 'invoices']);
            return;
        }
        $this->renderImportForm(['message' => 'Faktura byla smaz?na.', 'viewMode' => 'invoices']);
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
     * @return array{0:int,1:int,2:array<int,array<string,string>>,3:array<int,array<string,string>>}
     */
    private function persistInvoices(string $eshop, array $series, array $documents, string $batch): array
    {
        $pdo = DB::pdo();
        $importTs = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ignorePatterns = $this->loadIgnorePatterns();
        $docInsert = $pdo->prepare('INSERT INTO doklady_eshop (eshop_source,cislo_dokladu,typ_dokladu,platba_typ,dopravce_ids,cislo_objednavky,sym_var,datum_vystaveni,duzp,splatnost,mena_puvodni,kurz_na_czk,kontakt_id,import_batch_id,import_ts) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $itemInsert = $pdo->prepare('INSERT INTO polozky_eshop (eshop_source,cislo_dokladu,code_raw,stock_ids_raw,sku,ean,nazev,mnozstvi,merna_jednotka,cena_jedn_mena,cena_jedn_czk,mena_puvodni,sazba_dph_hint,plati_dph,sleva_procento,duzp,import_batch_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $deleteItems = $pdo->prepare('DELETE FROM polozky_eshop WHERE eshop_source=? AND cislo_dokladu=?');
        $deleteDoc = $pdo->prepare('DELETE FROM doklady_eshop WHERE eshop_source=? AND cislo_dokladu=?');
        $deleteMovement = $pdo->prepare('DELETE FROM polozky_pohyby WHERE ref_id=?');
        $movementInsert = $pdo->prepare('INSERT INTO polozky_pohyby (datum,sku,mnozstvi,merna_jednotka,typ_pohybu,poznamka,ref_id) VALUES (?,?,?,?,?,?,?)');
        $docCount = 0;
        $itemCount = 0;
        $missingSku = [];
        $skipped = [];
        $pdo->beginTransaction();
        try {
            foreach ($documents as $doc) {
                $docNumber = (string)$doc['cislo_dokladu'];
                try {
                    $this->validateSeriesNumber($series, $docNumber);
                } catch (\Throwable $e) {
                    $skipped[] = [
                        'cislo_dokladu' => $docNumber,
                        'duvod' => $e->getMessage(),
                    ];
                    continue;
                }
                $duzp = $this->normalizeDate($doc['duzp'] ?? null);
                if ($duzp === null) {
                    throw new \RuntimeException("Doklad {$docNumber} nemá platné DUZP.");
                }
                $dateIssue = $this->normalizeDate($doc['datum_vystaveni'] ?? null);
                $dueDate = $this->normalizeDate($doc['splatnost'] ?? null);
                $contactId = $this->syncContact($doc['contact'] ?? []);
                $docCurrency = strtoupper(trim((string)($doc['mena'] ?? '')));
                if ($docCurrency === '') {
                    $docCurrency = 'CZK';
                }
                $docRate = $this->normalizeDecimal($doc['kurz'] ?? null);
                if ($docCurrency === 'CZK') {
                    $docRate = '1';
                }
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
                    $this->emptyToNull($docCurrency),
                    $docRate,
                    $contactId,
                    $batch,
                    $importTs,
                ]);
                $docCount++;
                $movementBuckets = [];
                foreach ($doc['items'] as $item) {
                    $code = (string)($item['code'] ?? '');
                    $stock = (string)($item['stock'] ?? '');
                    $sku = $item['sku'] ?? $stock;
                    $quantity = $this->normalizeDecimal($item['quantity'] ?? null);
                    if ($quantity === null) {
                        $quantity = '0';
                    }
                    $unitForeign = $this->normalizeDecimal($item['unit_price_foreign'] ?? null);
                    $unitHome = $this->normalizeDecimal($item['unit_price_home'] ?? null);
                    if ($unitHome === null && $unitForeign !== null && $docRate !== null) {
                        $computed = $this->toFloat($unitForeign) * $this->toFloat($docRate);
                        $formatted = rtrim(rtrim(number_format($computed, 6, '.', ''), '0'), '.');
                        $unitHome = $this->normalizeDecimal($formatted);
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
                        $unitForeign,
                        $unitHome,
                        $this->emptyToNull($docCurrency),
                        $this->emptyToNull($item['rate_vat'] ?? null),
                        $item['pay_vat'] ?? null,
                        $this->normalizeDecimal($item['discount'] ?? null),
                        $duzp,
                        $batch,
                    ]);
                    $itemCount++;
                    $isMissingSku = ($sku === '' || $sku === null);
                    if ($isMissingSku) {
                    $ignoreMatch = $this->matchesIgnorePatterns($ignorePatterns, [
                        'nazev' => (string)($item['text'] ?? ''),
                        'code'  => (string)$code,
                        'doklad'=> (string)$docNumber,
                    ]);
                    if ($ignoreMatch !== null) {
                        continue;
                    }
                        $missingSku[] = [
                            'duzp' => $duzp,
                            'eshop_source' => $eshop,
                            'cislo_dokladu' => $docNumber,
                            'nazev' => (string)($item['text'] ?? ''),
                            'mnozstvi' => (string)($item['quantity'] ?? ''),
                            'code_raw' => $code,
                            'sku' => $sku !== '' ? $sku : null,
                            'ean' => $item['ean'] ?? null,
                        ];
                    }
                    if (!$isMissingSku) {
                        $movementQty = -1 * $this->toFloat($quantity);
                        if ($movementQty !== 0.0) {
                            $meta = $this->loadProductMeta($sku);
                            $isNonstock = (bool)($meta['is_nonstock'] ?? false);
                            $bomChildren = $isNonstock ? $this->loadBomChildren($sku) : [];

                            if ($isNonstock && !empty($bomChildren)) {
                                // rozpad na potomky, parent neodepisovat
                                foreach ($bomChildren as $edge) {
                                    $childSku = (string)$edge['sku'];
                                    $childQty = $movementQty * (float)$edge['koeficient'];
                                    if ($childQty === 0.0 || $childSku === '') {
                                        continue;
                                    }
                                    $childUnit = $edge['edge_unit'] ?? $edge['merna_jednotka'] ?? null;
                                    if (!isset($movementBuckets[$childSku])) {
                                        $movementBuckets[$childSku] = ['qty' => 0.0, 'unit' => $childUnit];
                                    }
                                    $movementBuckets[$childSku]['qty'] += $childQty;
                                    if ($movementBuckets[$childSku]['unit'] === null && $childUnit !== null) {
                                        $movementBuckets[$childSku]['unit'] = $childUnit;
                                    }
                                }
                            } else {
                                $key = (string)$sku;
                                if (!isset($movementBuckets[$key])) {
                                    $movementBuckets[$key] = ['qty' => 0.0, 'unit' => $this->emptyToNull($item['unit'] ?? null)];
                                }
                                $movementBuckets[$key]['qty'] += $movementQty;
                                if ($movementBuckets[$key]['unit'] === null) {
                                    $movementBuckets[$key]['unit'] = $this->emptyToNull($item['unit'] ?? null);
                                }
                            }
                        }
                    }
                }
                // zapiš pohyby agregovaně pro tento doklad
                foreach ($movementBuckets as $skuValue => $payload) {
                    $ref = $this->buildMovementRef($eshop, $docNumber, (string)$skuValue);
                    $deleteMovement->execute([$ref]);
                    $movementInsert->execute([
                        $duzp,
                        $skuValue,
                        $payload['qty'],
                        $payload['unit'],
                        'odpis',
                        sprintf('Doklad %s / %s', $eshop, $docNumber),
                        $ref,
                    ]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return [$docCount, $itemCount, $missingSku, $skipped];
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
        $stmt = $pdo->prepare("SELECT duzp, eshop_source, cislo_dokladu, nazev, mnozstvi, code_raw, stock_ids_raw, sku, ean FROM polozky_eshop WHERE duzp>=? ORDER BY eshop_source, duzp DESC");
        $stmt->execute([$since]);
        $groups = [];
        $flat = [];
        $flat = [];
        foreach ($stmt as $r) {
            $ignoreMatch = $this->matchesIgnorePatterns($patterns, [
                'nazev'  => (string)($r['nazev'] ?? ''),
                'code'   => (string)($r['code_raw'] ?? ''),
                'sku'    => (string)($r['sku'] ?? ''),
                'doklad' => (string)($r['cislo_dokladu'] ?? ''),
            ]);
            $status = 'unmatched';
            $highlight = null;
            $note = '';
            $skuValue = trim((string)($r['sku'] ?? ''));
            if ($ignoreMatch !== null) {
                $status = 'ignored';
                $highlight = $ignoreMatch['field'] ?? null;
                $note = $ignoreMatch['pattern'] !== '' ? 'Ignorováno dle: '.$ignoreMatch['pattern'] : 'Ignorováno';
            } elseif ($skuValue !== '') {
                $matchedField = $this->matchProductField($skuValue);
                if ($matchedField !== null) {
                    $status = 'matched';
                    $highlight = $matchedField === 'alt_sku' ? 'sku' : $matchedField;
                    $note = $matchedField === 'alt_sku' ? 'Spárováno (ALT SKU)' : 'Spárováno (SKU)';
                }
            }
            $eshop = (string)$r['eshop_source'];
            if (!isset($groups[$eshop])) {
                $groups[$eshop] = [];
            }
            $entry = $r;
            $entry['status'] = $status;
            $entry['highlight_field'] = $highlight;
            $entry['status_note'] = $note;
            $entry['ignore_pattern'] = $ignoreMatch['pattern'] ?? null;
            $groups[$eshop][] = $entry;
            $flat[] = $entry;
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
            $partner = $this->firstNode($xpath, './inv:partnerIdentity', $header);
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
                'mena' => '',
                'kurz' => '',
                'contact' => $partner ? [
                    'firma' => $this->xpathValue($xpath, './typ:address/typ:company', $partner),
                    'jmeno' => $this->xpathValue($xpath, './typ:address/typ:name', $partner),
                    'ulice' => $this->xpathValue($xpath, './typ:address/typ:street', $partner),
                    'mesto' => $this->xpathValue($xpath, './typ:address/typ:city', $partner),
                    'psc' => $this->xpathValue($xpath, './typ:address/typ:zip', $partner),
                    'zeme' => $this->xpathValue($xpath, './typ:address/typ:country', $partner),
                    'ic' => $this->xpathValue($xpath, './typ:address/typ:ico', $partner),
                    'dic' => $this->xpathValue($xpath, './typ:address/typ:dic', $partner),
                    'email' => $this->xpathValue($xpath, './typ:address/typ:email', $partner),
                    'telefon' => $this->xpathValue($xpath, './typ:address/typ:phone', $partner),
                ] : [],
                'items' => [],
            ];
            $summary = $this->firstNode($xpath, './inv:invoiceSummary', $invoiceNode);
            $currencyInfo = $this->resolveCurrencyInfo($xpath, $header, $summary);
            $doc['mena'] = $currencyInfo['currency'];
            $doc['kurz'] = $currencyInfo['rate'];
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

    /**
     * @return array{currency:string,rate:string}
     */
    private function resolveCurrencyInfo(\DOMXPath $xpath, ?\DOMNode $header, ?\DOMNode $summary): array
    {
        $currency = '';
        $rate = '';
        $amount = '';
        $pick = function (?\DOMNode $context, string $path) use ($xpath, &$currency, &$rate, &$amount): void {
            if (!$context || $currency !== '') {
                return;
            }
            $currency = $this->xpathValue($xpath, $path . '/typ:currency/typ:ids', $context);
            $rate = $this->xpathValue($xpath, $path . '/typ:rate', $context);
            $amount = $this->xpathValue($xpath, $path . '/typ:amount', $context);
        };
        $pick($summary, './inv:foreignCurrency');
        $pick($summary, './inv:homeCurrency');
        $pick($header, './inv:foreignCurrency');
        $pick($header, './inv:homeCurrency');
        return [
            'currency' => $currency,
            'rate' => $this->computeCurrencyRate($rate, $amount),
        ];
    }

    private function computeCurrencyRate(?string $rate, ?string $amount): string
    {
        $rateNorm = $this->normalizeDecimal($rate);
        if ($rateNorm === null) {
            return '';
        }
        $amountNorm = $this->normalizeDecimal($amount);
        if ($amountNorm === null) {
            return $rateNorm;
        }
        $amountValue = (float)$amountNorm;
        if ($amountValue == 0.0) {
            return $rateNorm;
        }
        $value = (float)$rateNorm / $amountValue;
        $formatted = number_format($value, 6, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
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

    /**
     * @param array<string,string> $contact
     */
    private function syncContact(array $contact): ?int
    {
        $fields = array_map('trim', $contact);
        $hasData = false;
        foreach (['firma','ic','dic','email','telefon','jmeno'] as $k) {
            if (!empty($fields[$k] ?? '')) {
                $hasData = true;
                break;
            }
        }
        if (!$hasData) {
            return null;
        }
        $pdo = DB::pdo();
        $ic = $fields['ic'] ?? '';
        $email = $fields['email'] ?? '';
        $id = null;
        if ($ic !== '') {
            $cacheKey = 'ic:' . $ic;
            if (isset($this->contactCache[$cacheKey])) {
                $id = $this->contactCache[$cacheKey];
            } else {
                static $selIc;
                if (!$selIc) {
                    $selIc = $pdo->prepare('SELECT id FROM kontakty WHERE ic = ? LIMIT 1');
                }
                $selIc->execute([$ic]);
                $id = $selIc->fetchColumn();
                if ($id !== false && $id !== null) {
                    $this->contactCache[$cacheKey] = (int)$id;
                }
            }
        }
        if ($id === false || $id === null) {
            if ($email !== '') {
                $cacheKey = 'email:' . $email;
                if (isset($this->contactCache[$cacheKey])) {
                    $id = $this->contactCache[$cacheKey];
                } else {
                    static $selEmail;
                    if (!$selEmail) {
                        $selEmail = $pdo->prepare('SELECT id FROM kontakty WHERE email = ? LIMIT 1');
                    }
                    $selEmail->execute([$email]);
                    $id = $selEmail->fetchColumn();
                    if ($id !== false && $id !== null) {
                        $this->contactCache[$cacheKey] = (int)$id;
                    }
                }
            }
        }
        if ($id === false) {
            $id = null;
        }
        if ($id === null) {
            static $ins;
            if (!$ins) {
                $ins = $pdo->prepare('INSERT INTO kontakty (email, telefon, firma, jmeno, ulice, mesto, psc, zeme, ic, dic) VALUES (?,?,?,?,?,?,?,?,?,?)');
            }
            $ins->execute([
                $fields['email'] ?: null,
                $fields['telefon'] ?: null,
                $fields['firma'] ?: null,
                $fields['jmeno'] ?: null,
                $fields['ulice'] ?: null,
                $fields['mesto'] ?: null,
                $fields['psc'] ?: null,
                $fields['zeme'] ?: null,
                $fields['ic'] ?: null,
                $fields['dic'] ?: null,
            ]);
            $newId = (int)$pdo->lastInsertId();
            if ($ic !== '') {
                $this->contactCache['ic:' . $ic] = $newId;
            }
            if ($email !== '') {
                $this->contactCache['email:' . $email] = $newId;
            }
            return $newId;
        }
        $update = $pdo->prepare('UPDATE kontakty SET email=COALESCE(?,email), telefon=COALESCE(?,telefon), firma=COALESCE(?,firma), jmeno=COALESCE(?,jmeno), ulice=COALESCE(?,ulice), mesto=COALESCE(?,mesto), psc=COALESCE(?,psc), zeme=COALESCE(?,zeme), ic=COALESCE(?,ic), dic=COALESCE(?,dic) WHERE id=?');
        $update->execute([
            $fields['email'] ?: null,
            $fields['telefon'] ?: null,
            $fields['firma'] ?: null,
            $fields['jmeno'] ?: null,
            $fields['ulice'] ?: null,
            $fields['mesto'] ?: null,
            $fields['psc'] ?: null,
            $fields['zeme'] ?: null,
            $fields['ic'] ?: null,
            $fields['dic'] ?: null,
            (int)$id,
        ]);
        if ($ic !== '') {
            $this->contactCache['ic:' . $ic] = (int)$id;
        }
        if ($email !== '') {
            $this->contactCache['email:' . $email] = (int)$id;
        }
        return (int)$id;
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
        $viewMode = isset($vars['viewMode']) ? $this->normalizeViewMode((string)$vars['viewMode']) : $this->currentViewMode();
        $invoiceLimit = $this->currentInvoiceLimit();
        $eshops = $this->loadEshops();
        $outstanding = $this->collectOutstandingMissingSku();
        $displayRows = $this->filterOutstandingRows($outstanding['rows'], $viewMode);
        if (!isset($vars['title'])) {
            $vars['title'] = 'Import Pohoda XML';
        }
        $vars['eshops'] = $eshops;
        $vars['outstandingMissing'] = $this->groupRowsByEshop($displayRows);
        $vars['outstandingDays'] = $outstanding['days'];
        $vars['viewMode'] = $viewMode;
        $vars['viewModes'] = $this->viewModes();
        $vars['invoiceRows'] = $viewMode === 'invoices' ? $this->loadImportedInvoices($invoiceLimit) : [];
        $vars['invoiceLimit'] = $invoiceLimit;
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

    private function matchProductField(string $skuOrAlt): ?string
    {
        static $cache = [];
        $key = mb_strtolower($skuOrAlt, 'UTF-8');
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = DB::pdo()->prepare('SELECT sku, alt_sku FROM produkty WHERE sku=? OR alt_sku=? LIMIT 1');
        $stmt->execute([$skuOrAlt, $skuOrAlt]);
        $row = $stmt->fetch();
        if (!$row) {
            $cache[$key] = null;
            return null;
        }
        $match = null;
        $dbSku = (string)$row['sku'];
        $dbAlt = $row['alt_sku'] === null ? null : (string)$row['alt_sku'];
        if (mb_strtolower($dbSku, 'UTF-8') === $key) {
            $match = 'sku';
        } elseif ($dbAlt !== null && $dbAlt !== '' && mb_strtolower($dbAlt, 'UTF-8') === $key) {
            $match = 'alt_sku';
        } else {
            $match = 'sku';
        }
        $cache[$key] = $match;
        return $match;
    }

    private function viewModes(): array
    {
        return [
            'unmatched' => 'Nespárované',
            'all'       => 'Všechny vazby',
            'unique'    => 'Všechny unikátní vazby',
            'invoices'  => 'Naimportované faktury',
        ];
    }

    private function currentViewMode(): string
    {
        $mode = $_GET['view'] ?? 'unmatched';
        return $this->normalizeViewMode((string)$mode);
    }

    private function currentInvoiceLimit(): int
    {
        $limit = (int)($_POST['limit'] ?? $_GET['limit'] ?? 50);
        $allowed = [50, 100, 200, 500];
        if (!in_array($limit, $allowed, true)) {
            $limit = 50;
        }
        return $limit;
    }

    private function normalizeViewMode(string $mode): string
    {
        $allowed = array_keys($this->viewModes());
        if (!in_array($mode, $allowed, true)) {
            return 'unmatched';
        }
        return $mode;
    }

    private function groupRowsByEshop(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $eshop = (string)($row['eshop_source'] ?? '');
            $groups[$eshop][] = $row;
        }
        foreach ($groups as $eshop => $items) {
            usort($items, function ($a, $b) {
                $qtyA = $this->toFloat($a['mnozstvi'] ?? 0);
                $qtyB = $this->toFloat($b['mnozstvi'] ?? 0);
                return $qtyB <=> $qtyA;
            });
            $groups[$eshop] = $items;
        }
        return $groups;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function filterOutstandingRows(array $rows, string $mode): array
    {
        if ($mode === 'invoices') {
            return [];
        }
        if ($mode === 'unmatched') {
            $rows = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'unmatched'));
            $rows = $this->uniqueBySkuOrName($rows);
        } elseif ($mode === 'unique') {
            $rows = $this->uniqueOutstandingRows($rows);
        }
        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function uniqueOutstandingRows(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $this->uniqueRowKey($row);
            if (!isset($map[$key])) {
                $map[$key] = $row;
                continue;
            }
            $map[$key] = $this->mergeAggregatedRow($map[$key], $row);
        }
        return array_values($map);
    }

    private function uniqueRowKey(array $row): string
    {
        $name = trim((string)($row['nazev'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['code_raw'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string)($row['cislo_dokladu'] ?? '') . '|' . (string)($row['mnozstvi'] ?? ''));
        }
        return mb_strtolower((string)($row['eshop_source'] ?? '') . '|' . $name, 'UTF-8');
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function uniqueBySkuOrName(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $this->skuOrNameKey($row);
            if (!isset($map[$key])) {
                $map[$key] = $row;
                continue;
            }
            $map[$key] = $this->mergeAggregatedRow($map[$key], $row);
        }
        return array_values($map);
    }

    private function skuOrNameKey(array $row): string
    {
        $eshop = mb_strtolower((string)($row['eshop_source'] ?? ''), 'UTF-8');
        $sku = mb_strtolower(trim((string)($row['sku'] ?? '')), 'UTF-8');
        if ($sku !== '') {
            return $eshop . '|sku|' . $sku;
        }
        $name = mb_strtolower(trim((string)($row['nazev'] ?? '')), 'UTF-8');
        if ($name !== '') {
            return $eshop . '|name|' . $name;
        }
        return $this->uniqueRowKey($row);
    }

    private function matchesIgnorePatterns(array $patterns, array $values): ?array
    {
        foreach ($patterns as $pattern) {
            $pattern = trim((string)$pattern);
            if ($pattern === '') {
                continue;
            }
            $regex = $this->wildcardToRegex($pattern);
            foreach ($values as $field => $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                if (preg_match($regex, mb_strtolower($value, 'UTF-8'))) {
                    return ['pattern'=>$pattern,'field'=>$field];
                }
            }
        }
        return null;
    }

    private function wildcardToRegex(string $pattern): string
    {
        $lower = mb_strtolower($pattern, 'UTF-8');
        $quoted = preg_quote($lower, '/');
        $quoted = str_replace(['\*', '\?'], ['.*', '.'], $quoted);
        return '/^' . $quoted . '$/u';
    }

    private function mergeAggregatedRow(array $dest, array $src): array
    {
        $destQty = $this->toFloat($dest['mnozstvi'] ?? 0);
        $srcQty  = $this->toFloat($src['mnozstvi'] ?? 0);
        $sum = $destQty + $srcQty;
        $dest['mnozstvi'] = $this->formatQuantity($sum);
        return $dest;
    }

    private function toFloat($value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float)$value;
        }
        $normalized = str_replace(',', '.', trim((string)$value));
        return is_numeric($normalized) ? (float)$normalized : 0.0;
    }

    private function formatQuantity(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function buildMovementRef(string $eshop, string $docNumber, string $sku): string
    {
        return sprintf('doc:%s:%s:%s', mb_strtolower($eshop, 'UTF-8'), $docNumber, $sku);
    }

    /**
     * @return array{sku:string,typ:string,is_nonstock:bool,merna_jednotka:?string}|null
     */
    private function loadProductMeta(string $sku): ?array
    {
        static $cache = [];
        $key = mb_strtolower($sku, 'UTF-8');
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $stmt = DB::pdo()->prepare(
            'SELECT p.sku,p.typ,pt.is_nonstock,p.merna_jednotka FROM produkty p ' .
            'LEFT JOIN product_types pt ON pt.code = p.typ WHERE p.sku=? LIMIT 1'
        );
        $stmt->execute([$sku]);
        $row = $stmt->fetch();
        if (!$row) {
            $cache[$key] = null;
            return null;
        }
        $cache[$key] = [
            'sku' => (string)$row['sku'],
            'typ' => (string)($row['typ'] ?? ''),
            'is_nonstock' => (bool)($row['is_nonstock'] ?? false),
            'merna_jednotka' => $row['merna_jednotka'] !== null ? (string)$row['merna_jednotka'] : null,
        ];
        return $cache[$key];
    }

    /**
     * @return array<int,array{sku:string,koeficient:float,edge_unit:?string,merna_jednotka:?string}>
     */
    private function loadBomChildren(string $sku): array
    {
        static $cache = [];
        if (isset($cache[$sku])) {
            return $cache[$sku];
        }
        $stmt = DB::pdo()->prepare(
            'SELECT b.potomek_sku AS sku,b.koeficient,' .
            'COALESCE(NULLIF(b.merna_jednotka_potomka, \'\'), NULL) AS edge_unit,' .
            'p.merna_jednotka ' .
            'FROM bom b LEFT JOIN produkty p ON p.sku = b.potomek_sku WHERE b.rodic_sku=?'
        );
        $stmt->execute([$sku]);
        $rows = [];
        foreach ($stmt as $row) {
            $rows[] = [
                'sku' => (string)$row['sku'],
                'koeficient' => (float)$row['koeficient'],
                'edge_unit' => $row['edge_unit'] !== null ? (string)$row['edge_unit'] : null,
                'merna_jednotka' => $row['merna_jednotka'] !== null ? (string)$row['merna_jednotka'] : null,
            ];
        }
        $cache[$sku] = $rows;
        return $rows;
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


    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadImportedInvoices(int $limit = 50): array
    {
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 50;
        }
        $sql = "
SELECT
  de.eshop_source,
  de.duzp,
  de.cislo_dokladu,
  ROUND(COALESCE(SUM(COALESCE(pe.cena_jedn_czk,0) * COALESCE(pe.mnozstvi,0)),0), 2) AS castka_czk
FROM doklady_eshop de
LEFT JOIN polozky_eshop pe
  ON pe.eshop_source = de.eshop_source
 AND pe.cislo_dokladu = de.cislo_dokladu
GROUP BY de.eshop_source, de.duzp, de.cislo_dokladu
ORDER BY de.duzp DESC, de.cislo_dokladu DESC
LIMIT {$limit}
";
        return DB::pdo()->query($sql)->fetchAll();
    }

    private function requireAdmin(): void
    {
        // CLI skripty (cron) mohou importovat bez interaktivního přihlášení
        if (PHP_SAPI === 'cli') {
            return;
        }
        $u = $_SESSION['user'] ?? null;
        if (!$u) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        if (!in_array(($u['role'] ?? 'user'), ['admin', 'superadmin'], true)) {
            $this->forbidden('Přístup jen pro administrátory.');
        }
    }

    /**
     * Programový import Pohoda XML (pro cron/CLI). Vrací souhrn bez renderu.
     * @return array<string,mixed>
     */
    public function importPohodaFromStringCli(string $eshop, string $xml): array
    {
        $eshop = trim($eshop);
        if ($eshop === '' || !$this->isKnownEshop($eshop)) {
            throw new \RuntimeException('Zvolte e-shop z nastaveného seznamu.');
        }
        $xml = $this->ensureUtf8($xml);
        $series = $this->loadSeries($eshop);
        $documents = $this->parsePohodaXml($xml);
        if (empty($documents)) {
            throw new \RuntimeException('XML neobsahuje žádné doklady.');
        }
        $batch = date('YmdHis');
        [$docCount, $itemCount, $missingSku] = $this->persistInvoices($eshop, $series, $documents, $batch);
        return [
            'batch' => $batch,
            'doklady' => $docCount,
            'polozky' => $itemCount,
            'missingSku' => $missingSku,
        ];
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function forbidden(string $message): void
    {
        http_response_code(403);
        $this->render('forbidden.php', [
            'title' => 'Přístup odepřen',
            'message' => $message,
        ]);
        exit;
    }

    public function getInvoiceDetail(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $eshop = trim((string)($_GET['eshop'] ?? ''));
        $docNumber = trim((string)($_GET['cislo_dokladu'] ?? ''));

        if ($eshop === '' || $docNumber === '') {
            echo json_encode(['error' => 'Chybí parametry ']);
            return;
        }

        $pdo = DB::pdo();

        // Načti hlavičku dokladu
        $headerStmt = $pdo->prepare('
            SELECT de.*, k.firma, k.jmeno, k.ic, k.email
            FROM doklady_eshop de
            LEFT JOIN kontakty k ON k.id = de.kontakt_id
            WHERE de.eshop_source = ? AND de.cislo_dokladu = ?
            LIMIT 1
        ');
        $headerStmt->execute([$eshop, $docNumber]);
        $header = $headerStmt->fetch();

        if (!$header) {
            echo json_encode(['error' => 'Faktura nenalezena'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Načti položky dokladu
        $itemsStmt = $pdo->prepare('
            SELECT pe.*
            FROM polozky_eshop pe
            WHERE pe.eshop_source = ? AND pe.cislo_dokladu = ?
            ORDER BY pe.id
        ');
        $itemsStmt->execute([$eshop, $docNumber]);
        $items = $itemsStmt->fetchAll();

        // Pro každou položku načti informace o odpisu
        foreach ($items as &$item) {
            $sku = (string)($item['sku'] ?? '');

            if ($sku === '') {
                // Nemá SKU, nemůže být odepsáno
                $item['odpis_mnozstvi'] = 0.0;
                $item['odpis_proveden'] = false;
                $item['odpis_info'] = [];
                continue;
            }

            // Zkontroluj, zda je produkt nonstock a má BOM potomky
            $meta = $this->loadProductMeta($sku);
            $isNonstock = $meta ? (bool)($meta['is_nonstock'] ?? false) : false;
            $bomChildren = $isNonstock ? $this->loadBomChildren($sku) : [];

            // Vytvoř seznam SKU, která mají být odepsána (buď parent nebo potomci)
            $skusToCheck = [];
            if ($isNonstock && !empty($bomChildren)) {
                // Nonstock - hledej odpisy potomků
                foreach ($bomChildren as $child) {
                    $childSku = (string)($child['sku'] ?? '');
                    if ($childSku !== '') {
                        $skusToCheck[] = $childSku;
                    }
                }
            } else {
                // Normální produkt - hledej přímý odpis
                $skusToCheck[] = $sku;
            }

            // Načti pohyby pro nalezené SKU
            $movements = [];
            if (!empty($skusToCheck)) {
                $refPrefix = sprintf('doc:%s:%s:', mb_strtolower($eshop, 'UTF-8'), $docNumber);
                $placeholders = implode(',', array_fill(0, count($skusToCheck), '?'));
                $movementStmt = $pdo->prepare("
                    SELECT sku, mnozstvi, merna_jednotka, ref_id
                    FROM polozky_pohyby
                    WHERE ref_id LIKE ? AND sku IN ($placeholders)
                ");
                $params = array_merge([$refPrefix . '%'], $skusToCheck);
                $movementStmt->execute($params);
                $movements = $movementStmt->fetchAll();
            }

            if (!empty($movements)) {
                if ($isNonstock && !empty($bomChildren)) {
                    // Nonstock rozpad na potomky
                    $item['odpis_mnozstvi'] = 0.0;
                    $item['odpis_proveden'] = true;
                    $item['odpis_info'] = $movements;
                } else {
                    // Přímý odpis
                    $item['odpis_mnozstvi'] = (float)($movements[0]['mnozstvi'] ?? 0.0);
                    $item['odpis_proveden'] = true;
                    $item['odpis_info'] = $movements;
                }
            } else {
                // Žádný odpis
                $item['odpis_mnozstvi'] = 0.0;
                $item['odpis_proveden'] = false;
                $item['odpis_info'] = [];
            }
        }

        echo json_encode([
            'header' => $header,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
    }
}
