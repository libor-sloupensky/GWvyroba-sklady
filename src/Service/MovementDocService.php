<?php
declare(strict_types=1);

namespace App\Service;

use App\Support\DB;
use PDO;

/**
 * Skladové doklady (záložka Pohyby).
 *
 * Hlavička žije v `sklad_doklady`, řádky zůstávají v `polozky_pohyby` (sloupce
 * doklad_id, parent_pohyb_id, user_id), takže výpočet stavu skladu nic nového
 * číst nemusí. Rodičovský řádek a jeho automaticky vygenerovaní potomci sdílejí
 * ref_id `dok-<doklad>-<řádek>`; potomci navíc ukazují na rodiče přes
 * parent_pohyb_id a samostatně se nemažou ani needitují.
 *
 * Režim řádku:
 *  - vyroba  = příjem rodiče, výdej nejbližších SKLADOVÝCH potomků podle BOM
 *              (neskladové mezistupně se prokaskádují, stejně jako u rezervací)
 *  - korekce = jen tento řádek, bez dopadu na komponenty (likvidace, odpis, oprava)
 */
final class MovementDocService
{
    private static bool $schemaVerified = false;

    /** @var array<string,bool>|null */
    private static ?array $nonstockCache = null;

    // ---------------------------------------------------------------- schéma

