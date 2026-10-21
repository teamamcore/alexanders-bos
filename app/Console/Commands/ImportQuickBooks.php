<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Migration\RunImport;
use App\Models\Tenant;
use App\Platform\Migration\Importer;
use Illuminate\Console\Command;

/**
 * SOW §4 — bulk import from a folder of exports:
 *   php artisan import:quickbooks storage/imports/batsons --commit
 * Looks for customers.csv, items.csv, receivables.csv (or invoices.csv), work_orders.csv
 * and runs them in dependency order. Dry run unless --commit.
 */
final class ImportQuickBooks extends Command
{
    protected $signature = 'import:quickbooks {dir : Folder with the export CSVs} {--commit : Write to the database (default is a dry run)} {--tenant=1 : Tenant id}';

    protected $description = 'Import QuickBooks / spreadsheet exports (customers, items, receivables, work orders) with a report of anything skipped';

    public function handle(): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/');
        if (! is_dir($dir)) {
            $this->error("Not a directory: {$dir}");

            return self::FAILURE;
        }
        $tenant = Tenant::query()->find((int) $this->option('tenant'));
        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }
        app()->instance('currentTenantId', $tenant->id);
        app()->instance('currentTenant', $tenant);

        $files = ['customers' => ['customers.csv'], 'items' => ['items.csv', 'products.csv'], 'receivables' => ['receivables.csv', 'invoices.csv', 'ar.csv'], 'work_orders' => ['work_orders.csv', 'workorders.csv', 'wo.csv']];
        $dry = ! $this->option('commit');
        $this->line($dry ? 'DRY RUN — nothing will be written. Each file is checked on its own, so links between files (customer ↔ invoice ↔ work order) show as warnings until you commit.' : 'COMMIT — writing to '.$tenant->name.'.');

        $any = false;
        foreach ($files as $kind => $names) {
            $path = null;
            foreach ($names as $n) {
                if (is_file("{$dir}/{$n}")) {
                    $path = "{$dir}/{$n}";
                    break;
                }
            }
            if ($path === null) {
                continue;
            }
            $any = true;
            $b = RunImport::run($kind, $path, $dry, null, basename($path));
            $label = Importer::KINDS[$kind];
            if ($b->status === 'failed') {
                $this->error("{$label}: FAILED — {$b->error}");

                continue;
            }
            $this->info("{$label}: {$b->total} rows → {$b->imported} new, {$b->updated} updated, {$b->skipped} skipped, {$b->warnings} warnings (batch #{$b->id}).");
            foreach (array_slice($b->issues ?? [], 0, 15) as $i) {
                $this->line("   [{$i['level']}] row {$i['row']}: {$i['reason']}");
            }
            if (count($b->issues ?? []) > 15) {
                $this->line('   … full list on Admin → Data import (Issues CSV).');
            }
        }
        if (! $any) {
            $this->warn('No recognizable files. Expected customers.csv, items.csv, receivables.csv / invoices.csv, work_orders.csv.');
        }

        return self::SUCCESS;
    }
}
