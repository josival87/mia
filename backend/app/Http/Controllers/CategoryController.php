<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        return $this->render($request);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(['income', 'expense', 'task'])],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        Category::updateOrCreate(['user_id' => $request->user()->id, 'name' => $data['name'], 'kind' => $data['kind']], $data + ['active' => true]);

        return back()->with('success', 'Categoria salva.');
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorize('delete', $category);
        $category->delete();

        return back()->with('success', 'Categoria personalizada removida.');
    }

    public function edit(Request $request, Category $category): View
    {
        $this->authorize('update', $category);

        return $this->render($request, $category);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('categories')->where('user_id', $request->user()->id)
                    ->where('kind', $request->input('kind'))->ignore($category),
            ],
            'kind' => ['required', Rule::in(['income', 'expense', 'task'])],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'name.unique' => 'Você já possui uma categoria com esse nome para esse uso.',
        ]);
        $category->update($data);

        return redirect()->route('categories.index')->with('success', 'Categoria atualizada.');
    }

    private function render(Request $request, ?Category $editCategory = null): View
    {
        $categories = Category::availableTo($request->user())->orderByRaw('user_id nulls first')->orderBy('kind')->orderBy('name')->get()->groupBy('kind');

        return view('categories.index', compact('categories', 'editCategory'));
    }
}
