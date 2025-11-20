<?php
namespace App\Controller;

use App\Support\DB;
use PDO;

final class AnalyticsController
{
    public function revenue(): void
    {
        $this->requireRole(['admin','superadmin']);
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
        $this->requireRole(['admin','superadmin']);
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
        $schema = $this->schemaDigest();
        $systemPrompt = $this->buildSystemPrompt($schema);

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
                $rows = $this->runSelect($sql);
            } catch (\Throwable $e) {
                $renderedOutputs[] = [
                    'type' => 'error',
                    'title' => $output['title'] ?? 'Dotaz nelze spustit',
                    'message' => $e->getMessage(),
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
        $this->requireRole(['admin','superadmin']);
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

    private function runSelect(string $sql): array
    {
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
        if (!preg_match('/^select/i', $trimmed)) {
            return false;
        }
        if (preg_match('/;/', $trimmed)) {
            return false;
        }
        $blocked = ['insert ', 'update ', 'delete ', 'drop ', 'alter ', 'truncate ', 'create ', 'into '];
        $lower = strtolower($trimmed);
        foreach ($blocked as $word) {
            if (str_contains($lower, $word) && $word !== 'select ') {
                return false;
            }
        }
        return true;
    }

    private function buildSystemPrompt(string $schema): string
    {
        return <<<PROMPT
Jsi datový analytik ve firmě WormUP. Máš přístup pouze k databázi MySQL uvedené níže. Odpověď drž v jazyce, ve kterém přišel uživatelský dotaz.

Výstup vracej jako platný JSON objekt (používáme response_format json_object) s klíči:
- "language": kód jazyka odpovědi (např. "cs" nebo "en"),
- "explanation": krátké shrnutí zjištění v přirozeném jazyce,
- "outputs": pole objektů popisujících tabulky nebo grafy, každý ve tvaru:
  {
    "type": "table" | "line_chart",
    "title": "Titulek výstupu",
    "sql": "SELECT ...",
    "columns": [{"key":"nazev_sloupce","label":"Titulek"}],
    "x_column": "sloupec_osa_x_pro_graf",
    "y_column": "sloupec_osa_y_pro_graf",
    "series_label": "Legenda_série"
  }

Instrukce:
- Připrav jen SQL dotazy typu SELECT, nic jiného (žádné DML/DDL).
- U každé tabulky/grafu přidej LIMIT tak, aby výstup byl přehledný (např. LIMIT 200).
- Pokud uživatel neurčí formu, použij tabulku; graf doporuč jen když dává smysl.

Dostupná schémata:
{$schema}
PROMPT;
    }

    private function schemaDigest(): string
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query("SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE() ORDER BY table_name, ordinal_position");
        $map = [];
        foreach ($stmt as $row) {
            $table = (string)$row['table_name'];
            $map[$table][] = $row['column_name'] . ' (' . $row['data_type'] . ')';
        }
        $chunks = [];
        foreach ($map as $table => $columns) {
            $chunks[] = $table . ': ' . implode(', ', $columns);
        }
        return implode("\n", $chunks);
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
            'message' => 'OpenAI klíč je načten a rozhraní je připraveno.',
        ];
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
        // Fallback na config/openai, pokud není proměnná prostředí
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




