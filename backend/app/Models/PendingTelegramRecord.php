<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingTelegramRecord extends Model
{
    protected $fillable = [
        'user_id', 'token', 'kind', 'payload', 'confidence', 'reason', 'status',
        'telegram_chat_id', 'origin_update_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'confidence' => 'float',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
