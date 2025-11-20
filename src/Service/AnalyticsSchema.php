<?php

namespace App\Service;

use App\Support\DB;

/**
 * Jednoduchý provider schématu a návrhů aliasů pro AI analýzu.
 * Načítá názvy tabulek/sloupců z aktuální DB a umí nabídnout podobné názvy při chybě.
 */
final class AnalyticsSchema
{
    /** @var array<string, array<string, string>> */
    private array $columns = [];

    public function __construct()
    {
        $this->loadSchema();
    }

    /**
     * Textový přehled pro System prompt (tabulka: sloupce,Typ).
     */
    public function summary(): string
    {
        $lines = [];
        foreach ($this->columns as $table => $cols) {
            $items = [];
            foreach ($cols as $col => $type) {
                $items[] = "{$col} ({$type})";
            }
            $lines[] = "{$table}: " . implode(', ', $items);
        }
        return implode("\n", $lines);
    }

    /**
     * Najde podobné sloupce v daných tabulkách (nebo ve všech).
     *
     * @param string $name hledaný (chybějící) sloupec
     * @param string[] $tables omezení na tabulky; prázdné = všechny tabulky
     * @return array<string> návrhy ve formátu "tabulka.sloupec"
     */
    public function suggestColumns(string $name, array $tables = []): array
    {
        $name = strtolower($name);
        $candidates = [];
        $scope = $tables ?: array_keys($this->columns);
        foreach ($scope as $table) {
            foreach ($this->columns[$table] ?? [] as $col => $type) {
                $score = levenshtein($name, strtolower($col));
                $candidates[] = ['key' => "{$table}.{$col}", 'score' => $score];
            }
        }
        usort($candidates, fn($a, $b) => $a['score'] <=> $b['score']);
        $suggestions = array_slice(array_column($candidates, 'key'), 0, 5);
        return array_values(array_unique($suggestions));
    }

    /**
     * Z hrubého SQL vytáhne názvy tabulek za FROM/JOIN.
     *
     * @return string[]
     */
    public function extractTables(string $sql): array
    {
        $found = [];
        if (preg_match_all('/\\b(from|join)\\s+([a-z0-9_]+)/i', $sql, $m)) {
            foreach ($m[2] as $table) {
                $found[] = strtolower($table);
            }
        }
        return array_values(array_unique($found));
    }

    /**
     * Z textu chyby DB vytáhne chybějící sloupec (pokud je to "Unknown column 'X'").
     */
    public function extractMissingColumn(string $errorMessage): ?string
    {
        if (preg_match("/Unknown column '(.*?)'/i", $errorMessage, $m)) {
            return $m[1];
        }
        return null;
    }

    private function loadSchema(): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query("SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE()");
        foreach ($stmt as $row) {
            $table = strtolower((string)$row['table_name']);
            $col = (string)$row['column_name'];
            $type = (string)$row['data_type'];
            $this->columns[$table][$col] = $type;
        }
    }
}
