<?php

namespace App\Controller;



use App\Service\StockService;

use App\Support\DB;



final class ProductionController

{

    public function plans(): void

    {

        $this->requireAuth();

        $pdo = DB::pdo();

        $filters = $this->currentFilters();

        $hasSearch = isset($_GET['search']);

        $items = [];



        if ($hasSearch) {

            [$searchCondition, $searchParams] = $this->buildSearchClauses(

                $filters['search'],

                ['sku','nazev','alt_sku','ean']

            );



            $conditions = ['aktivni = 1'];

            $params = [];



            if ($filters['type'] !== '') {

                $conditions[] = 'typ = ?';

                $params[] = $filters['type'];

            }

            if ($filters['brand'] > 0) {

                $conditions[] = 'COALESCE(znacka_id,0) = ?';

                $params[] = $filters['brand'];

            }

            if ($filters['group'] > 0) {

                $conditions[] = 'COALESCE(skupina_id,0) = ?';

                $params[] = $filters['group'];

            }

            if ($searchCondition !== '') {

                $conditions[] = '(' . $searchCondition . ')';

                $params = array_merge($params, $searchParams);

            }



            $where = implode(' AND ', $conditions);

            $sql = 'SELECT p.sku,p.typ,p.nazev,p.min_zasoba,p.min_davka,p.krok_vyroby,p.vyrobni_doba_dni,COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code=p.typ WHERE ' .

                $where . ' ORDER BY p.nazev';

            $stmt = $pdo->prepare($sql);

            $stmt->execute($params);

            $items = $stmt->fetchAll();

            if ($items) {

                $skus = array_map('strval', array_column($items, 'sku'));

                $graph = StockService::getBomGraph();

                $children = $graph['children'] ?? [];

                $descendantCache = [];

                $statusSkus = $skus;

                foreach ($skus as $skuValue) {

                    $desc = $this->collectDescendants($skuValue, $children, $descendantCache);

                    if ($desc) {

                        $statusSkus = array_merge($statusSkus, $desc);

                    }

                }

                $statusSkus = array_values(array_unique(array_filter($statusSkus)));

                $statusMap = $statusSkus ? StockService::getStatusForSkus($statusSkus) : [];



                foreach ($items as &$item) {

                    $sku = (string)$item['sku'];

                    $status = $statusMap[$sku] ?? [];

                    $item['stock'] = $status['stock'] ?? 0.0;

                    $item['stav'] = $status['available'] ?? 0.0;

                    $item['available'] = $status['available'] ?? 0.0;

                    $item['reservations'] = $status['reservations'] ?? 0.0;

                    $item['target'] = $status['target'] ?? (float)($item['min_zasoba'] ?? 0.0);

                    $item['deficit'] = $status['deficit'] ?? 0.0;

                    $item['ratio'] = $status['ratio'] ?? 0.0;

                    $item['mode'] = $status['mode'] ?? 'manual';

                    $item['daily'] = $status['daily'] ?? 0.0;

                    $blockers = [];

                    if ($item['deficit'] > 0.0) {

                        $blockers = $this->detectBlockingComponents($sku, (float)$item['deficit'], $children, $statusMap);

                    }

                    $item['blockers'] = $blockers;

                    $item['blocked'] = !empty($blockers);

                }

                unset($item);

                usort($items, function ($a, $b) {

                    $ratioA = $a['ratio'] ?? 0.0;

                    $ratioB = $b['ratio'] ?? 0.0;

                    if ($ratioA === $ratioB) {

                        return ($b['deficit'] ?? 0.0) <=> ($a['deficit'] ?? 0.0);

                    }

                    return $ratioB <=> $ratioA;

                });

            }

        }
        $recentLimit = $this->getRecentLimit();
        $this->render('production_plans.php', [
            'title' => 'Výroba – návrhy',
            'items' => $items,
            'brands' => $this->fetchBrands(),
            'groups' => $this->fetchGroups(),
            'types' => $this->productTypes(),
            'filters' => $filters,

            'hasSearch' => $hasSearch,

            'resultCount' => $hasSearch ? count($items) : 0,

            'recentProductions' => $this->recentProductions($recentLimit),

            'recentLimit' => $recentLimit,

            'isAdmin' => $this->isAdmin(),
        ]);

    }



    public function produce(): void

