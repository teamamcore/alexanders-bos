<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.9 — Reporting & Job Costing: the dates and terms A/R aging needs,
// and the pay rate labor costing needs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->foreignId('customer_id')->nullable()->after('work_order_id')->constrained()->nullOnDelete();
            $t->string('terms')->default('net_30')->after('total');  // due_on_receipt|net_15|net_30
            $t->date('issued_at')->nullable()->after('terms');        // set on approval
            $t->date('due_at')->nullable()->after('issued_at');
            $t->date('paid_at')->nullable()->after('due_at');
        });

        Schema::table('customers', function (Blueprint $t) {
            $t->string('payment_terms')->default('net_30')->after('preferred_channel');
        });

        Schema::table('employees', function (Blueprint $t) {
            $t->decimal('hourly_rate', 8, 2)->nullable()->after('hired_at'); // base pay; burden applied in costing
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('hourly_rate'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('payment_terms'));
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_id');
            $t->dropColumn(['terms', 'issued_at', 'due_at', 'paid_at']);
        });
    }
};
