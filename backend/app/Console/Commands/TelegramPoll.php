<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramController;
use App\Models\SystemSetting;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramPoll extends Command
{
    protected $signature = 'mia:telegram-poll {--once : Executa somente uma consulta, para diagnóstico e testes}';

    protected $description = 'Mantém o Bot Mia recebendo atualizações do Telegram no ambiente local';

    private bool $running = true;

    public function handle(TelegramBotService $bot): int
    {
        $once = (bool) $this->option('once');
        $offset = null;
        $this->registerSignalHandlers();
        $this->info('Worker do Telegram iniciado.');

        do {
            if ($bot->mode() !== 'polling') {
                if ($once) {
                    return self::SUCCESS;
                }
                sleep(5);

                continue;
            }

            $token = trim((string) SystemSetting::read('telegram_bot_token', env('TELEGRAM_BOT_TOKEN')));
            if ($token === '') {
                SystemSetting::write('telegram_polling_last_error', 'Aguardando o token do bot nas configurações.');
                if ($once) {
                    return self::FAILURE;
                }
                sleep(5);

                continue;
            }

            SystemSetting::write('telegram_polling_heartbeat_at', now()->toIso8601String());
            $result = $this->poll($token, $offset);
            if (! $result && ! $once) {
                sleep(3);
            }
        } while ($this->running && ! $once);

        return self::SUCCESS;
    }

    private function poll(string $token, ?int &$offset): bool
    {
        try {
            $query = [
                'timeout' => $this->option('once') ? 0 : 25,
                'allowed_updates' => json_encode(['message', 'edited_message', 'callback_query']),
            ];
            if ($offset !== null) {
                $query['offset'] = $offset;
            }

            $response = Http::timeout(35)->get("https://api.telegram.org/bot{$token}/getUpdates", $query);
            if (! $response->successful() || ! data_get($response->json(), 'ok')) {
                $description = (string) data_get($response->json(), 'description');
                $message = str_contains(mb_strtolower($description), 'webhook')
                    ? 'Existe um webhook ativo; o polling local está aguardando.'
                    : 'O Telegram respondeu com erro HTTP '.$response->status().'.';
                SystemSetting::write('telegram_polling_last_error', $message);

                return false;
            }

            foreach ((array) data_get($response->json(), 'result', []) as $update) {
                $request = Request::create('/telegram/webhook', 'POST', $update);
                $secret = SystemSetting::read('telegram_webhook_secret', env('TELEGRAM_WEBHOOK_SECRET'));
                if ($secret) {
                    $request->headers->set('X-Telegram-Bot-Api-Secret-Token', $secret);
                }
                app(TelegramController::class)->webhook($request);
                $offset = max($offset ?? 0, ((int) data_get($update, 'update_id')) + 1);
                SystemSetting::write('telegram_polling_last_update_at', now()->toIso8601String());
            }

            SystemSetting::write('telegram_polling_heartbeat_at', now()->toIso8601String());
            SystemSetting::write('telegram_polling_last_error', null);

            return true;
        } catch (Throwable) {
            SystemSetting::write('telegram_polling_last_error', 'Falha temporária ao consultar ou processar o Telegram.');

            return false;
        }
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->running = false);
        pcntl_signal(SIGINT, fn () => $this->running = false);
    }
}