    {

        $this->requireAuth();

        $sku = $this->toUtf8((string)($_POST['sku'] ?? ''));

        $qty = (float)($_POST['mnozstvi'] ?? 0);

        $mode = (string)($_POST['modus'] ?? 'odecti_subpotomky'); // odecti_subpotomky | korekce | korekce_skladu
        $returnUrl = $this->sanitizeReturnUrl((string)($_POST['return_url'] ?? ''));

        $metaStmt = DB::pdo()->prepare('SELECT COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ WHERE p.sku=? LIMIT 1');
        $metaStmt->execute([$sku]);
        $meta = $metaStmt->fetch();

        if ($sku === '' || $qty <= 0 || empty($meta)) {

            $this->redirect($returnUrl ?: '/production/plans');

            return;

        }

        if (!empty($meta['is_nonstock'])) {

            $this->redirect($returnUrl ?: '/production/plans');

            return;

        }

        if ($mode === 'korekce_skladu') {

            $this->requireAdmin();

            $pdo = DB::pdo();

            $ref = 'corr-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

            $ins = $pdo->prepare('INSERT INTO polozky_pohyby (datum,sku,mnozstvi,merna_jednotka,typ_pohybu,poznamka,ref_id) VALUES (NOW(),?,?,?,?,?,?)');

            $ins->execute([$sku, $qty, null, 'korekce', 'Manuální korekce skladu', $ref]);

            $this->redirect($returnUrl ?: '/production/plans');

            return;

        }
        if ($sku === '' || $qty <= 0) {

            $this->redirect($returnUrl ?: '/production/plans');

            return;

        }

        $pdo = DB::pdo();

        $ref = 'prod-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

        $ins = $pdo->prepare('INSERT INTO polozky_pohyby (datum,sku,mnozstvi,merna_jednotka,typ_pohybu,poznamka,ref_id) VALUES (NOW(),?,?,?,?,?,?)');

        $ins->execute([$sku, $qty, null, 'vyroba', null, $ref]);



        if ($mode === 'odecti_subpotomky') {

            $components = $this->loadBomComponents($sku);

            foreach ($components as $component) {

                $required = $qty * $component['koeficient'];

                $ins->execute([

                    $component['sku'],

                    -1 * $required,

                    $component['merna_jednotka'],

                    'vyroba',

                    'odeÄŤĂ­st komponenty',

                    $ref

                ]);

            }

        } else {

            $ins->execute([$sku . '*', 0, null, 'korekce', 'Komponenty k odeÄŤtu â€“ Ĺ™eĹˇit ruÄŤnÄ›', $ref]);

        }



        $this->redirect($returnUrl ?: '/production/plans');

    }



    public function check(): void

    {

        $this->requireAuth();

        header('Content-Type: application/json');

        $data = $this->collectJsonRequest();

        $sku = $this->toUtf8((string)($data['sku'] ?? ''));

        $qty = (float)($data['mnozstvi'] ?? 0);

        if ($sku === '' || $qty <= 0) {

            echo json_encode(['ok'=>false,'error'=>'Zadejte platnĂ© SKU i mnoĹľstvĂ­.']);

            return;

        }

        $deficits = $this->calculateDeficits($sku, $qty);

        echo json_encode(['ok'=>true,'deficits'=>$deficits]);

    }



    public function demandTree(): void

    {

        $this->requireAuth();

        header('Content-Type: application/json');

        $sku = $this->toUtf8((string)($_GET['sku'] ?? ''));

        if ($sku === '') {

            echo json_encode(['ok' => false, 'error' => 'ChybĂ­ SKU.']);

            return;

        }

        try {

            $graph = StockService::getBomGraph();

            $parentsMap = $graph['parents'] ?? [];

            $relevant = $this->collectDemandAncestors($sku, $parentsMap);

            if (empty($relevant)) {

                $relevant = [$sku];

            }

            $statusMap = StockService::getStatusForSkus($relevant);

            $basics = $this->fetchBasicsForSkus($relevant);

            $rootNeed = max(0.0, (float)($statusMap[$sku]['deficit'] ?? 0.0));

            $tree = $this->buildDemandTreeNode(

                $sku,

                $parentsMap,

                $statusMap,

                $basics,

                [],

                $rootNeed,

                null,

                true

            );

            echo json_encode(['ok' => true, 'tree' => $tree]);

        } catch (\Throwable $e) {

            echo json_encode(['ok' => false, 'error' => 'NepodaĹ™ilo se naÄŤĂ­st zdroje poptĂˇvky.']);

        }

    }



    public function deleteRecord(): void

