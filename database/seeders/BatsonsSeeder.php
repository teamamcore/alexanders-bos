<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
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
        ])->forceFill(['email_verified_at' => now()])->save();

        // AmCore support administrator (prototype access).
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'David Conn',
            'email' => 'david@amcoresystems.com',
            'password' => Hash::make('password'),
            'role' => Role::Administrator,
        ])->forceFill(['email_verified_at' => now()])->save();

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
            'work_type' => 'backflow_testing',
        ]);

        // Team records (SOW §3.11): two field techs with certs + qualification values,
        // so the dispatch board indicators have something to show.
        $marcus = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Marcus Reyes', 'email' => 'marcus@batsonsplumbing.com',
            'password' => Hash::make('password'), 'role' => Role::Field,
        ]);
        $marcus->forceFill(['email_verified_at' => now()])->save();
        $dana = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Dana Whitfield', 'email' => 'dana@batsonsplumbing.com',
            'password' => Hash::make('password'), 'role' => Role::Field,
        ]);
        $dana->forceFill(['email_verified_at' => now()])->save();

        $eMarcus = Employee::create(['user_id' => $marcus->id, 'name' => 'Marcus Reyes', 'title' => 'Lead plumber',
            'hired_at' => '2019-03-04', 'background_check_status' => 'cleared', 'background_check_date' => '2025-01-15']);
        $eMarcus->milestones()->create(['title' => 'Hired', 'occurred_at' => '2019-03-04']);
        $eMarcus->milestones()->create(['title' => 'Promoted to lead', 'occurred_at' => '2023-06-01']);
        $eMarcus->certifications()->create(['name' => 'Backflow Assembly Tester', 'type' => 'certification', 'number' => 'BAT-4471',
            'issued_at' => now()->subYears(3)->addDays(30), 'expires_at' => now()->addDays(30)]);
        $eMarcus->certifications()->create(['name' => 'Journeyman Plumber', 'type' => 'license', 'number' => 'JP-20918',
            'issued_at' => '2020-02-01', 'expires_at' => now()->addMonths(14)]);
        foreach (['backflow_testing' => 3, 'sewer' => 2, 'water_heater' => 2, 'camera_inspection' => 1] as $wt => $lvl) {
            $eMarcus->qualifications()->create(['work_type' => $wt, 'value' => $lvl]);
        }

        $eDana = Employee::create(['user_id' => $dana->id, 'name' => 'Dana Whitfield', 'title' => 'Service technician',
            'hired_at' => '2022-08-15', 'background_check_status' => 'pending']);
        $eDana->milestones()->create(['title' => 'Hired', 'occurred_at' => '2022-08-15']);
        $eDana->certifications()->create(['name' => 'Journeyman Plumber', 'type' => 'license', 'number' => 'JP-31102',
            'issued_at' => '2022-09-01', 'expires_at' => now()->subDays(10)]);
        $eDana->certifications()->create(['name' => 'OSHA 10', 'type' => 'certification', 'issued_at' => '2022-09-20']);
        foreach (['camera_inspection' => 3, 'sewer' => 3, 'backflow_testing' => 1] as $wt => $lvl) {
            $eDana->qualifications()->create(['work_type' => $wt, 'value' => $lvl]);
        }

        Employee::create(['user_id' => User::where('email', 'eric@batsonsplumbing.com')->value('id'), 'name' => 'Eric White',
            'title' => 'Owner / project lead', 'hired_at' => '2008-01-02', 'background_check_status' => 'cleared', 'background_check_date' => '2024-11-01']);
    }
}
