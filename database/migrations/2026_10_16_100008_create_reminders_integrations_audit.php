<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.10 Reminders, §3.14 Vendor Portals, + cross-cutting audit log.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type');                  // backflow_test|water_heater_service|...
            $t->date('due_at');
            $t->boolean('auto_message')->default(false);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('vendor_portal_integrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');                  // service_channel|paramark|...
            $t->string('status')->default('planned'); // planned|building|live|blocked
            $t->date('go_live_at')->nullable();
            $t->boolean('counts_toward_included')->default(true); // §3.14: blocked ones don't
            $t->timestamps();
        });

        // Cross-cutting append-only audit trail (App\Models\Concerns\Auditable).
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable();
            $t->foreignId('user_id')->nullable();
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id');
            $t->string('action');                // created|updated|deleted
            $t->json('changes')->nullable();
            $t->timestamps();
            $t->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs','vendor_portal_integrations','reminders'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
