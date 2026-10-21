<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design pass (docs/design-notes-2026-10.md): the Work Order *parent* holds
// everything — hold sub-state + reason, emergency priority, half/multi-day
// counter, office-only scope of work with redline revisions, tech notes /
// scope-update pings, photos & files; contacts module fields.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $t) {
            $t->boolean('on_hold')->default(false)->after('status');
            $t->string('hold_reason')->nullable()->after('on_hold');      // config('batsons.hold_reasons')
            $t->string('priority')->default('normal')->after('hold_reason'); // normal|emergency
            $t->decimal('day_fraction', 3, 2)->default(1)->after('schedule_window'); // 0.5, 1, 2…
            $t->text('scope_office')->nullable()->after('internal_notes');  // office-maintained SOW
            $t->text('work_performed')->nullable()->after('scope_office');  // per Gus: distinct from the issue
        });

        Schema::create('work_order_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind')->default('tech_note'); // tech_note|field_finding|scope_update|office_note|system
            $t->text('body');
            $t->timestamp('acknowledged_at')->nullable();   // scope updates: office acknowledged
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'work_order_id', 'kind']);
        });

        Schema::create('scope_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('added_text');                          // redline addition
            $t->foreignId('source_note_id')->nullable()->constrained('work_order_notes')->nullOnDelete(); // what authorized it
            $t->timestamps();
        });

        Schema::create('work_order_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind')->default('photo');            // photo|file|receipt
            $t->string('path');
            $t->string('caption')->nullable();
            $t->timestamps();
        });

        Schema::table('customers', function (Blueprint $t) {
            $t->string('segment')->nullable()->after('name');                                   // config('batsons.customer_segments')
            $t->foreignId('owner_user_id')->nullable()->after('segment')->constrained('users')->nullOnDelete();   // contact owner
            $t->foreignId('corp_poc_contact_id')->nullable()->after('owner_user_id')->constrained('contacts')->nullOnDelete();
        });

        Schema::table('service_locations', function (Blueprint $t) {
            $t->foreignId('contact_id')->nullable()->after('customer_id')->constrained()->nullOnDelete(); // local contact
        });
    }

    public function down(): void
    {
        Schema::table('service_locations', fn (Blueprint $t) => $t->dropConstrainedForeignId('contact_id'));
        Schema::table('customers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('corp_poc_contact_id');
            $t->dropConstrainedForeignId('owner_user_id');
            $t->dropColumn('segment');
        });
        Schema::dropIfExists('work_order_files');
        Schema::dropIfExists('scope_revisions');
        Schema::dropIfExists('work_order_notes');
        Schema::table('work_orders', fn (Blueprint $t) => $t->dropColumn(['on_hold', 'hold_reason', 'priority', 'day_fraction', 'scope_office', 'work_performed']));
    }
};
