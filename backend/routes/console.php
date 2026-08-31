<?php

use App\Models\SystemSetting;
use App\Services\RecordSafety;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

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
    $apiKey = SystemSetting::read('gemini_api_key');
    if (! $apiKey) {
        $this->error('Nenhuma chave Gemini está configurada.');

        return 1;
    }

    $url = app(RecordSafety::class)->trustedCognitionBaseUrl(
        (string) SystemSetting::read('cognition_url', config('services.cognition.url')),
    ).'/parse';
    $response = Http::connectTimeout(5)->timeout(60)->post($url, [
        'text' => 'Paguei R$ 12,50 em um café hoje.',
        'hint' => 'finance',
        'categories' => [],
        'primary_provider' => 'gemini',
        'gemini_api_key' => $apiKey,
        'gemini_model' => SystemSetting::read('gemini_model', 'gemini-3.6-flash'),
    ]);

    if (! $response->successful()) {
        $this->error('O serviço cognitivo respondeu com HTTP '.$response->status().'.');

        return 1;
    }

    $provider = (string) data_get($response->json(), 'provider', 'desconhecido');
    if ($provider !== 'gemini') {
        $this->error('O Gemini não respondeu; o serviço usou o provedor '.$provider.'.');

        return 2;
    }

    $this->info('Integração validada: o Gemini interpretou o texto com sucesso.');

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

    $apiKey = SystemSetting::read('gemini_api_key');
    if (! $apiKey) {
        $this->error('Nenhuma chave Gemini está configurada.');

        return 1;
    }

    $url = app(RecordSafety::class)->trustedCognitionBaseUrl(
        (string) SystemSetting::read('cognition_url', config('services.cognition.url')),
    ).'/parse-audio';
    $response = Http::connectTimeout(5)->timeout(90)
        ->attach('file', file_get_contents($file), basename($file), ['Content-Type' => 'audio/ogg'])
        ->post($url, [
            'categories' => '[]',
            'duration_seconds' => $duration,
            'primary_provider' => 'gemini',
            'gemini_api_key' => $apiKey,
            'gemini_model' => SystemSetting::read('gemini_model', 'gemini-3.6-flash'),
        ]);

    if (! $response->successful()) {
        $this->error('O serviço cognitivo respondeu com HTTP '.$response->status().'.');

        return 1;
    }

    if (data_get($response->json(), 'audio_provider') !== 'gemini') {
        $this->error('O áudio não foi interpretado diretamente pelo Gemini.');

        return 2;
    }

    $this->info('Integração validada: o Gemini interpretou o áudio com sucesso.');

    return 0;
})->purpose('Valida a interpretação de um arquivo de áudio pelo Gemini');
