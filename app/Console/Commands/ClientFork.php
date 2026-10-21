<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\Gate;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Fork an existing client build into a NEW customer: copies the reusable
 * structure (tenant settings, gates, equipment catalog) and scaffolds a fresh
 * client config layer — but NO transactional Client Data. This is the reuse
 * path under MSA §6.2 (AmCore may reuse platform components for other customers).
 */
class ClientFork extends Command
{
    protected $signature = 'client:fork {source : source tenant id or name} {name : new client display name}';

    protected $description = 'Fork a client build into a new customer (structure + config, no data) — MSA §6.2 reuse.';

    public function handle(): int
    {
        $source = is_numeric($this->argument('source'))
            ? Tenant::find((int) $this->argument('source'))
            : Tenant::where('name', 'like', '%'.$this->argument('source').'%')->first();

        if (! $source) {
            $this->error('Source tenant not found.');

            return self::FAILURE;
        }

        $name = $this->argument('name');
        $slug = Str::slug($name);

        $new = Tenant::create([
            'name' => $name,
            'trade' => $source->trade,
            // reusable config (pay rules, etc.), but its OWN client key
            'settings' => array_merge((array) $source->settings, ['client_key' => $slug]),
        ]);
        app()->instance('currentTenantId', $new->id);

        // Copy reusable STRUCTURE only — not customers/jobs/invoices.
        $gates = 0;
        foreach (Gate::withoutGlobalScopes()->where('tenant_id', $source->id)->get() as $g) {
            Gate::create(['status' => $g->status, 'name' => $g->name, 'required_actions' => $g->required_actions]);
            $gates++;
        }
        $equip = 0;
        foreach (Equipment::withoutGlobalScopes()->where('tenant_id', $source->id)->get() as $e) {
            Equipment::create([
                'name' => $e->name, 'type' => $e->type,
                'billing_rate' => 0, 'billing_unit' => $e->billing_unit,
                'maintenance_schedule' => $e->maintenance_schedule,
            ]);
            $equip++;
        }

        // Scaffold the new client config layer.
        $path = config_path("clients/{$slug}.php");
        File::ensureDirectoryExists(dirname($path));
        if (! File::exists($path)) {
            File::put($path, $this->configStub($slug, $name, (string) $source->trade));
        }

        $this->info("Forked “{$source->name}” → new client “{$name}” (tenant #{$new->id}).");
        $this->line("  copied: {$gates} gate(s), {$equip} equipment item(s)");
        $this->line("  config: config/clients/{$slug}.php");
        $this->line('  next:   seed users/customers, then `php artisan client:export '.$slug.'`');

        return self::SUCCESS;
    }

    private function configStub(string $slug, string $name, string $trade): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        // CLIENT LAYER — {$name} (forked). Client Deliverables, MSA §6.1.
        return [
            'key' => '{$slug}',
            'display_name' => '{$name}',
            'trade' => '{$trade}',
            'overrides' => [],
            'branding' => ['primary_color' => '#1f6feb', 'terminology' => []],
        ];
        PHP;
    }
}