    {

        $this->requireAuth();

        $ref = (string)($_POST['ref_id'] ?? '');

        if ($ref !== '') {

            DB::pdo()->prepare('DELETE FROM polozky_pohyby WHERE ref_id=?')->execute([$ref]);

        }

        $this->redirect('/production/plans');

    }



    private function calculateDeficits(string $sku, float $qty): array

    {

        $components = $this->loadBomComponents($sku);

        if (empty($components)) {

            return [];

        }

        $childSkus = array_column($components, 'sku');

        $stock = StockService::buildStockMap($childSkus);

        $reservations = StockService::buildReservationMap($childSkus);

        $names = $this->loadProductNames($childSkus);

        $deficits = [];

        foreach ($components as $component) {

            $required = $qty * $component['koeficient'];

            $available = ($stock[$component['sku']] ?? 0.0) - ($reservations[$component['sku']] ?? 0.0);

            $missing = $required - $available;

            if ($missing > 0) {

                $deficits[] = [

                    'sku' => $component['sku'],

                    'nazev' => $names[$component['sku']] ?? '',

                    'required' => $this->formatQty($required),

                    'available' => $this->formatQty($available),

                    'missing' => $this->formatQty($missing),

                ];

            }

        }

        return $deficits;

    }



    private function loadBomComponents(string $sku): array

    {

        $stmt = DB::pdo()->prepare("SELECT potomek_sku AS sku, koeficient, merna_jednotka_potomka FROM bom WHERE rodic_sku=?");

        $stmt->execute([$sku]);

        $list = [];

        foreach ($stmt as $row) {

            $list[] = [

                'sku' => (string)$row['sku'],

                'koeficient' => (float)$row['koeficient'],

                'merna_jednotka' => $row['merna_jednotka_potomka'] !== '' ? (string)$row['merna_jednotka_potomka'] : null,

            ];

        }

        return $list;

    }



    private function loadProductNames(array $skus): array

    {

        if (empty($skus)) {

            return [];

        }

        $placeholders = implode(',', array_fill(0, count($skus), '?'));

        $stmt = DB::pdo()->prepare("SELECT sku, nazev FROM produkty WHERE sku IN ({$placeholders})");

        $stmt->execute($skus);

        $names = [];

        foreach ($stmt as $row) {

            $names[(string)$row['sku']] = (string)$row['nazev'];

        }

        return $names;

    }



    /**

     * @param array<string,array<int,array{sku:string,koeficient:float}>> $children

     * @param array<string,array<int,string>> $cache

     * @return array<int,string>

     */

    private function collectDescendants(string $root, array $children, array &$cache): array

    {

        if (isset($cache[$root])) {

            return $cache[$root];

        }

        $result = [];

        $stack = [$root];

        $visited = [$root => true];

        while ($stack) {

            $node = array_pop($stack);

            foreach ($children[$node] ?? [] as $edge) {

                $childSku = (string)$edge['sku'];

                if ($childSku === '' || isset($visited[$childSku])) {

                    continue;

                }

                $visited[$childSku] = true;

                $result[$childSku] = true;

                $stack[] = $childSku;

            }

        }

        $cache[$root] = array_keys($result);

        return $cache[$root];

    }



    /**

     * @param array<string,array<int,array{sku:string,koeficient:float}>> $children

     * @param array<string,array<string,mixed>> $statusMap

     * @return array<int,array{sku:string,required:float,available:float,missing:float}>

     */

    private function detectBlockingComponents(string $rootSku, float $quantity, array $children, array $statusMap): array

    {

        $quantity = max(0.0, $quantity);

        if ($quantity <= 0.0 || empty($children[$rootSku])) {

            return [];

        }

        $requirements = [];

        $path = [];

        $this->accumulateRequirements($rootSku, $quantity, $children, $requirements, $path);

        $blocking = [];

        foreach ($requirements as $sku => $required) {

            $available = $statusMap[$sku]['available'] ?? 0.0;

            $missing = $required - $available;

            if ($missing > 0.0005) {

                $blocking[] = [

                    'sku' => $sku,

                    'required' => $required,

                    'available' => $available,

                    'missing' => $missing,

                ];

            }

        }

        return $blocking;

    }



    /**

     * @param array<string,array<int,array{sku:string,koeficient:float}>> $children

     * @param array<string,float> $requirements

     * @param array<string,bool> $path

     */

    private function accumulateRequirements(string $nodeSku, float $quantity, array $children, array &$requirements, array &$path): void

