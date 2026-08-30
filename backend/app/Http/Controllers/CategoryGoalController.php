<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryGoal;
use App\Services\CategoryGoalAlertService;
use Illuminate\Http\Request;

class CategoryGoalController extends Controller
{
    public function store(Request $request, CategoryGoalAlertService $alerts)
    {
        $data = $request->validateWithBag('goal', [
            'category_id' => ['required', 'integer'],
            'monthly_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
        ], [
            'category_id.required' => 'Selecione uma categoria.',
            'monthly_amount.required' => 'Informe o valor mensal da meta.',
            'monthly_amount.min' => 'A meta deve ser maior que zero.',
        ]);
        $category = Category::availableTo($request->user())
            ->where('kind', 'expense')
            ->where('active', true)
            ->findOrFail($data['category_id']);
        $goal = CategoryGoal::updateOrCreate(
            ['user_id' => $request->user()->id, 'category_id' => $category->id],
            ['monthly_amount' => number_format((float) $data['monthly_amount'], 2, '.', '')],
        );
        $progress = $alerts->evaluateGoal($goal, now());

        return back()->with(
            'success',
            "Meta de {$category->name} salva. A Mia dividirá o valor igualmente entre {$progress['weeks_in_month']} semanas e reiniciará o controle no próximo mês."
        );
    }

    public function destroy(Request $request, CategoryGoal $goal)
    {
        abort_unless($goal->user_id === $request->user()->id, 403);
        $category = $goal->category?->name ?? 'categoria';
        $goal->delete();

        return back()->with('success', "Meta de {$category} removida.");
    }
}
