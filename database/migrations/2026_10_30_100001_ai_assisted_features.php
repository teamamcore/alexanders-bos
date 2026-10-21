<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.16 — AI-assisted search, tagging, in-app help; call summaries; usage
// metering (AI features carry a monthly usage charge, Exhibit A).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('source')->default('ai'); // ai|user|system
            $t->timestamps();
            $t->unique(['tenant_id', 'slug']);
        });

        Schema::create('taggables', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->morphs('taggable');
            $t->string('source')->default('ai'); // who applied it: ai|user
            $t->timestamps();
            $t->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });

        Schema::table('call_transcripts', function (Blueprint $t) {
            $t->json('action_items')->nullable()->after('summary');
            $t->string('sentiment')->nullable()->after('action_items'); // positive|neutral|negative
            $t->timestamp('summarized_at')->nullable()->after('sentiment');
        });

        Schema::create('ai_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('feature');              // search|tagging|help|call_summary
            $t->string('provider');             // claude|offline
            $t->string('model')->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('cache_read_tokens')->default(0);
            $t->decimal('cost_usd', 10, 6)->default(0);
            $t->unsignedInteger('duration_ms')->default(0);
            $t->boolean('ok')->default(true);
            $t->string('error')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
        Schema::table('call_transcripts', fn (Blueprint $t) => $t->dropColumn(['action_items', 'sentiment', 'summarized_at']));
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
