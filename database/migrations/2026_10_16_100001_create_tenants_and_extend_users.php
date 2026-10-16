<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('trade')->nullable();
            $t->json('settings')->nullable(); // per-tenant terminology, rates, etc.
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $t->string('role')->default('field')->after('email'); // App\Enums\Role
            $t->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tenant_id');
            $t->dropColumn(['role', 'is_active']);
        });
        Schema::dropIfExists('tenants');
    }
};
