<?php

declare(strict_types=1);

namespace App\Providers;

use App\Platform\Messaging\LogSmsDriver;
use App\Platform\Messaging\SmsDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SOW §3.4 — outbound texting transport (provider swappable, MSA §2.3).
        $this->app->bind(SmsDriver::class, fn () => match (config('messaging.sms_driver', 'log')) {
            default => new LogSmsDriver,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
