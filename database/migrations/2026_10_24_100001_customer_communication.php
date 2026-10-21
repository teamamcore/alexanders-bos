<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.4 — Customer Communication: text/email updates, configurable
// templates, field-to-customer messaging (office-monitored), call transcripts.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->string('phone')->nullable()->after('name');
            $t->string('email')->nullable()->after('phone');
            $t->boolean('sms_opt_in')->default(true)->after('email');
            $t->string('preferred_channel')->nullable()->after('sms_opt_in'); // sms|email
        });

        // Tenant overrides of the platform default templates (config/batsons.php).
        Schema::create('message_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key');                     // scheduled|rescheduled|on_the_way|arrived|…
            $t->string('name');
            $t->string('channel')->default('sms'); // sms|email|both
            $t->string('subject')->nullable();     // email only
            $t->text('body');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });

        Schema::create('customer_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_id')->nullable()->constrained('jobs_records')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('direction')->default('outbound'); // outbound|inbound
            $t->string('channel');                        // sms|email
            $t->string('to')->nullable();                 // phone or email
            $t->string('from')->nullable();
            $t->string('subject')->nullable();
            $t->text('body');
            $t->string('template_key')->nullable();
            $t->string('status')->default('queued');      // queued|sent|failed|received
            $t->string('provider_ref')->nullable();
            $t->text('error')->nullable();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'customer_id']);
        });

        // Phone call transcription (office + limited field). Client provides any
        // legally required notices / consent for recording (SOW §3.4).
        Schema::create('call_transcripts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_id')->nullable()->constrained('jobs_records')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('direction')->default('inbound'); // inbound|outbound
            $t->string('phone')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->string('audio_path')->nullable();
            $t->text('transcript')->nullable();
            $t->text('summary')->nullable();
            $t->string('status')->default('transcribed'); // pending|transcribed|failed
            $t->boolean('consent_noted')->default(false);
            $t->timestamp('called_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcripts');
        Schema::dropIfExists('customer_messages');
        Schema::dropIfExists('message_templates');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['phone', 'email', 'sms_opt_in', 'preferred_channel']));
    }
};
