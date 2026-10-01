<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\MovementDocService;
use App\Service\StockService;
use App\Support\Auth;
use App\Support\DB;

/**
 * Záložka Pohyby — skladové doklady (výroba / korekce).
 * Logika je v MovementDocService, tady jen routing, oprávnění a JSON.
 */
final class MovementsController
{
    public function index(): void
    {
        $this->requireAuth();
        $filters = [
            'od' => $this->dateParam('od'),
            'do' => $this->dateParam('do'),
            'typ' => (string)($_GET['typ'] ?? ''),
            'q' => trim($this->toUtf8((string)($_GET['q'] ?? ''))),
        ];
        $docs = MovementDocService::listDocs($filters);
        $user = $this->currentUser();
        foreach ($docs as &$d) {
            $d['lock'] = MovementDocService::lockReason($d, $user);
        }
        unset($d);
        $this->render('movements.php', [
            'title' => 'Pohyby – skladové doklady',
            'mode' => 'list',
            'docs' => $docs,
            'filters' => $filters,
            'canCreate' => Auth::isAdmin(),
            'message' => $this->flash('movements_message'),
            'error' => $this->flash('movements_error'),
        ]);
    }

    public function create(): void
    {
        Auth::requireAdmin('Doklady může zakládat jen admin.');
        $typ = (string)($_POST['typ'] ?? 'vyroba');
        try {
            $id = MovementDocService::createDoc($this->currentUser(), $typ);
        } catch (\Throwable $e) {
            $_SESSION['movements_error'] = 'Založení dokladu selhalo: ' . $e->getMessage();
            $this->redirect('/movements');
            return;
        }
        $this->redirect('/movements/doc?id=' . $id);
    }

    public function doc(): void
    {
        $this->requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $doc = $id > 0 ? MovementDocService::loadDoc($id) : null;
        if (!$doc) {
            $_SESSION['movements_error'] = 'Doklad nenalezen.';
            $this->redirect('/movements');
            return;
        }
        $user = $this->currentUser();
        $lock = MovementDocService::lockReason($doc, $user);

        $this->render('movements.php', [
            'title' => 'Doklad ' . $doc['cislo'],
            'mode' => 'doc',
            'doc' => $doc,
            'lock' => $lock,
            'canEditNote' => MovementDocService::canEditNote($doc, $user),
            'canRecalc' => Auth::isAdmin() && MovementDocService::isInventoryDoc($doc) && !empty($doc['inventura_id']),
            'lines' => MovementDocService::loadLines($id),
            'log' => MovementDocService::loadLog($id),
            'brands' => DB::pdo()->query('SELECT id,nazev FROM produkty_znacky ORDER BY nazev')->fetchAll(),
            'groups' => DB::pdo()->query('SELECT id,nazev FROM produkty_skupiny ORDER BY nazev')->fetchAll(),
            'types' => $this->productTypes(),
            'message' => $this->flash('movements_message'),
            'error' => $this->flash('movements_error'),
        ]);
    }

    public function delete(): void
    {
        Auth::requireAdmin('Doklady může mazat jen admin.');
        $id = (int)($_POST['id'] ?? 0);
        $doc = $id > 0 ? MovementDocService::loadDoc($id) : null;
        if (!$doc) {
            $_SESSION['movements_error'] = 'Doklad nenalezen.';
            $this->redirect('/movements');
            return;
        }
        if (MovementDocService::isInventoryDoc($doc)) {
            $_SESSION['movements_error'] = 'Inventární doklad nelze smazat samostatně – maže se spolu s inventurou.';
            $this->redirect('/movements/doc?id=' . $id);
            return;
        }
        $lock = MovementDocService::lockReason($doc, $this->currentUser());
        if ($lock !== null) {
            $_SESSION['movements_error'] = $lock;
            $this->redirect('/movements/doc?id=' . $id);
            return;
        }
        if (!MovementDocService::deleteDoc($doc)) {
            $_SESSION['movements_error'] = 'Doklad obsahuje položky, nejdřív je smažte.';
            $this->redirect('/movements/doc?id=' . $id);
            return;
        }
        $_SESSION['movements_message'] = 'Doklad ' . $doc['cislo'] . ' byl smazán.';
        $this->redirect('/movements');
    }

