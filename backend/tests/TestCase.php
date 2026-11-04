<?php

namespace Tests;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::flushMemo();
        config(['queue.default' => 'sync']);

        if (! in_array(RefreshDatabase::class, class_uses_recursive(static::class))
            && ! User::where('email', 'cliente@mia.local')->exists()) {
            $this->seed();
        }
    }

    protected function tearDown(): void
    {
        SystemSetting::flushMemo();
        parent::tearDown();
    }
}
