<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessAsset;
use App\Models\FinanceRecord;
use App\Models\Project;
use App\Models\Receivable;
use App\Models\TimeEntry;
use App\Services\PlanB\PlanBLedger;
use App\Services\PlanB\PlanBMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Plano B: cada renda extra é um negócio com caixa próprio.
 * Os models usam o trait BelongsToUser: route model binding de outro usuário dá 404.
 */
class PlanBController extends Controller
{
    public function __construct(
        private readonly PlanBMetrics $metrics,
        private readonly PlanBLedger $ledger,
    ) {}

    public function index(): View
    {
        $businesses = Business::where('status', 'active')->orderBy('name')->get();
        $summaries = $businesses->mapWithKeys(fn (Business $b) => [$b->id => $this->metrics->monthSummary($b, now())]);

        return view('planb.index', compact('businesses', 'summaries'));
    }

    public function create(): View
    {
        return view('planb.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Business::TYPES))],
            'name' => ['required', 'string', 'max:80', Rule::unique('businesses')->where('user_id', $request->user()->id)],
            'tax_regime' => ['required', Rule::in(array_keys(Business::TAX_REGIMES))],
            'reserve_tax_percent' => ['nullable', 'numeric', 'between:0,50'],
            'reserve_lean_percent' => ['nullable', 'numeric', 'between:0,80'],
            'goal_monthly_profit' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'goal_weekly_hours' => ['nullable', 'numeric', 'between:0,100'],
            'vehicle_name' => ['nullable', 'required_if:type,driver', 'string', 'max:80'],
            'ownership' => ['nullable', 'required_if:type,driver', Rule::in(['owned', 'financed', 'rented'])],
            'current_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'residual_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'useful_life_months' => ['nullable', 'integer', 'between:1,240'],
            'fixed_monthly_cost' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'maintenance_per_km' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'fuel_km_per_l' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $business = DB::transaction(function () use ($data, $request) {
            $business = Business::create([
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'name' => $data['name'],
                'tax_regime' => $data['tax_regime'],
                'reserve_tax_percent' => $data['reserve_tax_percent'] ?? 0,
                'reserve_lean_percent' => $data['reserve_lean_percent'] ?? 0,
                'goal_monthly_profit' => $data['goal_monthly_profit'] ?? null,
                'goal_weekly_hours' => $data['goal_weekly_hours'] ?? null,
            ]);
            if ($data['type'] === 'driver') {
                BusinessAsset::create([
                    'business_id' => $business->id,
                    'user_id' => $request->user()->id,
                    'kind' => 'vehicle',
                    'name' => $data['vehicle_name'],
                    'ownership' => $data['ownership'],
                    'current_value' => $data['current_value'] ?? 0,
                    'residual_value' => $data['residual_value'] ?? 0,
                    'useful_life_months' => $data['useful_life_months'] ?? 60,
                    'fixed_monthly_cost' => $data['fixed_monthly_cost'] ?? 0,
                    'maintenance_per_km' => $data['maintenance_per_km'] ?? 0,
                    'fuel_km_per_l' => $data['fuel_km_per_l'] ?? null,
                ]);
            }

            return $business;
        });

        return redirect()->route('planb.show', $business)->with('success', 'Negócio criado. Registre turnos, horas e custos aqui ou pelo Telegram.');
    }

    public function show(Business $business): View
    {
        $business->load('assets');
        $summary = $this->metrics->monthSummary($business, now());
        $previous = $this->metrics->monthSummary($business, now()->subMonthNoOverflow());
        $withdrawal = $this->metrics->safeWithdrawal($business);
        $costPerKm = $business->type === 'driver' ? $this->metrics->costPerKm($business) : null;
        $records = FinanceRecord::where('business_id', $business->id)->where('user_id', $business->user_id)
            ->latest('occurred_on')->latest('id')->limit(15)->get();
        $shifts = $business->shifts()->latest('worked_on')->latest('id')->limit(10)->get();
        $projects = $business->projects()->withSum('timeEntries', 'minutes')->latest('updated_at')->get();
        $receivables = $business->receivables()->where('status', 'open')->orderBy('due_on')->get();

        return view('planb.show', compact('business', 'summary', 'previous', 'withdrawal', 'costPerKm', 'records', 'shifts', 'projects', 'receivables'));
    }

    public function storeShift(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'gross' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'km' => ['required', 'numeric', 'min:0', 'max:2000'],
            'hours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'platform' => ['nullable', 'string', 'max:40'],
            'worked_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $this->ledger->recordShift($business, (float) $data['gross'], (float) $data['km'], (float) $data['hours'], $data['platform'] ?? null, 'manual', $data['worked_on'] ?? null);

        return back()->with('success', 'Turno registrado.');
    }

    public function storeEntry(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'description' => ['required', 'string', 'max:180'],
            'occurred_on' => ['nullable', 'date'],
        ]);
        $this->ledger->recordEntry($business, $data['type'], (float) $data['amount'], $data['description'], 'manual', $data['occurred_on'] ?? null);

