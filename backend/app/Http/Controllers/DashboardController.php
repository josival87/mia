<?php

namespace App\Http\Controllers;

use App\Models\FinanceRecord;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $this->month($request->input('month'));
        $finances = FinanceRecord::where('user_id', $request->user()->id)
            ->whereBetween('occurred_on', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
        $income = (clone $finances)->where('type', 'income')->sum('amount');
        $expense = (clone $finances)->where('type', 'expense')->sum('amount');
        $tasks = Task::where('user_id', $request->user()->id)->where('created_at', '<=', $month->copy()->endOfMonth());
        $completed = (clone $tasks)->whereBetween('completed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count();
        $open = (clone $tasks)->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>', $month->copy()->endOfMonth()))->count();

        $recentFinances = (clone $finances)->with('category')->latest('occurred_on')->limit(5)->get();
        $priorityTasks = Task::where('user_id', $request->user()->id)->whereIn('status', ['todo', 'doing'])
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")->limit(5)->get();

        return view('dashboard.index', compact('month', 'income', 'expense', 'completed', 'open', 'recentFinances', 'priorityTasks'));
    }

    private function month(?string $value): Carbon
    {
        try { return $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth(); }
        catch (\Throwable) { return now()->startOfMonth(); }
    }
}
