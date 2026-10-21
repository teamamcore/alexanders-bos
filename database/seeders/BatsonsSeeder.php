<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Gate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Part;
use App\Models\RecurringService;
use App\Models\ServiceLocation;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vehicle;
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

        // Equipment catalog from the configured types (SOW §3.2), with the rates,
        // schedules and purchase info the §3.7 register needs.
        $equipmentSeed = [
            'excavator' => ['rate' => 185, 'unit' => 'hour', 'cost' => 68000, 'life' => 10, 'salvage' => 12000, 'hours' => 412, 'every_hours' => 250],
            'dump_truck' => ['rate' => 95, 'unit' => 'hour', 'cost' => 54000, 'life' => 10, 'salvage' => 9000, 'hours' => 1210, 'every_hours' => 300],
            'compactor' => ['rate' => 60, 'unit' => 'day', 'cost' => 4200, 'life' => 6, 'salvage' => 400, 'hours' => 0, 'every_days' => 180],
            'jetter' => ['rate' => 150, 'unit' => 'hour', 'cost' => 32000, 'life' => 8, 'salvage' => 4000, 'hours' => 265, 'every_hours' => 100],
            'sewer_machine' => ['rate' => 75, 'unit' => 'hour', 'cost' => 6500, 'life' => 7, 'salvage' => 500, 'hours' => 98, 'every_hours' => 50],
            'video_inspection' => ['rate' => 2.5, 'unit' => 'foot', 'cost' => 14500, 'life' => 5, 'salvage' => 1000, 'hours' => 0, 'every_days' => 365],
            'concrete_saw' => ['rate' => 45, 'unit' => 'hour', 'cost' => 1900, 'life' => 5, 'salvage' => 100, 'hours' => 40, 'every_hours' => 25],
        ];
        foreach (config('batsons.equipment_types') as $type) {
            $s = $equipmentSeed[$type] ?? ['rate' => 0, 'unit' => 'hour', 'cost' => null, 'life' => null, 'salvage' => null, 'hours' => 0];
            Equipment::create([
                'name' => ucwords(str_replace('_', ' ', $type)),
                'type' => $type,
                'billing_rate' => $s['rate'],
                'billing_unit' => $s['unit'],
                'hours_used' => $s['hours'],
                'purchase_cost' => $s['cost'],
                'purchased_at' => $s['cost'] ? now()->subYears(2)->subMonths(3) : null,
                'useful_life_years' => $s['life'],
                'salvage_value' => $s['salvage'],
                'maintenance_schedule' => array_filter(['every_hours' => $s['every_hours'] ?? null, 'every_days' => $s['every_days'] ?? null]) ?: null,
            ]);
        }

        // Default gates (SOW §3.2) — required steps before a WO advances.
        Gate::create(['status' => 'complete', 'name' => 'Completion gate',
            'required_actions' => ['photos', 'parts_entry', 'customer_sign_off']]);

        // A sample customer → site → job → work order so screens have data.
        $customer = Customer::create(['name' => 'Acme Industrial Park', 'notes' => 'Net 30',
            'phone' => '(555) 214-7788', 'email' => 'facilities@acme-industrial.example']);
        Contact::create(['customer_id' => $customer->id, 'name' => 'Pat Morales', 'role' => 'Facilities manager',
            'phone' => '(555) 214-7790', 'email' => 'pat.morales@acme-industrial.example']);
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

        // Recurring service (SOW §3.10): Acme's RPZ is due in 50 days → inside the 45–60 day
        // window, so the Reminders page has something to work; a water heater a few months out.
        $rpz = RecurringService::create([
            'customer_id' => $customer->id, 'service_location_id' => $site->id, 'type' => 'backflow_test', 'label' => 'RPZ #1 — mech room',
            'interval_months' => 12, 'lead_days_min' => 45, 'lead_days_max' => 60, 'auto_message' => false,
            'last_serviced_at' => now()->addDays(50)->subYear(), 'next_due_at' => now()->addDays(50),
        ]);
        $rpz->reminders()->create(['customer_id' => $customer->id, 'service_location_id' => $site->id, 'type' => 'backflow_test',
            'due_at' => $rpz->next_due_at, 'remind_at' => $rpz->remindAt(), 'contact_by' => $rpz->contactBy(), 'status' => 'pending', 'auto_message' => false]);
        $wh = RecurringService::create([
            'customer_id' => $customer->id, 'service_location_id' => $site->id, 'type' => 'water_heater_service', 'label' => 'Break-room 50-gal',
            'interval_months' => 12, 'lead_days_min' => 7, 'lead_days_max' => 30, 'auto_message' => true,
            'last_serviced_at' => now()->subMonths(8), 'next_due_at' => now()->addMonths(4),
        ]);
        $wh->reminders()->create(['customer_id' => $customer->id, 'service_location_id' => $site->id, 'type' => 'water_heater_service',
            'due_at' => $wh->next_due_at, 'remind_at' => $wh->remindAt(), 'contact_by' => $wh->contactBy(), 'status' => 'pending', 'auto_message' => true]);

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

        $eMarcus = Employee::create(['user_id' => $marcus->id, 'name' => 'Marcus Reyes', 'title' => 'Lead plumber', 'hourly_rate' => 42.00,
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

        $eDana = Employee::create(['user_id' => $dana->id, 'name' => 'Dana Whitfield', 'title' => 'Service technician', 'hourly_rate' => 34.00,
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

        // Parts & inventory (SOW §3.7): suppliers + a small catalog with crew nicknames.
        $gateway = Supplier::create(['name' => 'Gateway Supplies']);
        $tms = Supplier::create(['name' => 'TMS']);
        foreach ([
            ['Gateway', '3/4" Brass Ball Valve', 'BV-34', 'Valves', ['3/4 ball', 'BV34'], 18.40, 11.20, 14, 6],
            ['Gateway', '1/2" Copper 90° Elbow', 'CU-90-12', 'Fittings', ['half-inch 90', 'copper L'], 1.85, 0.92, 120, 40],
            ['Gateway', '3" PVC DWV Pipe (10 ft)', 'PVC-3-10', 'Pipe', ['3 inch PVC', 'DWV 3'], 24.00, 15.50, 4, 6],
            ['TMS', 'Backflow Repair Kit — Watts 009 3/4"', 'WT-009-RK34', 'Backflow', ['009 kit', 'watts kit'], 96.00, 61.00, 2, 2],
            ['TMS', '24" Pipe Wrench', 'PW-24', 'Tools', ['the big wrench', 'big red'], 68.00, 41.00, 3, 1],
            ['TMS', 'Wax Ring w/ Horn', 'WAX-H', 'Fixtures', ['wax', 'johnny ring'], 6.25, 2.90, 22, 10],
        ] as [$sup, $name, $sku, $cat, $nicks, $price, $cost, $qty, $reorder]) {
            Part::create([
                'supplier_id' => $sup === 'Gateway' ? $gateway->id : $tms->id,
                'name' => $name, 'sku' => $sku, 'category' => $cat, 'nicknames' => $nicks,
                'price' => $price, 'cost' => $cost, 'stock_qty' => $qty, 'reorder_threshold' => $reorder,
            ]);
        }

        // Fleet (SOW §3.7): one truck with service intervals, a stale mileage reading, and an inspection due.
        $truck = Vehicle::create([
            'name' => 'Truck 3', 'year' => 2021, 'make' => 'Ford', 'model' => 'F-250', 'vin' => '1FT7W2BT5MED12345', 'plate' => 'PLB-4471',
            'assigned_user_id' => $marcus->id, 'mileage' => 61240,
            'service_intervals' => [
                ['name' => 'oil_change', 'every_miles' => 5000, 'every_months' => 6],
                ['name' => 'tires', 'every_miles' => 40000, 'every_months' => null],
                ['name' => 'inspection', 'every_miles' => null, 'every_months' => 12],
            ],
            'registration_due' => now()->addMonths(4), 'inspection_due' => now()->addDays(12), 'insurance_due' => now()->addMonths(7),
            'purchase_cost' => 48500, 'purchased_at' => '2021-05-14', 'useful_life_years' => 8, 'salvage_value' => 9000,
        ]);
        $truck->readings()->create(['odometer' => 61240, 'recorded_at' => now()->subDays(45), 'recorded_by' => $marcus->id]);
        $truck->maintenance()->create(['type' => 'maintenance', 'service_item' => 'oil_change', 'cost' => 84.00, 'performed_at' => now()->subMonths(4), 'odometer' => 57900]);
        $truck->maintenance()->create(['type' => 'fuel', 'cost' => 142.30, 'performed_at' => now()->subDays(9), 'odometer' => 61240]);
        $truck->maintenance()->create(['type' => 'repair', 'cost' => 410.00, 'performed_at' => now()->subMonths(2), 'odometer' => 59800, 'notes' => 'Brake pads + rotors (front)']);

        $this->seedHistory($tenant, $customer, $site, $marcus, $dana);
    }

    /**
     * SOW §3.9 — six months of completed, invoiced work so A/R aging, job costing,
     * profitability by type and the trends chart have real shape. Deterministic.
     */
    private function seedHistory(Tenant $tenant, Customer $acme, ServiceLocation $acmeSite, User $marcus, User $dana): void
    {
        $riverside = Customer::create(['name' => 'Riverside Dental', 'phone' => '(555) 201-3344', 'email' => 'office@riverside-dental.example', 'payment_terms' => 'net_15']);
        $riversideSite = ServiceLocation::create(['customer_id' => $riverside->id, 'label' => 'Clinic', 'address' => '88 River Rd', 'type' => 'job']);
        $hoa = Customer::create(['name' => 'Oak Street HOA', 'phone' => '(555) 318-9900', 'payment_terms' => 'due_on_receipt']);
        $hoaSite = ServiceLocation::create(['customer_id' => $hoa->id, 'label' => 'Clubhouse', 'address' => '410 Oak St', 'type' => 'job']);

        $jetter = Equipment::where('type', 'jetter')->first();
        $camera = Equipment::where('type', 'video_inspection')->first();
        $valve = Part::where('sku', 'BV-34')->first();
        $kit = Part::where('sku', 'WT-009-RK34')->first();
        $pvc = Part::where('sku', 'PVC-3-10')->first();

        // [months ago, customer, site, type, title, tech, hours(regular), parts [part, qty], equipment [item, qty], invoice total, payment: paid|open|overdue|collections]
        $history = [
            [6, $acme, $acmeSite, 'backflow_testing', 'Annual backflow test — RPZ #1', $marcus, 3.0, [[$kit, 1]], [], 685.00, 'paid'],
            [5, $riverside, $riversideSite, 'sewer', 'Main line stoppage', $dana, 4.5, [], [[$jetter, 2.0]], 1180.00, 'paid'],
            [5, $hoa, $hoaSite, 'water_heater', 'Replace 50-gal water heater', $marcus, 5.0, [[$valve, 2], [$pvc, 1]], [], 2450.00, 'paid'],
            [4, $acme, $acmeSite, 'camera_inspection', 'Camera inspect building C lateral', $dana, 2.0, [], [[$camera, 120.0]], 540.00, 'paid'],
            [3, $riverside, $riversideSite, 'sewer', 'Recurring stoppage — jet + camera', $dana, 6.0, [[$pvc, 2]], [[$jetter, 3.0], [$camera, 80.0]], 1960.00, 'collections'],
            [3, $hoa, $hoaSite, 'excavation', 'Dig + repair irrigation main', $marcus, 9.0, [[$pvc, 4], [$valve, 3]], [], 4100.00, 'paid'],
            [2, $acme, $acmeSite, 'water_heater', 'Break-room water heater service', $marcus, 1.5, [], [], 285.00, 'overdue'],
            [1, $riverside, $riversideSite, 'backflow_testing', 'Backflow test — clinic', $marcus, 2.0, [[$kit, 1]], [], 640.00, 'open'],
            [1, $hoa, $hoaSite, 'camera_inspection', 'Pool house drain inspection', $dana, 2.5, [], [[$camera, 60.0]], 480.00, 'open'],
            [0, $acme, $acmeSite, 'sewer', 'Loading dock drain backing up', $dana, 3.0, [], [[$jetter, 1.5]], 890.00, 'open'],
        ];

        $n = 2000;
        foreach ($history as [$monthsAgo, $cust, $site, $type, $title, $tech, $hours, $parts, $equip, $total, $payment]) {
            $n++;
            $day = now()->subMonths($monthsAgo)->startOfMonth()->addDays(9)->setTime(8, 30); // the 10th, 8:30am
            $job = Job::create(['customer_id' => $cust->id, 'service_location_id' => $site->id, 'number' => "J-{$n}", 'title' => $title, 'status' => 'closed']);
            $wo = WorkOrder::create(['job_id' => $job->id, 'number' => "WO-{$n}", 'status' => 'complete', 'work_type' => $type,
                'customer_notes' => $title, 'assigned_to' => $tech->id, 'scheduled_date' => $day->toDateString(), 'schedule_window' => 'morning']);
            WorkOrder::withoutGlobalScopes()->whereKey($wo->id)->update(['created_at' => $day, 'updated_at' => $day->copy()->addHours((int) ceil($hours))]);

            $te = TimeEntry::create(['tenant_id' => $tenant->id, 'user_id' => $tech->id, 'work_order_id' => $wo->id, 'job_id' => $job->id,
                'category' => 'on_site', 'billable' => true, 'started_at' => $day, 'ended_at' => $day->copy()->addMinutes((int) round($hours * 60))]);
            TimeEntry::withoutGlobalScopes()->whereKey($te->id)->update(['created_at' => $day, 'updated_at' => $day]);

            foreach ($parts as [$part, $qty]) {
                if ($part) {
                    $adj = $part->stockAdjustments()->create(['quantity' => -$qty, 'reason' => 'used_on_job', 'work_order_id' => $wo->id, 'created_by' => $tech->id]);
                    StockAdjustment::withoutGlobalScopes()->whereKey($adj->id)->update(['created_at' => $day, 'updated_at' => $day]);
                }
            }
            foreach ($equip as [$item, $qty]) {
                if ($item) {
                    $item->usages()->create(['work_order_id' => $wo->id, 'quantity' => $qty, 'unit' => $item->billing_unit, 'rate' => $item->billing_rate,
                        'amount' => round($qty * (float) $item->billing_rate, 2), 'used_on' => $day->toDateString(), 'logged_by' => $tech->id]);
                }
            }

            $terms = $cust->payment_terms ?? 'net_30'; // DB default isn't loaded on a fresh model
            $termsDays = (int) (config('batsons.payment_terms')[$terms] ?? 30);
            $issued = $day->copy()->addDay()->startOfDay();
            $inv = Invoice::create(['work_order_id' => $wo->id, 'customer_id' => $cust->id, 'number' => "INV-{$n}", 'total' => $total,
                'terms' => $terms, 'is_progress_payment' => false,
                'status' => $payment === 'paid' ? 'paid' : 'approved',
                'issued_at' => $issued, 'due_at' => $issued->copy()->addDays($termsDays),
                'paid_at' => $payment === 'paid' ? $issued->copy()->addDays(max(1, $termsDays - 4)) : null,
                'qb_synced_at' => $issued->copy()->addHours(2),
            ]);
            Invoice::withoutGlobalScopes()->whereKey($inv->id)->update(['created_at' => $issued, 'updated_at' => $issued]);
        }
    }
}
