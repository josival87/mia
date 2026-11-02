<?php

namespace App\Jobs;

use App\Http\Controllers\TelegramController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processa uma atualização do Telegram fora da requisição HTTP.
 * O webhook já validou o secret e deduplicou o update_id antes de despachar.
 */
class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    /** Sem novas tentativas: repetir poderia duplicar respostas e lançamentos. */
    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $update
     */
    public function __construct(public array $update) {}

    public function handle(TelegramController $telegram): void
    {
        $telegram->processUpdate($this->update);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Falha ao processar atualização do Telegram na fila.', [
            'exception_class' => $exception ? $exception::class : null,
            'update_hash' => hash('sha256', (string) ($this->update['update_id'] ?? '')),
        ]);
    }
}
