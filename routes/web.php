<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Phase 2 vertical slice: Intake -> Job -> Work Order (SOW §3.1, §3.2).
Volt::route('jobs', 'pages.jobs.index')->middleware(['auth'])->name('jobs.index');
Volt::route('jobs/{job}', 'pages.jobs.show')->middleware(['auth'])->name('jobs.show');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
