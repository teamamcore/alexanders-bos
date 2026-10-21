<?php

declare(strict_types=1);

namespace App\Platform\Export;

use App\Models\AssetIssue;
use App\Models\AuditLog;
use App\Models\CallTranscript;
use App\Models\Certification;
use App\Models\Contact;
use App\Models\Crew;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\DeliveryCheck;
use App\Models\Employee;
use App\Models\EmployeeMilestone;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentUsage;
use App\Models\Gate;
use App\Models\GateOverride;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\MessageTemplate;
use App\Models\MileageReading;
use App\Models\Part;
use App\Models\Proposal;
use App\Models\Qualification;
use App\Models\Reminder;
use App\Models\ScheduleChange;
use App\Models\ServiceLocation;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Models\VendorPortalIntegration;
use App\Models\WorkOrder;
use App\Models\WorkOrderUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Produces a clean, self-contained export of ONE client build:
 *   • Client Data (all tenant-scoped rows) as CSV
 *   • the Client layer code/config (Client Deliverables)
 *   • a MANIFEST + README stating what is included and what is deliberately excluded
 *
 * Contract basis: MSA §2.8 (third-party maintenance / code access), §6.1 (Client
 * Deliverables + Client Data ownership), §11.3 (Data and Code export), §11.4
 * (business continuity). Platform core ("AmCore Technology", §6.2) and shared
 * infrastructure / subprocessor credentials are intentionally NOT exported.
 *
 * The same bundle is the fork point for a new customer (see Console\Commands\ClientFork).
 */
class ClientExporter
{
    /** Tenant-scoped Client Data models. */
    public const CLIENT_DATA = [
        User::class,
        Customer::class,
        Contact::class,
        ServiceLocation::class,
        Job::class,
        WorkOrder::class,
        WorkOrderUpdate::class,
        Gate::class,
        GateOverride::class,
        Crew::class,
        ScheduleChange::class,
        Proposal::class,
        Invoice::class,
        Supplier::class,
        Part::class,
        StockAdjustment::class,
        Equipment::class,
        EquipmentMaintenance::class,
        Vehicle::class,
        VehicleMaintenance::class,
        TimeEntry::class,
        Employee::class,
        EmployeeMilestone::class,
        Certification::class,
        Qualification::class,
        Reminder::class,
        VendorPortalIntegration::class,
        DeliveryCheck::class,
        EquipmentUsage::class,
        MileageReading::class,
        AssetIssue::class,
        MessageTemplate::class,
        CustomerMessage::class,
        CallTranscript::class,
        AuditLog::class,
    ];

    /** Columns never exported (credentials — not Client Data). */
    public const REDACT = ['password', 'remember_token'];

    /** @return string absolute path to the written .zip */
    public function export(Tenant $tenant): string
    {
        $slug = $tenant->clientKey();
        $ts = now()->format('Ymd-His');
        $base = storage_path("app/exports/{$slug}-{$ts}");
        File::ensureDirectoryExists($base.'/data');
        File::ensureDirectoryExists($base.'/code');
        File::ensureDirectoryExists($base.'/config');

        $manifest = [
            'client' => $tenant->name,
            'tenant_id' => $tenant->id,
            'exported_at' => now()->toIso8601String(),
            'contract_basis' => 'MSA §2.8, §6.1, §11.3, §11.4',
            'excluded' => [
                'AmCore Technology — platform core / reusable engine (MSA §6.2)',
                'Shared infrastructure, subprocessor credentials, other customers\' data (MSA §2.8)',
            ],
            'data' => [],
            'code' => [],
        ];

        // 1) Client Data → CSV, one file per table, tenant-scoped.
        foreach (self::CLIENT_DATA as $model) {
            $table = (new $model)->getTable();
            $rows = $model::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
            $this->writeCsv($base."/data/{$table}.csv", $rows);
            $manifest['data'][$table] = $rows->count();
        }
        // The tenant record itself.
        $this->writeCsv($base.'/data/tenants.csv', collect([$tenant]));
        $manifest['data']['tenants'] = 1;

        // 2) Client layer code + config (Client Deliverables).
        $clientConfig = config_path("clients/{$slug}.php");
        if (File::exists($clientConfig)) {
            File::copy($clientConfig, $base."/config/{$slug}.php");
            $manifest['code'][] = "config/clients/{$slug}.php";
        }
        $clientCodeDir = app_path('Client/'.Str::studly($slug));
        if (File::isDirectory($clientCodeDir)) {
            File::copyDirectory($clientCodeDir, $base.'/code/'.Str::studly($slug));
            $manifest['code'][] = 'app/Client/'.Str::studly($slug);
        }

        // 3) Manifest + README.
        File::put($base.'/MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($base.'/README.md', $this->readme($tenant, $manifest));

        // 4) Zip the bundle.
        $zipPath = storage_path("app/exports/{$slug}-{$ts}.zip");
        $this->zipDir($base, $zipPath);

        return $zipPath;
    }

    private function writeCsv(string $path, Collection $rows): void
    {
        $fh = fopen($path, 'w');
        if ($first = $rows->first()) {
            $cols = array_values(array_diff(array_keys($first->getAttributes()), self::REDACT));
            fputcsv($fh, $cols);
            foreach ($rows as $row) {
                $attrs = $row->getAttributes();
                fputcsv($fh, array_map(fn ($c) => $attrs[$c] ?? null, $cols));
            }
        }
        fclose($fh);
    }

    private function zipDir(string $dir, string $zipPath): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $root = rtrim($dir, '/').'/';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($root)));
        }
        $zip->close();
    }

    private function readme(Tenant $tenant, array $manifest): string
    {
        $tables = collect($manifest['data'])->map(fn ($n, $t) => "- `$t.csv` — $n rows")->implode("\n");

        return <<<MD
        # {$tenant->name} — Build Export

        Generated {$manifest['exported_at']}.

        This is a **Client Deliverables + Client Data** export per MSA §2.8, §6.1,
        §11.3, and §11.4. It is sufficient for Client (or a Client-authorized
        third-party contractor) to operate and maintain this deployment.

        ## Included
        - `data/` — all Client Data, one CSV per table:
        {$tables}
        - `config/` — this client's configuration layer.
        - `code/` — this client's custom deliverables (if any).
        - `MANIFEST.json` — machine-readable contents.

        ## Deliberately NOT included (MSA §6.2 / §2.8)
        - **AmCore Technology** — the reusable platform core / engine.
        - Shared infrastructure, subprocessor credentials, and any other customer's data.

        ## To fork this into a new customer build
        Load the client config + structural data (gates, equipment catalog, pay
        rules) into a fresh tenant, then re-brand and re-configure. See
        `php artisan client:fork`.
        MD;
    }
}
