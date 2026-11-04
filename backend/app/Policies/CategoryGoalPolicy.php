<?php

namespace App\Policies;

use App\Models\CategoryGoal;
use App\Models\User;

class CategoryGoalPolicy
{
    public function view(User $user, CategoryGoal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function update(User $user, CategoryGoal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function delete(User $user, CategoryGoal $goal): bool
    {
        return $user->id === $goal->user_id;
    }
}

