<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\FinanceRecord;
use App\Models\User;
use App\Services\PlanB\PlanBLedger;
use App\Services\PlanB\PlanBMetrics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_export_own_data_only(): void
    {
        $user = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        FinanceRecord::create(['user_id' => $user->id, 'type' => 'expense', 'description' => 'Meu almoço', 'amount' => 20, 'occurred_on' => today()]);
        FinanceRecord::create(['user_id' => $other->id, 'type' => 'expense', 'description' => 'Almoço alheio', 'amount' => 30, 'occurred_on' => today()]);

        $response = $this->actingAs($user)->get(route('account.export'))->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('Meu almoço', $body);
        $this->assertStringNotContainsString('Almoço alheio', $body);
        $this->assertStringNotContainsString('password', $body);
    }

    public function test_account_deletion_requires_password_and_removes_everything(): void
    {
        $user = User::factory()->client()->create(['password' => 'Senha@12345']);
        $business = Business::withoutGlobalScopes()->create(['user_id' => $user->id, 'type' => 'freelancer', 'name' => 'Design', 'tax_regime' => 'none']);
        app(PlanBLedger::class)->recordEntry($business, 'income', 100, 'Projeto');

        $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'errada', 'confirmation' => 'EXCLUIR'])->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'Senha@12345', 'confirmation' => 'EXCLUIR'])->assertRedirect(route('landing'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
        $this->assertSame(0, FinanceRecord::where('user_id', $user->id)->count());
        $this->assertGuest();
    }

    public function test_survival_index_compares_reserves_and_plan_b_with_fixed_costs(): void
    {
        $user = User::factory()->client()->create();
        $business = Business::withoutGlobalScopes()->create(['user_id' => $user->id, 'type' => 'freelancer', 'name' => 'Freela', 'tax_regime' => 'none']);
        foreach ([1, 2, 3] as $m) {
            FinanceRecord::create(['user_id' => $user->id, 'type' => 'expense', 'description' => 'Contas', 'amount' => 2000, 'occurred_on' => now()->startOfMonth()->subMonthsNoOverflow($m)->addDays(5)]);
            app(PlanBLedger::class)->recordEntry($business, 'income', 1000, 'Projeto', 'manual', now()->startOfMonth()->subMonthsNoOverflow($m)->addDays(5)->toDateString());
        }

        $index = app(PlanBMetrics::class)->survivalIndex($user);

        $this->assertSame(2000.0, $index['monthly_personal_expenses']);
        $this->assertSame(1000.0, $index['monthly_plan_b_profit']);
        $this->assertSame(50.0, $index['coverage_percent']);
        $this->assertSame(3.0, $index['months']); // caixa de 3.000 cobre 3 meses de déficit de 1.000
    }
}
