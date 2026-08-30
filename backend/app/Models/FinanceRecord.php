<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceRecord extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'category_id', 'type', 'title', 'description', 'amount', 'occurred_on', 'source', 'ai_confidence', 'source_reference'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'occurred_on' => 'date', 'ai_confidence' => 'float']; }
    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
}
