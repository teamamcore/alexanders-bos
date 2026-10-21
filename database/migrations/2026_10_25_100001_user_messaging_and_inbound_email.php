<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.12 — in-app messaging between Users + the Client's inbound email
// address that routes mail into the platform. In-app notifications use
// Laravel's notifications table (separate migration).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->foreignId('job_id')->nullable()->constrained('jobs_records')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'recipient_id', 'read_at']);
        });

        Schema::create('inbound_emails', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->string('message_id')->nullable()->unique();
            $t->string('from_email');
            $t->string('from_name')->nullable();
            $t->string('to_email')->nullable();
            $t->string('subject')->nullable();
            $t->longText('body')->nullable();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_id')->nullable()->constrained('jobs_records')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_message_id')->nullable()->constrained('customer_messages')->nullOnDelete();
            $t->string('status')->default('new');  // new|routed|archived
            $t->string('routed_by_rule')->nullable(); // customer_email|contact_email|job_number|manual
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
        Schema::dropIfExists('user_messages');
    }
};
