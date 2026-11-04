<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Category;
use App\Models\CategoryGoal;
use App\Models\Consent;
use App\Models\DriverShift;
use App\Models\FinanceRecord;
use App\Models\Project;
use App\Models\Receivable;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Direitos do titular (LGPD art. 18): ver, exportar e excluir os próprios dados sem pedir ao admin. */
class AccountController extends Controller
{
    public function show(): View
    {
        return view('account.show');
    }

    public function updateIncome(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'primary_salary' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'primary_monthly_hours' => ['nullable', 'numeric', 'min:1', 'max:744'],
        ]);

        $request->user()->update([
            'primary_salary' => $data['primary_salary'] ?? null,
            'primary_monthly_hours' => $data['primary_monthly_hours'] ?? null,
        ]);

        return back()->with('success', 'Informações da renda principal atualizadas com sucesso.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $data = [
            'exportado_em' => now()->toIso8601String(),
            'conta' => $user->only(['name', 'email', 'telegram', 'primary_salary', 'primary_monthly_hours', 'created_at']),
            'consentimentos' => Consent::where('user_id', $user->id)->get(['document', 'version', 'accepted_at']),
            'categorias' => Category::where('user_id', $user->id)->get(['id', 'name', 'kind', 'color', 'active']),
            'lancamentos' => FinanceRecord::where('user_id', $user->id)->orderBy('occurred_on')
                ->get(['id', 'business_id', 'category_id', 'type', 'entry_kind', 'title', 'description', 'amount', 'occurred_on', 'source', 'created_at']),
            'metas' => CategoryGoal::where('user_id', $user->id)->get(['category_id', 'monthly_amount']),
            'atividades' => Task::where('user_id', $user->id)->get(['name', 'description', 'priority', 'status', 'due_on', 'completed_at', 'created_at']),
            'plano_b' => [
                'negocios' => Business::where('user_id', $user->id)->with('assets')->get(),
                'turnos' => DriverShift::where('user_id', $user->id)->get(['business_id', 'worked_on', 'hours', 'km', 'gross', 'platform']),
                'projetos' => Project::where('user_id', $user->id)->get(),
                'horas' => TimeEntry::where('user_id', $user->id)->get(['project_id', 'worked_on', 'minutes']),
                'recebiveis' => Receivable::where('user_id', $user->id)->get(['business_id', 'project_id', 'description', 'amount', 'due_on', 'status']),
            ],
        ];

        return response()->streamDownload(
            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'mia-meus-dados-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string'], 'confirmation' => ['required', 'in:EXCLUIR']], [
            'confirmation.in' => 'Digite EXCLUIR para confirmar.',
        ]);
        $user = $request->user();
        abort_if($user->isAdmin(), 403);
        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Senha incorreta.']);
        }

        Auth::logout();
        $user->delete(); // as chaves estrangeiras apagam lançamentos, atividades, Plano B e consentimentos em cascata
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing')->with('success', 'Sua conta e todos os seus dados foram excluídos.');
    }
}