        return back()->with('success', 'Lançamento do negócio salvo.');
    }

    public function destroyEntry(Business $business, FinanceRecord $record): RedirectResponse
    {
        abort_unless($record->business_id === $business->id && $record->user_id === $business->user_id, 404);
        DB::transaction(function () use ($record) {
            // Uma retirada tem duas pernas (negócio e pessoal): apaga as duas juntas.
            if ($record->transfer_group_id) {
                FinanceRecord::where('user_id', $record->user_id)->where('transfer_group_id', $record->transfer_group_id)->delete();
            } else {
                $record->delete();
            }
        });

        return back()->with('success', 'Lançamento removido.');
    }

    public function storeProject(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'contract_value' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'due_on' => ['nullable', 'date'],
            'deposit_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        DB::transaction(function () use ($data, $business) {
            $project = Project::create([
                'business_id' => $business->id,
                'user_id' => $business->user_id,
                'status' => 'active',
                'name' => $data['name'],
                'client_name' => $data['client_name'] ?? null,
                'contract_value' => $data['contract_value'],
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'due_on' => $data['due_on'] ?? null,
            ]);
            $deposit = round($project->contract_value * (float) ($data['deposit_percent'] ?? 0) / 100, 2);
            if ($deposit > 0) {
                Receivable::create(['business_id' => $business->id, 'user_id' => $business->user_id, 'project_id' => $project->id, 'description' => 'Sinal — '.$project->name, 'amount' => $deposit, 'due_on' => today()]);
            }
            $rest = round($project->contract_value - $deposit, 2);
            if ($rest > 0) {
                Receivable::create(['business_id' => $business->id, 'user_id' => $business->user_id, 'project_id' => $project->id, 'description' => 'Entrega — '.$project->name, 'amount' => $rest, 'due_on' => $project->due_on ?? today()->addDays(30)]);
            }
        });

        return back()->with('success', 'Projeto criado com as parcelas a receber.');
    }

    public function storeTime(Request $request, Business $business, Project $project): RedirectResponse
    {
        abort_unless($project->business_id === $business->id, 404);
        $data = $request->validate(['hours' => ['required', 'numeric', 'min:0.1', 'max:24'], 'worked_on' => ['nullable', 'date', 'before_or_equal:today']]);
        TimeEntry::create(['project_id' => $project->id, 'user_id' => $business->user_id, 'worked_on' => $data['worked_on'] ?? today(), 'minutes' => (int) round($data['hours'] * 60)]);
        $project->touch();

        return back()->with('success', 'Horas registradas.');
    }

    public function payReceivable(Business $business, Receivable $receivable): RedirectResponse
    {
        abort_unless($receivable->business_id === $business->id && $receivable->status === 'open', 404);
        DB::transaction(function () use ($business, $receivable) {
            $record = $this->ledger->recordEntry($business, 'income', $receivable->amount, $receivable->description);
            $receivable->update(['status' => 'paid', 'paid_record_id' => $record->id]);
            $project = $receivable->project;
            if ($project && ! $project->receivables()->where('status', 'open')->exists()) {
                $project->update(['status' => 'paid']);
            }
        });

        return back()->with('success', 'Recebimento registrado no caixa do negócio.');
    }

    public function withdraw(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:9999999']]);
        $this->ledger->withdraw($business, (float) $data['amount']);

        return back()->with('success', 'Retirada registrada: o valor entrou no seu financeiro pessoal.');
    }

    public function evaluateRide(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'km' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'minutes' => ['nullable', 'numeric', 'min:1', 'max:600'],
        ]);
        $result = $this->metrics->evaluateRide($business, (float) $data['value'], (float) $data['km'], isset($data['minutes']) ? (float) $data['minutes'] : null);

        return back()->withInput()->with('ride_result', $result ?? 'sem_historico');
    }

    public function archive(Business $business): RedirectResponse
    {
        $business->update(['status' => 'archived']);

        return redirect()->route('planb.index')->with('success', 'Negócio arquivado. O histórico continua salvo.');
    }
}
