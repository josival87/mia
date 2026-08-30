<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CategoryGoalThresholdReached extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $progress
     */
    public function __construct(
        private readonly array $progress,
        private readonly int $threshold,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $category = $this->progress['category_name'];
        $message = $this->threshold >= 100
            ? "{$category} atingiu 100% da meta semanal."
            : "{$category} atingiu {$this->threshold}% da meta semanal.";

        return [
            'kind' => 'category_goal_threshold',
            'message' => $message,
            'category_id' => $this->progress['category_id'],
            'category_name' => $category,
            'category_color' => $this->progress['category_color'],
            'threshold' => $this->threshold,
            'percentage' => $this->progress['percentage'],
            'remaining_amount' => $this->progress['remaining_amount'],
            'period_month' => $this->progress['period_month'],
            'week_start' => $this->progress['week_start'],
            'week_end' => $this->progress['week_end'],
        ];
    }
}
