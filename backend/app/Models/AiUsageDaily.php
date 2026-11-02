<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageDaily extends Model
{
    protected $table = 'ai_usage_daily';

    protected $fillable = ['user_id', 'day', 'route', 'provider', 'model', 'requests', 'input_tokens', 'output_tokens'];
}
