<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryGoal extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'category_id', 'monthly_amount'];

    protected function casts(): array
    {
        return ['monthly_amount' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function milestones()
    {
        return $this->hasMany(CategoryGoalMilestone::class);
    }
}
