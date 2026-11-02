<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Isolamento por usuário: com alguém logado na web, toda consulta e todo route model binding
 * enxergam só os registros dele. Um ID de outro usuário vira 404, não 403 (sem vazar existência).
 * Jobs e comandos do Telegram não têm sessão e filtram por usuário explicitamente.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            if (Auth::hasUser()) {
                $query->where($query->getModel()->getTable().'.user_id', Auth::id());
            }
        });

        static::creating(function ($model): void {
            if (! $model->user_id && Auth::hasUser()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
