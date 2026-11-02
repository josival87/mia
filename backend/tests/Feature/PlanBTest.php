<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessAsset;
use App\Models\FinanceRecord;
use App\Models\User;
use App\Services\PlanB\PlanBLedger;
use App\Services\PlanB\PlanBMetrics;
use App\Services\PlanB\PlanBTelegramCommands;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PlanBTest extends TestCase
{
    use DatabaseTransactions;

    private function driver(User $user): Business
    {
        $business = Business::withoutGlobalScopes()->create(['user_id' => $user->id, 'type' => 'driver', 'name' => 'Uber '.uniqid(), 'tax_regime' => 'mei', 'reserve_lean_percent' => 10]);
        BusinessAsset::withoutGlobalScopes()->create([
            'business_id' => $business->id, 'user_id' => $user->id, 'kind' => 'vehicle', 'name' => 'Onix', 'ownership' => 'financed',
            'current_value' => 60000, 'residual_value' => 30000, 'useful_life_months' => 60, 'fixed_monthly_cost' => 1500, 'maintenance_per_km' => 0.12,
        ]);

        return $business;
    }

    public function test_driver_month_summary_counts_every_cost(): void
    {
        $user = User::factory()->client()->create();
        $business = $this->driver($user);
        $ledger = app(PlanBLedger::class);
        $ledger->recordShift($business, 3000, 2000, 100, 'uber');
        $ledger->recordEntry($business, 'expense', 900, 'Combustível');

        $s = app(PlanBMetrics::class)->monthSummary($business, now());

        // 3000 − 900 combustível − 1500 fixo − 500 depreciação − 240 manutenção − 86,05 DAS
        $this->assertSame(3000.0, $s['income']);
        $this->assertSame(500.0, $s['depreciation']);
        $this->assertSame(240.0, $s['maintenance_provision']);
        $this->assertSame(-226.05, $s['profit']);
        $this->assertSame(1.57, $s['cost_per_km']);
    }

    public function test_business_records_do_not_leak_into_personal_dashboard(): void
    {
        $user = User::factory()->client()->create();
        $business = $this->driver($user);
        app(PlanBLedger::class)->recordShift($business, 500, 200, 8, 'uber');

        $this->assertSame(0, FinanceRecord::personal()->where('user_id', $user->id)->count());
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('R$ 500,00');
    }

    public function test_withdrawal_moves_money_to_personal_with_two_linked_records(): void
    {
        $user = User::factory()->client()->create();
        $business = $this->driver($user);
        app(PlanBLedger::class)->recordShift($business, 800, 300, 10, 'uber');

        $this->actingAs($user)->post(route('planb.withdraw', $business), ['amount' => 300])->assertSessionHasNoErrors();

        $personal = FinanceRecord::personal()->where('user_id', $user->id)->sole();
        $this->assertSame('income', $personal->type);
        $this->assertSame('withdrawal', $personal->entry_kind);
        $this->assertSame(500.0, app(PlanBMetrics::class)->cashBalance($business));
        $this->assertSame(2, FinanceRecord::where('transfer_group_id', $personal->transfer_group_id)->count());
    }

    public function test_withdrawal_above_cash_is_refused(): void
    {
        $user = User::factory()->client()->create();
        $business = $this->driver($user);

        $this->actingAs($user)->post(route('planb.withdraw', $business), ['amount' => 50])->assertSessionHasErrors('amount');
    }

    public function test_another_users_business_returns_404(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $business = $this->driver($owner);

        $this->actingAs($intruder)->get(route('planb.show', $business))->assertNotFound();
        $this->actingAs($intruder)->post(route('planb.withdraw', $business), ['amount' => 1])->assertNotFound();
        $this->actingAs($owner)->get(route('planb.show', $business))->assertOk()->assertSee($business->name);
    }

    public function test_telegram_commands_record_shift_and_evaluate_ride_without_ai(): void
    {
        $user = User::factory()->client()->create();
        $this->driver($user);
        $commands = app(PlanBTelegramCommands::class);

        $this->assertStringContainsString('Turno salvo', $commands->handle($user, '/turno 320 180km 6h uber'));
        $reply = $commands->handle($user, '/corrida 18 12km 25min');
        $this->assertMatchesRegularExpression('/Vale a pena|Não compensa/', $reply);
        $this->assertNull($commands->handle($user, 'gastei 50 no mercado'));
    }

    public function test_freelancer_project_creates_deposit_and_delivery_receivables(): void
    {
        $user = User::factory()->client()->create();
        $business = Business::withoutGlobalScopes()->create(['user_id' => $user->id, 'type' => 'freelancer', 'name' => 'Design', 'tax_regime' => 'none']);

        $this->actingAs($user)->post(route('planb.projects.store', $business), [
            'name' => 'Logo padaria', 'contract_value' => 1200, 'deposit_percent' => 50, 'estimated_hours' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertSame([600.0, 600.0], $business->receivables()->withoutGlobalScopes()->pluck('amount')->map(fn ($v) => (float) $v)->all());
        $this->assertStringContainsString('Valor/hora efetivo: R$ 400,00', app(PlanBTelegramCommands::class)->handle($user, '/horas 3 logo'));
    }
}
