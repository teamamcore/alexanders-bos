<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.10 — Recurring Service & Reminders: the schedule per customer/site,
// and the worked reminder (office alerted → customer messaged → job scheduled).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_location_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type');                           // config('batsons.reminder_types') key
            $t->string('label')->nullable();              // e.g. "Backflow — Building C, RPZ #2"
            $t->unsignedSmallInteger('interval_months');
            $t->unsignedSmallInteger('lead_days_min')->default(0);  // contact by (days before due)
            $t->unsignedSmallInteger('lead_days_max')->default(30); // start contacting (days before due)
            $t->boolean('auto_message')->default(false);
            $t->boolean('is_active')->default(true);
            $t->date('last_serviced_at')->nullable();
            $t->date('next_due_at');
            $t->foreignId('source_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'next_due_at']);
        });

        Schema::table('reminders', function (Blueprint $t) {
            $t->foreignId('recurring_service_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $t->foreignId('service_location_id')->nullable()->after('recurring_service_id')->constrained()->nullOnDelete();
            $t->date('remind_at')->nullable()->after('due_at');        // office alert / window opens
            $t->date('contact_by')->nullable()->after('remind_at');    // window closes (lead_days_min)
            $t->string('status')->default('pending')->after('contact_by'); // pending|notified|contacted|scheduled|done|skipped
            $t->timestamp('notified_at')->nullable()->after('status');
            $t->timestamp('contacted_at')->nullable()->after('notified_at');
            $t->string('contact_method')->nullable()->after('contacted_at'); // sms|email|call|in_person
            $t->foreignId('job_id')->nullable()->after('contact_method')->constrained('jobs_records')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->after('job_id')->constrained()->nullOnDelete();
            $t->text('notes')->nullable()->after('work_order_id');
            $t->index(['tenant_id', 'status', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('recurring_service_id');
            $t->dropConstrainedForeignId('service_location_id');
            $t->dropConstrainedForeignId('job_id');
            $t->dropConstrainedForeignId('work_order_id');
            $t->dropColumn(['remind_at', 'contact_by', 'status', 'notified_at', 'contacted_at', 'contact_method', 'notes']);
        });
        Schema::dropIfExists('recurring_services');
    }
};
