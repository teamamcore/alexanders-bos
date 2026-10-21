<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BuildController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OpsController;
use App\Http\Controllers\PartsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WebhookController;
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

    // Module 3.7 — Parts & Inventory
    Route::get('parts', [PartsController::class, 'index'])->name('parts.index');
    Route::get('parts/{part}', [PartsController::class, 'show'])->name('parts.show');
    Route::post('parts/{part}/adjust', [PartsController::class, 'adjust'])->name('parts.adjust');       // field ok (used on job, breakage)
    Route::post('parts/{part}/delivery', [PartsController::class, 'delivery'])->name('parts.delivery'); // field ok (receiving)
    Route::middleware('role:office,administrator')->group(function () {
        Route::post('parts', [PartsController::class, 'store'])->name('parts.store');
        Route::post('parts/import', [PartsController::class, 'import'])->name('parts.import');
        Route::post('parts/{part}/price', [PartsController::class, 'updatePrice'])->name('parts.price');
    });

    // Module 3.7 — Billable Equipment
    Route::get('equipment', [EquipmentController::class, 'index'])->name('equipment.index');
    Route::get('equipment/{equipment}', [EquipmentController::class, 'show'])->name('equipment.show');
    Route::post('equipment/{equipment}/usage', [EquipmentController::class, 'usage'])->name('equipment.usage');
    Route::post('equipment/{equipment}/checkout', [EquipmentController::class, 'checkout'])->name('equipment.checkout');
    Route::post('equipment/{equipment}/return', [EquipmentController::class, 'return'])->name('equipment.return');
    Route::post('equipment/{equipment}/issue', [EquipmentController::class, 'issue'])->name('equipment.issue');
    Route::post('equipment-issues/{issue}/resolve', [EquipmentController::class, 'resolveIssue'])->name('equipment.issues.resolve');
    Route::middleware('role:office,administrator')->group(function () {
        Route::post('equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::post('equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
        Route::post('equipment/{equipment}/maintenance', [EquipmentController::class, 'maintenance'])->name('equipment.maintenance');
    });

    // Module 3.7 — Fleet
    Route::get('fleet', [FleetController::class, 'index'])->name('fleet.index');
    Route::get('fleet/{vehicle}', [FleetController::class, 'show'])->name('fleet.show');
    Route::post('fleet/{vehicle}/mileage', [FleetController::class, 'mileage'])->name('fleet.mileage');
    Route::post('fleet/{vehicle}/cost', [FleetController::class, 'cost'])->name('fleet.cost');
    Route::post('fleet/{vehicle}/issue', [FleetController::class, 'issue'])->name('fleet.issue');
    Route::post('fleet-issues/{issue}/resolve', [FleetController::class, 'resolveIssue'])->name('fleet.issues.resolve');
    Route::middleware('role:office,administrator')->group(function () {
        Route::post('fleet', [FleetController::class, 'store'])->name('fleet.store');
        Route::post('fleet/{vehicle}', [FleetController::class, 'update'])->name('fleet.update');
    });

    // Module 3.4 — Customer Communication
    Route::middleware('role:office,administrator')->group(function () {
        Route::get('messages', [MessagesController::class, 'index'])->name('messages.index');
        Route::post('messages', [MessagesController::class, 'send'])->name('messages.send');
        Route::get('messages/templates', [MessagesController::class, 'templates'])->name('messages.templates');
        Route::post('messages/templates/{key}', [MessagesController::class, 'updateTemplate'])->name('messages.templates.update');
        Route::post('messages/auto-notify', [MessagesController::class, 'toggleAutoNotify'])->name('messages.autonotify');
    });
    Route::get('messages/calls', [MessagesController::class, 'calls'])->name('messages.calls');          // office + limited field
    Route::post('messages/calls', [MessagesController::class, 'storeCall'])->name('messages.calls.store');

    // Module 3.12 — Notifications & Messaging
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.readAll');
    Route::post('notifications/{id}/read', [NotificationsController::class, 'read'])->name('notifications.read');
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('inbox/email', [InboxController::class, 'email'])->middleware('role:office,administrator')->name('inbox.email');
    Route::post('inbox/email/{email}/route', [InboxController::class, 'routeEmail'])->middleware('role:office,administrator')->name('inbox.email.route');
    Route::get('inbox/{with}', [InboxController::class, 'index'])->name('inbox.thread');
    Route::post('inbox', [InboxController::class, 'send'])->name('inbox.send');

    // Build checklist (mirrors the Notion Build Timeline)
    Route::get('progress', [BuildController::class, 'index'])->name('build.index');

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

// Texting-platform webhook (token-protected, no session) — SOW §3.4.
Route::post('webhooks/sms', [WebhookController::class, 'inboundSms'])->name('webhooks.sms');
Route::post('webhooks/email', [WebhookController::class, 'inboundEmail'])->name('webhooks.email');

require __DIR__.'/auth.php';