    public static function ensureSchema(): void
    {
        if (self::$schemaVerified) {
            return;
        }
        $pdo = DB::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS sklad_doklady (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cislo VARCHAR(16) NOT NULL,
            datum DATE NOT NULL,
            typ ENUM('vyroba','korekce') NOT NULL DEFAULT 'vyroba',
            poznamka VARCHAR(1024) NULL,
            user_id INT NOT NULL DEFAULT 0,
            user_email VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_sklad_doklady_cislo (cislo),
            KEY idx_sklad_doklady_datum (datum)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sklad_doklady_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doklad_id INT NOT NULL,
            pohyb_id INT NULL,
            sku VARCHAR(128) NULL,
            akce VARCHAR(32) NOT NULL,
            stare_mnozstvi DECIMAL(18,3) NULL,
            nove_mnozstvi DECIMAL(18,3) NULL,
            detail VARCHAR(255) NULL,
            user_email VARCHAR(255) NULL,
            datum DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sklad_doklady_log_doklad (doklad_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        // Inventární doklad (typ inventura, vazba na inventury.id) – doplněno 2026-09-30
        $typCol = $pdo->query("SHOW COLUMNS FROM sklad_doklady LIKE 'typ'")->fetch(PDO::FETCH_ASSOC);
        if ($typCol && stripos((string)$typCol['Type'], 'inventura') === false) {
            $pdo->exec("ALTER TABLE sklad_doklady MODIFY typ ENUM('vyroba','korekce','inventura') NOT NULL DEFAULT 'vyroba'");
        }
        self::ensureColumn($pdo, 'sklad_doklady', 'inventura_id', 'INT NULL AFTER `typ`');
        $idxInv = $pdo->query("SHOW INDEX FROM sklad_doklady WHERE Key_name = 'idx_sklad_doklady_inventura'")->fetch();
        if (!$idxInv) {
            $pdo->exec('ALTER TABLE sklad_doklady ADD KEY idx_sklad_doklady_inventura (inventura_id)');
        }
        self::ensureColumn($pdo, 'polozky_pohyby', 'doklad_id', 'INT NULL AFTER `ref_id`');
        self::ensureColumn($pdo, 'polozky_pohyby', 'parent_pohyb_id', 'INT NULL AFTER `doklad_id`');
        self::ensureColumn($pdo, 'polozky_pohyby', 'user_id', 'INT NULL AFTER `parent_pohyb_id`');
        $idx = $pdo->query("SHOW INDEX FROM polozky_pohyby WHERE Key_name = 'idx_pohyby_doklad'")->fetch();
        if (!$idx) {
            $pdo->exec('ALTER TABLE polozky_pohyby ADD KEY idx_pohyby_doklad (doklad_id)');
        }
        self::$schemaVerified = true;
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    // -------------------------------------------------------------- hlavička

    /**
     * Číslo dokladu RR-NNNN, pořadí v rámci roku. Volat uvnitř transakce.
     */
    private static function nextNumber(PDO $pdo, string $datum): string
    {
        $rr = substr($datum, 2, 2);
        $stmt = $pdo->prepare('SELECT cislo FROM sklad_doklady WHERE cislo LIKE ? ORDER BY cislo DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$rr . '-%']);
        $last = (string)($stmt->fetchColumn() ?: '');
        $n = $last !== '' ? (int)substr($last, 3) + 1 : 1;
        return sprintf('%s-%04d', $rr, $n);
    }

    /**
     * @param array{id:int,email:string,role:string} $user
     */
    public static function createDoc(array $user, string $typ): int
    {
        self::ensureSchema();
        $typ = $typ === 'korekce' ? 'korekce' : 'vyroba';
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $datum = date('Y-m-d');
            $cislo = self::nextNumber($pdo, $datum);
            $ins = $pdo->prepare('INSERT INTO sklad_doklady (cislo, datum, typ, poznamka, user_id, user_email) VALUES (?,?,?,NULL,?,?)');
            $ins->execute([$cislo, $datum, $typ, (int)$user['id'], (string)$user['email']]);
            $id = (int)$pdo->lastInsertId();
            self::log($pdo, $id, null, null, 'vytvoreni', null, null, 'Doklad ' . $cislo . ' (' . $typ . ')', (string)$user['email']);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Počet položek dokladu: u inventury počet SKU s rozdílem, jinak rodičovské řádky. */
    private const SQL_RADKU = "CASE WHEN d.typ = 'inventura'
        THEN (SELECT COUNT(*) FROM (SELECT doklad_id, sku, SUM(mnozstvi) AS s FROM polozky_pohyby WHERE doklad_id IS NOT NULL GROUP BY doklad_id, sku HAVING ABS(s) > 0.0005) x WHERE x.doklad_id = d.id)
        ELSE (SELECT COUNT(*) FROM polozky_pohyby pp WHERE pp.doklad_id = d.id AND pp.parent_pohyb_id IS NULL) END";

    /** @return array<string,mixed>|null */
    public static function loadDoc(int $id): ?array
    {
        self::ensureSchema();
        $stmt = DB::pdo()->prepare('SELECT d.*, ' . self::SQL_RADKU . ' AS radku FROM sklad_doklady d WHERE d.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function isInventoryDoc(array $doc): bool
    {
        return (string)($doc['typ'] ?? '') === 'inventura';
    }

    // ------------------------------------------------------ inventární doklad

    /**
     * Založí (nebo při opětovném uzavření znovu naplní) inventární doklad.
     * Řádky dokladu jsou existující rozdílové pohyby inventury (ref inv:<id>:<zápis>),
     * jen se jim nastaví doklad_id – nic se nekopíruje. Doklad drží své číslo
     * i po znovuotevření inventury (řádky se odpojí, doklad zůstane prázdný).
     *
     * Volá se uvnitř transakce volajícího; ensureSchema() musí proběhnout před ní
     * (DDL by transakci potichu potvrdilo).
     *
     * @param array{id:int,email:string,role:string} $user
     */
    public static function attachInventoryDoc(PDO $pdo, int $inventoryId, string $closedAt, array $user, ?string $poznamka): int
    {
        $datum = substr($closedAt, 0, 10);
        $poznamka = ($poznamka !== null && trim($poznamka) !== '') ? mb_substr(trim($poznamka), 0, 1024) : null;
        $sel = $pdo->prepare('SELECT id, cislo FROM sklad_doklady WHERE inventura_id = ? LIMIT 1');
        $sel->execute([$inventoryId]);
        $existing = $sel->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $docId = (int)$existing['id'];
            $pdo->prepare('UPDATE sklad_doklady SET datum = ?, poznamka = ?, user_id = ?, user_email = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$datum, $poznamka, (int)$user['id'], (string)$user['email'], $docId]);
            self::log($pdo, $docId, null, null, 'inventura_uzavrena', null, null, 'Inventura #' . $inventoryId . ' znovu uzavřena ' . substr($closedAt, 0, 16), (string)$user['email']);
        } else {
            $cislo = self::nextNumber($pdo, $datum);
            $pdo->prepare('INSERT INTO sklad_doklady (cislo, datum, typ, inventura_id, poznamka, user_id, user_email, created_at) VALUES (?,?,\'inventura\',?,?,?,?,?)')
                ->execute([$cislo, $datum, $inventoryId, $poznamka, (int)$user['id'], (string)$user['email'], $closedAt]);
            $docId = (int)$pdo->lastInsertId();
            self::log($pdo, $docId, null, null, 'vytvoreni', null, null, 'Doklad ' . $cislo . ' – uzavření inventury #' . $inventoryId . ' ' . substr($closedAt, 0, 16), (string)$user['email']);
        }
        $pdo->prepare("UPDATE polozky_pohyby SET doklad_id = ? WHERE ref_id LIKE ?")->execute([$docId, sprintf('inv:%d:%%', $inventoryId)]);
        return $docId;
    }

    /** Znovuotevření inventury: řádky se odpojí, doklad zůstane (prázdný) se svým číslem. */
    public static function detachInventoryDoc(PDO $pdo, int $inventoryId, array $user): void
    {
        $sel = $pdo->prepare('SELECT id FROM sklad_doklady WHERE inventura_id = ? LIMIT 1');
        $sel->execute([$inventoryId]);
        $docId = (int)($sel->fetchColumn() ?: 0);
        if ($docId <= 0) {
            return;
        }
        $pdo->prepare('UPDATE polozky_pohyby SET doklad_id = NULL WHERE doklad_id = ?')->execute([$docId]);
        $pdo->prepare('UPDATE sklad_doklady SET updated_at = NOW() WHERE id = ?')->execute([$docId]);
        self::log($pdo, $docId, null, null, 'inventura_otevrena', null, null, 'Inventura #' . $inventoryId . ' znovu otevřena, položky odpojeny', (string)$user['email']);
    }

    /** Smazání inventury maže i její doklad (pohyby maže inventura sama). */
    public static function deleteInventoryDoc(PDO $pdo, int $inventoryId): void
    {
        $sel = $pdo->prepare('SELECT id FROM sklad_doklady WHERE inventura_id = ? LIMIT 1');
        $sel->execute([$inventoryId]);
        $docId = (int)($sel->fetchColumn() ?: 0);
        if ($docId <= 0) {
            return;
        }
        $pdo->prepare('UPDATE polozky_pohyby SET doklad_id = NULL WHERE doklad_id = ?')->execute([$docId]);
        $pdo->prepare('DELETE FROM sklad_doklady_log WHERE doklad_id = ?')->execute([$docId]);
        $pdo->prepare('DELETE FROM sklad_doklady WHERE id = ?')->execute([$docId]);
    }

    /** Poznámku inventárního dokladu smí měnit admin/superadmin kdykoli; u ostatních platí lockReason(). */
    public static function canEditNote(array $doc, array $user): bool
    {
        if (self::isInventoryDoc($doc)) {
            return in_array((string)($user['role'] ?? ''), ['admin', 'superadmin'], true);
        }
        return self::lockReason($doc, $user) === null;
    }

    /**
     * @param array{id:int,email:string,role:string} $user
     */
    public static function updateHeader(array $doc, array $user, string $typ, string $poznamka): void
    {
        $pdo = DB::pdo();
        $poznamka = mb_substr(trim($poznamka), 0, 1024);
        if (self::isInventoryDoc($doc)) {
            // Jediné, co se u inventárního dokladu mění, je poznámka – a ta má jeden
            // zdroj v inventury.poznamka, aby se doklad a inventura nerozešly.
            if ($poznamka === (string)($doc['poznamka'] ?? '')) {
                return;
            }
            $pdo->prepare('UPDATE sklad_doklady SET poznamka = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$poznamka === '' ? null : $poznamka, (int)$doc['id']]);
            if (!empty($doc['inventura_id'])) {
                $pdo->prepare('UPDATE inventury SET poznamka = ? WHERE id = ?')
                    ->execute([$poznamka === '' ? null : mb_substr($poznamka, 0, 255), (int)$doc['inventura_id']]);
            }
            self::log($pdo, (int)$doc['id'], null, null, 'hlavicka', null, null, 'poznámka', (string)$user['email']);
            return;
        }
        $typ = $typ === 'korekce' ? 'korekce' : 'vyroba';
        $changes = [];
        if ($typ !== (string)$doc['typ']) {
            $changes[] = 'typ ' . $doc['typ'] . ' → ' . $typ;
        }
        if ($poznamka !== (string)($doc['poznamka'] ?? '')) {
            $changes[] = 'poznámka';
        }
        if (!$changes) {
            return;
        }
        $pdo->prepare('UPDATE sklad_doklady SET typ = ?, poznamka = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$typ, $poznamka === '' ? null : $poznamka, (int)$doc['id']]);
        self::log($pdo, (int)$doc['id'], null, null, 'hlavicka', null, null, implode(', ', $changes), (string)$user['email']);
    }

    public static function deleteDoc(array $doc): bool
    {
        $pdo = DB::pdo();
        if ((int)$doc['radku'] > 0) {
            return false;
        }
        $pdo->prepare('DELETE FROM sklad_doklady_log WHERE doklad_id = ?')->execute([(int)$doc['id']]);
        $pdo->prepare('DELETE FROM sklad_doklady WHERE id = ?')->execute([(int)$doc['id']]);
        return true;
    }

    // ------------------------------------------------------------- oprávnění

    /**
     * Vrátí null, když smí uživatel doklad editovat, jinak důvod zámku.
     *
     * Pravidla: autor do konce následujícího dne, superadmin 7 dní od data dokladu.
     * Nezávisle na tom se doklad zamkne, jakmile byla po jeho založení uzavřena
     * inventura — stav skladu se od ní počítá znovu a změna staršího řádku by ho
     * rozjela.
     *
     * @param array{id:int,email:string,role:string} $user
     */
    public static function lockReason(array $doc, array $user): ?string
    {
        if ((string)($user['role'] ?? 'user') === 'user') {
            return 'Role čtenář může doklady jen prohlížet.';
        }
        if (self::isInventoryDoc($doc)) {
            return 'Inventární doklad – položky vznikají uzavřením inventury a nelze je měnit. Upravit lze jen poznámku (admin).';
        }
        $inv = DB::pdo()->prepare('SELECT id, closed_at FROM inventury WHERE closed_at IS NOT NULL AND closed_at > ? ORDER BY closed_at LIMIT 1');
        $inv->execute([(string)$doc['created_at']]);
        $closed = $inv->fetch(PDO::FETCH_ASSOC);
        if ($closed) {
            return 'Po založení dokladu byla uzavřena inventura #' . (int)$closed['id'] . ' (' . substr((string)$closed['closed_at'], 0, 16) . '), doklad je uzamčen.';
        }
        $role = (string)($user['role'] ?? 'user');
        $isAuthor = (int)$doc['user_id'] === (int)$user['id'] && (string)$doc['user_email'] === (string)$user['email'];
        $today = new \DateTimeImmutable('today');
        $docDate = new \DateTimeImmutable((string)$doc['datum']);
        if ($role === 'superadmin') {
            if ($today > $docDate->modify('+7 days')) {
                return 'Doklad je starší než 7 dní, editace už není možná.';
            }
            return null;
        }
        if (!$isAuthor) {
            return 'Doklad založil ' . (string)$doc['user_email'] . ', editovat ho může jen autor (nebo superadmin).';
        }
        if ($today > $docDate->modify('+1 day')) {
            return 'Doklad lze editovat jen do konce následujícího dne.';
        }
        return null;
    }

    // ----------------------------------------------------------------- řádky

    /** @return array<string,bool> */
    private static function nonstockSkus(): array
    {
        if (self::$nonstockCache !== null) {
            return self::$nonstockCache;
        }
        $map = [];
        $stmt = DB::pdo()->query('SELECT p.sku FROM produkty p JOIN product_types pt ON pt.code = p.typ WHERE pt.is_nonstock = 1');
        foreach ($stmt as $row) {
            $map[(string)$row['sku']] = true;
        }
        self::$nonstockCache = $map;
        return $map;
    }

    public static function isNonstock(string $sku): bool
    {
        return isset(self::nonstockSkus()[$sku]);
    }

    /**
     * Nejbližší skladoví potomci podle BOM pro dané množství rodiče. Neskladové
     * mezistupně (karton → balení) se rozpustí dál, dokud se nenarazí na skladovou
     * položku. Stejné SKU z více větví se sečte.
     *
     * @return array<int,array{sku:string,mnozstvi:float,merna_jednotka:?string}>
     */
    public static function stockComponents(string $sku, float $qty): array
    {
        $children = StockService::getBomGraph()['children'];
        $nonstock = self::nonstockSkus();
        $out = [];
        $queue = [[$sku, $qty, [$sku => true]]];
        $guard = 0;
        while ($queue && $guard++ < 100000) {
            [$cur, $curQty, $path] = array_shift($queue);
            foreach ($children[$cur] ?? [] as $edge) {
                $child = (string)$edge['sku'];
                $coef = (float)$edge['koeficient'];
                if ($child === '' || $coef <= 0.0 || isset($path[$child])) {
                    continue;
                }
                $childQty = $curQty * $coef;
                if (isset($nonstock[$child])) {
                    $queue[] = [$child, $childQty, $path + [$child => true]];
                    continue;
                }
                if (!isset($out[$child])) {
                    $out[$child] = ['sku' => $child, 'mnozstvi' => 0.0, 'merna_jednotka' => $edge['edge_mj'] ?? null];
                }
                $out[$child]['mnozstvi'] += $childQty;
            }
        }
        return array_values($out);
    }

    /**
     * Přidá rodičovský řádek (+ potomky v režimu výroba). Vrací id řádku.
     *
     * @param array{id:int,email:string,role:string} $user
     */
    public static function addLine(array $doc, array $user, string $sku, float $qty, string $rezim): int
    {
        $pdo = DB::pdo();
        $rezim = $rezim === 'korekce' ? 'korekce' : 'vyroba';
        $meta = self::productMeta($sku);
        if (!$meta) {
            throw new \RuntimeException('Produkt ' . $sku . ' neexistuje.');
        }
        if (self::isNonstock($sku)) {
            throw new \RuntimeException('Neskladovou položku nelze do dokladu vložit.');
        }
        $datum = (string)$doc['datum'] . ' ' . date('H:i:s');
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO polozky_pohyby (datum, sku, mnozstvi, merna_jednotka, typ_pohybu, poznamka, ref_id, doklad_id, parent_pohyb_id, user_id) VALUES (?,?,?,?,?,?,?,?,NULL,?)');
            $ins->execute([$datum, $sku, $qty, $meta['merna_jednotka'], $rezim, null, null, (int)$doc['id'], (int)$user['id']]);
            $lineId = (int)$pdo->lastInsertId();
            $ref = 'dok-' . (int)$doc['id'] . '-' . $lineId;
            $pdo->prepare('UPDATE polozky_pohyby SET ref_id = ? WHERE id = ?')->execute([$ref, $lineId]);
            self::regenerateChildren($pdo, $doc, $user, $lineId, $sku, $qty, $rezim, $datum, $ref);
            self::log($pdo, (int)$doc['id'], $lineId, $sku, 'pridani', null, $qty, 'režim ' . $rezim, (string)$user['email']);
            $pdo->prepare('UPDATE sklad_doklady SET updated_at = NOW() WHERE id = ?')->execute([(int)$doc['id']]);
            $pdo->commit();
            return $lineId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array{id:int,email:string,role:string} $user
     */
    public static function updateLine(array $doc, array $user, int $lineId, float $qty, string $rezim): void
    {
        $pdo = DB::pdo();
        $line = self::loadLine($lineId);
        if (!$line || (int)$line['doklad_id'] !== (int)$doc['id']) {
            throw new \RuntimeException('Řádek nepatří k tomuto dokladu.');
        }
        if ($line['parent_pohyb_id'] !== null) {
            throw new \RuntimeException('Automaticky odepsaného potomka nelze měnit, upravte rodičovský řádek.');
        }
        $rezim = $rezim === 'korekce' ? 'korekce' : 'vyroba';
        $oldQty = (float)$line['mnozstvi'];
        $oldRezim = (string)$line['typ_pohybu'];
        if (abs($oldQty - $qty) < 0.0000001 && $oldRezim === $rezim) {
            return;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE polozky_pohyby SET mnozstvi = ?, typ_pohybu = ?, user_id = ? WHERE id = ?')
                ->execute([$qty, $rezim, (int)$user['id'], $lineId]);
            self::regenerateChildren($pdo, $doc, $user, $lineId, (string)$line['sku'], $qty, $rezim, (string)$line['datum'], (string)$line['ref_id']);
            if ($oldRezim !== $rezim) {
                self::log($pdo, (int)$doc['id'], $lineId, (string)$line['sku'], 'zmena_rezimu', $oldQty, $qty, $oldRezim . ' → ' . $rezim, (string)$user['email']);
            } else {
                self::log($pdo, (int)$doc['id'], $lineId, (string)$line['sku'], 'zmena_mnozstvi', $oldQty, $qty, null, (string)$user['email']);
            }
            $pdo->prepare('UPDATE sklad_doklady SET updated_at = NOW() WHERE id = ?')->execute([(int)$doc['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array{id:int,email:string,role:string} $user
     */
    public static function deleteLine(array $doc, array $user, int $lineId): void
    {
        $pdo = DB::pdo();
        $line = self::loadLine($lineId);
        if (!$line || (int)$line['doklad_id'] !== (int)$doc['id']) {
            throw new \RuntimeException('Řádek nepatří k tomuto dokladu.');
        }
        if ($line['parent_pohyb_id'] !== null) {
            throw new \RuntimeException('Automaticky odepsaného potomka nelze smazat samostatně, smažte rodičovský řádek.');
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM polozky_pohyby WHERE parent_pohyb_id = ?')->execute([$lineId]);
            $pdo->prepare('DELETE FROM polozky_pohyby WHERE id = ?')->execute([$lineId]);
            self::log($pdo, (int)$doc['id'], $lineId, (string)$line['sku'], 'smazani', (float)$line['mnozstvi'], null, null, (string)$user['email']);
            $pdo->prepare('UPDATE sklad_doklady SET updated_at = NOW() WHERE id = ?')->execute([(int)$doc['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Smaže dosavadní potomky rodičovského řádku a v režimu výroba je vygeneruje
     * znovu podle AKTUÁLNÍHO BOM. Potomci nesou stejný ref_id i datum jako rodič.
     */
    private static function regenerateChildren(PDO $pdo, array $doc, array $user, int $lineId, string $sku, float $qty, string $rezim, string $datum, string $ref): void
    {
        $pdo->prepare('DELETE FROM polozky_pohyby WHERE parent_pohyb_id = ?')->execute([$lineId]);
        if ($rezim !== 'vyroba' || $qty == 0.0) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO polozky_pohyby (datum, sku, mnozstvi, merna_jednotka, typ_pohybu, poznamka, ref_id, doklad_id, parent_pohyb_id, user_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach (self::stockComponents($sku, $qty) as $comp) {
            $unit = $comp['merna_jednotka'] ?? (self::productMeta($comp['sku'])['merna_jednotka'] ?? null);
            $ins->execute([$datum, $comp['sku'], -1 * $comp['mnozstvi'], $unit, 'vyroba', 'odečet komponenty', $ref, (int)$doc['id'], $lineId, (int)$user['id']]);
        }
    }

    /** @return array<string,mixed>|null */
    private static function loadLine(int $id): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM polozky_pohyby WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{nazev:string,merna_jednotka:?string,typ:string}|null */
    private static function productMeta(string $sku): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT nazev, merna_jednotka, typ FROM produkty WHERE sku = ? LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'nazev' => (string)$row['nazev'],
            'merna_jednotka' => ($row['merna_jednotka'] ?? '') !== '' ? (string)$row['merna_jednotka'] : null,
            'typ' => (string)$row['typ'],
        ];
    }

    /**
     * Řádky dokladu se stavem skladu před a po. Stav "po" je aktuální fyzický
     * stav (řádek už je zapsaný), stav "před" = po − množství řádku.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function loadLines(int $docId): array
    {
        $head = DB::pdo()->prepare('SELECT typ, inventura_id FROM sklad_doklady WHERE id = ? LIMIT 1');
        $head->execute([$docId]);
        $headRow = $head->fetch(PDO::FETCH_ASSOC);
        if ($headRow && (string)$headRow['typ'] === 'inventura') {
            return self::loadInventoryLines($docId, (int)$headRow['inventura_id']);
        }
        $stmt = DB::pdo()->prepare('SELECT pp.id, pp.datum, pp.sku, pp.mnozstvi, pp.merna_jednotka, pp.typ_pohybu, pp.parent_pohyb_id, p.nazev, p.merna_jednotka AS p_mj
            FROM polozky_pohyby pp LEFT JOIN produkty p ON p.sku = pp.sku
            WHERE pp.doklad_id = ? ORDER BY COALESCE(pp.parent_pohyb_id, pp.id), pp.parent_pohyb_id IS NOT NULL, pp.id');
        $stmt->execute([$docId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return [];
        }
        $skus = array_values(array_unique(array_map(static fn($r) => (string)$r['sku'], $rows)));
        $stock = StockService::getStockState($skus)['stock'];
        $byId = [];
        $order = [];
        foreach ($rows as $r) {
            $qty = (float)$r['mnozstvi'];
            $after = (float)($stock[(string)$r['sku']] ?? 0.0);
            $item = [
                'id' => (int)$r['id'],
                'sku' => (string)$r['sku'],
                'nazev' => (string)($r['nazev'] ?? ''),
                'mj' => (string)($r['merna_jednotka'] ?? $r['p_mj'] ?? ''),
                'mnozstvi' => $qty,
                'rezim' => (string)$r['typ_pohybu'],
                'stav_po' => $after,
                'stav_pred' => $after - $qty,
                'datum' => (string)$r['datum'],
                'children' => [],
            ];
            if ($r['parent_pohyb_id'] === null) {
                $byId[$item['id']] = $item;
                $order[] = $item['id'];
            } else {
                $pid = (int)$r['parent_pohyb_id'];
                if (isset($byId[$pid])) {
                    $byId[$pid]['children'][] = $item;
                }
            }
        }
        $out = [];
        foreach ($order as $id) {
            $out[] = $byId[$id];
        }
        return $out;
    }

    /**
     * Řádky inventárního dokladu: jen SKU s nenulovým rozdílem, seskupené po SKU
     * (jeden zápis se může skládat z více dílčích počítání). Stav "po" je zjištěný
     * stav ze snímku inventury, "před" = zjištěný − rozdíl (tedy očekávaný).
     *
     * @return array<int,array<string,mixed>>
     */
    private static function loadInventoryLines(int $docId, int $inventoryId): array
    {
        $stmt = DB::pdo()->prepare('SELECT pp.sku, SUM(pp.mnozstvi) AS delta, MIN(pp.id) AS id, MIN(pp.datum) AS datum,
                MAX(pp.merna_jednotka) AS mj, p.nazev, p.merna_jednotka AS p_mj, s.stav
            FROM polozky_pohyby pp
            LEFT JOIN produkty p ON p.sku = pp.sku
            LEFT JOIN inventura_stavy s ON s.inventura_id = ? AND s.sku = pp.sku
            WHERE pp.doklad_id = ?
            GROUP BY pp.sku, p.nazev, p.merna_jednotka, s.stav
            HAVING ABS(SUM(pp.mnozstvi)) > 0.0005
            ORDER BY p.nazev, pp.sku');
        $stmt->execute([$inventoryId, $docId]);
        $out = [];
        foreach ($stmt as $r) {
            $delta = (float)$r['delta'];
            $after = $r['stav'] !== null ? (float)$r['stav'] : $delta;
            $out[] = [
                'id' => (int)$r['id'],
                'sku' => (string)$r['sku'],
                'nazev' => (string)($r['nazev'] ?? ''),
                'mj' => (string)($r['mj'] ?? $r['p_mj'] ?? ''),
                'mnozstvi' => $delta,
                'rezim' => 'inventura',
                'stav_po' => $after,
                'stav_pred' => $after - $delta,
                'datum' => (string)$r['datum'],
                'children' => [],
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function loadLog(int $docId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM sklad_doklady_log WHERE doklad_id = ? ORDER BY id DESC LIMIT 200');
        $stmt->execute([$docId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Změny množství téhož řádku od téhož uživatele do této doby se slučují do jednoho záznamu. */
    private const LOG_MERGE_SECONDS = 180;

    private static function log(PDO $pdo, int $docId, ?int $lineId, ?string $sku, string $akce, ?float $old, ?float $new, ?string $detail, string $email): void
    {
        if ($akce === 'zmena_mnozstvi' && $lineId !== null) {
            // Klikání na šipky u množství vyvolá sérii uložení – v logu má být jedna
            // změna "z původního na výsledné", ne deset mezikroků.
            $last = $pdo->prepare('SELECT id, akce, stare_mnozstvi, user_email, datum FROM sklad_doklady_log WHERE doklad_id = ? AND pohyb_id = ? ORDER BY id DESC LIMIT 1');
            $last->execute([$docId, $lineId]);
            $prev = $last->fetch(PDO::FETCH_ASSOC);
            if ($prev && $prev['akce'] === 'zmena_mnozstvi' && (string)$prev['user_email'] === $email
                && (time() - strtotime((string)$prev['datum'])) <= self::LOG_MERGE_SECONDS) {
                if ($new !== null && abs((float)$prev['stare_mnozstvi'] - $new) < 0.0000001) {
                    // vrátil se na původní hodnotu – změna se v součtu nestala
                    $pdo->prepare('DELETE FROM sklad_doklady_log WHERE id = ?')->execute([(int)$prev['id']]);
                } else {
                    $pdo->prepare('UPDATE sklad_doklady_log SET nove_mnozstvi = ?, datum = NOW() WHERE id = ?')->execute([$new, (int)$prev['id']]);
                }
                return;
            }
        }
        $pdo->prepare('INSERT INTO sklad_doklady_log (doklad_id, pohyb_id, sku, akce, stare_mnozstvi, nove_mnozstvi, detail, user_email) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$docId, $lineId, $sku, $akce, $old, $new, $detail, $email]);
    }

    // ---------------------------------------------------------------- seznam

    /**
     * @param array{od:string,do:string,typ:string,q:string} $f
     * @return array<int,array<string,mixed>>
     */
    public static function listDocs(array $f, int $limit = 200): array
    {
        self::ensureSchema();
        $where = ['1=1'];
        $params = [];
        if ($f['od'] !== '') {
            $where[] = 'd.datum >= ?';
            $params[] = $f['od'];
        }
        if ($f['do'] !== '') {
            $where[] = 'd.datum <= ?';
            $params[] = $f['do'];
        }
        if (in_array($f['typ'], ['vyroba', 'korekce', 'inventura'], true)) {
            $where[] = 'd.typ = ?';
            $params[] = $f['typ'];
        }
        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $where[] = '(d.cislo LIKE ? OR d.poznamka LIKE ? OR d.user_email LIKE ? OR EXISTS (SELECT 1 FROM polozky_pohyby pp WHERE pp.doklad_id = d.id AND pp.sku LIKE ?))';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = 'SELECT d.*,
                ' . self::SQL_RADKU . ' AS radku,
                (SELECT GROUP_CONCAT(DISTINCT pp.sku ORDER BY pp.id SEPARATOR ", ") FROM polozky_pohyby pp WHERE pp.doklad_id = d.id AND pp.parent_pohyb_id IS NULL) AS skus
                FROM sklad_doklady d WHERE ' . implode(' AND ', $where) . ' ORDER BY d.datum DESC, d.id DESC LIMIT ' . (int)$limit;
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
