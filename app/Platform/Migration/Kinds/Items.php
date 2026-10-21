<?php

declare(strict_types=1);

namespace App\Platform\Migration\Kinds;

use App\Models\Part;
use App\Platform\Migration\Importer;

/** QuickBooks "Products and Services" / "Item List" → Parts catalog (SOW §3.7). */
final class Items implements Kind
{
    public function aliases(): array
    {
        return [
            'name' => ['Product/Service', 'Product/Service Name', 'Item', 'Item Name', 'Name', 'Product'],
            'sku' => ['SKU', 'Part Number', 'Item Number', 'MPN'],
            'type' => ['Type', 'Item Type'],
            'description' => ['Sales Description', 'Description', 'Purchase Description'],
            'price' => ['Sales Price', 'Price', 'Rate', 'Sales Price/Rate', 'Unit Price'],
            'cost' => ['Cost', 'Purchase Cost', 'Unit Cost'],
            'qty' => ['Qty on Hand', 'Quantity On Hand', 'Qty On Hand', 'On Hand', 'Quantity'],
            'reorder' => ['Reorder Point', 'Reorder Pt', 'Reorder Threshold'],
            'category' => ['Category', 'Income Account', 'Account'],
            'active' => ['Active', 'Status'],
        ];
    }

    public function required(): array
    {
        return ['name'];
    }

    public function dateField(): ?string
    {
        return null;
    }

    public function import(array $row, Importer $ctx): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $row['name']) ?? $row['name']);
        if ($name === '') {
            $ctx->skip('name', 'Blank item name.');

            return 'skipped';
        }
        $type = mb_strtolower(trim($row['type'] ?? ''));
        if (in_array($type, ['bundle', 'group', 'category', 'subtotal', 'discount', 'payment', 'sales tax item', 'sales tax', 'other charge'], true)) {
            $ctx->skip('type', "Item type '{$row['type']}' is not a part or service (not imported).");

            return 'skipped';
        }
        if (in_array(mb_strtolower(trim($row['active'] ?? '')), ['no', 'inactive', 'false', '0'], true)) {
            $ctx->skip('active', 'Inactive item.');

            return 'skipped';
        }

        // QuickBooks sub-items are "Parent:Child"; keep the child as the name, parent as category.
        $segments = array_map(trim(...), explode(':', $name));
        $leaf = end($segments);
        $parentCategory = count($segments) > 1 ? $segments[0] : null;

        $price = Importer::money($row['price'] ?? null);
        $cost = Importer::money($row['cost'] ?? null);
        if ($price === null && ($row['price'] ?? '') !== '') {
            $ctx->warn('price', "Price '{$row['price']}' not numeric — set to 0.");
        }
        $qty = Importer::money($row['qty'] ?? null);
        $sku = trim($row['sku'] ?? '') ?: null;
        $key = $sku !== null ? 'sku:'.Importer::key($sku) : 'name:'.Importer::key($name);

        $attrs = array_filter([
            'sku' => $sku,
            'category' => trim($row['category'] ?? '') ?: $parentCategory ?: (str_contains($type, 'service') ? 'Service' : null),
            'price' => $price ?? 0,
            'cost' => $cost,
            'stock_qty' => $qty !== null ? max(0, (int) $qty) : null,
            'reorder_threshold' => ($r = Importer::money($row['reorder'] ?? null)) !== null ? (int) $r : null,
            'notes' => trim($row['description'] ?? '') ?: null,
            'unit' => str_contains($type, 'service') ? 'hour' : 'each',
        ], fn ($v) => $v !== null);

        $existing = $ctx->existing('items', $key);
        if (! $existing instanceof Part && $sku !== null) {
            $existing = Part::query()->where('sku', $sku)->first();
        }
        if (! $existing instanceof Part) {
            $existing = Part::query()->whereRaw('lower(name) = ?', [mb_strtolower($leaf)])->first();
        }

        if ($existing instanceof Part) {
            $existing->fill($attrs)->save();
            $ctx->remember('items', $key, $existing, $row);

            return 'updated';
        }
        $part = Part::create(['name' => $leaf, 'nicknames' => [], 'is_one_off' => false] + $attrs + ['stock_qty' => 0]);
        $ctx->remember('items', $key, $part, $row);

        return 'imported';
    }
}
