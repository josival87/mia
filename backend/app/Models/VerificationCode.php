<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    protected $fillable = ['user_id', 'code', 'purpose', 'expires_at', 'used_at', 'attempts'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}
