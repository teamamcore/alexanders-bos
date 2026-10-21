<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.7 — Parts & Inventory, Billable Equipment, Fleet: the detail the
// three workflows need beyond the base registers.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $t) {
            $t->string('photo_path')->nullable()->after('nicknames');
            $t->string('unit')->default('each')->after('price');
            $t->decimal('cost', 12, 2)->nullable()->after('unit');           // last purchase / receipt cost
            $t->boolean('is_one_off')->default(false)->after('reorder_threshold'); // custom item from a receipt
            $t->foreignId('job_id')->nullable()->after('is_one_off')->constrained('jobs_records')->nullOnDelete();
            $t->text('notes')->nullable()->after('job_id');
        });

        Schema::table('stock_adjustments', function (Blueprint $t) {
            $t->foreignId('work_order_id')->nullable()->after('part_id')->constrained()->nullOnDelete();
            $t->string('notes')->nullable()->after('reason');
        });

        // Check on delivery: ordered vs received vs damaged, photo, linked to job/item.
        Schema::create('delivery_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('part_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reference')->nullable();     // PO / order number
            $t->integer('qty_ordered');
            $t->integer('qty_received');
            $t->integer('qty_damaged')->default(0);
            $t->string('photo_path')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('equipment', function (Blueprint $t) {
            $t->date('purchased_at')->nullable()->after('purchase_cost');
            $t->unsignedTinyInteger('useful_life_years')->nullable()->after('purchased_at');
            $t->decimal('salvage_value', 12, 2)->nullable()->after('useful_life_years');
            $t->string('status')->default('available')->after('salvage_value'); // available|checked_out|down
            $t->foreignId('checked_out_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $t->timestamp('checked_out_at')->nullable()->after('checked_out_to');
            $t->text('notes')->nullable()->after('checked_out_at');
        });

        Schema::table('equipment_maintenance', function (Blueprint $t) {
            $t->decimal('hours_at_service', 10, 2)->nullable()->after('performed_at');
            $t->foreignId('logged_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        // Equipment billed on a work order → usage + revenue per item.
        Schema::create('equipment_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->decimal('quantity', 10, 2);             // in billing_unit
            $t->string('unit');
            $t->decimal('rate', 12, 2);                 // snapshot of billing_rate
            $t->decimal('amount', 12, 2);
            $t->date('used_on')->nullable();
            $t->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('vehicles', function (Blueprint $t) {
            $t->string('name')->nullable()->after('tenant_id');             // unit number / nickname
            $t->foreignId('assigned_user_id')->nullable()->after('assigned_crew_id')->constrained('users')->nullOnDelete();
            $t->decimal('purchase_cost', 12, 2)->nullable()->after('insurance_due');
            $t->date('purchased_at')->nullable()->after('purchase_cost');
            $t->unsignedTinyInteger('useful_life_years')->nullable()->after('purchased_at');
            $t->decimal('salvage_value', 12, 2)->nullable()->after('useful_life_years');
            $t->string('status')->default('in_service')->after('salvage_value'); // in_service|down|retired
        });

        Schema::table('vehicle_maintenance', function (Blueprint $t) {
            $t->string('service_item')->nullable()->after('type'); // oil_change|tires|inspection|…
            $t->text('notes')->nullable()->after('odometer');
            $t->foreignId('logged_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        Schema::create('mileage_readings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->integer('odometer');
            $t->date('recorded_at');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Field issue / damage reports for equipment and vehicles.
        Schema::create('asset_issues', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->morphs('issuable');                     // Equipment | Vehicle
            $t->string('severity')->default('minor');   // minor|major
            $t->text('description');
            $t->string('photo_path')->nullable();
            $t->string('status')->default('open');      // open|resolved
            $t->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_issues');
        Schema::dropIfExists('mileage_readings');
        Schema::table('vehicle_maintenance', fn (Blueprint $t) => $t->dropColumn(['service_item', 'notes', 'logged_by']));
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn(['name', 'assigned_user_id', 'purchase_cost', 'purchased_at', 'useful_life_years', 'salvage_value', 'status']));
        Schema::dropIfExists('equipment_usages');
        Schema::table('equipment_maintenance', fn (Blueprint $t) => $t->dropColumn(['hours_at_service', 'logged_by']));
        Schema::table('equipment', fn (Blueprint $t) => $t->dropColumn(['purchased_at', 'useful_life_years', 'salvage_value', 'status', 'checked_out_to', 'checked_out_at', 'notes']));
        Schema::dropIfExists('delivery_checks');
        Schema::table('stock_adjustments', fn (Blueprint $t) => $t->dropColumn(['work_order_id', 'notes']));
        Schema::table('parts', fn (Blueprint $t) => $t->dropColumn(['photo_path', 'unit', 'cost', 'is_one_off', 'job_id', 'notes']));
    }
};
