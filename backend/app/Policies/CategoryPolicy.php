<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function view(User $user, Category $category): bool
    {
        return $category->user_id === null || $category->user_id === $user->id;
    }

    public function update(User $user, Category $category): bool
    {
        return $category->user_id !== null && $category->user_id === $user->id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $category->user_id !== null && $category->user_id === $user->id;
    }
}

