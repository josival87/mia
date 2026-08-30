<?php

namespace App\Observers;

use App\Models\FinanceRecord;
use App\Services\CategoryGoalAlertService;
use App\Services\RecordSafety;

class FinanceRecordObserver
{
    public function __construct(
        private readonly CategoryGoalAlertService $alerts,
        private readonly RecordSafety $safety,
    ) {}

    public function creating(FinanceRecord $record): void
    {
        $this->safety->assertFinanceRecordIsSafe($record);
    }

    public function updating(FinanceRecord $record): void
    {
        $this->safety->assertFinanceRecordIsSafe($record);
    }

    public function created(FinanceRecord $record): void
    {
        $this->alerts->evaluateRecord($record);
    }

    public function updated(FinanceRecord $record): void
    {
        if ($record->wasChanged(['user_id', 'category_id', 'type', 'amount', 'occurred_on'])) {
            $this->alerts->evaluateRecord($record);
        }
    }
}
