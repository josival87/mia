<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryGoal;
use App\Models\FinanceRecord;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PolicyIdorTest extends TestCase
{
    use DatabaseTransactions;

    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->client()->create();
        $this->userB = User::factory()->client()->create();
    }

    // --- FinanceRecord IDOR Tests ---

    public function test_client_cannot_edit_another_users_finance_record(): void
    {
        $recordB = FinanceRecord::create([
            'user_id' => $this->userB->id,
            'description' => 'Gasto Usuário B',
            'amount' => 50,
            'type' => 'expense',
            'occurred_on' => now()->toDateString(),
            'source' => 'manual',
        ]);

        // GET web request redirects to landing on forbidden
        $this->actingAs($this->userA)->get(route('finance.edit', $recordB))
            ->assertRedirect(route('landing'));

        // JSON request receives 403 Forbidden
        $this->actingAs($this->userA)->getJson(route('finance.edit', $recordB))
            ->assertForbidden();
    }

    public function test_client_cannot_update_another_users_finance_record(): void
    {
        $recordB = FinanceRecord::create([
            'user_id' => $this->userB->id,
            'description' => 'Gasto Original B',
            'amount' => 50,
            'type' => 'expense',
            'occurred_on' => now()->toDateString(),
            'source' => 'manual',
        ]);

        $this->actingAs($this->userA)->put(route('finance.update', $recordB), [
            'description' => 'Tentativa de Hack',
            'amount' => 10,
            'type' => 'expense',
            'occurred_on' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame('Gasto Original B', $recordB->fresh()->description);
    }

    public function test_client_cannot_delete_another_users_finance_record(): void
    {
        $recordB = FinanceRecord::create([
            'user_id' => $this->userB->id,
            'description' => 'Gasto Seguro B',
            'amount' => 50,
            'type' => 'expense',
            'occurred_on' => now()->toDateString(),
            'source' => 'manual',
        ]);

        $this->actingAs($this->userA)->delete(route('finance.destroy', $recordB))
            ->assertForbidden();

        $this->assertDatabaseHas('finance_records', ['id' => $recordB->id]);
    }

    // --- Task IDOR Tests ---

    public function test_client_cannot_edit_another_users_task(): void
    {
        $taskB = Task::create([
            'user_id' => $this->userB->id,
            'name' => 'Tarefa B',
            'priority' => 'medium',
            'status' => 'todo',
            'source' => 'manual',
        ]);

        $this->actingAs($this->userA)->get(route('tasks.edit', $taskB))
            ->assertRedirect(route('landing'));

        $this->actingAs($this->userA)->getJson(route('tasks.edit', $taskB))
            ->assertForbidden();
    }

    public function test_client_cannot_update_another_users_task(): void
    {
        $taskB = Task::create([
            'user_id' => $this->userB->id,
            'name' => 'Tarefa B Original',
            'priority' => 'medium',
            'status' => 'todo',
            'source' => 'manual',
        ]);

        $this->actingAs($this->userA)->put(route('tasks.update', $taskB), [
            'name' => 'Tarefa B Alterada',
            'priority' => 'high',
            'status' => 'doing',
        ])->assertForbidden();

        $this->assertSame('Tarefa B Original', $taskB->fresh()->name);
    }

    public function test_client_cannot_update_status_of_another_users_task(): void
    {
        $taskB = Task::create([
            'user_id' => $this->userB->id,
            'name' => 'Tarefa B Status',
            'priority' => 'low',
            'status' => 'todo',
            'source' => 'manual',
        ]);

        $this->actingAs($this->userA)->patch(route('tasks.status', $taskB), [
            'status' => 'done',
        ])->assertForbidden();

        $this->assertSame('todo', $taskB->fresh()->status);
    }

    public function test_client_cannot_delete_another_users_task(): void
    {
        $taskB = Task::create([
            'user_id' => $this->userB->id,
            'name' => 'Tarefa B Delete',
            'priority' => 'low',
            'status' => 'todo',
            'source' => 'manual',
        ]);

        $this->actingAs($this->userA)->delete(route('tasks.destroy', $taskB))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $taskB->id]);
    }

    // --- Category IDOR Tests ---

    public function test_client_cannot_edit_another_users_category_or_global_category(): void
    {
        $categoryB = Category::create([
            'user_id' => $this->userB->id,
            'name' => 'Categoria Privada B',
            'kind' => 'expense',
            'color' => '#123456',
            'active' => true,
        ]);
        $globalCategory = Category::create([
            'user_id' => null,
            'name' => 'Global Fixa',
            'kind' => 'expense',
            'color' => '#654321',
            'active' => true,
        ]);

        $this->actingAs($this->userA)->get(route('categories.edit', $categoryB))
            ->assertRedirect(route('landing'));
        $this->actingAs($this->userA)->getJson(route('categories.edit', $categoryB))
            ->assertForbidden();

        $this->actingAs($this->userA)->get(route('categories.edit', $globalCategory))
            ->assertRedirect(route('landing'));
        $this->actingAs($this->userA)->getJson(route('categories.edit', $globalCategory))
            ->assertForbidden();
    }

    public function test_client_cannot_update_another_users_category_or_global_category(): void
    {
        $categoryB = Category::create([
            'user_id' => $this->userB->id,
            'name' => 'Cat B Original',
            'kind' => 'expense',
            'color' => '#112233',
            'active' => true,
        ]);
        $globalCategory = Category::create([
            'user_id' => null,
            'name' => 'Cat Global Original',
            'kind' => 'expense',
            'color' => '#332211',
            'active' => true,
        ]);

        $this->actingAs($this->userA)->put(route('categories.update', $categoryB), [
            'name' => 'Cat B Modificada',
            'kind' => 'expense',
            'color' => '#999999',
        ])->assertForbidden();
        $this->assertSame('Cat B Original', $categoryB->fresh()->name);

        $this->actingAs($this->userA)->put(route('categories.update', $globalCategory), [
            'name' => 'Cat Global Modificada',
            'kind' => 'expense',
            'color' => '#999999',
        ])->assertForbidden();
        $this->assertSame('Cat Global Original', $globalCategory->fresh()->name);
    }

    public function test_client_cannot_delete_another_users_category_or_global_category(): void
    {
        $categoryB = Category::create([
            'user_id' => $this->userB->id,
            'name' => 'Cat B Para Deletar',
            'kind' => 'expense',
            'color' => '#aabbcc',
            'active' => true,
        ]);
        $globalCategory = Category::create([
            'user_id' => null,
            'name' => 'Cat Global Para Deletar',
            'kind' => 'expense',
            'color' => '#ccddee',
            'active' => true,
        ]);

        $this->actingAs($this->userA)->delete(route('categories.destroy', $categoryB))
            ->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $categoryB->id]);

        $this->actingAs($this->userA)->delete(route('categories.destroy', $globalCategory))
            ->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $globalCategory->id]);
    }

    // --- CategoryGoal IDOR Tests ---

    public function test_client_cannot_delete_another_users_category_goal(): void
    {
        $categoryB = Category::create([
            'user_id' => $this->userB->id,
            'name' => 'Categoria Meta B',
            'kind' => 'expense',
            'color' => '#111111',
            'active' => true,
        ]);
        $goalB = CategoryGoal::create([
            'user_id' => $this->userB->id,
            'category_id' => $categoryB->id,
            'monthly_amount' => 500.00,
        ]);

        $this->actingAs($this->userA)->delete(route('finance.goals.destroy', $goalB))
            ->assertForbidden();

        $this->assertDatabaseHas('category_goals', ['id' => $goalB->id]);
    }
}