    /**
     * „Přepočítat rozdíly" – jen u inventárního dokladu, jen na povel admina.
     * Dorovná rozdíly podle aktuálních pohybů (pozdní import, nezapsané položky).
     */
    public function recalc(): void
    {
        Auth::requireAdmin('Přepočet rozdílů může spustit jen admin.');
        $id = (int)($_POST['id'] ?? 0);
        $doc = $id > 0 ? MovementDocService::loadDoc($id) : null;
        if (!$doc || !MovementDocService::isInventoryDoc($doc) || empty($doc['inventura_id'])) {
            $_SESSION['movements_error'] = 'Přepočet rozdílů je možný jen u inventárního dokladu.';
            $this->redirect($doc ? '/movements/doc?id=' . $id : '/movements');
            return;
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $r = MovementDocService::recalcInventoryDoc($pdo, (int)$doc['inventura_id'], $this->currentUser());
            $pdo->commit();
            $total = $r['pridano'] + $r['upraveno'] + $r['smazano'];
            $_SESSION['movements_message'] = $total === 0
                ? 'Přepočet proběhl, rozdíly odpovídají aktuálním pohybům – nic se nezměnilo.'
                : sprintf('Přepočet proběhl: přidáno %d, upraveno %d, odstraněno %d opravných řádků.', $r['pridano'], $r['upraveno'], $r['smazano']);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['movements_error'] = 'Přepočet selhal: ' . $e->getMessage();
        }
        $this->redirect('/movements/doc?id=' . $id);
    }

    // ------------------------------------------------------------ JSON API

    public function header(): void
    {
        $this->jsonAction(function (array $doc, array $user, array $in): array {
            MovementDocService::updateHeader($doc, $user, (string)($in['typ'] ?? $doc['typ']), (string)($in['poznamka'] ?? ''));
            return [];
        }, true);
    }

    public function lineAdd(): void
    {
        $this->jsonAction(function (array $doc, array $user, array $in): array {
            $sku = trim($this->toUtf8((string)($in['sku'] ?? '')));
            $qty = (float)($in['mnozstvi'] ?? 1);
            if ($sku === '') {
                throw new \RuntimeException('Chybí SKU.');
            }
            $rezim = (string)($in['rezim'] ?? $doc['typ']);
            $lineId = MovementDocService::addLine($doc, $user, $sku, $qty, $rezim);
            return ['line_id' => $lineId];
        });
    }

    public function lineUpdate(): void
    {
        $this->jsonAction(function (array $doc, array $user, array $in): array {
            $lineId = (int)($in['line_id'] ?? 0);
            if (!isset($in['mnozstvi']) || !is_numeric($in['mnozstvi'])) {
                throw new \RuntimeException('Zadejte číselné množství.');
            }
            MovementDocService::updateLine($doc, $user, $lineId, (float)$in['mnozstvi'], (string)($in['rezim'] ?? 'vyroba'));
            return [];
        });
    }

    public function lineDelete(): void
    {
        $this->jsonAction(function (array $doc, array $user, array $in): array {
            MovementDocService::deleteLine($doc, $user, (int)($in['line_id'] ?? 0));
            return [];
        });
    }

