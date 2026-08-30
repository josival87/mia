<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use RuntimeException;

class TelegramSync extends Command
{
    protected $signature = 'mia:telegram-sync {--webhook-only : Não remove webhooks quando o modo atual é polling}';

    protected $description = 'Sincroniza o modo de recebimento configurado com a API do Telegram';

    public function handle(TelegramBotService $bot): int
    {
        if ($this->option('webhook-only') && $bot->mode() !== 'webhook') {
            $this->info('Modo local: o worker de polling cuidará das mensagens.');

            return self::SUCCESS;
        }

        try {
            $result = $bot->synchronize(! $this->option('webhook-only'));
            $this->info($result['message']);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
