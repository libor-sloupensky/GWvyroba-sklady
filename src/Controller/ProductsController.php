<?php
namespace App\Controller;

use App\Support\DB;

final class ProductsController
{
    public function index(): void
    {
        $this->requireAuth();
        $filters = $this->currentFilters();
        $hasSearch = $this->searchTriggered();
        $message = $_SESSION['products_message'] ?? null;
        $errorMessage = $_SESSION['products_error'] ?? null;
        $formOld = $_SESSION['products_old'] ?? null;
        $importMessage = $_SESSION['products_import_message'] ?? null;
        $importErrors = $_SESSION['products_import_errors'] ?? [];
        $bomMessage = $_SESSION['products_bom_message'] ?? null;
        $bomError = $_SESSION['products_bom_error'] ?? null;
        $bomErrors = $_SESSION['products_bom_errors'] ?? [];
        unset(
            $_SESSION['products_message'],
            $_SESSION['products_error'],
            $_SESSION['products_old'],
            $_SESSION['products_import_message'],
            $_SESSION['products_import_errors'],
            $_SESSION['products_bom_message'],
            $_SESSION['products_bom_error'],
            $_SESSION['products_bom_errors']
        );

        $items = $this->fetchProducts($filters);

        $this->render('products_index.php', [
            'title'  => 'Produkty',
            'items'  => $items,
            'brands' => $this->fetchBrands(),
            'groups' => $this->fetchGroups(),
            'units'  => $this->fetchUnits(),
            'types'  => $this->productTypes(),
            'message'=> $message,
            'error'  => $errorMessage,
            'filters'=> $filters,
            'hasSearch' => $hasSearch,
            'resultCount' => count($items),
            'formOld' => $formOld,
            'importMessage' => $importMessage,
            'importErrors' => $importErrors,
            'bomMessage' => $bomMessage,
            'bomError' => $bomError,
            'bomErrors' => $bomErrors,
            'bomTotal' => $this->countBomLinks(),
        ]);
    }

