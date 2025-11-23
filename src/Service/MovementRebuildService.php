<?php
namespace App\Service;

use App\Support\DB;
use PDO;
use PDOStatement;

final class MovementRebuildService
{
    /**
     * @return array{documents:int,items:int,movements:int,missing:int}
     */
    public static function rebuild(): array
    {
        $pdo = DB::pdo();
        $stats = [
            'documents' => 0,
            'items' => 0,
            'movements' => 0,
            'missing_products' => [],
        ];
        $meta = self::loadProducts();
        $bom = self::loadBom();
        $itemsStmt = $pdo->prepare('SELECT sku, mnozstvi FROM polozky_eshop WHERE eshop_source=? AND cislo_dokladu=?');
        $deleteStmt = $pdo->prepare('DELETE FROM polozky_pohyby WHERE ref_id LIKE ?');
        $insertStmt = $pdo->prepare('INSERT INTO polozky_pohyby (datum, sku, mnozstvi, merna_jednotka, typ_pohybu, poznamka, ref_id) VALUES (?,?,?,?,?,?,?)');

        $pdo->beginTransaction();
        try {
            $docs = $pdo->query('SELECT eshop_source, cislo_dokladu, duzp FROM doklady_eshop ORDER BY duzp')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($docs as $doc) {
                $duzp = (string)($doc['duzp'] ?? '');
                if ($duzp === '') {
                    continue;
                }
                $stats['documents']++;
                $eshop = (string)$doc['eshop_source'];
                $docNumber = (string)$doc['cislo_dokladu'];
                $itemsStmt->execute([$eshop, $docNumber]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!$items) {
                    continue;
                }
                $refBase = self::buildDocRef($eshop, $docNumber);
                $deleteStmt->execute([$refBase . ':%']);
                foreach ($items as $item) {
                    $sku = trim((string)($item['sku'] ?? ''));
                    $qty = (float)($item['mnozstvi'] ?? 0);
                    if ($sku === '' || $qty == 0.0) {
                        continue;
                    }
                    $stats['items']++;
                    $note = sprintf('Doklad %s / %s', $eshop, $docNumber);
                    self::expandAndInsert(
                        $sku,
                        -1 * $qty,
                        $duzp,
                        $note,
                        $refBase,
                        $insertStmt,
                        $meta,
                        $bom,
                        $stats
                    );
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'documents' => $stats['documents'],
            'items' => $stats['items'],
            'movements' => $stats['movements'],
            'missing' => count($stats['missing_products']),
        ];
    }

    /**
     * @return array{products:array<string,array{sku:string,typ:string,merna_jednotka:?string,is_nonstock:bool}>,alt:array<string,string>}
     */
    private static function loadProducts(): array
    {
        $products = [];
        $alt = [];
        foreach (DB::pdo()->query('SELECT p.sku, p.alt_sku, p.typ, p.merna_jednotka, COALESCE(pt.is_nonstock,0) AS is_nonstock FROM produkty p LEFT JOIN product_types pt ON pt.code = p.typ') as $row) {
            $sku = (string)$row['sku'];
            if ($sku === '') {
                continue;
            }
            $products[mb_strtolower($sku, 'UTF-8')] = [
                'sku' => $sku,
                'typ' => (string)($row['typ'] ?? ''),
                'merna_jednotka' => $row['merna_jednotka'] !== null ? (string)$row['merna_jednotka'] : null,
                'is_nonstock' => (bool)$row['is_nonstock'],
            ];
            $altSku = trim((string)($row['alt_sku'] ?? ''));
            if ($altSku !== '') {
                $alt[mb_strtolower($altSku, 'UTF-8')] = $sku;
            }
        }
        return ['products' => $products, 'alt' => $alt];
    }

    /**
     * @return array<string,array<int,array{sku:string,koeficient:float,unit:?string}>>
     */
    private static function loadBom(): array
    {
        $map = [];
        foreach (DB::pdo()->query('SELECT rodic_sku, potomek_sku, koeficient, merna_jednotka_potomka FROM bom') as $row) {
            $parent = (string)$row['rodic_sku'];
            $child = (string)$row['potomek_sku'];
            if ($parent === '' || $child === '') {
                continue;
            }
            $map[$parent][] = [
                'sku' => $child,
                'koeficient' => (float)$row['koeficient'],
                'unit' => $row['merna_jednotka_potomka'] !== null ? (string)$row['merna_jednotka_potomka'] : null,
            ];
        }
        return $map;
    }

    /**
     * @param array{products:array<string,array{sku:string,typ:string,merna_jednotka:?string}>,alt:array<string,string>} $meta
     * @param array<string,array<int,array{sku:string,koeficient:float,unit:?string}>> $bom
     * @param array<string,mixed> $stats
     */
    private static function expandAndInsert(
        string $requestedSku,
        float $qty,
        string $date,
        string $note,
        string $refBase,
        PDOStatement $insertStmt,
        array $meta,
        array $bom,
        array &$stats,
        array $path = []
    ): void {
        $product = self::resolveProduct($requestedSku, $meta['products'], $meta['alt']);
        if (!$product) {
            $stats['missing_products'][$requestedSku] = true;
            return;
        }
        $sku = $product['sku'];
        if (isset($path[$sku])) {
            return;
        }
        $path[$sku] = true;
        if (!empty($product['is_nonstock']) && !empty($bom[$sku])) {
            foreach ($bom[$sku] as $edge) {
                $childQty = $qty * $edge['koeficient'];
                self::expandAndInsert(
                    $edge['sku'],
                    $childQty,
                    $date,
                    $note,
                    $refBase,
                    $insertStmt,
                    $meta,
                    $bom,
                    $stats,
                    $path
                );
            }
            return;
        }
        $ref = $refBase . ':' . mb_strtolower($sku, 'UTF-8');
        $insertStmt->execute([
            $date,
            $sku,
            $qty,
            $product['merna_jednotka'],
            'odpis',
            $note,
            $ref,
        ]);
        $stats['movements']++;
    }

    /**
     * @param array<string,array{sku:string,typ:string,merna_jednotka:?string}> $products
     * @param array<string,string> $alt
     */
    private static function resolveProduct(string $sku, array $products, array $alt): ?array
    {
        $normalized = mb_strtolower($sku, 'UTF-8');
        if (isset($products[$normalized])) {
            return $products[$normalized];
        }
        if (isset($alt[$normalized])) {
            $target = mb_strtolower($alt[$normalized], 'UTF-8');
            return $products[$target] ?? null;
        }
        return null;
    }

    private static function buildDocRef(string $eshop, string $docNumber): string
    {
        return sprintf('doc:%s:%s', mb_strtolower($eshop, 'UTF-8'), $docNumber);
    }
}
