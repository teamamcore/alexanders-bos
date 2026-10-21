<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Platform\Export\ClientExporter;
use Illuminate\Console\Command;

class ClientExport extends Command
{
    protected $signature = 'client:export {tenant? : Tenant id, or part of its name}';

    protected $description = "Export a client's Deliverables + Client Data as a clean, forkable bundle (MSA §2.8/§11.3).";

    public function handle(ClientExporter $exporter): int
    {
        $tenant = $this->resolveTenant($this->argument('tenant'));

        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found. Pass an id or name: php artisan client:export batsons');

            return self::FAILURE;
        }

        $this->info("Exporting “{$tenant->name}” (tenant #{$tenant->id})…");
        $zip = $exporter->export($tenant);
        $this->newLine();
        $this->info('Export bundle written to:');
        $this->line("  {$zip}");

        return self::SUCCESS;
    }

    private function resolveTenant(string|int|null $arg): ?Tenant
    {
        if ($arg === null || $arg === '') {
            return Tenant::query()->oldest('id')->first();
        }

        return is_numeric($arg)
            ? Tenant::find((int) $arg)
            : Tenant::where('name', 'like', "%{$arg}%")->first();
    }
}
