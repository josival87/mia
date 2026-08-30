<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankNotificationEvent extends Model
{
    protected $fillable = [
        'user_id', 'event_id', 'channel', 'sender', 'content_hash', 'received_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
