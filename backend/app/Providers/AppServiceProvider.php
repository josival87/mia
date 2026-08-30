<?php

namespace App\Providers;

use App\Models\FinanceRecord;
use App\Models\Task;
use App\Observers\FinanceRecordObserver;
use App\Observers\TaskObserver;
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
        Task::observe(TaskObserver::class);

        RateLimiter::for('telegram-webhook', function (Request $request): Limit {
            $telegramUserId = (string) (data_get($request->input('message'), 'from.id')
                ?? data_get($request->input('edited_message'), 'from.id')
                ?? data_get($request->input('callback_query'), 'from.id')
                ?? 'anonymous:'.($request->ip() ?: 'unknown'));

            return Limit::perMinute(30)->by('telegram:'.hash('sha256', $telegramUserId));
        });

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
