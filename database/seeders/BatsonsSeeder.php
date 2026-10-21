<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Gate;
use App\Models\Job;
use App\Models\ServiceLocation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BatsonsSeeder extends Seeder
{
    public function run(): void
    {
        // `migrate:fresh --seed` unsets the model event dispatcher before seeding,
        // which would skip our BelongsToTenant/Auditable model hooks. Restore it.
        Model::setEventDispatcher(app('events'));

        // Founding client tenant (SOW §1, §7).
        $tenant = Tenant::create([
            'name' => 'Batsons Plumbing, Inc.',
            'trade' => 'Plumbing — commercial, industrial, institutional, residential (T&M)',
            'settings' => [
                'client_key' => 'batsons', // → config/clients/batsons.php, app/Client/Batsons
                'included_users' => config('batsons.included_users'),
                'pay_rules' => config('batsons.pay_rules'),
            ],
        ]);

        // Bind the tenant so BelongsToTenant auto-fills tenant_id below.
        app()->instance('currentTenantId', $tenant->id);

        // Administrator user (SOW §9 — Eric White, project lead).
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Eric White',
            'email' => 'eric@batsonsplumbing.com',
            'password' => Hash::make('password'),
            'role' => Role::Administrator,
        ]);

        // Equipment catalog from the configured types (SOW §3.2).
        foreach (config('batsons.equipment_types') as $type) {
            Equipment::create([
                'name' => ucwords(str_replace('_', ' ', $type)),
                'type' => $type,
                'billing_rate' => 0,
                'billing_unit' => 'hour',
            ]);
        }

        // Default gates (SOW §3.2) — required steps before a WO advances.
        Gate::create(['status' => 'complete', 'name' => 'Completion gate',
            'required_actions' => ['photos', 'parts_entry', 'customer_sign_off']]);

        // A sample customer → site → job → work order so screens have data.
        $customer = Customer::create(['name' => 'Acme Industrial Park', 'notes' => 'Net 30']);
        $site = ServiceLocation::create([
            'customer_id' => $customer->id,
            'label' => 'Building C',
            'address' => '1200 Industrial Blvd',
            'type' => 'job',
            'access_notes' => 'Badge required at gate; check in with site super.',
        ]);
        $job = Job::create([
            'customer_id' => $customer->id,
            'service_location_id' => $site->id,
            'number' => 'J-1001',
            'title' => 'Backflow assembly test + repair',
            'status' => 'open',
        ]);
        WorkOrder::create([
            'job_id' => $job->id,
            'number' => 'WO-1001',
            'status' => 'new',
            'customer_notes' => 'Annual backflow test due.',
            'schedule_window' => 'morning',
        ]);
    }
}
