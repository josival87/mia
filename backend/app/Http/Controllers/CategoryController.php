<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::availableTo($request->user())->orderByRaw('user_id nulls first')->orderBy('kind')->orderBy('name')->get()->groupBy('kind');
        return view('categories.index', compact('categories'));
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
        abort_unless($category->user_id === $request->user()->id, 403);
        $category->delete();
        return back()->with('success', 'Categoria personalizada removida.');
    }
}
