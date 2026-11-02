<?php

use App\Models\SystemSetting;
use App\Services\RecordSafety;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mia:configure-gemini', function (): int {
    $apiKey = trim((string) $this->secret('Cole a API key do Gemini'));
    if ($apiKey === '') {
        $this->error('A chave não pode ficar vazia.');

        return 1;
    }

    SystemSetting::write('gemini_api_key', $apiKey, true);
    SystemSetting::write('ai_primary_provider', 'gemini');
    SystemSetting::write('gemini_model', 'gemini-3.6-flash');
    $this->info('Chave Gemini salva de forma criptografada e definida como provedora principal.');

    return 0;
})->purpose('Salva uma chave Gemini criptografada sem exibi-la no terminal');

Artisan::command('mia:test-ai', function (): int {
    $url = app(RecordSafety::class)->trustedCognitionBaseUrl(
        (string) SystemSetting::read('cognition_url', config('services.cognition.url')),
    ).'/parse';
    $response = Http::cognition()->timeout(30)->post($url, [
        'text' => 'Paguei R$ 12,50 em um café hoje.',
        'hint' => 'finance',
        'categories' => [],
    ]);

    if (! $response->successful()) {
        $this->error('O serviço cognitivo respondeu com HTTP '.$response->status().'.');

        return 1;
    }

    $provider = (string) data_get($response->json(), 'usage.provider', data_get($response->json(), 'provider', 'desconhecido'));
    $this->info('Integração validada: o texto foi interpretado pelo provedor '.$provider.'.');

    return 0;
})->purpose('Valida a chave Gemini configurada sem exibi-la');

Artisan::command('mia:test-audio {file} {--duration= : Duração verificada do áudio em segundos}', function (string $file): int {
    if (! is_file($file)) {
        $this->error('Arquivo de áudio não encontrado.');

        return 1;
    }

    $duration = filter_var($this->option('duration'), FILTER_VALIDATE_INT);
    if ($duration === false || $duration < 1) {
        $this->error('Informe a duração verificada com --duration=N.');

        return 1;
    }
    if ($duration > RecordSafety::MAX_AUDIO_SECONDS || filesize($file) > RecordSafety::MAX_AUDIO_BYTES) {
        $this->error('Áudio Muito Longo');

        return 1;
    }

    $url = app(RecordSafety::class)->trustedCognitionBaseUrl(
        (string) SystemSetting::read('cognition_url', config('services.cognition.url')),
    ).'/parse-audio';
    $response = Http::cognition()->timeout(50)
        ->attach('file', file_get_contents($file), basename($file), ['Content-Type' => 'audio/ogg'])
        ->post($url, [
            'categories' => '[]',
            'duration_seconds' => $duration,
        ]);

    if (! $response->successful()) {
        $this->error('O serviço cognitivo respondeu com HTTP '.$response->status().'.');

        return 1;
    }

    $this->info('Integração validada: áudio interpretado via '.data_get($response->json(), 'audio_provider', 'desconhecido').'.');

    return 0;
})->purpose('Valida a interpretação de um arquivo de áudio pelo Gemini');

// ---- Agendamentos (container `scheduler`: php artisan schedule:work) ----
Schedule::command('mia:planb-notify --weekly')->weeklyOn(0, '20:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
Schedule::command('mia:planb-notify --reminders')->dailyAt('09:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::call(function (): void {
    DB::table('processed_telegram_updates')->where('created_at', '<', now()->subDays(7))->delete();
    DB::table('pending_registrations')->where('verification_code_expires_at', '<', now()->subDay())->delete();
    DB::table('pending_telegram_records')->where('expires_at', '<', now()->subDays(30))->delete();
    DB::table('sessions')->where('last_activity', '<', now()->subDays(30)->getTimestamp())->delete();
})->name('mia:limpeza-diaria')->dailyAt('03:30')->withoutOverlapping();
