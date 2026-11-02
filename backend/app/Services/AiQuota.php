<?php

namespace App\Services;

use App\Models\AiUsageDaily;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/** Cota diária de mensagens interpretadas por IA, por usuário, e registro do uso devolvido pelo Go. */
class AiQuota
{
    public function limit(): int
    {
        return max(1, (int) config('services.cognition.daily_user_limit', 50));
    }

    public function consume(User $user): void
    {
        $key = 'ai-quota:'.$user->id.':'.today()->toDateString();
        Cache::add($key, 0, now()->endOfDay());
        if (Cache::increment($key) > $this->limit()) {
            throw ValidationException::withMessages([
                'quota' => 'Você chegou ao limite de '.$this->limit().' mensagens interpretadas hoje. Use os comandos rápidos (/turno, /corrida, /horas) ou lance pelo site. A cota volta amanhã.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $usage
     */
    public function record(User $user, ?array $usage, string $route = 'record'): void
    {
        if (! $usage) {
            return;
        }
        $row = AiUsageDaily::firstOrCreate([
            'user_id' => $user->id,
            'day' => today()->toDateString(),
            'route' => $route,
            'provider' => mb_substr((string) ($usage['provider'] ?? 'desconhecido'), 0, 40),
            'model' => mb_substr((string) ($usage['model'] ?? ''), 0, 80),
        ]);
        $row->increment('requests', 1, [
            'input_tokens' => $row->input_tokens + max(0, (int) ($usage['input_tokens'] ?? 0)),
            'output_tokens' => $row->output_tokens + max(0, (int) ($usage['output_tokens'] ?? 0)),
        ]);
    }
}