    public function exportCsv(): void
    {
        $this->requireAuth();
        $pdo = DB::pdo();
        $rows = $pdo->query($this->productsSelectSql() . ' ORDER BY p.nazev')->fetchAll();
        $fh = fopen('php://output', 'wb');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="produkty.csv"');
        $delimiter = ';';
        $enclosure = '"';
        $escape = '\\';
        fputcsv($fh, ['sku','alt_sku','ean','znacka','skupina','typ','merna_jednotka','nazev','min_zasoba','min_davka','krok_vyroby','vyrobni_doba_dni','aktivni','poznamka'], $delimiter, $enclosure, $escape);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['sku'],
                $r['alt_sku'],
                $r['ean'],
                $r['znacka'],
                $r['skupina'],
                $r['typ'],
                $r['merna_jednotka'],
                $r['nazev'],
                $r['min_zasoba'],
                $r['min_davka'],
                $r['krok_vyroby'],
                $r['vyrobni_doba_dni'],
                $r['aktivni'],
                $r['poznamka'],
            ], $delimiter, $enclosure, $escape);
        }
        exit;
    }

    public function importCsv(): void
    {
        $this->requireAdmin();
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->flashProductImportError('Soubor nebyl nahrán.');
        }
        $fh = fopen($_FILES['csv']['tmp_name'], 'rb');
        if (!$fh) {
            $this->flashProductImportError('Nelze číst soubor.');
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $header = $this->readCsvRow($fh);
            $expected = ['sku','alt_sku','ean','znacka','skupina','typ','merna_jednotka','nazev','min_zasoba','min_davka','krok_vyroby','vyrobni_doba_dni','aktivni','poznamka'];
            if (!$header || array_map('strtolower', $header) !== $expected) {
                throw new \RuntimeException('Neplatná hlavička CSV.');
            }
            $brands = $this->loadDictionary('produkty_znacky');
            $groups = $this->loadDictionary('produkty_skupiny');
            $units  = $this->loadUnitsDictionary();
            if (empty($units)) {
                throw new \RuntimeException('Nejprve definujte povolené měrné jednotky v Nastavení.');
            }
            [$existingSkuMap, $existingAltMap] = $this->loadExistingSkuMaps();
            $pendingSku = [];
            $pendingAlt = [];
            $stmt = $pdo->prepare(
                'INSERT INTO produkty (sku,alt_sku,nazev,typ,merna_jednotka,ean,min_zasoba,min_davka,krok_vyroby,vyrobni_doba_dni,aktivni,znacka_id,poznamka,skupina_id) ' .
                'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ' .
                'ON DUPLICATE KEY UPDATE alt_sku=VALUES(alt_sku),nazev=VALUES(nazev),typ=VALUES(typ),merna_jednotka=VALUES(merna_jednotka),ean=VALUES(ean),min_zasoba=VALUES(min_zasoba),min_davka=VALUES(min_davka),krok_vyroby=VALUES(krok_vyroby),vyrobni_doba_dni=VALUES(vyrobni_doba_dni),aktivni=VALUES(aktivni),znacka_id=VALUES(znacka_id),poznamka=VALUES(poznamka),skupina_id=VALUES(skupina_id)'
            );
            $ok = 0;
            $errors = [];
            $line = 1;
            while (($row = $this->readCsvRow($fh)) !== false) {
                $line++;
                if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue;
                }
                $row = array_pad($row, 14, '');
                [$sku,$altSku,$ean,$brandName,$groupName,$typ,$mj,$nazev,$min,$md,$krok,$vdd,$act,$note] = $row;
                $sku = $this->toUtf8($sku);
                $altSku = $this->toUtf8($altSku);
                $nazev = $this->toUtf8($nazev);
                $typ = $this->toUtf8($typ);
                $mj = $this->toUtf8($mj);
                $ean = $this->toUtf8($ean);
                $brandName = $this->toUtf8($brandName);
                $groupName = $this->toUtf8($groupName);
                $note = $this->toUtf8($note);

                if ($sku === '') { $errors[] = "Řádek {$line}: chybí sku"; continue; }
                if ($nazev === '') { $errors[] = "Řádek {$line}: chybí název"; continue; }
                if ($typ === '' || !in_array($typ, $this->productTypes(), true)) { $errors[] = "Řádek {$line}: neplatný typ"; continue; }
                if ($mj === '') { $errors[] = "Řádek {$line}: chybí měrná jednotka"; continue; }
                $unitKey = mb_strtolower($mj, 'UTF-8');
                if (!isset($units[$unitKey])) { $errors[] = "Řádek {$line}: měrná jednotka '{$mj}' není definovaná"; continue; }
                $mj = $units[$unitKey];
                if ($act === '') { $errors[] = "Řádek {$line}: aktivní je povinné (0/1)"; continue; }
                $aktivni = (int)$act;
                $brandId = null;
                if ($brandName !== '') {
                    $brandKey = mb_strtolower($brandName, 'UTF-8');
                    if (!isset($brands[$brandKey])) { $errors[] = "Řádek {$line}: značka '{$brandName}' není definovaná"; continue; }
                    $brandId = $brands[$brandKey];
                }
                $groupId = null;
                if ($groupName !== '') {
                    $groupKey = mb_strtolower($groupName, 'UTF-8');
                    if (!isset($groups[$groupKey])) { $errors[] = "Řádek {$line}: skupina '{$groupName}' není definovaná"; continue; }
                    $groupId = $groups[$groupKey];
                }
                if ($ean === '') {
                    $ean = null;
                }
                $skuKey = mb_strtolower($sku, 'UTF-8');
                if (isset($pendingSku[$skuKey])) {
                    $errors[] = "Řádek {$line}: duplicitní SKU '{$sku}'";
                    continue;
                }
                $pendingSku[$skuKey] = true;
                $conflict = isset($existingSkuMap[$skuKey]) && $existingSkuMap[$skuKey] !== $sku;
                if ($altSku !== '') {
                    $altKey = mb_strtolower($altSku, 'UTF-8');
                    if (isset($existingSkuMap[$altKey]) || isset($existingAltMap[$altKey])) {
                        $conflict = true;
                    }
                    if (isset($pendingSku[$altKey]) && $pendingSku[$altKey] === true) { $conflict = true; }
                    if (isset($pendingAlt[$altKey]) && $pendingAlt[$altKey] !== $skuKey) { $conflict = true; }
                    if ($conflict) {
                        $errors[] = "Řádek {$line}: alt_sku '{$altSku}' je již použito.";
                        continue;
                    }
                    $pendingAlt[$altKey] = $skuKey;
                } else {
                    $altSku = null;
                }
                $stmt->execute([
                    $sku,
                    $altSku,
                    $nazev,
                    $typ,
                    $mj,
                    $ean,
                    $min === '' ? 0 : $min,
                    $md === '' ? 0 : $md,
                    $krok === '' ? 0 : $krok,
                    $vdd === '' ? 0 : $vdd,
                    $aktivni,
                    $brandId,
                    $note === '' ? null : $note,
                    $groupId,
                ]);
                $ok++;
            }
            $pdo->commit();
            fclose($fh);
            $this->flashProductImportSuccess("Import OK: {$ok}", $errors);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fclose($fh);
            $this->flashProductImportError('Import selhal: ' . $e->getMessage());
        }
    }


    public function create(): void
    {
        $this->requireAdmin();
        $sku   = trim((string)($_POST['sku'] ?? ''));
        $altSku= trim((string)($_POST['alt_sku'] ?? ''));
        $ean   = trim((string)($_POST['ean'] ?? ''));
        $name  = trim((string)($_POST['nazev'] ?? ''));
        $type  = trim((string)($_POST['typ'] ?? ''));
        $unit  = trim((string)($_POST['merna_jednotka'] ?? ''));
        $min   = trim((string)($_POST['min_zasoba'] ?? ''));
        $batch = trim((string)($_POST['min_davka'] ?? ''));
        $step  = trim((string)($_POST['krok_vyroby'] ?? ''));
        $lead  = trim((string)($_POST['vyrobni_doba_dni'] ?? ''));
        $active= (int)($_POST['aktivni'] ?? 1);
        $brandId = (int)($_POST['znacka_id'] ?? 0);
        $groupId = (int)($_POST['skupina_id'] ?? 0);
        $note  = trim((string)($_POST['poznamka'] ?? ''));

        $oldInput = [
            'sku' => $sku,
            'alt_sku' => $altSku,
            'ean' => $ean,
            'znacka_id' => $brandId,
            'skupina_id' => $groupId,
            'typ' => $type,
            'merna_jednotka' => $unit,
            'nazev' => $name,
            'min_zasoba' => $min,
            'min_davka' => $batch,
            'krok_vyroby' => $step,
            'vyrobni_doba_dni' => $lead,
            'aktivni' => $active,
            'poznamka' => $note,
        ];

        $errors = [];
        if ($sku === '') $errors[] = 'Zadejte SKU.';
        if ($name === '') $errors[] = 'Zadejte nĂˇzev.';
        if (!in_array($type, $this->productTypes(), true)) $errors[] = 'NeplatnĂ˝ typ.';
        $unitCodes = array_column($this->fetchUnits(), 'kod');
        if ($unit === '' || !in_array($unit, $unitCodes, true)) $errors[] = 'Vyberte mÄ›rnou jednotku.';
        if ($brandId > 0 && !$this->dictionaryIdExists('produkty_znacky', $brandId)) $errors[] = 'NeplatnĂˇ znaÄŤka.';
        if ($groupId > 0 && !$this->dictionaryIdExists('produkty_skupiny', $groupId)) $errors[] = 'NeplatnĂˇ skupina.';
        $altSku = $this->toUtf8($altSku);
        if ($altSku !== '') {
            if (mb_strtolower($altSku, 'UTF-8') === mb_strtolower($sku, 'UTF-8')) {
                $errors[] = 'alt_sku nesmĂ­ bĂ˝t shodnĂ© se sku.';
            } elseif ($this->altSkuConflictExists($altSku)) {
                $errors[] = 'alt_sku je jiĹľ pouĹľito nebo koliduje s jinĂ˝m SKU.';
            }
        }

        if ($errors) {
            $_SESSION['products_error'] = implode(' ', $errors);
            $_SESSION['products_old'] = $oldInput;
            header('Location: /products');
            return;
        }

        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare('INSERT INTO produkty (sku, alt_sku, ean, znacka_id, skupina_id, typ, merna_jednotka, nazev, min_zasoba, min_davka, krok_vyroby, vyrobni_doba_dni, aktivni, poznamka) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $sku,
                $altSku === '' ? null : $altSku,
                $ean === '' ? null : $ean,
                $brandId ?: null,
                $groupId ?: null,
                $type,
                $unit,
                $name,
                $min === '' ? 0 : $min,
                $batch === '' ? 0 : $batch,
                $step === '' ? 0 : $step,
                $lead === '' ? 0 : $lead,
                $active === 1 ? 1 : 0,
                $note === '' ? null : $note,
            ]);
            $_SESSION['products_message'] = 'Produkt byl pĹ™idĂˇn.';
            unset($_SESSION['products_old']);
        } catch (\Throwable $e) {
            $_SESSION['products_error'] = 'UloĹľenĂ­ selhalo: ' . $e->getMessage();
            $_SESSION['products_old'] = $oldInput;
        }
        header('Location: /products');
    }

    public function inlineUpdate(): void
    {
        $this->requireAdmin();
        $payload = $_POST;
        if (empty($payload)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false) {
                $payload = json_decode($raw, true) ?? [];
            }
        }
        $sku = trim((string)($payload['sku'] ?? ''));
        $field = (string)($payload['field'] ?? '');
        $value = $payload['value'] ?? '';

        header('Content-Type: application/json');
        if ($sku === '' || $field === '') {
            echo json_encode(['ok'=>false,'error'=>'NeplatnĂ˝ poĹľadavek.']);
            return;
        }
        if (!in_array($field, $this->editableFields(), true)) {
            echo json_encode(['ok'=>false,'error'=>'Pole nelze upravit.']);
            return;
        }

        [$normalized, $error] = $this->normalizeFieldValue($field, $value, $sku);
        if ($error !== null) {
            echo json_encode(['ok'=>false,'error'=>$error]);
            return;
        }

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("UPDATE produkty SET {$field}=? WHERE sku=?");
        $stmt->execute([$normalized, $sku]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['ok'=>false,'error'=>'Produkt nenalezen.']);
            return;
        }
        echo json_encode(['ok'=>true]);
    }

    public function bomTree(): void
    {
        $this->requireAuth();
        $sku = trim((string)($_GET['sku'] ?? ''));
        header('Content-Type: application/json');
        if ($sku === '') {
            echo json_encode(['ok'=>false,'error'=>'ChybĂ­ SKU.']);
            return;
        }
        try {
            $tree = $this->buildBomTree($sku);
            echo json_encode(['ok'=>true,'tree'=>$tree]);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'error'=>'NepodaĹ™ilo se naÄŤĂ­st BOM strom.']);
        }
    }

    public function search(): void
    {
        $this->requireAuth();
        $term = $this->toUtf8((string)($_GET['q'] ?? ''));
        header('Content-Type: application/json');
        if ($term === '') {
            echo json_encode(['items' => []]);
            return;
        }
        [$searchCondition, $searchParams] = $this->buildSearchClauses(
            $term,
            ['sku','alt_sku','nazev','ean']
        );
        $sql = 'SELECT sku, alt_sku, nazev, ean, merna_jednotka, typ FROM produkty';
        if ($searchCondition !== '') {
            $sql .= ' WHERE ' . $searchCondition;
        }
        $sql .= ' ORDER BY nazev LIMIT 20';
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($searchParams);
        $items = [];
        foreach ($stmt as $row) {
            $items[] = [
                'sku' => (string)$row['sku'],
                'alt_sku' => (string)($row['alt_sku'] ?? ''),
                'nazev' => (string)$row['nazev'],
                'ean' => (string)($row['ean'] ?? ''),
                'merna_jednotka' => (string)($row['merna_jednotka'] ?? ''),
                'typ' => (string)($row['typ'] ?? ''),
            ];
        }
        echo json_encode(['items' => $items]);
    }

    public function bomAdd(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $payload = $this->collectRequestData();
        $parent = $this->toUtf8((string)($payload['parent'] ?? ''));
        $child  = $this->toUtf8((string)($payload['child'] ?? ''));
        $coefRaw = str_replace(',', '.', (string)($payload['koeficient'] ?? ''));
        $unit = $this->toUtf8((string)($payload['merna_jednotka_potomka'] ?? ''));
        $bond = $this->toUtf8((string)($payload['druh_vazby'] ?? ''));
        if ($parent === '' || $child === '') {
            echo json_encode(['ok'=>false,'error'=>'ChybĂ­ rodiÄŤ nebo potomek.']);
            return;
        }
        if (!is_numeric($coefRaw) || (float)$coefRaw <= 0) {
            echo json_encode(['ok'=>false,'error'=>'Koeficient musĂ­ bĂ˝t kladnĂ© ÄŤĂ­slo.']);
            return;
        }
        $parentInfo = $this->fetchProductBasics($parent);
        if (!$parentInfo) {
            echo json_encode(['ok'=>false,'error'=>'RodiÄŤovskĂ˝ produkt neexistuje.']);
            return;
        }
        $childInfo = $this->fetchProductBasics($child);
        if (!$childInfo) {
            echo json_encode(['ok'=>false,'error'=>'Potomek neexistuje.']);
            return;
        }
        if ($unit === '') {
            $unit = $childInfo['merna_jednotka'] ?? '';
        }
        if ($unit === '') {
            echo json_encode(['ok'=>false,'error'=>'Zadejte mÄ›rnou jednotku potomka.']);
            return;
        }
        if ($bond === '') {
            $bond = $this->deriveBondType($parentInfo['typ'] ?? null);
        }
        if (!in_array($bond, ['karton','sada'], true)) {
            echo json_encode(['ok'=>false,'error'=>'NeplatnĂ˝ druh vazby.']);
            return;
        }
        $coef = (float)$coefRaw;
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM bom WHERE rodic_sku=? AND potomek_sku=?')->execute([$parent, $child]);
            $pdo->prepare('INSERT INTO bom (rodic_sku,potomek_sku,koeficient,merna_jednotka_potomka,druh_vazby) VALUES (?,?,?,?,?)')
                ->execute([$parent, $child, $coef, $unit, $bond]);
            $pdo->commit();
            echo json_encode(['ok'=>true]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['ok'=>false,'error'=>'NepodaĹ™ilo se uloĹľit vazbu.']);
        }
    }

    public function bomDelete(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $payload = $this->collectRequestData();
        $parent = $this->toUtf8((string)($payload['parent'] ?? ''));
        $child  = $this->toUtf8((string)($payload['child'] ?? ''));
        if ($parent === '' || $child === '') {
            echo json_encode(['ok'=>false,'error'=>'ChybĂ­ rodiÄŤ nebo potomek.']);
            return;
        }
        $stmt = DB::pdo()->prepare('DELETE FROM bom WHERE rodic_sku=? AND potomek_sku=?');
        $stmt->execute([$parent, $child]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['ok'=>false,'error'=>'Vazba nebyla nalezena.']);
            return;
        }
        echo json_encode(['ok'=>true]);
    }

    private function editableFields(): array
    {
        return ['ean','alt_sku','znacka_id','skupina_id','typ','merna_jednotka','nazev','min_zasoba','min_davka','krok_vyroby','vyrobni_doba_dni','aktivni','poznamka'];
    }

    private function normalizeFieldValue(string $field, $value, string $currentSku = ''): array
    {
        switch ($field) {
            case 'ean':
                $val = $this->toUtf8((string)$value);
                return [$val === '' ? null : $val, null];
            case 'alt_sku':
                $val = $this->toUtf8((string)$value);
                if ($val === '') {
                    return [null, null];
                }
                if ($currentSku === '') {
                    return [null, 'Nelze urÄŤit SKU produktu.'];
                }
                if (mb_strtolower($val, 'UTF-8') === mb_strtolower($currentSku, 'UTF-8')) {
                    return [null, 'alt_sku nesmĂ­ bĂ˝t shodnĂ© se sku.'];
                }
                if ($this->altSkuConflictExists($val, $currentSku)) {
                    return [null, 'alt_sku je jiĹľ pouĹľito nebo koliduje se SKU.'];
                }
                return [$val, null];
            case 'nazev':
                $val = $this->toUtf8((string)$value);
                return $val === '' ? [null, 'NĂˇzev nesmĂ­ bĂ˝t prĂˇzdnĂ˝.'] : [$val, null];
            case 'poznamka':
                $val = $this->toUtf8((string)$value);
                return [$val === '' ? null : $val, null];
            case 'typ':
                $val = $this->toUtf8((string)$value);
                return in_array($val, $this->productTypes(), true) ? [$val, null] : [null, 'NeplatnĂ˝ typ.'];
            case 'merna_jednotka':
                $val = $this->toUtf8((string)$value);
                $units = array_column($this->fetchUnits(), 'kod');
                return in_array($val, $units, true) ? [$val, null] : [null, 'NeznĂˇmĂˇ mÄ›rnĂˇ jednotka.'];
            case 'znacka_id':
                $id = (int)$value;
                if ($id !== 0 && !$this->dictionaryIdExists('produkty_znacky', $id)) {
                    return [null, 'ZnaÄŤka neexistuje.'];
                }
                return [$id ?: null, null];
            case 'skupina_id':
                $id = (int)$value;
                if ($id !== 0 && !$this->dictionaryIdExists('produkty_skupiny', $id)) {
                    return [null, 'Skupina neexistuje.'];
                }
                return [$id ?: null, null];
            case 'min_zasoba':
            case 'min_davka':
            case 'krok_vyroby':
                return $this->normalizeDecimal($value);
            case 'vyrobni_doba_dni':
                return $this->normalizeInteger($value);
            case 'aktivni':
                return [(int)($value === '0' ? 0 : 1), null];
        }
        return [null, 'NeznĂˇmĂ© pole.'];
    }

    private function normalizeDecimal($value): array
    {
        $val = trim((string)$value);
        if ($val === '') {
            return ['0', null];
        }
        if (!is_numeric($val)) {
            return [null, 'Hodnota musĂ­ bĂ˝t ÄŤĂ­slo.'];
        }
        return [$val, null];
    }

    private function normalizeInteger($value): array
    {
        $val = trim((string)$value);
        if ($val === '') {
            return [0, null];
        }
        if (!is_numeric($val)) {
            return [null, 'Hodnota musĂ­ bĂ˝t ÄŤĂ­slo.'];
        }
        return [(int)$val, null];
    }

    private function fetchProducts(?array $filters = null): array
    {
        $filters ??= $this->currentFilters();
        if (!$this->searchTriggered()) {
            return [];
        }
        $sql = $this->productsSelectSql();
        $conditions = [];
        $params = [];

        $brandId = (int)($filters['brand'] ?? 0);
        if ($brandId > 0) {
            $conditions[] = 'p.znacka_id = ?';
            $params[] = $brandId;
        }

        $groupId = (int)($filters['group'] ?? 0);
        if ($groupId > 0) {
            $conditions[] = 'p.skupina_id = ?';
            $params[] = $groupId;
        }

        $type = (string)($filters['type'] ?? '');
        if ($type !== '' && in_array($type, $this->productTypes(), true)) {
            $conditions[] = 'p.typ = ?';
            $params[] = $type;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            [$searchCondition, $searchParams] = $this->buildSearchClauses(
                $search,
                ['p.sku','p.alt_sku','p.nazev','p.ean']
            );
            if ($searchCondition !== '') {
                $conditions[] = $searchCondition;
                $params = array_merge($params, $searchParams);
            }
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.nazev LIMIT 500';

        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function fetchBrands(): array
    {
        return DB::pdo()->query('SELECT id,nazev FROM produkty_znacky ORDER BY nazev')->fetchAll();
    }

    private function fetchGroups(): array
    {
        return DB::pdo()->query('SELECT id,nazev FROM produkty_skupiny ORDER BY nazev')->fetchAll();
    }

    private function fetchUnits(): array
    {
        return DB::pdo()->query('SELECT id,kod FROM produkty_merne_jednotky ORDER BY kod')->fetchAll();
    }

    private function productTypes(): array
    {
        return ['produkt','obal','etiketa','surovina','baleni','karton'];
    }

    /**
     * @param array<int,string> $fields
     * @return array{0:string,1:array<int,string>}
     */
    private function buildSearchClauses(string $search, array $fields): array
    {
        $terms = preg_split('/\s+/u', trim($search)) ?: [];
        $terms = array_values(array_filter($terms, static fn($t) => $t !== ''));
        if (empty($terms)) {
            return ['', []];
        }
        $clauses = [];
        $params = [];
        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $inner = [];
            foreach ($fields as $field) {
                $inner[] = "{$field} LIKE ?";
                $params[] = $like;
            }
            $clauses[] = '(' . implode(' OR ', $inner) . ')';
        }
        return [implode(' AND ', $clauses), $params];
    }

    /**
     * @return array{sku:string,nazev:string,typ:string,merna_jednotka:string,edge:mixed,children:array<int,array>}
     */
    private function buildBomTree(string $sku, array $visited = []): array
    {
        $key = mb_strtolower($sku, 'UTF-8');
        $visited[$key] = true;
        $product = $this->loadProductInfo($sku);
        $product['edge'] = null;
        $children = [];
        foreach ($this->loadBomChildren($sku) as $child) {
            $childKey = mb_strtolower($child['sku'], 'UTF-8');
            if (isset($visited[$childKey])) {
                $children[] = [
                    'sku' => $child['sku'],
                    'nazev' => $child['nazev'],
                    'typ' => $child['typ'],
                    'merna_jednotka' => $child['merna_jednotka'],
                    'edge' => [
                        'koeficient' => $child['koeficient'],
                        'merna_jednotka' => $child['edge_mj'] ?: $child['merna_jednotka'],
                        'druh_vazby' => $child['druh_vazby'],
                    ],
                    'cycle' => true,
                    'children' => [],
                ];
                continue;
            }
            $subtree = $this->buildBomTree($child['sku'], $visited);
            $subtree['edge'] = [
                'koeficient' => $child['koeficient'],
                'merna_jednotka' => $child['edge_mj'] ?: $subtree['merna_jednotka'],
                'druh_vazby' => $child['druh_vazby'],
            ];
            $children[] = $subtree;
        }
        $product['children'] = $children;
        return $product;
    }

    /**
     * @return array{sku:string,nazev:string,typ:string,merna_jednotka:string}
     */
    private function loadProductInfo(string $sku): array
    {
        $stmt = DB::pdo()->prepare('SELECT sku,nazev,typ,merna_jednotka FROM produkty WHERE sku=? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch();
        if (!$row) {
            return [
                'sku' => $sku,
                'nazev' => '(neznĂˇmĂ˝ produkt)',
                'typ' => '',
                'merna_jednotka' => '',
            ];
        }
        return [
            'sku' => (string)$row['sku'],
            'nazev' => (string)$row['nazev'],
            'typ' => (string)$row['typ'],
            'merna_jednotka' => (string)$row['merna_jednotka'],
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function loadBomChildren(string $sku): array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT b.potomek_sku AS sku, b.koeficient, COALESCE(NULLIF(b.merna_jednotka_potomka, \'\'), NULL) AS edge_mj, ' .
            'b.druh_vazby, p.nazev, p.typ, p.merna_jednotka ' .
            'FROM bom b LEFT JOIN produkty p ON p.sku = b.potomek_sku ' .
            'WHERE b.rodic_sku=? ORDER BY b.potomek_sku'
        );
        $stmt->execute([$sku]);
        $rows = [];
        foreach ($stmt as $row) {
            $rows[] = [
                'sku' => (string)$row['sku'],
                'koeficient' => (float)$row['koeficient'],
                'edge_mj' => $row['edge_mj'] === null ? null : (string)$row['edge_mj'],
                'druh_vazby' => (string)$row['druh_vazby'],
                'nazev' => (string)($row['nazev'] ?? '(neznĂˇmĂ˝)'),
                'typ' => (string)($row['typ'] ?? ''),
                'merna_jednotka' => (string)($row['merna_jednotka'] ?? ''),
            ];
        }
        return $rows;
    }

    private function productsSelectSql(): string
    {
        return 'SELECT p.sku,p.alt_sku,p.nazev,p.typ,p.merna_jednotka,p.ean,p.min_zasoba,p.min_davka,p.krok_vyroby,p.vyrobni_doba_dni,p.aktivni,p.znacka_id,p.skupina_id,p.poznamka,zb.nazev AS znacka,sg.nazev AS skupina ' .
               'FROM produkty p ' .
               'LEFT JOIN produkty_znacky zb ON p.znacka_id = zb.id ' .
               'LEFT JOIN produkty_skupiny sg ON p.skupina_id = sg.id';
    }

    /**
     * @return array{0:array<string,array{sku:string,alt:?string}>,1:array<string,string>}
     */
    private function loadExistingSkuMaps(): array
    {
        $skuMap = [];
        $altMap = [];
        foreach (DB::pdo()->query('SELECT sku, alt_sku FROM produkty') as $row) {
            $sku = trim((string)$row['sku']);
            if ($sku === '') {
                continue;
            }
            $skuKey = mb_strtolower($sku, 'UTF-8');
            $alt = $row['alt_sku'] === null ? null : trim((string)$row['alt_sku']);
            $skuMap[$skuKey] = [
                'sku' => $sku,
                'alt' => $alt === '' ? null : $alt,
            ];
            if ($alt !== null && $alt !== '') {
                $altMap[mb_strtolower($alt, 'UTF-8')] = $sku;
            }
        }
        return [$skuMap, $altMap];
    }

    private function altSkuConflictExists(string $altSku, string $currentSku = ''): bool
    {
        if ($altSku === '') {
            return false;
        }
        $ownerNorm = $currentSku === '' ? '' : mb_strtolower($currentSku, 'UTF-8');
        if ($ownerNorm !== '' && $ownerNorm === mb_strtolower($altSku, 'UTF-8')) {
            return true;
        }
        $stmt = DB::pdo()->prepare('SELECT 1 FROM produkty WHERE (sku = :alt OR alt_sku = :alt) AND (:sku = \'\' OR sku <> :sku) LIMIT 1');
        $stmt->execute([
            ':alt' => $altSku,
            ':sku' => $currentSku,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    private function loadDictionary(string $table, string $column = 'nazev'): array
    {
        $map = [];
        $stmt = DB::pdo()->query("SELECT id,{$column} AS value FROM {$table}");
        foreach ($stmt as $row) {
            $map[mb_strtolower((string)$row['value'], 'UTF-8')] = (int)$row['id'];
        }
        return $map;
    }

    private function loadUnitsDictionary(): array
    {
        $map = [];
        foreach (DB::pdo()->query('SELECT kod FROM produkty_merne_jednotky') as $row) {
            $value = trim((string)$row['kod']);
            if ($value === '') {
                continue;
            }
            $map[mb_strtolower($value, 'UTF-8')] = $value;
        }
        return $map;
    }

    private function fetchProductBasics(string $sku): ?array
    {
        if ($sku === '') {
            return null;
        }
        $stmt = DB::pdo()->prepare('SELECT sku,nazev,typ,merna_jednotka FROM produkty WHERE sku=? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'sku' => (string)$row['sku'],
            'nazev' => (string)$row['nazev'],
            'typ' => (string)$row['typ'],
            'merna_jednotka' => (string)$row['merna_jednotka'],
        ];
    }

    private function deriveBondType(?string $parentType): string
    {
        return $parentType === 'karton' ? 'karton' : 'sada';
    }

    private function collectRequestData(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        $raw = file_get_contents('php://input');
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function dictionaryIdExists(string $table, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = DB::pdo()->prepare("SELECT 1 FROM {$table} WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    private function readCsvRow($handle)
    {
        return fgetcsv($handle, 0, ';', '"', '\\');
    }

    private function toUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (mb_detect_encoding($value, 'UTF-8', true) === false) {
            $value = mb_convert_encoding($value, 'UTF-8', 'WINDOWS-1250,ISO-8859-2,ISO-8859-1');
        }
        return trim($value);
    }

    private function flashProductImportSuccess(string $message, array $errors): void
    {
        $_SESSION['products_import_message'] = $message;
        $_SESSION['products_import_errors'] = $errors;
        unset($_SESSION['products_error']);
        header('Location: /products#product-import');
        exit;
    }

    private function flashProductImportError(string $message): void
    {
        $_SESSION['products_error'] = $message;
        unset($_SESSION['products_import_message']);
        $_SESSION['products_import_errors'] = [];
        header('Location: /products#product-import');
        exit;
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }

    private function requireAdmin(): void
    {
        $this->requireAuth();
        $role = $_SESSION['user']['role'] ?? 'user';
        if (!in_array($role, ['admin','superadmin'], true)) {
            http_response_code(403);
            echo 'PĹ™Ă­stup jen pro admina.';
            exit;
        }
    }

    private function currentFilters(): array
    {
        $brand = (int)($_GET['znacka_id'] ?? 0);
        $group = (int)($_GET['skupina_id'] ?? 0);
        $typeRaw = trim((string)($_GET['typ'] ?? ''));
        $type = in_array($typeRaw, $this->productTypes(), true) ? $typeRaw : '';
        $search = $this->toUtf8((string)($_GET['q'] ?? ''));
        return [
            'brand' => $brand > 0 ? $brand : 0,
            'group' => $group > 0 ? $group : 0,
            'type'  => $type,
            'search'=> $search,
        ];
    }

    private function searchTriggered(): bool
    {
        return isset($_GET['search']);
    }

    private function countBomLinks(): int
    {
        $stmt = DB::pdo()->query('SELECT COUNT(*) FROM bom');
        return (int)$stmt->fetchColumn();
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}

