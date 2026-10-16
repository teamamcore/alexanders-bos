<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.5 Proposals & §3.6 Invoicing. NOTE: no card/bank fields (MSA Ex. B.2).
return new class extends Migration {
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('stage')->default('draft'); // draft|sent|follow_up|won|lost
            $t->decimal('total', 12, 2)->default(0);
            $t->json('line_items')->nullable();     // labor + materials + equipment
            $t->timestamp('follow_up_at')->nullable();
            $t->foreignId('converted_job_id')->nullable()->constrained('jobs_records')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number')->nullable();       // paired with work order (§3.6)
            $t->string('status')->default('draft'); // draft|approved|sent|paid|void
            $t->decimal('total', 12, 2)->default(0);
            $t->boolean('is_progress_payment')->default(false);
            $t->timestamp('qb_synced_at')->nullable();   // QuickBooks sync (§3.6)
            $t->timestamp('voided_at')->nullable();
            // Payment STATUS only — never card/bank numbers (MSA Ex. B.2).
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('proposals');
    }
};
