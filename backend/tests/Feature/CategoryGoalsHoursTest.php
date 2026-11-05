<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\CategoryGoal;
use App\Models\User;
use App\Services\PlanB\PlanBLedger;
use App\Services\PlanB\PlanBMetrics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CategoryGoalsHoursTest extends TestCase
{
    use DatabaseTransactions;

    private function expenseCategory(User $user): Category
    {
        return Category::create([
            'user_id' => $user->id,
            'name' => 'Lazer '.uniqid(),
            'kind' => 'expense',
            'color' => '#f97316',
            'active' => true,
        ]);
    }

    public function test_goal_card_shows_hours_of_plan_b_when_user_has_active_business_with_profit(): void
    {
        $user = User::factory()->client()->create();
        $category = $this->expenseCategory($user);

        $goal = CategoryGoal::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_amount' => 500.00,
        ]);

        $business = Business::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'type' => 'driver',
            'name' => 'Uber',
            'tax_regime' => 'none',
            'status' => 'active',
        ]);

        // 500 gain, 100 km, 10 hours -> profit = 500, profit/hr = 50.0
        // Goal = 500 -> 500 / 50 = 10.0 hours
        app(PlanBLedger::class)->recordShift($business, 500, 100, 10, 'uber');

        $this->assertSame(10.0, app(PlanBMetrics::class)->hoursFor($business, 500.00));

        $response = $this->actingAs($user)->get(route('finance.index'));

        $response->assertOk();
        $response->assertSee('custa 10 horas de Plano B');
    }

    public function test_goal_card_does_not_show_plan_b_hours_when_user_has_no_business(): void
    {
        $user = User::factory()->client()->create();
        $category = $this->expenseCategory($user);

        CategoryGoal::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_amount' => 500.00,
        ]);

        $response = $this->actingAs($user)->get(route('finance.index'));

        $response->assertOk();
        $response->assertDontSee('horas de Plano B');
    }

    public function test_goal_card_does_not_show_plan_b_hours_when_business_has_no_profit_per_hour(): void
    {
        $user = User::factory()->client()->create();
        $category = $this->expenseCategory($user);

        CategoryGoal::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_amount' => 500.00,
        ]);

        Business::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'type' => 'driver',
            'name' => 'Uber Inativo',
            'tax_regime' => 'none',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('finance.index'));

        $response->assertOk();
        $response->assertDontSee('horas de Plano B');
    }

    public function test_goal_card_shows_multiple_businesses_with_names_when_both_have_profit(): void
    {
        $user = User::factory()->client()->create();
        $category = $this->expenseCategory($user);

        CategoryGoal::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_amount' => 500.00,
        ]);

        $uber = Business::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'type' => 'driver',
            'name' => 'Uber',
            'tax_regime' => 'none',
            'status' => 'active',
        ]);
        app(PlanBLedger::class)->recordShift($uber, 500, 100, 10, 'uber'); // 50/h -> 10h

        $app99 = Business::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'type' => 'driver',
            'name' => '99 Pop',
            'tax_regime' => 'none',
            'status' => 'active',
        ]);
        app(PlanBLedger::class)->recordShift($app99, 1000, 200, 10, '99'); // 100/h -> 5h

        $response = $this->actingAs($user)->get(route('finance.index'));

        $response->assertOk();
        $response->assertSee('custa 10 horas de Plano B (Uber)');
        $response->assertSee('custa 5 horas de Plano B (99 Pop)');
    }
}

