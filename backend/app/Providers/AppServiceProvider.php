<?php

namespace App\Providers;

use App\Models\FinanceRecord;
use App\Observers\FinanceRecordObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FinanceRecord::observe(FinanceRecordObserver::class);

        RateLimiter::for('external-finance', function (Request $request): array {
            $ip = $request->ip() ?: 'unknown';
            $credential = $request->bearerToken() ?: 'missing';

            return [
                Limit::perMinute(300)->by('ip:'.$ip),
                Limit::perMinute(120)->by('credential:'.hash('sha256', $credential)),
            ];
        });

        RateLimiter::for('iphone-bank', function (Request $request): array {
            $ip = $request->ip() ?: 'unknown';
            $credential = $request->bearerToken() ?: 'missing';

            return [
                Limit::perMinute(60)->by('ip:'.$ip),
                Limit::perMinute(30)->by('credential:'.hash('sha256', $credential)),
            ];
        });
    }
}