    /**
     * Společný obal JSON akcí: načte doklad, ověří zámek, provede callback
     * a vrátí aktuální řádky + log, aby si stránka mohla překreslit tabulku.
     */
    private function jsonAction(callable $fn, bool $noteOnly = false): void
    {
        Auth::requireAdmin('Doklady může měnit jen admin. Role čtenář může data pouze prohlížet.');
        header('Content-Type: application/json; charset=utf-8');
        $in = $this->collectJson();
        $id = (int)($in['doklad_id'] ?? 0);
        $doc = $id > 0 ? MovementDocService::loadDoc($id) : null;
        if (!$doc) {
            echo json_encode(['ok' => false, 'error' => 'Doklad nenalezen.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $user = $this->currentUser();
        if (MovementDocService::isInventoryDoc($doc)) {
            // Inventární doklad: jen poznámka, jen admin – položky vznikají uzavřením inventury
            if (!$noteOnly || !MovementDocService::canEditNote($doc, $user)) {
                echo json_encode(['ok' => false, 'error' => 'Inventární doklad nelze měnit' . ($noteOnly ? ' (poznámku smí upravit jen admin).' : '.'), 'locked' => !$noteOnly], JSON_UNESCAPED_UNICODE);
                return;
            }
        } else {
            $lock = MovementDocService::lockReason($doc, $user);
            if ($lock !== null) {
                echo json_encode(['ok' => false, 'error' => $lock, 'locked' => true], JSON_UNESCAPED_UNICODE);
                return;
            }
        }
        try {
            $extra = $fn($doc, $user, $in);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }
        $fresh = MovementDocService::loadDoc($id) ?? $doc;
        echo json_encode([
            'ok' => true,
            'doc' => ['id' => (int)$fresh['id'], 'typ' => $fresh['typ'], 'poznamka' => $fresh['poznamka'], 'radku' => (int)$fresh['radku']],
            'lines' => MovementDocService::loadLines($id),
            'log' => MovementDocService::loadLog($id),
        ] + $extra, JSON_UNESCAPED_UNICODE);
    }

    // ------------------------------------------------------- vyhledávání

    /**
     * Živé vyhledávání produktů pro vložení do dokladu (max 10 výsledků + příznak, že je jich víc).
     * S ?all=1 („Vypsat vše nalezené") vrací všechny shody, s pojistkou SEARCH_LIMIT_ALL.
     */
    public function search(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');
        $f = $this->productFilters();
        if ($f['search'] === '' && $f['brand'] === 0 && $f['group'] === 0 && $f['type'] === '') {
            echo json_encode(['ok' => true, 'items' => [], 'more' => false], JSON_UNESCAPED_UNICODE);
            return;
        }
        $limit = ($_GET['all'] ?? '') === '1' ? self::SEARCH_LIMIT_ALL : self::SEARCH_LIMIT;
        $rows = $this->searchProducts($f, $limit + 1);
        $more = count($rows) > $limit;
        echo json_encode(['ok' => true, 'items' => array_slice($rows, 0, $limit), 'more' => $more, 'limit' => $limit], JSON_UNESCAPED_UNICODE);
    }

    private const SEARCH_LIMIT = 10;
    private const SEARCH_LIMIT_ALL = 1000;

    /** @return array{brand:int,group:int,type:string,search:string} */
    private function productFilters(): array
    {
        $typeRaw = $this->toUtf8((string)($_GET['typ'] ?? ''));
        return [
            'brand' => max(0, (int)($_GET['znacka_id'] ?? 0)),
            'group' => max(0, (int)($_GET['skupina_id'] ?? 0)),
            'type' => in_array($typeRaw, $this->productTypes(), true) ? $typeRaw : '',
            'search' => trim($this->toUtf8((string)($_GET['q'] ?? ''))),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function searchProducts(array $f, int $limit = 300): array
    {
        $conditions = ['1=1'];
        $params = [];
        if ($f['type'] !== '') {
            $conditions[] = 'p.typ = ?';
            $params[] = $f['type'];
        }
        if ($f['brand'] > 0) {
            $conditions[] = 'COALESCE(p.znacka_id,0) = ?';
            $params[] = $f['brand'];
        }
        if ($f['group'] > 0) {
            $conditions[] = 'COALESCE(p.skupina_id,0) = ?';
            $params[] = $f['group'];
        }
        $terms = array_values(array_filter(preg_split('/\s+/u', $f['search']) ?: [], static fn($t) => $t !== ''));
        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $conditions[] = '(p.sku LIKE ? OR p.nazev LIKE ? OR p.alt_sku LIKE ? OR p.ean LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = 'SELECT p.sku, p.nazev, p.typ, p.aktivni, p.merna_jednotka, COALESCE(pt.is_nonstock,0) AS is_nonstock
                FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ
                WHERE ' . implode(' AND ', $conditions) . ' ORDER BY p.aktivni DESC, p.nazev LIMIT ' . (int)$limit;
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return [];
        }
        $skus = array_map(static fn($r) => (string)$r['sku'], $rows);
        $stock = StockService::getStockState($skus)['stock'];
        foreach ($rows as &$r) {
            $r['stav'] = (float)($stock[(string)$r['sku']] ?? 0.0);
        }
        unset($r);
        return $rows;
    }

    /** @return array<int,string> */
    private function productTypes(): array
    {
        $stmt = DB::pdo()->query('SELECT code FROM product_types ORDER BY name');
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    // ---------------------------------------------------------------- helpers

    private function dateParam(string $key): string
    {
        $v = trim((string)($_GET[$key] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }

    /** @return array{id:int,email:string,role:string} */
    private function currentUser(): array
    {
        $u = $_SESSION['user'] ?? [];
        return [
            'id' => (int)($u['id'] ?? 0),
            'email' => (string)($u['email'] ?? ''),
            'role' => (string)($u['role'] ?? 'user'),
        ];
    }

    private function flash(string $key): ?string
    {
        $v = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $v === null ? null : (string)$v;
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

    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $converted = @iconv('Windows-1250', 'UTF-8//IGNORE', $value);
        return $converted !== false ? $converted : $value;
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login');
            exit;
        }
    }

    private function render(string $view, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/../../views/_layout.php';
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
