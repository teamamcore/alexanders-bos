<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SOW §3.12 — morning in-app digest replaces the internal reminder emails.
Schedule::command('notifications:digest')->dailyAt('07:00');

// SOW §3.10 — open cycle reminders, alert the office when a window opens, auto-message customers.
Schedule::command('reminders:run')->dailyAt('06:30');
