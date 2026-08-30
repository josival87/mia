<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\RecordSafety;

class TaskObserver
{
    public function __construct(private readonly RecordSafety $safety) {}

    public function creating(Task $task): void
    {
        $this->safety->assertTaskIsSafe($task);
    }

    public function updating(Task $task): void
    {
        $this->safety->assertTaskIsSafe($task);
    }
}
