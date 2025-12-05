<?php

namespace App\Controller;

use App\Support\DB;
use App\Service\AnalyticsSchema;
use PDO;

final class AnalyticsController
{
    // No-op change to trigger deployment; logic unchanged.
    // Another no-op touch.

    // PoznĂˇmka: Tipy a aliasy nĂ­Ĺľe se odvozujĂ­ od aktuĂˇlnĂ­ho schĂ©matu DB, proto udrĹľuj aktualizovanĂ© pokyny, pokud se DB mÄ›nĂ­.
    public function revenue(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        $templates = $this->loadTemplatesV2();
        $favoritesV2 = $this->loadFavoritesV2();
        $this->render('analytics_revenue.php', [
            'title' => 'AnalĂ˝za',
            'templates' => $templates,
            'favoritesV2' => $favoritesV2,
        ]);
    }

    public function ai(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');

        $payload = $this->collectJson();
        $prompt = trim((string)($payload['prompt'] ?? ''));
        $title = trim((string)($payload['title'] ?? ''));
        $saveFavorite = !empty($payload['saveFavorite']);

        if ($prompt === '') {
            $this->jsonError('Zadejte prosĂ­m prompt.');
            return;
        }

        $apiKey = $this->resolveOpenAiKey();
        if ($apiKey === '') {
            $this->jsonError('ChybĂ­ serverovĂˇ promÄ›nnĂˇ OPENAI_API_KEY.');
            return;
        }

        $model = getenv('OPENAI_MODEL') ?: ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini');
        $schema = $this->schemaProvider();
        $systemPrompt = $this->buildSystemPrompt($schema->summary());

        $requestBody = [
            'model' => $model,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        try {
            $aiResponse = $this->callOpenAi($apiKey, $requestBody);
        } catch (\Throwable $e) {
            $this->jsonError('OpenAI API nenĂ­ dostupnĂ©: ' . $e->getMessage());
            return;
        }

        $plan = $this->parseAiPlan($aiResponse);
        if ($plan === null) {
            $this->jsonError('AI vrĂˇtila neoÄŤekĂˇvanĂ˝ vĂ˝stup.');
            return;
        }

        $renderedOutputs = [];
        foreach ($plan['outputs'] as $output) {
            $sql = trim((string)($output['sql'] ?? ''));
            if (!$this->isSelectQuery($sql)) {
                continue;
            }
            try {
                $tables = $schema->extractTables($sql);
                $rows = $this->runSelect($sql, $tables);
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $missingCol = $schema->extractMissingColumn($message);
                if ($missingCol) {
                    $suggest = $schema->suggestColumns($missingCol, $tables);
                    if (!empty($suggest)) {
                        $message = "NeznĂˇmĂ˝ sloupec '{$missingCol}'. Zkuste: " . implode(', ', $suggest);
                    }
                }
                $renderedOutputs[] = [
                    'type' => 'error',
                    'title' => $output['title'] ?? 'Dotaz nelze spustit',
                    'message' => $message,
                ];
                continue;
            }
            if (($output['type'] ?? '') === 'line_chart') {
                $renderedOutputs[] = [
                    'type' => 'line_chart',
                    'title' => $output['title'] ?? 'Graf',
                    'xColumn' => $output['x_column'] ?? null,
                    'yColumn' => $output['y_column'] ?? null,
                    'seriesLabel' => $output['series_label'] ?? 'Hodnota',
                    'seriesColumn' => $output['series_column'] ?? null,
                    'rows' => $rows,
                ];
            } else {
                $renderedOutputs[] = [
                    'type' => 'table',
                    'title' => $output['title'] ?? 'Tabulka',
                    'columns' => $output['columns'] ?? null,
                    'rows' => $rows,
                ];
            }
        }

        $response = [
            'ok' => true,
            'language' => $plan['language'],
            'explanation' => $plan['explanation'],
            'outputs' => $renderedOutputs,
        ];

        if ($saveFavorite && $title !== '') {
            $this->saveFavorite($title, $prompt);
            $response['favorites'] = $this->loadFavorites();
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    private function loadFavorites(): array
    {
        $userId = $this->currentUserId();
        $pdo = DB::pdo();
        $mineStmt = $pdo->prepare('SELECT id, title, prompt, created_at FROM ai_prompts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
        $mineStmt->execute([$userId]);
        $mine = $mineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sharedStmt = $pdo->prepare('SELECT id, user_id, title, prompt, created_at FROM ai_prompts WHERE is_public = 1 AND user_id <> ? ORDER BY created_at DESC LIMIT 20');
        $sharedStmt->execute([$userId]);
        $shared = $sharedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'mine' => array_map(fn($row) => [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'prompt' => (string)$row['prompt'],
                'created_at' => (string)$row['created_at'],
            ], $mine),
            'shared' => array_map(fn($row) => [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'prompt' => (string)$row['prompt'],
                'created_at' => (string)$row['created_at'],
            ], $shared),
        ];
    }

    public function saveFavoriteAjax(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        $payload = $this->collectJson();
        $title = trim((string)($payload['title'] ?? ''));
        $prompt = trim((string)($payload['prompt'] ?? ''));
        if ($title === '' || $prompt === '') {
            echo json_encode(['ok' => false, 'error' => 'NĂˇzev i text promptu jsou povinnĂ©.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $this->saveFavorite($title, $prompt);
        echo json_encode(['ok' => true, 'favorites' => $this->loadFavorites()], JSON_UNESCAPED_UNICODE);
    }

    private function saveFavorite(string $title, string $prompt): void
    {
        $userId = $this->currentUserId();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO ai_prompts (user_id, title, prompt, is_public) VALUES (?,?,?,1)');
        $stmt->execute([$userId, $title, $prompt]);
    }

    public function deleteFavoriteAjax(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        $payload = $this->collectJson();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'NeplatnĂ© ID.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $userId = $this->currentUserId();
        $st = DB::pdo()->prepare('DELETE FROM ai_prompts WHERE id = ? AND user_id = ? LIMIT 1');
        $st->execute([$id, $userId]);
        echo json_encode(['ok' => true, 'favorites' => $this->loadFavorites()], JSON_UNESCAPED_UNICODE);
    }

    private function parseAiPlan(string $content): ?array
    {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }
        $language = (string)($data['language'] ?? 'cs');
        $explanation = trim((string)($data['explanation'] ?? ''));
        $outputs = [];
        foreach (($data['outputs'] ?? []) as $item) {
            if (!is_array($item) || empty($item['sql'])) {
                continue;
            }
            $outputs[] = $item;
        }
        return [
            'language' => $language !== '' ? $language : 'cs',
            'explanation' => $explanation,
            'outputs' => $outputs,
        ];
    }

    private function runSelect(string $sql, array $tables = []): array
    {
        $sql = $this->sanitizeSql($sql);
        $this->validateSqlIsSafe($sql);
        $sql = $this->appendLimit($sql);
        try {
            $stmt = DB::pdo()->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows ?: [];
        } catch (\Throwable $e) {
            // PĹ™idĂˇme SQL do hlĂˇĹˇky, aby bylo vidÄ›t, co spadlo (pomĹŻĹľe s ladÄ›nĂ­m GROUP BY/HAVING).
            throw new \RuntimeException($e->getMessage() . ' | SQL: ' . $sql, 0, $e);
        }
    }

    private function appendLimit(string $sql): string
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', $sql));
        if (str_contains($normalized, ' limit ')) {
            return $sql;
        }
        return rtrim($sql, '; ') . ' LIMIT 500';
    }

    private function isSelectQuery(string $sql): bool
    {
        $trimmed = ltrim($sql);
        return (bool)preg_match('/^select/i', $trimmed);
    }

    private function validateSqlIsSafe(string $sql): void
    {
        $clean = strtolower(preg_replace('/\s+/', ' ', $sql));
        if (!preg_match('/^select\s+/i', $sql) || str_contains($sql, ';')) {
            throw new \RuntimeException('Povoleny jsou jen dotazy SELECT bez stĹ™ednĂ­kĹŻ.');
        }
        $blocked = [' insert ', ' update ', ' delete ', ' drop ', ' alter ', ' truncate ', ' create ', ' into ', ' outfile ', ' infile ', ' grant ', ' revoke '];
        foreach ($blocked as $word) {
            if (str_contains($clean, $word)) {
                throw new \RuntimeException('Dotaz musĂ­ bĂ˝t pouze SELECT nad reportovacĂ­mi daty.');
            }
        }
    }

    private function buildSystemPrompt(string $schema): string
    {
        return <<<PROMPT
Jsi datovy analytik ve firme WormUP. Mas pristup pouze k databazi MySQL uvedene nize. Odpovidej ve stejnem jazyce jako uzivatel.

Vystup vracej jako platny JSON objekt (response_format json_object) s klici:
- "language": kod jazyka odpovedi (napr. "cs" nebo "en"),
- "explanation": kratke srozumitelne shrnuti vysledku,
- "outputs": pole objektu popisujicich tabulky nebo grafy, kazdy ve tvaru:
  {
    "type": "table" | "line_chart",
    "title": "Titulek",
    "sql": "SELECT ...",
    "columns": [{"key":"nazev_sloupce","label":"Titulek"}],
    "x_column": "sloupec_osa_x_pro_graf",
    "y_column": "sloupec_osa_y_pro_graf",
    "series_label": "Legenda_serie",
    "series_column": "sloupec_pro_vice_serii_pokud_je_v_jednom_grafu_vice_car (volitelne)"
  }

Instrukce:
- Pouzivej jen SELECT nad tabulkami/sloupci uvedenymi nize, nic jineho (zadne DML/DDL).
- Pridej LIMIT (napr. 200) pro prehlednost.
- Pokud chybi upresneni (produkt, obdobi, e-shop), zvol rozumne vychozi omezeni (napr. poslednich 12 mesicu) a ve vysvetleni napis, co by se hodilo zpresnit.
- Graf pouzij, jen kdyz dava smysl (casova rada, porovnani), jinak tabulku.
- Dodrz strukturu JSON, aby sel vystup strojove zpracovat.
- Pro vice linii v jednom grafu pouzij "series_column" (napr. kanal/eshop_source nebo produkt), y_column zustava hodnota.
- Pokud filtrujes agregace (SUM/AVG/COUNT), pouzij HAVING; agregacni funkce nepatri do WHERE (vyhnes se chybe "Invalid use of group function").
- Nezapouzdruj agregace do sebe (ne SUM uvnitr SUM) - priprav si subdotaz s agregaci a na vnejsi urovni pouzij jeden SUM/AVG.

Dostupne tabulky a sloupce:
{$schema}

Tipy a aliasy:
- Datum objednavky: polozky_eshop.duzp (datum/DUZP).
- Obrat/trzby: sum(polozky_eshop.cena_jedn_czk * polozky_eshop.mnozstvi), ceny jsou bez DPH, pouzivej ceny v CZK.
- Vyroba: polozky_pohyby s typ_pohybu = 'vyroba', suma mnozstvi podle sku a casu.
- Kanaly (eshop_source): velkoobchod=b2b.wormup.com, gogrig.com; maloobchod GRIG=grig.cz; maloobchod SK=grig.sk; maloobchod WormUP=wormup.com; stranky=grigsupply.cz.
- Aktualni skladova hodnota: pocitej stejne jako ve Vyrobe = snapshot posledni uzavrene inventury + pohyby po inventure - platne rezervace, pak vynasob skl_hodnota. Postup:
  - Posledni inventura: SELECT id, closed_at FROM inventury WHERE closed_at IS NOT NULL ORDER BY closed_at DESC LIMIT 1.
  - Snapshot: SELECT sku, stav FROM inventura_stavy WHERE inventura_id = (id z kroku 1).
  - Pohyby po inventure: SELECT sku, SUM(mnozstvi) AS qty FROM polozky_pohyby WHERE (closed_at IS NULL OR datum > closed_at) GROUP BY sku.
  - Rezervace: SELECT sku, SUM(mnozstvi) AS qty FROM rezervace WHERE platna_do >= CURDATE() GROUP BY sku.
  - stav_sku = COALESCE(snapshot,0) + COALESCE(pohyby,0) - COALESCE(rezervace,0).
  - hodnota = SUM(p.skl_hodnota * COALESCE(stav_sku,0)) pres aktivni produkty; pro rozpad podle typu pridej GROUP BY p.typ.
- Kontaktni udaje klienta jsou v tabulce kontakty (ic, dic, email, telefon, adresa). polozky_eshop nema kontakt_id; pro prodeje pouzij JOIN doklady_eshop ON (eshop_source, cislo_dokladu) a doklady_eshop.kontakt_id -> kontakty.id (filtruj podle kontakty.ic).
- Pokud uzivatel neupresni obdobi, pouzij poslednich 12 mesicu; ve vysvetleni uved, jake omezeni bylo pouzito a co upresnit.
- Skladove dostupne polozky nejsou primo v analyticke tabulce; lze je odvodit z pohybu (polozky_pohyby) nebo uvest, ze hodnota je aproximace.
PROMPT;
    }

    private function callOpenAi(string $apiKey, array $body): string
    {
        $url = getenv('OPENAI_BASE_URL') ?: ($_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1');
        $ch = curl_init(rtrim($url, '/') . '/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        $result = curl_exec($ch);
        if ($result === false) {
            throw new \RuntimeException('Chyba cURL: ' . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 400) {
            throw new \RuntimeException("HTTP {$status}: {$result}");
        }
        $json = json_decode($result, true);
        if (!is_array($json) || empty($json['choices'][0]['message']['content'])) {
            throw new \RuntimeException('NeplatnĂˇ odpovÄ›ÄŹ OpenAI');
        }
        return (string)$json['choices'][0]['message']['content'];
    }

    private function openAiStatus(): array
    {
        $key = $this->resolveOpenAiKey();
        // JednoduchĂ˝ health check, jen ovÄ›Ĺ™ujeme, Ĺľe klĂ­ÄŤ existuje
        if ($key === '') {
            return [
                'ready' => false,
                'message' => 'ChybĂ­ promÄ›nnĂˇ OPENAI_API_KEY â€“ poĹľĂˇdejte sprĂˇvce hostingu o doplnÄ›nĂ­.',
            ];
        }
        return [
            'ready' => true,
            'message' => '',
        ];
    }

    private function sanitizeSql(string $sql): string
    {
        // Odebereme koncovĂ© stĹ™ednĂ­ky a whitespace, internĂ­ stĹ™ednĂ­ky dĂˇl blokuje validateSqlIsSafe
        return rtrim($sql, " \r\n\t;");
    }

    private function resolveOpenAiKey(): string
    {
        $env = getenv('OPENAI_API_KEY');
        if ($env !== false && $env !== '') {
            return (string)$env;
        }
        if (!empty($_ENV['OPENAI_API_KEY'])) {
            return (string)$_ENV['OPENAI_API_KEY'];
        }
        $cfg = include __DIR__ . '/../../config/config.php';
        return (string)($cfg['openai']['api_key'] ?? '');
    }

    private function collectJson(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function jsonError(string $message): void
    {
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    private function currentUserId(): int
    {
        return (int)($_SESSION['user']['id'] ?? 0);
    }

    private function schemaProvider(): AnalyticsSchema
    {
        static $schema;
        if ($schema === null) {
            $schema = new AnalyticsSchema();
        }
        return $schema;
    }

    private function requireRole(array $allowed): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
        $role = $_SESSION['user']['role'] ?? 'user';
        if (!in_array($role, $allowed, true)) {
            http_response_code(403);
            $this->render('forbidden.php', [
                'title' => 'PĹ™Ă­stup odepĹ™en',
                'message' => 'NemĂˇte oprĂˇvnÄ›nĂ­ pro AnalĂ˝zu.',
            ]);
            exit;
        }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    public function revenueV2(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        $templates = $this->loadTemplatesV2();
        $favoritesV2 = $this->loadFavoritesV2();
        $this->render('analytics_revenue.php', [
            'title' => 'AnalĂ˝za',
            'templates' => $templates,
            'favoritesV2' => $favoritesV2,
        ]);
    }

    public function runTemplateV2(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        $payload = $this->collectJson();
        $templateId = (string)($payload['template_id'] ?? '');
        $params = (array)($payload['params'] ?? []);

        $templates = $this->loadTemplatesV2();
        if (!isset($templates[$templateId])) {
            echo json_encode(['ok' => false, 'error' => 'NeznĂˇmĂˇ Ĺˇablona.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $template = $templates[$templateId];

        [$validated, $errors] = $this->validateTemplateParams($template, $params);
        if (!empty($errors)) {
            echo json_encode(['ok' => false, 'error' => implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
            return;
        }
        $validated = $this->hydrateFlagsForTemplate($validated, $template);
        $sql = $this->expandArrayParams($template['sql'], $validated);

        try {
            $rows = $this->runTemplateQuery($sql, $validated);
            echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function favoriteListV2(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'favorites' => $this->loadFavoritesV2()], JSON_UNESCAPED_UNICODE);
    }

    public function saveFavoriteV2(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        $payload = $this->collectJson();
        $title = trim((string)($payload['title'] ?? ''));
        $templateId = (string)($payload['template_id'] ?? '');
        $params = (array)($payload['params'] ?? []);
        $isPublic = (bool)($payload['is_public'] ?? true);

        if ($title === '') {
            echo json_encode(['ok' => false, 'error' => 'NĂˇzev je povinnĂ˝.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $templates = $this->loadTemplatesV2();
        if (!isset($templates[$templateId])) {
            echo json_encode(['ok' => false, 'error' => 'NeznĂˇmĂˇ Ĺˇablona.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        [$validated, $errors] = $this->validateTemplateParams($templates[$templateId], $params);
        if (!empty($errors)) {
            echo json_encode(['ok' => false, 'error' => implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
            return;
        }
        $favoritePayload = [
            'type' => 'analytics_v2',
            'template_id' => $templateId,
            'params' => $validated,
        ];
        $prompt = json_encode($favoritePayload, JSON_UNESCAPED_UNICODE);
        $this->saveFavoriteRaw($title, $prompt, $isPublic);
        echo json_encode(['ok' => true, 'favorites' => $this->loadFavoritesV2()], JSON_UNESCAPED_UNICODE);
    }

    public function deleteFavoriteV2(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        $payload = $this->collectJson();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ChybĂ­ ID.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $userId = $this->currentUserId();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM ai_prompts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        echo json_encode(['ok' => true, 'favorites' => $this->loadFavoritesV2()], JSON_UNESCAPED_UNICODE);
    }

    public function searchContactsV2(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $like = '%' . $q . '%';
        $likeNorm = '%' . str_replace([' ', "\t"], '', $q) . '%';
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            SELECT id, firma, ic, dic, email, telefon
            FROM kontakty
            WHERE (ic LIKE :q OR REPLACE(ic, ' ', '') LIKE :qnorm OR firma LIKE :q OR email LIKE :q OR dic LIKE :q OR telefon LIKE :q)
            ORDER BY (firma IS NULL OR firma = '') ASC, firma, ic
            LIMIT 30
        ");
        $stmt->execute([':q' => $like, ':qnorm' => $likeNorm]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(function (array $r): array {
            $parts = [];
            if (!empty($r['firma'])) {
                $parts[] = $r['firma'];
            }
            if (!empty($r['ic'])) {
                $parts[] = 'IÄŚ ' . $r['ic'];
            }
            if (!empty($r['email'])) {
                $parts[] = $r['email'];
            }
            if (!empty($r['telefon'])) {
                $parts[] = $r['telefon'];
            }
            return [
                'id' => (int)$r['id'],
                'label' => trim(implode(' â€˘ ', $parts)),
            ];
        }, $rows ?: []);
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

    public function searchContactsByIdsV2(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        header('Content-Type: application/json; charset=utf-8');
        $raw = $_GET['ids'] ?? [];
        if (!is_array($raw)) {
            // allow comma-separated string
            $raw = is_string($raw) ? explode(',', $raw) : [];
        }
        if (empty($raw)) {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $ids = array_values(array_filter(array_map('intval', $raw), static fn($v) => $v > 0));
        if (empty($ids)) {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT id, firma, ic, email FROM kontakty WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(function (array $r): array {
            $parts = [];
            if (!empty($r['firma'])) {
                $parts[] = $r['firma'];
            }
            if (!empty($r['ic'])) {
                $parts[] = 'IÄŚ ' . $r['ic'];
            }
            if (!empty($r['email'])) {
                $parts[] = $r['email'];
            }
            return [
                'id' => (int)$r['id'],
                'label' => trim(implode(' â€˘ ', $parts)),
            ];
        }, $rows ?: []);
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function loadTemplatesV2(): array
    {
        $defaultStart = (new \DateTimeImmutable('-18 months'))->format('Y-m-d');
        $defaultEnd = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $eshops = [
            'vsechny', // explicit volba pro souÄŤet vĹˇech kanĂˇlĹŻ
            'b2b.wormup.com',
            'gogrig.com',
            'grig.cz',
            'grig.sk',
            'wormup.com',
            'grigsupply.cz',
        ];
        $brands = $this->loadOptions('produkty_znacky', 'id', 'nazev');
        $groups = $this->loadOptions('produkty_skupiny', 'id', 'nazev');
        $types = $this->loadProductTypes();

        return [
            'monthly_revenue_by_ic' => [
                'title' => 'MÄ›sĂ­ÄŤnĂ­ trĹľby',
                'description' => 'SouÄŤet trĹľeb bez DPH podle DUZP po mÄ›sĂ­cĂ­ch; filtry kontakt (IÄŚ/e-mail/firma) a kanĂˇl.',
                'sql' => "
SELECT DATE_FORMAT(pe.duzp, '%Y-%m') AS mesic,
       CASE
         WHEN :has_contacts = 1 THEN CAST(COALESCE(c.id, -1) AS CHAR)
         WHEN :has_eshops = 1 THEN de.eshop_source
         ELSE 'all'
       END AS serie_key,
  CASE
    WHEN :has_contacts = 1 THEN TRIM(CONCAT(COALESCE(c.firma, ''), ' ', COALESCE(c.ic, '')))
    WHEN :has_eshops = 1 THEN de.eshop_source
    ELSE 'Celkem'
  END AS serie_label,
  CASE
    WHEN :has_contacts = 1 THEN TRIM(CONCAT(COALESCE(c.firma, ''), ' ', COALESCE(c.ic, '')))
    ELSE NULL
  END AS kontakt,
  ROUND(SUM(pe.cena_jedn_czk * pe.mnozstvi), 0) AS trzby,
  ROUND(SUM(pe.mnozstvi), 0) AS qty
FROM polozky_eshop pe
JOIN doklady_eshop de ON de.eshop_source = pe.eshop_source AND de.cislo_dokladu = pe.cislo_dokladu
LEFT JOIN kontakty c ON c.id = de.kontakt_id
WHERE pe.duzp BETWEEN :start_date AND :end_date
  AND (:has_contacts = 0 OR de.kontakt_id IN (%contact_ids%))
  AND (:has_eshops = 0 OR de.eshop_source IN (%eshop_source%))
GROUP BY DATE_FORMAT(pe.duzp, '%Y-%m'), serie_key, serie_label
ORDER BY mesic, serie_label
",
                'params' => [
                    ['name' => 'start_date', 'label' => 'Od', 'type' => 'date', 'required' => true, 'default' => $defaultStart],
                    ['name' => 'end_date', 'label' => 'Do', 'type' => 'date', 'required' => true, 'default' => $defaultEnd],
                    ['name' => 'contact_ids', 'label' => 'Kontakt', 'type' => 'contact_multi', 'required' => false, 'default' => []],
                    ['name' => 'eshop_source', 'label' => 'E-shop', 'type' => 'enum_multi', 'required' => false, 'default' => [], 'values' => $eshops],
                ],
                'suggested_render' => 'table',
            ],
            'stock_value_by_month' => [
                'title' => 'Sklady',
                'description' => 'AktuĂˇlnĂ­ skladovĂˇ hodnota = DostupnĂ© * skl_hodnota; filtr znaÄŤky, skupiny a typu.',
                'sql' => "
SELECT
  m.month_end AS stav_ke_dni,
  'Vse' AS serie_label,
  'all' AS serie_key,
  CASE WHEN :aggregate_all = 1 THEN 'vse' ELSE COALESCE(zn.nazev, 'bez znacky') END AS znacka,
  CASE WHEN :aggregate_all = 1 THEN 'vse' ELSE COALESCE(sg.nazev, 'bez skupiny') END AS skupina,
  CASE WHEN :aggregate_all = 1 THEN 'vse' ELSE typ END AS typ,
  ROUND(SUM(p.skl_hodnota * (
    COALESCE(snap.stav, 0)
    + COALESCE((
        SELECT SUM(pm.mnozstvi)
        FROM polozky_pohyby pm
        WHERE pm.sku = p.sku
          AND (inv.last_closed IS NULL OR pm.datum > inv.last_closed)
          AND pm.datum <= m.month_end
    ), 0)
    - COALESCE((
        SELECT SUM(r.mnozstvi)
        FROM rezervace r
        WHERE r.sku = p.sku
          AND r.platna_do >= m.month_end
    ), 0)
  )), 0) AS hodnota_czk,
  SUM((
    COALESCE(snap.stav, 0)
    + COALESCE((
        SELECT SUM(pm.mnozstvi)
        FROM polozky_pohyby pm
        WHERE pm.sku = p.sku
          AND (inv.last_closed IS NULL OR pm.datum > inv.last_closed)
          AND pm.datum <= m.month_end
    ), 0)
    - COALESCE((
        SELECT SUM(r.mnozstvi)
        FROM rezervace r
        WHERE r.sku = p.sku
          AND r.platna_do >= m.month_end
    ), 0)
  )) AS stav_kusy
FROM (
  SELECT
    DATE_ADD(DATE_FORMAT(:start_date, '%Y-%m-01'), INTERVAL seq MONTH) AS month_start,
    LEAST(LAST_DAY(DATE_ADD(DATE_FORMAT(:start_date, '%Y-%m-01'), INTERVAL seq MONTH)), :end_date) AS month_end
  FROM (
    SELECT a.n + b.n * 10 AS seq
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
  ) seqs
  WHERE DATE_ADD(DATE_FORMAT(:start_date, '%Y-%m-01'), INTERVAL seq MONTH) <= :end_date
) m
JOIN produkty p ON p.aktivni = 1
LEFT JOIN produkty_znacky zn ON zn.id = p.znacka_id
LEFT JOIN produkty_skupiny sg ON sg.id = p.skupina_id
LEFT JOIN (
  SELECT s.sku, s.stav
  FROM inventura_stavy s
  WHERE s.inventura_id = (
    SELECT id FROM inventury WHERE closed_at IS NOT NULL ORDER BY closed_at DESC LIMIT 1
  )
) snap ON snap.sku = p.sku
CROSS JOIN (
  SELECT
    CASE 
      WHEN (SELECT closed_at FROM inventury WHERE closed_at IS NOT NULL ORDER BY closed_at DESC LIMIT 1) IS NULL THEN NULL
      ELSE (SELECT closed_at FROM inventury WHERE closed_at IS NOT NULL ORDER BY closed_at DESC LIMIT 1)
    END AS last_closed
) inv
CROSS JOIN (
  SELECT 1 AS one
) constants
CROSS JOIN (
  SELECT 1 AS dummy
) t
CROSS JOIN (
  SELECT 1 AS dummy2
) t2
WHERE (:has_znacka = 0 OR p.znacka_id IN (%znacka_id%))
  AND (:has_skupina = 0 OR p.skupina_id IN (%skupina_id%))
  AND (:has_typ = 0 OR p.typ IN (%typ%))
  AND (:has_sku = 0 OR p.sku IN (%sku%))
GROUP BY
  m.month_end,
  'all',
  'Vse',
  CASE WHEN :aggregate_all = 1 THEN 'vse' ELSE COALESCE(zn.nazev, 'bez znacky') END,
  CASE WHEN :aggregate_all = 1 THEN 'vse' ELSE COALESCE(sg.nazev, 'bez skupiny') END,
  CASE WHEN :aggregate_all = 1 THEN 'vse' ELSE typ END
HAVING SUM((
    COALESCE(snap.stav, 0)
    + COALESCE((
        SELECT SUM(pm.mnozstvi)
        FROM polozky_pohyby pm
        WHERE pm.sku = p.sku
          AND (inv.last_closed IS NULL OR pm.datum > inv.last_closed)
          AND pm.datum <= m.month_end
    ), 0)
    - COALESCE((
        SELECT SUM(r.mnozstvi)
        FROM rezervace r
        WHERE r.sku = p.sku
          AND r.platna_do >= m.month_end
    ), 0)
  )) <> 0
ORDER BY m.month_end, serie_label
",
'params' => [
                    ['name' => 'start_date', 'label' => 'Od', 'type' => 'date', 'required' => true, 'default' => $defaultStart],
                    ['name' => 'end_date', 'label' => 'Do', 'type' => 'date', 'required' => true, 'default' => $defaultEnd],
                    ['name' => 'znacka_id', 'label' => 'ZnaÄŤka', 'type' => 'enum_multi', 'required' => false, 'default' => [], 'values' => $brands],
                    ['name' => 'skupina_id', 'label' => 'Skupina', 'type' => 'enum_multi', 'required' => false, 'default' => [], 'values' => $groups],
                    ['name' => 'typ', 'label' => 'Typ', 'type' => 'enum_multi', 'required' => false, 'default' => [], 'values' => $types],
                    ['name' => 'sku', 'label' => 'SKU', 'type' => 'string_multi', 'required' => false, 'default' => []],
                ],
                'suggested_render' => 'table',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @param array<string,mixed> $input
     * @return array{0:array<string,mixed>,1:array<int,string>}
     */
    private function validateTemplateParams(array $template, array $input): array
    {
        $validated = [];
        $errors = [];
        foreach ($template['params'] as $param) {
            $name = (string)$param['name'];
            $type = (string)$param['type'];
            $required = (bool)($param['required'] ?? false);
            $default = $param['default'] ?? null;
            $raw = $input[$name] ?? $default;
            if ($raw === null && $required) {
                $errors[] = "ChybĂ­ parametr {$name}.";
                continue;
            }
            switch ($type) {
                case 'date':
                    $val = trim((string)$raw);
                    if ($val === '') {
                        $errors[] = "ChybĂ­ datum {$name}.";
                        break;
                    }
                    $validated[$name] = $val;
                    break;
                case 'string':
                    $validated[$name] = trim((string)$raw);
                    break;
                case 'contact_multi':
                    $vals = is_array($raw) ? $raw : [];
                    $validated[$name] = array_values(array_filter(array_map('intval', $vals), static fn($v) => $v > 0));
                    break;
                case 'enum':
                    $val = trim((string)$raw);
                    $allowed = (array)($param['values'] ?? []);
                    if ($val !== '' && !in_array($val, $allowed, true)) {
                        $errors[] = "NeplatnĂˇ hodnota pro {$name}.";
                    }
                    $validated[$name] = $val;
                    break;
                case 'int':
                    $validated[$name] = (int)$raw;
                    if ($validated[$name] < 0) {
                        $validated[$name] = 0;
                    }
                    break;
                case 'enum_multi':
                    $vals = is_array($raw) ? $raw : [];
                    $allowed = (array)($param['values'] ?? []);
                    $allowedValues = array_map(function ($item) {
                        return is_array($item) ? (string)($item['value'] ?? '') : (string)$item;
                    }, $allowed);
                    $filtered = [];
                    foreach ($vals as $v) {
                        $v = trim((string)$v);
                        if ($v === '') {
                            continue;
                        }
                        if (!in_array($v, $allowedValues, true)) {
                            $errors[] = "NeplatnĂˇ hodnota pro {$name}.";
                            continue;
                        }
                        $filtered[] = $v;
                    }
                    $validated[$name] = array_values($filtered);
                    break;
                case 'string_multi':
                    $vals = is_array($raw) ? $raw : explode(',', (string)$raw);
                    $vals = array_values(array_filter(array_map('trim', $vals), static fn($v) => $v !== ''));
                    $validated[$name] = $vals;
                    break;
                default:
                    $validated[$name] = $raw;
            }
        }
        return [$validated, $errors];
    }

    /**
     * @param array<string,mixed> $params
     */
    private function runTemplateQuery(string $sql, array $params): array
    {
        // Filter only parameters actually present in the SQL to avoid HY093 mismatch
        if (!empty($params)) {
            $used = [];
            foreach ($params as $k => $v) {
                if (is_int($k)) {
                    $used[$k] = $v;
                    continue;
                }
                $needle = ':' . $k;
                if (strpos($sql, $needle) !== false) {
                    $used[$k] = $v;
                }
            }
            $params = $used;
        }
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * Pokud jsou parametry pole, nahradĂ­ placeholdery %name% za jednotlivĂ© bindy (params jsou by-ref).
     * @param array<string,mixed> $params
     */
    private function expandArrayParams(string $sql, array &$params): string
    {
        foreach ($params as $name => $val) {
            if (!is_array($val)) {
                continue;
            }
            $placeholder = '%' . $name . '%';
            if (strpos($sql, $placeholder) === false) {
                unset($params[$name]);
                continue;
            }
            if (empty($val)) {
                $sql = str_replace($placeholder, 'NULL', $sql);
                unset($params[$name]);
                continue;
            }
            $holders = [];
            foreach (array_values($val) as $idx => $item) {
                $ph = ':' . $name . '_' . $idx;
                $holders[] = $ph;
                $params[$name . '_' . $idx] = $item;
            }
            unset($params[$name]);
            $sql = str_replace($placeholder, implode(',', $holders), $sql);
        }
        return $sql;
    }

    /**
     * DoplnĂ­ booleovskĂ© pĹ™Ă­znaky podle pole parametrĹŻ.
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function hydrateFlagsForTemplate(array $params, array $template = []): array
    {
        // "vsechny" znamenĂˇ neomezovat kanĂˇl -> chovej se jako prĂˇzdnĂ˝ vĂ˝bÄ›r
        if (isset($params['eshop_source']) && is_array($params['eshop_source'])) {
            $params['eshop_source'] = array_values(array_filter($params['eshop_source'], static function ($v) {
                $v = strtolower((string)$v);
                return $v !== 'vsechny' && $v !== 'vĹˇechny';
            }));
        }
        foreach (['znacka_id', 'skupina_id', 'typ'] as $key) {
            if (isset($params[$key]) && is_array($params[$key])) {
                $params[$key] = array_values(array_filter($params[$key], static function ($v) {
                    $v = strtolower((string)$v);
                    return $v !== 'vse' && $v !== 'vĹˇe';
                }));
            }
        }
        if (isset($params['sku']) && is_array($params['sku'])) {
            $params['sku'] = array_values(array_filter($params['sku'], static function ($v) {
                return trim((string)$v) !== '';
            }));
        }
        $params['has_contacts'] = !empty($params['contact_ids']) ? 1 : 0;
        $params['has_eshops'] = !empty($params['eshop_source']) ? 1 : 0;
        $params['has_znacka'] = !empty($params['znacka_id']) ? 1 : 0;
        $params['has_skupina'] = !empty($params['skupina_id']) ? 1 : 0;
        $params['has_typ'] = !empty($params['typ']) ? 1 : 0;
        $params['has_sku'] = !empty($params['sku']) ? 1 : 0;
        // pokud nejsou zvoleny ĹľĂˇdnĂ© filtry, agregujeme vĹˇe do jednoho Ĺ™Ăˇdku
        $params['aggregate_all'] = ($params['has_znacka'] === 0 && $params['has_skupina'] === 0 && $params['has_typ'] === 0 && $params['has_sku'] === 0) ? 1 : 0;
        // doplĹ labely pro stock template
        if (!empty($template['params'])) {
            $params['znacka_label'] = $this->selectionLabel($template['params'], 'znacka_id', $params['znacka_id'] ?? []);
            $params['skupina_label'] = $this->selectionLabel($template['params'], 'skupina_id', $params['skupina_id'] ?? []);
            $params['typ_label'] = $this->selectionLabel($template['params'], 'typ', $params['typ'] ?? []);
        }
        return $params;
    }

    /**
     * @param array<int,array<string,mixed>> $paramsDef
     * @param array<int,string>|string $selected
     */
    private function selectionLabel(array $paramsDef, string $name, $selected): string
    {
        $selected = is_array($selected) ? $selected : [$selected];
        $selected = array_filter(array_map('strval', $selected));
        if (empty($selected)) {
            return 'vĹˇe';
        }
        $map = [];
        foreach ($paramsDef as $def) {
            if (($def['name'] ?? '') !== $name) {
                continue;
            }
            foreach ((array)($def['values'] ?? []) as $val) {
                if (is_array($val)) {
                    $map[(string)($val['value'] ?? '')] = (string)($val['label'] ?? $val['value'] ?? '');
                } else {
                    $map[(string)$val] = (string)$val;
                }
            }
        }
        $labels = [];
        foreach ($selected as $val) {
            if ($val === 'vse') {
                return 'vĹˇe';
            }
            $labels[] = $map[$val] ?? $val;
        }
        return implode(', ', $labels);
    }

    /**
     * @return array{mine:array<int,array>,shared:array<int,array>}
     */
    private function loadFavoritesV2(): array
    {
        $userId = $this->currentUserId();
        $pdo = DB::pdo();
        $mineStmt = $pdo->prepare("SELECT id, title, prompt, created_at, is_public FROM ai_prompts WHERE user_id = ? AND prompt LIKE '%\"type\":\"analytics_v2\"%' ORDER BY created_at DESC LIMIT 50");
        $mineStmt->execute([$userId]);
        $sharedStmt = $pdo->prepare("SELECT id, title, prompt, created_at, is_public FROM ai_prompts WHERE is_public = 1 AND user_id != ? AND prompt LIKE '%\"type\":\"analytics_v2\"%' ORDER BY created_at DESC LIMIT 50");
        $sharedStmt->execute([$userId]);
        return [
            'mine' => $this->mapFavoritesV2($mineStmt->fetchAll(PDO::FETCH_ASSOC)),
            'shared' => $this->mapFavoritesV2($sharedStmt->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function mapFavoritesV2(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $payload = json_decode((string)$r['prompt'], true);
            if (!is_array($payload) || ($payload['type'] ?? '') !== 'analytics_v2') {
                continue;
            }
            $out[] = [
                'id' => (int)$r['id'],
                'title' => (string)$r['title'],
                'template_id' => (string)($payload['template_id'] ?? ''),
                'params' => (array)($payload['params'] ?? []),
                'is_public' => (bool)($r['is_public'] ?? false),
            ];
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    private function loadProductTypes(): array
    {
        $stmt = DB::pdo()->query('SELECT DISTINCT typ FROM produkty WHERE typ IS NOT NULL AND typ <> "" ORDER BY typ');
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        array_unshift($types, 'vse'); // moĹľnost "vĹˇe"
        return $types;
    }

    /**
     * @return array<int,string|array<string,string>>
     */
    private function loadOptions(string $table, string $idCol, string $labelCol): array
    {
        $stmt = DB::pdo()->query("SELECT {$idCol} AS id, {$labelCol} AS nazev FROM {$table} ORDER BY nazev");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        $out[] = ['value' => 'vse', 'label' => 'VĹˇe'];
        foreach ($rows as $row) {
            $out[] = [
                'value' => (string)$row['id'],
                'label' => (string)$row['nazev'],
            ];
        }
        return $out;
    }

    private function saveFavoriteRaw(string $title, string $prompt, bool $isPublic = true): void
    {
        $userId = $this->currentUserId();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO ai_prompts (user_id, title, prompt, is_public) VALUES (?,?,?,?)');
        $stmt->execute([$userId, $title, $prompt, $isPublic ? 1 : 0]);
    }
}


