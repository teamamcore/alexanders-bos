<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BuildController;
use App\Http\Controllers\OpsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    // Prototype: no email-verification gate (re-add ->middleware('verified') for production).
    Route::get('dashboard', [OpsController::class, 'dashboard'])->name('dashboard');

    // Modules 3.1/3.2/3.3 — Volt pages
    Volt::route('jobs', 'pages.jobs.index')->name('jobs.index');
    Volt::route('jobs/{job}', 'pages.jobs.show')->name('jobs.show');
    Volt::route('schedule', 'pages.schedule.index')->name('schedule.index');

    // Module 3.5 — Proposals & Estimating
    Route::get('proposals', [OpsController::class, 'proposals'])->name('proposals.index');
    Route::post('proposals', [OpsController::class, 'createProposal'])->name('proposals.store');
    Route::post('proposals/{proposal}/stage', [OpsController::class, 'moveProposalStage'])->name('proposals.stage');
    Route::post('proposals/{proposal}/convert', [OpsController::class, 'convertProposal'])->name('proposals.convert');

    // Module 3.6 — Invoicing
    Route::get('invoices', [OpsController::class, 'invoices'])->name('invoices.index');
    Route::post('work-orders/{workOrder}/invoice', [OpsController::class, 'generateInvoice'])->name('invoices.generate');
    Route::post('work-orders/{workOrder}/progress', [OpsController::class, 'progressPayment'])->name('invoices.progress');
    Route::post('invoices/{invoice}/{action}', [OpsController::class, 'invoiceAction'])
        ->whereIn('action', ['approve', 'void', 'paid', 'sync'])
        ->name('invoices.action');

    // Module 3.8 — Time & Pay
    Route::get('time', [OpsController::class, 'time'])->name('time.index');
    Route::post('time/clock-in', [OpsController::class, 'clockIn'])->name('time.clockin');
    Route::post('time/{timeEntry}/clock-out', [OpsController::class, 'clockOut'])->name('time.clockout');

    // Module 3.11 — Team Records & Qualifications
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->middleware('role:office,administrator')->name('team.store');
    Route::get('team/{employee}', [TeamController::class, 'show'])->name('team.show');
    Route::post('team/{employee}/certifications', [TeamController::class, 'storeCertification'])->middleware('role:office,administrator')->name('team.certifications.store');
    Route::post('team/{employee}/milestones', [TeamController::class, 'storeMilestone'])->middleware('role:office,administrator')->name('team.milestones.store');
    Route::post('team/{employee}/background-check', [TeamController::class, 'updateBackgroundCheck'])->middleware('role:administrator')->name('team.background');

    // Build checklist (mirrors the Notion Build Timeline)
    Route::get('build', [BuildController::class, 'index'])->name('build.index');

    Route::view('profile', 'profile')->name('profile');

    // Module 3.13 — Administration & Security (administrators only)
    Route::prefix('admin')->middleware('role:administrator')->name('admin.')->group(function () {
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::post('users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::post('users/{user}/deactivate', [AdminController::class, 'deactivateUser'])->name('users.deactivate');
        Route::post('users/{user}/reactivate', [AdminController::class, 'reactivateUser'])->name('users.reactivate');
        Route::post('users/{user}/role', [AdminController::class, 'changeRole'])->name('users.role');
        Route::get('terminology', [AdminController::class, 'terminology'])->name('terminology');
        Route::post('terminology', [AdminController::class, 'updateTerminology'])->name('terminology.update');
        Route::get('security', [AdminController::class, 'security'])->name('security');
        // 3.11 — management assignment area for qualification values
        Route::get('qualifications', [AdminController::class, 'qualifications'])->name('qualifications');
        Route::post('qualifications', [AdminController::class, 'updateQualifications'])->name('qualifications.update');
    });
});

require __DIR__.'/auth.php';
