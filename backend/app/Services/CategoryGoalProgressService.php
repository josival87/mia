<?php

namespace App\Services;

use App\Models\CategoryGoal;
use App\Models\FinanceRecord;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CategoryGoalProgressService
{
    /**
     * @return array<string, mixed>|null
     */
    public function forRecord(FinanceRecord $record): ?array
    {
        if ($record->type !== 'expense' || ! $record->category_id) {
            return null;
        }

        $goal = CategoryGoal::query()
            ->with('category')
            ->where('user_id', $record->user_id)
            ->where('category_id', $record->category_id)
            ->first();

        return $goal ? $this->forGoal($goal, $record->occurred_on) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function forGoal(CategoryGoal $goal, CarbonInterface|string|null $date = null): array
    {
        $date = $date instanceof CarbonInterface
            ? Carbon::instance($date)->startOfDay()
            : Carbon::parse($date ?: now())->startOfDay();
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $segments = $this->weekSegments($monthStart);
        $segmentIndex = 0;
        foreach ($segments as $index => $candidate) {
            if ($date->gte($candidate['start']) && $date->lte($candidate['end'])) {
                $segmentIndex = $index;
                break;
            }
        }
        $segment = $segments[$segmentIndex];

        $monthlyCents = $this->toCents($goal->monthly_amount);
        $baseWeeklyCents = intdiv($monthlyCents, count($segments));
        $remainder = $monthlyCents % count($segments);
        $weeklyTargetCents = $baseWeeklyCents + ($segmentIndex < $remainder ? 1 : 0);

        $spentCents = $this->toCents(FinanceRecord::query()
            ->where('user_id', $goal->user_id)
            ->where('category_id', $goal->category_id)
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$segment['start']->toDateString(), $segment['end']->toDateString()])
            ->sum('amount'));
        $percentage = $weeklyTargetCents > 0
            ? round(($spentCents / $weeklyTargetCents) * 100, 1)
            : 0.0;

        return [
            'goal_id' => $goal->id,
            'category_id' => $goal->category_id,
            'category_name' => $goal->category?->name ?? 'Categoria',
            'category_color' => $goal->category?->color ?? '#94a3b8',
            'period_month' => $monthStart->toDateString(),
            'week_start' => $segment['start']->toDateString(),
            'week_end' => $segment['end']->toDateString(),
            'week_number' => $segmentIndex + 1,
            'weeks_in_month' => count($segments),
            'monthly_amount' => $this->fromCents($monthlyCents),
            'weekly_target' => $this->fromCents($weeklyTargetCents),
            'spent_amount' => $this->fromCents($spentCents),
            'remaining_amount' => $this->fromCents(max(0, $weeklyTargetCents - $spentCents)),
            'exceeded_amount' => $this->fromCents(max(0, $spentCents - $weeklyTargetCents)),
            'percentage' => $percentage,
            'bar_percentage' => min(100, $percentage),
            'status' => $percentage >= 100 ? 'exceeded' : ($percentage >= 90 ? 'critical' : ($percentage >= 80 ? 'warning' : 'normal')),
        ];
    }

    /**
     * @return list<array{start: Carbon, end: Carbon}>
     */
    public function weekSegments(CarbonInterface|string $month): array
    {
        $monthStart = $month instanceof CarbonInterface
            ? Carbon::instance($month)->startOfMonth()
            : Carbon::parse($month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $cursor = $monthStart->copy();
        $segments = [];

        while ($cursor->lte($monthEnd)) {
            $end = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            if ($end->gt($monthEnd)) {
                $end = $monthEnd->copy();
            }
            $segments[] = ['start' => $cursor->copy(), 'end' => $end];
            $cursor = $end->copy()->addDay()->startOfDay();
        }

        return $segments;
    }

    private function toCents(string|int|float|null $amount): int
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $decimal] = explode('.', $normalized);

        return ((int) $whole * 100) + (int) $decimal;
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
