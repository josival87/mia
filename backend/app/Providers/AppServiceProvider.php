<?php

namespace App\Providers;

use App\Models\FinanceRecord;
use App\Models\Task;
use App\Observers\FinanceRecordObserver;
use App\Observers\TaskObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

        // Cliente HTTP para o serviço Go: sempre autenticado, nunca envia chaves de IA.
        Http::macro('cognition', function (): PendingRequest {
            return Http::withHeaders(['X-Internal-Token' => (string) config('services.cognition.token')])
                ->connectTimeout(5)
                ->acceptJson();
        });

        RateLimiter::for('login', function (Request $request): array {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login:'.hash('sha256', $email.'|'.$request->ip())),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('verification', fn (Request $request): Limit => Limit::perMinute(10)->by('verify:'.$request->ip()));

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
