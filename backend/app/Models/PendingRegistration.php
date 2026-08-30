<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $fillable = [
        'name',
        'email',
        'cpf',
        'verification_code',
        'verification_code_expires_at',
        'verification_code_used_at',
        'verification_attempts',
        'telegram',
        'telegram_chat_id',
        'telegram_user_id',
        'telegram_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verification_code_expires_at' => 'datetime',
            'verification_code_used_at' => 'datetime',
            'telegram_verified_at' => 'datetime',
        ];
    }
}
