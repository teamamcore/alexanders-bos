<?php

declare(strict_types=1);

use App\Http\Controllers\OpsController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [OpsController::class, 'dashboard'])->middleware('verified')->name('dashboard');

    // Modules 3.1/3.2/3.3 — Volt pages
    Volt::route('jobs', 'pages.jobs.index')->name('jobs.index');
    Volt::route('jobs/{job}', 'pages.jobs.show')->name('jobs.show');
    Volt::route('schedule', 'pages.schedule.index')->name('schedule.index');

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

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
