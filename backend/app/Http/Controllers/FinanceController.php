<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryGoal;
use App\Models\FinanceRecord;
use App\Services\CategoryGoalProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(private readonly CategoryGoalProgressService $goalProgress) {}

    public function index(Request $request)
    {
        return $this->render($request);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        FinanceRecord::create($data + ['user_id' => $request->user()->id, 'source' => 'manual']);

        return back()->with('success', 'Lançamento salvo com sucesso.');
    }

    public function edit(Request $request, FinanceRecord $finance)
    {
        $this->authorizeOwner($request, $finance);

        return $this->render($request, $finance);
    }

    public function update(Request $request, FinanceRecord $finance)
    {
        $this->authorizeOwner($request, $finance);
        $returnTo = $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $selectedCategoryId = $this->categoryFilter($request);
        $selectedType = $this->typeFilter($request);
        $finance->update($this->validated($request));

        return redirect()->route('finance.index', [
            'month' => $returnTo['month'] ?? $finance->occurred_on->format('Y-m'),
            'page' => $returnTo['page'] ?? 1,
            'filter_category' => $selectedCategoryId,
            'filter_type' => $selectedType,
        ])->with('success', 'Lançamento atualizado.');
    }

    public function destroy(Request $request, FinanceRecord $finance)
    {
        $this->authorizeOwner($request, $finance);
        $finance->delete();

        return back()->with('success', 'Lançamento removido.');
    }

    private function render(Request $request, ?FinanceRecord $editRecord = null)
    {
        try {
            $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }
        $query = FinanceRecord::where('user_id', $request->user()->id)->whereBetween('occurred_on', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
        $selectedCategoryId = $this->categoryFilter($request);
        $recordQuery = clone $query;
        $selectedType = $this->typeFilter($request);
        if ($selectedCategoryId !== null) {
            $recordQuery->where('category_id', $selectedCategoryId);
        }
        if ($selectedType !== null) {
            $recordQuery->where('type', $selectedType);
        }
        $records = $recordQuery->with('category')->latest('occurred_on')->latest('id')->paginate(20)->withPath(route('finance.index'))->withQueryString();
        $income = (clone $query)->where('type', 'income')->sum('amount');
        $expense = (clone $query)->where('type', 'expense')->sum('amount');
        $byCategory = (clone $query)->selectRaw('category_id, type, sum(amount) as total')->with('category')->groupBy('category_id', 'type')->get();
        $incomeCategoryReport = $this->categoryReport($byCategory->where('type', 'income'), (float) $income);
        $expenseCategoryReport = $this->categoryReport($byCategory->where('type', 'expense'), (float) $expense);
        $categories = Category::availableTo($request->user())->whereIn('kind', ['income', 'expense'])->where('active', true)->orderBy('name')->get();
        $filterCategories = Category::availableTo($request->user())->whereIn('kind', ['income', 'expense'])->orderBy('name')->get();
        $goalCategories = $categories->where('kind', 'expense')->values();
        $categoryGoals = CategoryGoal::query()
            ->with('category')
            ->where('user_id', $request->user()->id)
            ->get()
            ->sortBy(fn (CategoryGoal $goal) => $goal->category?->name)
            ->values();
        $trackingDate = $month->format('Y-m') === now()->format('Y-m')
            ? now()
            : ($month->isPast() ? $month->copy()->endOfMonth() : $month->copy()->startOfMonth());
        $goalProgress = $categoryGoals
            ->map(fn (CategoryGoal $goal) => $this->goalProgress->forGoal($goal, $trackingDate))
            ->keyBy('goal_id');

        return view('finance.index', compact(
            'month',
            'records',
            'income',
            'expense',
            'incomeCategoryReport',
            'expenseCategoryReport',
            'categories',
            'filterCategories',
            'selectedCategoryId',
            'selectedType',
            'goalCategories',
            'categoryGoals',
            'goalProgress',
            'trackingDate',
            'editRecord',
        ));
    }

    private function categoryFilter(Request $request): ?int
    {
        $filter = $request->query('filter_category');
        if ($filter === null || $filter === '') {
            return null;
        }

        $categoryId = filter_var($filter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($categoryId === false, 404);
        Category::availableTo($request->user())->whereIn('kind', ['income', 'expense'])->findOrFail($categoryId);

        return $categoryId;
    }

    private function typeFilter(Request $request): ?string
    {
        $filter = $request->query('filter_type');
        if ($filter === null || $filter === '') {
            return null;
        }

        abort_unless(in_array($filter, ['income', 'expense'], true), 404);

        return $filter;
    }

    private function categoryReport(Collection $rows, float $total): array
    {
        $items = $rows->sortByDesc(fn ($row) => (float) $row->total)
            ->values()
            ->map(function ($row) use ($total) {
                $color = (string) ($row->category?->color ?? '#94a3b8');

                return [
                    'name' => $row->category?->name ?? 'Sem categoria',
                    'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#94a3b8',
                    'total' => (float) $row->total,
                    'percentage' => $total > 0 ? round(((float) $row->total / $total) * 100, 1) : 0,
                ];
            })
            ->all();

        $cursor = 0.0;
        $segments = [];
        foreach ($items as $index => $item) {
            $start = $cursor;
            $cursor += $total > 0 ? ($item['total'] / $total) * 100 : 0;
            $end = $index === array_key_last($items) ? 100 : min(100, $cursor);
            $segments[] = sprintf('%s %.4f%% %.4f%%', $item['color'], $start, $end);
        }

        return [
            'items' => $items,
            'total' => $total,
            'gradient' => $segments ? 'conic-gradient('.implode(', ', $segments).')' : '#edf1ee',
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_on' => ['required', 'date'],
        ]);
        if (! empty($data['category_id'])) {
            $category = Category::availableTo($request->user())->findOrFail($data['category_id']);
            abort_unless($category->kind === $data['type'], 422, 'Categoria incompatível com o tipo.');
        }

        return $data;
    }

    private function authorizeOwner(Request $request, FinanceRecord $record): void
    {
        abort_unless($record->user_id === $request->user()->id, 403);
    }
}
