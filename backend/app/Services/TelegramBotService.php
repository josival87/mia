<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TelegramBotService
{
    public function mode(): string
    {
        $default = app()->environment('production') ? 'webhook' : 'polling';

        return (string) SystemSetting::read('telegram_update_mode', env('TELEGRAM_UPDATE_MODE', $default));
    }

    public function status(): array
    {
        $mode = $this->mode();
        $heartbeat = SystemSetting::read('telegram_polling_heartbeat_at');
        $pollingAlive = false;

        if ($heartbeat) {
            try {
                $pollingAlive = Carbon::parse($heartbeat)->greaterThan(now()->subSeconds(75));
            } catch (Throwable) {
                $pollingAlive = false;
            }
        }

        $status = [
            'configured' => false,
            'bot_valid' => false,
            'bot_username' => null,
            'mode' => $mode,
            'active' => false,
            'polling_alive' => $pollingAlive,
            'webhook_url' => null,
            'pending_updates' => null,
            'last_error' => SystemSetting::read('telegram_polling_last_error'),
        ];

        $token = $this->token();
        if (! $token) {
            return $status;
        }
        $status['configured'] = true;

        try {
            $me = Http::timeout(6)->get($this->apiUrl($token, 'getMe'));
            if (! $me->successful() || ! data_get($me->json(), 'ok')) {
                $status['last_error'] = 'O Telegram recusou o token configurado.';

                return $status;
            }

            $status['bot_valid'] = true;
            $status['bot_username'] = data_get($me->json(), 'result.username');

            $webhook = Http::timeout(6)->get($this->apiUrl($token, 'getWebhookInfo'));
            if (! $webhook->successful() || ! data_get($webhook->json(), 'ok')) {
                $status['last_error'] = 'Não foi possível consultar o webhook no Telegram.';

                return $status;
            }

            $status['webhook_url'] = data_get($webhook->json(), 'result.url') ?: null;
            $status['pending_updates'] = data_get($webhook->json(), 'result.pending_update_count');
            $status['last_error'] = data_get($webhook->json(), 'result.last_error_message')
                ?: $status['last_error'];
            $status['active'] = $mode === 'webhook'
                ? filled($status['webhook_url'])
                : blank($status['webhook_url']) && $pollingAlive;
        } catch (Throwable) {
            $status['last_error'] = 'Não foi possível consultar a API do Telegram agora.';
        }

        return $status;
    }

    public function synchronize(bool $allowPollingWebhookRemoval = true): array
    {
        $token = $this->token();
        if (! $token) {
            throw new RuntimeException('Informe primeiro o token do bot fornecido pelo BotFather.');
        }

        if ($this->mode() === 'polling') {
            if (! $allowPollingWebhookRemoval) {
                return ['mode' => 'polling', 'message' => 'O worker local fará a leitura por polling.'];
            }

            $response = Http::timeout(10)->post($this->apiUrl($token, 'deleteWebhook'), [
                'drop_pending_updates' => false,
            ]);
            $this->ensureTelegramAccepted($response->successful(), $response->json());

            return ['mode' => 'polling', 'message' => 'Polling local ativado e webhook anterior removido.'];
        }

        $url = trim((string) SystemSetting::read('telegram_webhook_url', env('TELEGRAM_WEBHOOK_URL')));
        $secret = trim((string) SystemSetting::read('telegram_webhook_secret', env('TELEGRAM_WEBHOOK_SECRET')));
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('Na produção, informe uma URL pública iniciada por https:// para o webhook.');
        }
        if (! preg_match('/^[A-Za-z0-9_-]{16,256}$/', $secret)) {
            throw new RuntimeException('Crie um secret do webhook com 16 a 256 letras, números, _ ou -.');
        }

        $response = Http::timeout(12)->post($this->apiUrl($token, 'setWebhook'), [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'edited_message', 'callback_query'],
        ]);
        $this->ensureTelegramAccepted($response->successful(), $response->json());

        return ['mode' => 'webhook', 'message' => 'Webhook de produção registrado no Telegram.'];
    }

    private function token(): ?string
    {
        $token = trim((string) SystemSetting::read('telegram_bot_token', env('TELEGRAM_BOT_TOKEN')));

        return $token !== '' ? $token : null;
    }

    private function apiUrl(string $token, string $method): string
    {
        return "https://api.telegram.org/bot{$token}/{$method}";
    }

    private function ensureTelegramAccepted(bool $successful, ?array $payload): void
    {
        if ($successful && data_get($payload, 'ok')) {
            return;
        }

        $description = trim((string) data_get($payload, 'description'));
        throw new RuntimeException($description !== ''
            ? 'O Telegram recusou a ativação: '.$description
            : 'Não foi possível ativar o recebimento de mensagens no Telegram.');
    }
}
