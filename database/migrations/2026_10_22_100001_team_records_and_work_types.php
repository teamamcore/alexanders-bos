<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOW §3.11 Team Records & Qualifications — profiles, milestones, licence
// details, and the work_type on work orders that the qualification values
// and certification indicators key off (§3.3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->string('title')->nullable()->after('name');
            $t->string('phone')->nullable()->after('title');
            $t->date('hired_at')->nullable()->after('phone');
            $t->text('notes')->nullable()->after('background_check_date');
        });

        Schema::table('certifications', function (Blueprint $t) {
            $t->string('type')->default('certification')->after('name'); // certification|license
            $t->string('number')->nullable()->after('type');
            $t->date('issued_at')->nullable()->after('number');
        });

        Schema::create('employee_milestones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->date('occurred_at');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::table('work_orders', function (Blueprint $t) {
            $t->string('work_type')->nullable()->after('schedule_window'); // config('batsons.work_types')
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', fn (Blueprint $t) => $t->dropColumn('work_type'));
        Schema::dropIfExists('employee_milestones');
        Schema::table('certifications', fn (Blueprint $t) => $t->dropColumn(['type', 'number', 'issued_at']));
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['title', 'phone', 'hired_at', 'notes']));
    }
};