    {

        if ($quantity <= 0.0) {

            return;

        }

        $path[$nodeSku] = true;

        foreach ($children[$nodeSku] ?? [] as $edge) {

            $childSku = (string)$edge['sku'];

            if ($childSku === '' || isset($path[$childSku])) {

                continue;

            }

            $coef = (float)($edge['koeficient'] ?? 0.0);

            if ($coef <= 0.0) {

                continue;

            }

            $childQty = $quantity * $coef;

            if ($childQty <= 0.0) {

                continue;

            }

            $requirements[$childSku] = ($requirements[$childSku] ?? 0.0) + $childQty;

            $this->accumulateRequirements($childSku, $childQty, $children, $requirements, $path);

        }

        unset($path[$nodeSku]);

    }



    /**

     * @param array<string,array<int,array{sku:string,koeficient:float,merna_jednotka:?string}>> $parents

     * @return array<int,string>

     */

    private function collectDemandAncestors(string $sku, array $parents): array

    {

        $queue = [$sku];

        $result = [];

        while ($queue) {

            $current = array_shift($queue);

            $norm = mb_strtolower($current, 'UTF-8');

            if (isset($result[$norm])) {

                continue;

            }

            $result[$norm] = $current;

            foreach ($parents[$current] ?? [] as $edge) {

                $queue[] = (string)$edge['sku'];

            }

        }

        return array_values($result);

    }



    /**

     * @param array<string,array<int,array{sku:string,koeficient:float,merna_jednotka:?string}>> $parents

     * @param array<string,array<string,mixed>> $statusMap

     * @param array<string,array{sku:string,nazev:string,typ:string,merna_jednotka:string}> $meta

     * @param array<string,bool> $path

     */

    private function buildDemandTreeNode(

        string $sku,

        array $parents,

        array $statusMap,

        array $meta,

        array $path,

        float $contribution,

        ?array $edge,

        bool $isRoot = false

    ): array {

        $info = $meta[$sku] ?? [

            'sku' => $sku,

            'nazev' => '(neznĂˇmĂ˝ produkt)',

            'typ' => '',

            'merna_jednotka' => '',

        ];

        $status = $statusMap[$sku] ?? [];

        $needed = max(0.0, (float)($status['deficit'] ?? 0.0));

        $node = [

            'sku' => $info['sku'],

            'nazev' => $info['nazev'],

            'typ' => $info['typ'],

            'merna_jednotka' => $info['merna_jednotka'],

            'status' => $status,

            'needed' => $needed,

            'contribution' => $contribution,

            'edge' => $edge,

            'is_root' => $isRoot,

            'children' => [],

        ];

        $path[$sku] = true;

        foreach ($parents[$sku] ?? [] as $parentEdge) {

            $parentSku = (string)$parentEdge['sku'];

            $edgeUnit = $parentEdge['merna_jednotka'] ?? '';

            if ($edgeUnit === '') {

                $edgeUnit = $meta[$sku]['merna_jednotka'] ?? '';

            }

            $edgePayload = [

                'koeficient' => (float)($parentEdge['koeficient'] ?? 0.0),

                'merna_jednotka' => $edgeUnit,

            ];

            if (isset($path[$parentSku])) {

                $node['children'][] = [

                    'sku' => $parentSku,

                    'nazev' => $meta[$parentSku]['nazev'] ?? $parentSku,

                    'typ' => $meta[$parentSku]['typ'] ?? '',

                    'merna_jednotka' => $meta[$parentSku]['merna_jednotka'] ?? '',

                    'status' => $statusMap[$parentSku] ?? [],

                    'needed' => max(0.0, (float)($statusMap[$parentSku]['deficit'] ?? 0.0)),

                    'contribution' => max(0.0, (float)($statusMap[$parentSku]['deficit'] ?? 0.0)) * $edgePayload['koeficient'],

                    'edge' => $edgePayload,

                    'cycle' => true,

                    'children' => [],

                ];

                continue;

            }

            $parentNeeded = max(0.0, (float)($statusMap[$parentSku]['deficit'] ?? 0.0));

            if ($parentNeeded <= 0.0) {

                continue;

            }

            $childContribution = $parentNeeded * $edgePayload['koeficient'];

            $node['children'][] = $this->buildDemandTreeNode(

                $parentSku,

                $parents,

                $statusMap,

                $meta,

                $path,

                $childContribution,

                $edgePayload,

                false

            );

        }

        return $node;

    }



    /**

     * @return array<string,array{sku:string,nazev:string,typ:string,merna_jednotka:string}>

     */

