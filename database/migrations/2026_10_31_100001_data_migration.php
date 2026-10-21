<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §4 — Data migration: QuickBooks / spreadsheet imports of customers, items,
// open receivables and work order records, with a record of what could not be imported.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind');                       // customers|items|receivables|work_orders
            $t->string('source');                     // csv|quickbooks_online
            $t->string('file_name')->nullable();
            $t->string('file_path')->nullable();      // stored upload (local disk) so a dry run can be committed later
            $t->string('mode');                       // dry_run|commit
            $t->string('status')->default('done');    // done|failed
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('imported')->default(0);
            $t->unsignedInteger('updated')->default(0);
            $t->unsignedInteger('skipped')->default(0);
            $t->unsignedInteger('warnings')->default(0);
            $t->json('issues')->nullable();           // first N {row, level, field, reason, record}
            $t->json('columns')->nullable();          // header → canonical field mapping that was detected
            $t->text('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        Schema::create('migration_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('batch_id')->nullable()->constrained('migration_batches')->nullOnDelete();
            $t->string('kind');
            $t->string('source_key');                 // QuickBooks id / invoice number / normalized name
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->json('raw')->nullable();              // the source row as received
            $t->timestamps();
            $t->unique(['tenant_id', 'kind', 'source_key']);
            $t->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_records');
        Schema::dropIfExists('migration_batches');
    }
};
