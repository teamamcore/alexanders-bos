<?php

declare(strict_types=1);

namespace App\Platform\Migration\Kinds;

use App\Platform\Migration\Importer;

/** One kind of record the importer knows how to bring in. */
interface Kind
{
    /** @return array<string, list<string>> canonical field → accepted header spellings (first = preferred) */
    public function aliases(): array;

    /** @return list<string> canonical fields a row must have */
    public function required(): array;

    /** Dates in rows older than the import window are skipped; null = no date filter. */
    public function dateField(): ?string;

    /**
     * Import one normalized row. Return 'imported' | 'updated' | 'skipped'.
     * Warnings go through $ctx->warn(); a skip returns 'skipped' after $ctx->skip().
     *
     * @param  array<string, string>  $row
     */
    public function import(array $row, Importer $ctx): string;
}
