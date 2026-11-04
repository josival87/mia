<?php

namespace App\Policies;

use App\Models\FinanceRecord;
use App\Models\User;

class FinanceRecordPolicy
{
    public function view(User $user, FinanceRecord $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function update(User $user, FinanceRecord $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function delete(User $user, FinanceRecord $record): bool
    {
        return $user->id === $record->user_id;
    }
}

