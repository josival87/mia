<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryGoalMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_goal_id',
        'period_month',
        'week_start',
        'threshold',
        'spent_amount',
        'weekly_target',
        'finance_record_id',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'week_start' => 'date',
            'threshold' => 'integer',
            'spent_amount' => 'decimal:2',
            'weekly_target' => 'decimal:2',
        ];
    }

    public function goal()
    {
        return $this->belongsTo(CategoryGoal::class, 'category_goal_id');
    }

    public function financeRecord()
    {
        return $this->belongsTo(FinanceRecord::class);
    }
}
