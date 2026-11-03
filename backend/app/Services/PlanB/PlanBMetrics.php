<?php

namespace App\Services\PlanB;

use App\Models\Business;
use App\Models\DriverShift;
use App\Models\FinanceRecord;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Cálculos do Plano B. Tudo é aritmética sobre os lançamentos do negócio: não usa IA.
 * Valores monetários em reais (float com 2 casas na saída).
 */
class PlanBMetrics
{
    /** DAS-MEI de serviços em 2026 (Contábeis, nov/2026). Atualize uma vez por ano. */
    public const MEI_DAS_MONTHLY = 86.05;

    /**
     * @return array<string, float|int|null>
     */
    public function monthSummary(Business $business, CarbonInterface $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $records = FinanceRecord::query()
            ->where('business_id', $business->id)
            ->where('user_id', $business->user_id)
            ->where('entry_kind', 'operational')
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()]);
        $income = (float) (clone $records)->where('type', 'income')->sum('amount');
        $expenses = (float) (clone $records)->where('type', 'expense')->sum('amount');

        $shifts = DriverShift::query()->where('business_id', $business->id)->where('user_id', $business->user_id)
            ->whereBetween('worked_on', [$start->toDateString(), $end->toDateString()]);
        $km = (float) (clone $shifts)->sum('km');
        $driverHours = (float) (clone $shifts)->sum('hours');

        $projectMinutes = (int) TimeEntry::query()->where('user_id', $business->user_id)
            ->whereIn('project_id', $business->projects()->withoutGlobalScopes()->select('id'))
            ->whereBetween('worked_on', [$start->toDateString(), $end->toDateString()])
            ->sum('minutes');
        $hours = $driverHours + $projectMinutes / 60;

        $vehicle = $business->assets()->withoutGlobalScopes()->where('kind', 'vehicle')->first();
        $fixed = (float) $business->assets()->withoutGlobalScopes()->sum('fixed_monthly_cost');
        $depreciation = $business->assets()->withoutGlobalScopes()->get()->sum(fn ($a) => $a->monthlyDepreciation());
        $maintenanceProvision = $vehicle ? $vehicle->maintenance_per_km * $km : 0.0;
        $tax = $this->taxEstimate($business, $income);

        $totalCosts = $expenses + $fixed + $depreciation + $maintenanceProvision;
        $profit = $income - $totalCosts - $tax;

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'fixed' => round($fixed, 2),
            'depreciation' => round($depreciation, 2),
            'maintenance_provision' => round($maintenanceProvision, 2),
            'tax_estimate' => round($tax, 2),
            'profit' => round($profit, 2),
            'hours' => round($hours, 2),
            'km' => round($km, 1),
            'profit_per_hour' => $hours > 0 ? round($profit / $hours, 2) : null,
            'cost_per_km' => $km > 0 ? round($totalCosts / $km, 2) : null,
            'goal_progress' => $business->goal_monthly_profit ? round(100 * $profit / $business->goal_monthly_profit, 1) : null,
        ];
    }

    public function taxEstimate(Business $business, float $income): float
    {
        return match ($business->tax_regime) {
            'mei' => $income > 0 ? self::MEI_DAS_MONTHLY : 0.0,
            // Carnê-Leão depende da tabela do ano e de deduções: usamos a reserva percentual definida pelo usuário.
            'carne_leao' => $income * $business->reserve_tax_percent / 100,
            default => 0.0,
        };
    }

    /**
     * Custo real por km dos últimos 30 dias; sem histórico, estima pelos dados do carro.
     */
    public function costPerKm(Business $business): ?float
    {
        $since = now()->subDays(30)->toDateString();
        $km = (float) DriverShift::query()->where('business_id', $business->id)->where('user_id', $business->user_id)->where('worked_on', '>=', $since)->sum('km');
        $vehicle = $business->assets()->withoutGlobalScopes()->where('kind', 'vehicle')->first();
        if ($km >= 100) {
            $expenses = (float) FinanceRecord::query()->where('business_id', $business->id)->where('user_id', $business->user_id)
                ->where('entry_kind', 'operational')->where('type', 'expense')->where('occurred_on', '>=', $since)->sum('amount');
            $fixed = $vehicle ? $vehicle->fixed_monthly_cost + $vehicle->monthlyDepreciation() : 0.0;
            $maintenance = $vehicle ? $vehicle->maintenance_per_km * $km : 0.0;

            return round(($expenses + $fixed + $maintenance) / $km, 2);
        }

        return null;
    }

    /**
     * Avaliador de corrida: vale a pena aceitar esta oferta?
     *
     * @return array{cost_per_km: float, profit: float, profit_per_km: float, profit_per_hour: float|null, worth: bool}|null
     */
    public function evaluateRide(Business $business, float $value, float $km, ?float $minutes): ?array
    {
        $costPerKm = $this->costPerKm($business);
        if ($costPerKm === null || $km <= 0) {
            return null;
        }
        $profit = $value - $costPerKm * $km;
        $perHour = $minutes && $minutes > 0 ? $profit / ($minutes / 60) : null;

        return [
            'cost_per_km' => $costPerKm,
            'profit' => round($profit, 2),
            'profit_per_km' => round($profit / $km, 2),
            'profit_per_hour' => $perHour !== null ? round($perHour, 2) : null,
            'worth' => $profit > 0,
        ];
    }

    /** Saldo em caixa do negócio desde sempre (inclui retiradas e aportes). */
    public function cashBalance(Business $business): float
    {
        $base = FinanceRecord::query()->where('business_id', $business->id)->where('user_id', $business->user_id);
        $in = (float) (clone $base)->where('type', 'income')->sum('amount');
        $out = (float) (clone $base)->where('type', 'expense')->sum('amount');

        return round($in - $out, 2);
    }

    /**
     * Retirada segura: caixa menos reservas de imposto, meses fracos, manutenção e custos fixos do mês.
     *
     * @return array{cash: float, reserves: float, safe: float}
     */
    public function safeWithdrawal(Business $business): array
    {
        $summary = $this->monthSummary($business, now());
        $cash = $this->cashBalance($business);
        $reserves = $summary['tax_estimate']
            + $summary['income'] * $business->reserve_lean_percent / 100
            + $summary['maintenance_provision']
            + $summary['fixed'];

        return ['cash' => $cash, 'reserves' => round($reserves, 2), 'safe' => round(max(0, $cash - $reserves), 2)];
    }

    /** Quantas horas de Plano B pagam um objetivo pessoal, no lucro por hora atual. */
    public function hoursFor(Business $business, float $amount): ?float
    {
        $perHour = $this->monthSummary($business, now())['profit_per_hour'];

        return $perHour && $perHour > 0 ? round($amount / $perHour, 1) : null;
    }

    /**
     * Índice de sobrevivência: se a renda principal parar, quantos meses o caixa do Plano B
     * sustenta o déficit entre os gastos pessoais e o lucro do Plano B (média dos 3 últimos meses fechados).
     *
     * @return array{monthly_personal_expenses: float, monthly_plan_b_profit: float, coverage_percent: float|null, months: float|null, reserve: float}
     */
    public function survivalIndex(User $user): array
    {
        $from = now()->startOfMonth()->subMonthsNoOverflow(3);
        $to = now()->startOfMonth()->subDay();
        $expenses = (float) FinanceRecord::query()->personal()->where('user_id', $user->id)->where('type', 'expense')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])->sum('amount') / 3;

        $businesses = Business::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'active')->get();
        $profit = 0.0;
        $reserve = 0.0;
        foreach ($businesses as $business) {
            foreach ([1, 2, 3] as $m) {
                $profit += $this->monthSummary($business, now()->startOfMonth()->subMonthsNoOverflow($m))['profit'];
            }
            $reserve += max(0, $this->cashBalance($business));
        }
        $profit /= 3;
        $deficit = $expenses - $profit;

        return [
            'monthly_personal_expenses' => round($expenses, 2),
            'monthly_plan_b_profit' => round($profit, 2),
            'coverage_percent' => $expenses > 0 ? round(100 * max(0, $profit) / $expenses, 1) : null,
            // null = o Plano B já cobre todos os gastos (ou não há gastos registrados)
            'months' => $deficit > 0 ? round($reserve / $deficit, 1) : null,
            'reserve' => round($reserve, 2),
        ];
    }
}
