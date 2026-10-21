<?php

declare(strict_types=1);

namespace App\Platform\Migration;

use RuntimeException;

/**
 * Reads a QuickBooks / spreadsheet CSV export: UTF-8 BOM, comma/tab/semicolon
 * delimiters, report title rows above the header, "Total" footer rows.
 */
final class CsvFile
{
    /** @var list<string> */
    private readonly array $headers;

    /** @var array<string, string> raw header → canonical field */
    private array $mapping = [];

    /** @var list<array<string, string>> rows keyed by canonical field (plus '_row' = line number) */
    private array $rows = [];

    /**
     * @param  array<string, list<string>>  $aliases  canonical field → accepted header spellings
     */
    public function __construct(string $path, array $aliases)
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}.");
        }
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $delimiter = $this->detectDelimiter($raw);

        $lines = [];
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Cannot open temp stream.');
        }
        fwrite($handle, $raw);
        rewind($handle);
        $n = 0;
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $n++;
            $cells = array_map(fn ($c) => trim((string) $c), $cells);
            if (implode('', $cells) === '') {
                continue;
            }
            $lines[] = [$n, $cells];
        }
        fclose($handle);

        // Header = first row (within the first 15) that matches the most aliases.
        $normalizedAliases = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $normalizedAliases[self::norm($name)] = $field;
            }
        }
        $best = null;
        $bestScore = 0;
        foreach (array_slice($lines, 0, 15) as $i => [, $cells]) {
            $score = 0;
            foreach ($cells as $c) {
                if (isset($normalizedAliases[self::norm($c)])) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $i;
            }
        }
        if ($best === null) {
            throw new RuntimeException('No recognizable header row. Expected columns like: '.implode(', ', array_map(fn ($a) => $a[0], array_values($aliases))).'.');
        }

        $this->headers = $lines[$best][1];
        $positions = [];
        foreach ($this->headers as $idx => $h) {
            $field = $normalizedAliases[self::norm($h)] ?? null;
            if ($field !== null && ! isset($positions[$field])) {
                $positions[$field] = $idx;
                $this->mapping[$h] = $field;
            }
        }

        foreach (array_slice($lines, $best + 1) as [$lineNo, $cells]) {
            $first = $cells[0];
            if (preg_match('/^(total|grand total|sub ?total)\b/i', $first)) {
                continue;
            }
            $row = ['_row' => (string) $lineNo];
            foreach ($positions as $field => $idx) {
                $row[$field] = $cells[$idx] ?? '';
            }
            $this->rows[] = $row;
        }
    }

    /** @return list<array<string, string>> */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @return array<string, string> */
    public function mapping(): array
    {
        return $this->mapping;
    }

    /** @return list<string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public static function norm(string $h): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($h))) ?? '';
    }

    private function detectDelimiter(string $raw): string
    {
        $sample = substr($raw, 0, 4000);
        $counts = ["\t" => substr_count($sample, "\t"), ',' => substr_count($sample, ','), ';' => substr_count($sample, ';')];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
