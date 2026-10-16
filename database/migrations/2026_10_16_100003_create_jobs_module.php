<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.2 — Jobs & Work Orders (+ gates).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_location_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number')->nullable();
            $t->string('title')->nullable();
            $t->string('status')->default('open');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_id')->constrained('jobs_records')->cascadeOnDelete();
            $t->string('number')->nullable();           // paired with invoice (§3.6)
            $t->string('status')->default('new');
            $t->text('customer_notes')->nullable();
            $t->text('internal_notes')->nullable();
            $t->decimal('labor_hours', 8, 2)->default(0);
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('crew_id')->nullable();
            $t->date('scheduled_date')->nullable();
            $t->string('schedule_window')->nullable();  // morning|afternoon|all_day|emergency|timed
            $t->timestamps();
        });

        Schema::create('work_order_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->date('update_date');
            $t->text('body')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // §3.2 Gates — required steps per status, configurable per tenant.
        Schema::create('gates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('status');                 // the WO status this gate guards
            $t->string('name');
            $t->json('required_actions')->nullable(); // photos, parts, notes, sign-off
            $t->timestamps();
        });

        // §3.2 Gate overrides — audited (who/when/why).
        Schema::create('gate_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('gate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('overridden_by')->constrained('users');
            $t->text('reason');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_overrides');
        Schema::dropIfExists('gates');
        Schema::dropIfExists('work_order_updates');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('jobs_records');
    }
};
