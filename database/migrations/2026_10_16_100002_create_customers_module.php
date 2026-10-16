<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.1 — Intake & Customer Records.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('role')->nullable();
            $t->timestamps();
        });

        Schema::create('service_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('label')->nullable();
            $t->string('address');
            $t->string('type')->default('job');   // billing | job (tracked separately)
            $t->text('access_notes')->nullable();  // access, badging, site training
            $t->json('site_requirements')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_locations');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('customers');
    }
};
