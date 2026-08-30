<?php

namespace App\Services;

use App\Models\CategoryGoal;
use App\Models\CategoryGoalMilestone;
use App\Models\FinanceRecord;
use App\Notifications\CategoryGoalThresholdReached;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CategoryGoalAlertService
{
    private const THRESHOLDS = [50, 80, 90, 100];

    public function __construct(private readonly CategoryGoalProgressService $progressService) {}

    /**
     * @return array<string, mixed>|null
     */
    public function evaluateRecord(FinanceRecord $record): ?array
    {
        $progress = $this->progressService->forRecord($record);
        if (! $progress) {
            return null;
        }

        $goal = CategoryGoal::with('user')->findOrFail($progress['goal_id']);
        $this->recordCrossedMilestones($goal, $progress, $record);

        return $progress;
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluateGoal(CategoryGoal $goal, CarbonInterface|string|null $date = null): array
    {
        $goal->loadMissing(['category', 'user']);
        $progress = $this->progressService->forGoal($goal, $date);
        $this->recordCrossedMilestones($goal, $progress);

        return $progress;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function recordCrossedMilestones(CategoryGoal $goal, array $progress, ?FinanceRecord $record = null): void
    {
        $crossed = array_values(array_filter(
            self::THRESHOLDS,
            fn (int $threshold) => $progress['percentage'] >= $threshold
        ));
        if ($crossed === []) {
            return;
        }

        $newThresholds = DB::transaction(function () use ($crossed, $goal, $progress, $record): array {
            $created = [];
            foreach ($crossed as $threshold) {
                $milestone = CategoryGoalMilestone::firstOrCreate(
                    [
                        'category_goal_id' => $goal->id,
                        'period_month' => $progress['period_month'],
                        'week_start' => $progress['week_start'],
                        'threshold' => $threshold,
                    ],
                    [
                        'spent_amount' => $progress['spent_amount'],
                        'weekly_target' => $progress['weekly_target'],
                        'finance_record_id' => $record?->id,
                    ],
                );
                if ($milestone->wasRecentlyCreated) {
                    $created[] = $threshold;
                }
            }

            return $created;
        });

        if ($newThresholds !== []) {
            $goal->user->notify(new CategoryGoalThresholdReached($progress, max($newThresholds)));
        }
    }
}
