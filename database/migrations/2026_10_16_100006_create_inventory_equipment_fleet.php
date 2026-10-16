<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.7 — Parts & Inventory, Billable Equipment, Fleet.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('parts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('sku')->nullable();
            $t->string('category')->nullable();
            $t->json('nicknames')->nullable();        // crew names/abbreviations (§3.7)
            $t->decimal('price', 12, 2)->default(0);  // manually editable (§3.7)
            $t->integer('stock_qty')->default(0);
            $t->integer('reorder_threshold')->nullable();
            $t->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('part_id')->constrained()->cascadeOnDelete();
            $t->integer('quantity');
            $t->string('reason'); // not_used | billable_not_used | breakage
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('equipment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type')->nullable();            // config('batsons.equipment_types')
            $t->decimal('billing_rate', 12, 2)->default(0);
            $t->string('billing_unit')->default('hour'); // hour|day|foot
            $t->decimal('hours_used', 10, 2)->default(0);
            $t->json('maintenance_schedule')->nullable(); // by hours, date, or both
            $t->decimal('purchase_cost', 12, 2)->nullable();
            $t->timestamps();
        });

        Schema::create('equipment_maintenance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $t->string('type')->nullable();            // maintenance|repair|parts|rental
            $t->decimal('cost', 12, 2)->default(0);
            $t->date('performed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->integer('year')->nullable();
            $t->string('make')->nullable();
            $t->string('model')->nullable();
            $t->string('vin')->nullable();
            $t->string('plate')->nullable();
            $t->foreignId('assigned_crew_id')->nullable()->constrained('crews')->nullOnDelete();
            $t->integer('mileage')->default(0);
            $t->json('service_intervals')->nullable(); // oil/tires/inspection by mi or date
            $t->date('registration_due')->nullable();
            $t->date('inspection_due')->nullable();
            $t->date('insurance_due')->nullable();
            $t->timestamps();
        });

        Schema::create('vehicle_maintenance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->string('type')->nullable();            // maintenance|repair|fuel
            $t->decimal('cost', 12, 2)->default(0);
            $t->date('performed_at')->nullable();
            $t->integer('odometer')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['vehicle_maintenance','vehicles','equipment_maintenance','equipment','stock_adjustments','parts','suppliers'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
