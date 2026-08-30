<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'category_id', 'name', 'description', 'priority', 'status', 'due_on', 'completed_at', 'source', 'ai_confidence', 'source_reference'];
    protected function casts(): array { return ['due_on' => 'date', 'completed_at' => 'datetime', 'ai_confidence' => 'float']; }
    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
}