    private function fetchBasicsForSkus(array $skus): array

    {

        $skus = array_values(array_filter(array_map('strval', array_unique($skus))));

        if (empty($skus)) {

            return [];

        }

        $placeholders = implode(',', array_fill(0, count($skus), '?'));

        $stmt = DB::pdo()->prepare("SELECT sku,nazev,typ,merna_jednotka FROM produkty WHERE sku IN ({$placeholders})");

        $stmt->execute($skus);

        $map = [];

        foreach ($stmt as $row) {

            $map[(string)$row['sku']] = [

                'sku' => (string)$row['sku'],

                'nazev' => (string)($row['nazev'] ?? ''),

                'typ' => (string)($row['typ'] ?? ''),

                'merna_jednotka' => (string)($row['merna_jednotka'] ?? ''),

            ];

        }

        return $map;

    }



    private function collectJsonRequest(): array

    {

        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {

            return [];

        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];

    }



    private function toUtf8(string $value): string

    {

        if ($value === '') {

            return '';

        }

        if (mb_detect_encoding($value, 'UTF-8', true) === false) {

            $value = mb_convert_encoding($value, 'UTF-8');

        }

        return trim($value);

    }



    private function formatQty(float $value): string

    {

        $formatted = number_format($value, 6, '.', '');

        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;

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

        if (!$this->isAdmin()) {

            http_response_code(403);

            $this->render('forbidden.php', [

                'title' => 'Přístup odepřen',

                'message' => 'Korekce skladu je dostupná jen pro administrátory.',

            ]);

            exit;

        }

    }



    private function isAdmin(): bool

    {

        $role = $_SESSION['user']['role'] ?? 'user';

        return in_array($role, ['admin','superadmin'], true);

    }



    private function render(string $view, array $vars=[]): void

    {

        extract($vars);

        require __DIR__ . '/../../views/_layout.php';

    }



    public function updateRecentLimit(): void

    {

        $this->requireAuth();

        $limit = (int)($_POST['recent_limit'] ?? 30);

        if ($limit < 1) {

            $limit = 1;

        } elseif ($limit > 500) {

            $limit = 500;

        }

        $_SESSION['production_recent_limit'] = $limit;

        $returnUrl = $this->sanitizeReturnUrl((string)($_POST['return_url'] ?? ''));

        $this->redirect($returnUrl ?: '/production/plans');

    }



    private function recentProductions(int $limit = 30): array
    {
        $sql = "SELECT m.datum, m.sku, m.mnozstvi, m.typ_pohybu AS typ, p.nazev
                FROM polozky_pohyby m
                LEFT JOIN produkty p ON p.sku = m.sku
                WHERE m.typ_pohybu IN ('vyroba','korekce')
                ORDER BY m.datum DESC, m.id DESC
                LIMIT {$limit}";

        return DB::pdo()->query($sql)->fetchAll();
    }



    private function redirect(string $path): void

    {

        header('Location: '.$path, true, 302);

        exit;

    }



    private function currentFilters(): array

    {

        $brand = (int)($_GET['znacka_id'] ?? 0);

        $group = (int)($_GET['skupina_id'] ?? 0);

        $typeRaw = $this->toUtf8((string)($_GET['typ'] ?? ''));

        $type = in_array($typeRaw, $this->productTypes(), true) ? $typeRaw : '';

        $search = $this->toUtf8((string)($_GET['q'] ?? ''));

        return [

            'brand' => $brand > 0 ? $brand : 0,

            'group' => $group > 0 ? $group : 0,

            'type'  => $type,

            'search'=> $search,

        ];

    }



    private function fetchBrands(): array

    {

        return DB::pdo()->query('SELECT id,nazev FROM produkty_znacky ORDER BY nazev')->fetchAll();

    }



    private function fetchGroups(): array

    {

        return DB::pdo()->query('SELECT id,nazev FROM produkty_skupiny ORDER BY nazev')->fetchAll();

    }



    private function productTypes(): array

    {

        $stmt = DB::pdo()->query('SELECT code FROM product_types ORDER BY name');
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

    }



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



    private function getRecentLimit(): int

    {

        $limit = isset($_SESSION['production_recent_limit']) ? (int)$_SESSION['production_recent_limit'] : 30;

        return $limit > 0 ? $limit : 30;

    }



    private function sanitizeReturnUrl(string $url): string

    {

        if ($url === '') {

            return '';

        }

        if (str_starts_with($url, '/')) {

            return $url;

        }

        return '';

    }

}

