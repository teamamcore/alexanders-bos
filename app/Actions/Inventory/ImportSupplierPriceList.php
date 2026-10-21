<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Part;
use App\Models\Supplier;
use DomainException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.7 — supplier price list import (CSV). Columns: sku, name, price
 * [, category, unit]. Existing parts (by SKU) get the new price + supplier;
 * unknown SKUs are created. Price changes land in the audit trail (Part is
 * Auditable) so item history shows the import.
 */
final class ImportSupplierPriceList
{
    use AsAction;

    /** @return array{created: int, updated: int, unchanged: int, skipped: int} */
    public function handle(Supplier $supplier, string $csv): array
    {
        $lines = array_filter(preg_split('/\r\n|\r|\n/', trim($csv)) ?: [], fn (string $l) => trim($l) !== '');
        $rows = array_values(array_map(str_getcsv(...), $lines));
        if ($rows === []) {
            throw new DomainException('The file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        foreach (['sku', 'price'] as $required) {
            if (! in_array($required, $header, true)) {
                throw new DomainException("Missing required column: {$required} (have: ".implode(', ', $header).')');
            }
        }
        $idx = array_flip($header);
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];

        foreach (array_slice($rows, 1) as $row) {
            $sku = trim((string) ($row[$idx['sku']] ?? ''));
            $price = (float) str_replace(['$', ','], '', (string) ($row[$idx['price']] ?? ''));
            if ($sku === '' || $price <= 0) {
                $result['skipped']++;

                continue;
            }
            $name = isset($idx['name']) ? trim((string) ($row[$idx['name']] ?? '')) : '';

            $part = Part::query()->where('sku', $sku)->first();
            if ($part instanceof Part) {
                $changed = round((float) $part->price, 2) !== round($price, 2) || $part->supplier_id !== $supplier->id;
                $part->fill(['price' => $price, 'supplier_id' => $supplier->id]);
                if ($name !== '' && ! $part->name) {
                    $part->name = $name;
                }
                $part->save();
                $result[$changed ? 'updated' : 'unchanged']++;
            } else {
                Part::create([
                    'supplier_id' => $supplier->id,
                    'sku' => $sku,
                    'name' => $name !== '' ? $name : $sku,
                    'price' => $price,
                    'category' => isset($idx['category']) ? (trim((string) ($row[$idx['category']] ?? '')) ?: null) : null,
                    'unit' => isset($idx['unit']) ? (trim((string) ($row[$idx['unit']] ?? '')) ?: 'each') : 'each',
                ]);
                $result['created']++;
            }
        }

        return $result;
    }
}
