<?php

namespace App\Controller;

use App\Support\DB;
use App\Service\AnalyticsSchema;
use PDO;

final class AnalyticsController
{
    public function revenue(): void
    {
        $this->requireRole(['admin', 'superadmin']);
        $favorites = $this->loadFavorites();
        $status = $this->openAiStatus();
        $this->render('analytics_revenue.php', [
            'title' => 'Analýza (AI)',
            'openAiStatus' => $status['message'],
            'openAiReady' => $status['ready'],
            'myFavorites' => $favorites['mine'],
            'sharedFavorites' => $favorites['shared'],
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
            $this->jsonError('Zadejte prosím prompt.');
            return;
        }

        $apiKey = $this->resolveOpenAiKey();
        if ($apiKey === '') {
            $this->jsonError('Chybí serverová proměnná OPENAI_API_KEY.');
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
            $this->jsonError('OpenAI API není dostupné: ' . $e->getMessage());
            return;
        }

        $plan = $this->parseAiPlan($aiResponse);
        if ($plan === null) {
            $this->jsonError('AI vrátila neočekávaný výstup.');
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
                        $message = "Neznámý sloupec '{$missingCol}'. Zkuste: " . implode(', ', $suggest);
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
            echo json_encode(['ok' => false, 'error' => 'Název i text promptu jsou povinné.'], JSON_UNESCAPED_UNICODE);
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
            echo json_encode(['ok' => false, 'error' => 'Neplatné ID.'], JSON_UNESCAPED_UNICODE);
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
        $stmt = DB::pdo()->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
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
            throw new \RuntimeException('Povoleny jsou jen dotazy SELECT bez středníků.');
        }
        $blocked = [' insert ', ' update ', ' delete ', ' drop ', ' alter ', ' truncate ', ' create ', ' into ', ' outfile ', ' infile ', ' grant ', ' revoke '];
        foreach ($blocked as $word) {
            if (str_contains($clean, $word)) {
                throw new \RuntimeException('Dotaz musí být pouze SELECT nad reportovacími daty.');
            }
        }
    }

    private function buildSystemPrompt(string $schema): string
    {
        return <<<PROMPT
Jsi datový analytik ve firmě WormUP. Máš přístup pouze k databázi MySQL uvedené níže. Odpovídej ve stejném jazyce jako uživatel.

Výstup vracej jako platný JSON objekt (response_format json_object) s klíči:
- "language": kód jazyka odpovědi (např. "cs" nebo "en"),
- "explanation": krátké srozumitelné shrnutí výsledků,
- "outputs": pole objektů popisujících tabulky nebo grafy, každý ve tvaru:
  {
    "type": "table" | "line_chart",
    "title": "Titulek",
    "sql": "SELECT ...",
    "columns": [{"key":"nazev_sloupce","label":"Titulek"}],
    "x_column": "sloupec_osa_x_pro_graf",
    "y_column": "sloupec_osa_y_pro_graf",
    "series_label": "Legenda_série"
  }

Instrukce:
- Používej jen SELECT nad tabulkami/sloupci uvedenými níže, nic jiného (žádné DML/DDL).
- Přidej LIMIT (např. 200) pro přehlednost.
- Pokud chybí upřesnění (produkt, období, e-shop), zvol rozumné výchozí omezení (např. posledních 12 měsíců) a ve vysvětlení napiš, co by se hodilo zpřesnit.
- Graf použij, jen když dává smysl (časové řady, porovnání), jinak tabulku.
- Dodrž strukturu JSON, aby šel výstup strojově zpracovat.

Dostupné tabulky a sloupce:
{$schema}

Tipy a aliasy:
- Datum objednávky: polozky_eshop.duzp (datum/DUZP).
- Obrat/tržby: sum(polozky_eshop.cena_jedn_czk * polozky_eshop.mnozstvi), ceny jsou bez DPH, použij ceny v CZK.
- Výroba: polozky_pohyby s typ_pohybu = 'vyroba', suma mnozstvi podle sku a času.
- Kanály (eshop_source): velkoobchod=b2b.wormup.com, gogrig.com; maloobchod GRIG=grig.cz; maloobchod SK=grig.sk; maloobchod WormUP=wormup.com; stánky=grigsupply.cz.
- Pokud uživatel neupřesní období, použij posledních 12 měsíců; ve vysvětlení uveď, jaké omezení bylo použito a co upřesnit.
- Skladové dostupné položky nejsou přímo v analytické tabulce; lze je odvodit z pohybů (polozky_pohyby) nebo uvést, že hodnota je aproximace.
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
            throw new \RuntimeException('Neplatná odpověď OpenAI');
        }
        return (string)$json['choices'][0]['message']['content'];
    }

    private function openAiStatus(): array
    {
        $key = $this->resolveOpenAiKey();
        if ($key === '') {
            return [
                'ready' => false,
                'message' => 'Chybí proměnná OPENAI_API_KEY – požádejte správce hostingu o doplnění.',
            ];
        }
        return [
            'ready' => true,
            'message' => '',
        ];
    }

    private function sanitizeSql(string $sql): string
    {
        // Odebereme koncové středníky a whitespace, interní středníky dál blokuje validateSqlIsSafe
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
                'title' => 'Přístup odepřen',
                'message' => 'Nemáte oprávnění pro Analýzu.',
            ]);
            exit;
        }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }
}
