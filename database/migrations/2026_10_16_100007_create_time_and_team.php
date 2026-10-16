<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.8 Time & Pay, §3.11 Team Records & Qualifications.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_id')->nullable()->constrained('jobs_records')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->default('on_site'); // travel|on_site|shop
            $t->boolean('billable')->default(true);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            // Adjustment audit (§3.8): who/prior value/when. Full trail in audit_logs.
            $t->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('prior_value')->nullable();
            $t->timestamp('adjusted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            // Background check: STATUS + DATE only; supporting docs admin-restricted,
            // never stored here (MSA Ex. B.2 / B.5(g)).
            $t->string('background_check_status')->nullable();
            $t->date('background_check_date')->nullable();
            $t->timestamps();
        });

        Schema::create('certifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->date('expires_at')->nullable();  // expiration alerts (§3.11)
            $t->timestamps();
        });

        // §3.11 Qualification values per work type → feed scheduling indicators (§3.3).
        Schema::create('qualifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('work_type');             // backflow|sewer|camera|...
            $t->integer('value')->default(0);    // levels set during discovery
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['qualifications','certifications','employees','time_entries'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
