<?php

namespace App\Services\PlanB;

use App\Models\Business;
use App\Models\Category;
use App\Models\DriverShift;
use App\Models\FinanceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Escritas do Plano B que mexem em mais de uma tabela, sempre em transação. */
class PlanBLedger
{
    public function __construct(private readonly PlanBMetrics $metrics) {}

    public function recordShift(Business $business, float $gross, float $km, float $hours, ?string $platform, string $source = 'manual', ?string $date = null): DriverShift
    {
        $this->assertDriver($business);
        if ($gross <= 0 || $km < 0 || $hours <= 0 || $hours > 24 || $km > 2000) {
            throw ValidationException::withMessages(['shift' => 'Confira os valores do turno (ganho, km e horas).']);
        }
        $date ??= today()->toDateString();

        return DB::transaction(function () use ($business, $gross, $km, $hours, $platform, $source, $date) {
            $record = FinanceRecord::create([
                'user_id' => $business->user_id,
                'business_id' => $business->id,
                'type' => 'income',
                'description' => 'Turno'.($platform ? ' '.Str::limit($platform, 30, '') : ''),
                'amount' => round($gross, 2),
                'occurred_on' => $date,
                'source' => $source,
            ]);

            return DriverShift::create([
                'business_id' => $business->id,
                'user_id' => $business->user_id,
                'worked_on' => $date,
                'hours' => $hours,
                'km' => $km,
                'gross' => round($gross, 2),
                'platform' => $platform ? Str::limit($platform, 40, '') : null,
                'finance_record_id' => $record->id,
            ]);
        });
    }

    public function recordEntry(Business $business, string $type, float $amount, string $description, string $source = 'manual', ?string $date = null): FinanceRecord
    {
        if (! in_array($type, ['income', 'expense'], true) || $amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor positivo.']);
        }

        return FinanceRecord::create([
            'user_id' => $business->user_id,
            'business_id' => $business->id,
            'type' => $type,
            'description' => Str::limit(trim($description) ?: 'Lançamento do Plano B', 180, ''),
            'amount' => round($amount, 2),
            'occurred_on' => $date ?? today()->toDateString(),
            'source' => $source,
        ]);
    }

    /**
     * Retirada: sai do caixa do negócio e entra como receita pessoal na categoria "Plano B".
     * As duas pernas compartilham o transfer_group_id.
     */
    public function withdraw(Business $business, float $amount, string $source = 'manual'): string
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor positivo.']);
        }
        if ($amount > $this->metrics->cashBalance($business)) {
            throw ValidationException::withMessages(['amount' => 'A retirada é maior que o saldo do negócio.']);
        }

        return DB::transaction(function () use ($business, $amount, $source) {
            $group = (string) Str::uuid();
            $category = Category::firstOrCreate(['user_id' => null, 'name' => 'Plano B', 'kind' => 'income'], ['color' => '#0d9488', 'active' => true]);
            $base = ['user_id' => $business->user_id, 'amount' => round($amount, 2), 'occurred_on' => today()->toDateString(), 'source' => $source, 'entry_kind' => 'withdrawal', 'transfer_group_id' => $group];
            FinanceRecord::create($base + ['business_id' => $business->id, 'type' => 'expense', 'description' => 'Retirada para o pessoal']);
            FinanceRecord::create($base + ['business_id' => null, 'type' => 'income', 'category_id' => $category->id, 'description' => 'Retirada de '.Str::limit($business->name, 60, '')]);

            return $group;
        });
    }

    private function assertDriver(Business $business): void
    {
        if ($business->type !== 'driver') {
            throw ValidationException::withMessages(['business' => 'Turnos só existem em negócios de motorista.']);
        }
    }
}
