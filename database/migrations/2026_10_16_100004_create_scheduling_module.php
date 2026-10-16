<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.3 — Scheduling & Dispatch (crews) + reschedule history.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('crews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('crew_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        // Reschedule records a reason + keeps history (§3.3).
        Schema::create('schedule_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->date('from_date')->nullable();
            $t->date('to_date')->nullable();
            $t->text('reason')->nullable();
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_changes');
        Schema::dropIfExists('crew_user');
        Schema::dropIfExists('crews');
    }
};
