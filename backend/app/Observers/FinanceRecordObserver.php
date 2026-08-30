<?php

namespace App\Observers;

use App\Models\FinanceRecord;
use App\Services\CategoryGoalAlertService;

class FinanceRecordObserver
{
    public function __construct(private readonly CategoryGoalAlertService $alerts) {}

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
