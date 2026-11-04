<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\PlanB\PlanBLedger;
use App\Services\PlanB\PlanBMetrics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HourlyRateAlertTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_set_primary_salary_and_hours_in_account(): void
    {
        $user = User::factory()->client()->create([
            'primary_salary' => null,
            'primary_monthly_hours' => null,
        ]);

        $this->actingAs($user)->put(route('account.income.update'), [
            'primary_salary' => 5000,
            'primary_monthly_hours' => 160,
        ])->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals(5000.0, (float) $user->primary_salary);
        $this->assertEquals(160.0, (float) $user->primary_monthly_hours);
        $this->assertSame(31.25, $user->primaryHourlyRate());
    }

    public function test_invalid_primary_income_values_are_rejected(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->put(route('account.income.update'), [
            'primary_salary' => -100,
            'primary_monthly_hours' => 0,
        ])->assertSessionHasErrors(['primary_salary', 'primary_monthly_hours']);
    }

    public function test_metrics_detects_underpaid_hourly_rate_and_well_paid_hourly_rate(): void
    {
        $user = User::factory()->client()->create([
            'primary_salary' => 4800,
            'primary_monthly_hours' => 160, // R$ 30,00/h
        ]);

        $business = Business::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'type' => 'driver',
            'name' => 'Driver Test',
            'tax_regime' => 'mei',
        ]);

        $ledger = app(PlanBLedger::class);
        $metrics = app(PlanBMetrics::class);

        // Turno gerando R$ 100 de ganho bruto para 10h (R$ 10/h antes dos custos, bem abaixo de R$ 30/h)
        $ledger->recordShift($business, 100, 50, 10, 'uber');

        $alert = $metrics->hourlyRateAlert($business, $user);
        $this->assertNotNull($alert);
        $this->assertTrue($alert['is_underpaid']);
        $this->assertSame(30.0, $alert['primary_rate']);
        $this->assertLessThan(30.0, $alert['plan_b_rate']);
        $this->assertGreaterThan(0, $alert['difference']);

        // Agora adicionamos um turno de R$ 1000 em 5h (lucro por hora sobe)
        $ledger->recordShift($business, 1000, 50, 5, 'uber');
        $alertUpdated = $metrics->hourlyRateAlert($business, $user);
        $this->assertNotNull($alertUpdated);
        $this->assertFalse($alertUpdated['is_underpaid']);
        $this->assertGreaterThanOrEqual(30.0, $alertUpdated['plan_b_rate']);
    }

    public function test_planb_views_show_underpaid_alert_when_rate_is_below_primary(): void
    {
        $user = User::factory()->client()->create([
            'primary_salary' => 5000,
            'primary_monthly_hours' => 100, // R$ 50,00/h
        ]);

        $business = Business::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'type' => 'driver',
            'name' => 'Driver View Test',
            'tax_regime' => 'mei',
        ]);

        // 10h de trabalho com pouco ganho
        app(PlanBLedger::class)->recordShift($business, 200, 100, 10, 'uber');

        // Painel index
        $this->actingAs($user)->get(route('planb.index'))
            ->assertOk()
            ->assertSee('Hora mal paga');

        // Painel show
        $this->actingAs($user)->get(route('planb.show', $business))
            ->assertOk()
            ->assertSee('Alerta de hora mal paga')
            ->assertSee('abaixo da sua hora na renda principal');
    }

    public function test_weekly_summary_command_includes_hourly_rate_alert_in_telegram(): void
    {
        \App\Models\SystemSetting::write('telegram_bot_token', '123456:test-token', true);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->client()->create([
            'telegram_user_id' => '123999',
            'telegram_chat_id' => '123999',
            'primary_salary' => 6000,
            'primary_monthly_hours' => 150, // R$ 40,00/h
        ]);

        $business = Business::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'type' => 'driver',
            'name' => 'Uber Alerta',
            'tax_regime' => 'mei',
        ]);

        app(PlanBLedger::class)->recordShift($business, 150, 100, 10, 'uber');

        $this->artisan('mia:planb-notify', ['--weekly' => true])->assertSuccessful();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Resumo semanal')
            && str_contains((string) $request['text'], 'Alerta de hora mal paga'));
    }
}

