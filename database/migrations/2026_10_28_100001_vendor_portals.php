<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.14 — Vendor Portal Integrations: the connection per portal, the work
// requests imported from it, and the sync log in both directions.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_portal_integrations', function (Blueprint $t) {
            $t->string('key')->nullable()->after('tenant_id');           // service_channel|paramark|sandbox|<slug>
            $t->string('adapter')->default('sandbox')->after('name');    // which PortalAdapter talks to it
            $t->text('credentials')->nullable()->after('adapter');      // encrypted JSON: credentials / ids / base url
            $t->string('webhook_token', 64)->nullable()->after('credentials');
            $t->foreignId('default_customer_id')->nullable()->after('webhook_token')->constrained('customers')->nullOnDelete();
            $t->string('blocked_reason')->nullable()->after('counts_toward_included');
            $t->timestamp('last_polled_at')->nullable()->after('blocked_reason');
            $t->text('last_error')->nullable()->after('last_polled_at');
            $t->text('notes')->nullable()->after('last_error');
        });

        Schema::create('portal_work_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('integration_id')->constrained('vendor_portal_integrations')->cascadeOnDelete();
            $t->string('external_id');                      // the portal's id
            $t->string('external_number')->nullable();      // the number the customer quotes
            $t->string('external_status')->nullable();      // as last reported by the portal
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('service_location_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_id')->nullable()->constrained('jobs_records')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name')->nullable();
            $t->string('location_name')->nullable();
            $t->string('address')->nullable();
            $t->string('trade')->nullable();
            $t->string('priority')->nullable();
            $t->decimal('nte', 12, 2)->nullable();          // not-to-exceed
            $t->text('description')->nullable();
            $t->date('scheduled_by')->nullable();
            $t->string('status')->default('imported');      // imported|scheduled|in_progress|completed|invoiced|paid|rejected
            $t->json('raw')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->unique(['integration_id', 'external_id']);
        });

        Schema::create('portal_sync_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('integration_id')->constrained('vendor_portal_integrations')->cascadeOnDelete();
            $t->foreignId('portal_work_order_id')->nullable()->constrained('portal_work_orders')->nullOnDelete();
            $t->string('direction');                        // inbound|outbound
            $t->string('type');                             // import|status|invoice|payment|poll|error
            $t->string('status')->default('ok');            // ok|failed|queued
            $t->string('message')->nullable();
            $t->json('payload')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_sync_events');
        Schema::dropIfExists('portal_work_orders');
        Schema::table('vendor_portal_integrations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('default_customer_id');
            $t->dropColumn(['key', 'adapter', 'credentials', 'webhook_token', 'blocked_reason', 'last_polled_at', 'last_error', 'notes']);
        });
    }
};
