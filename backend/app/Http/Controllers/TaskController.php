<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request) { return $this->render($request); }

    public function store(Request $request)
    {
        Task::create($this->validated($request) + ['user_id' => $request->user()->id, 'source' => 'manual']);
        return back()->with('success', 'Atividade criada.');
    }

    public function edit(Request $request, Task $task)
    {
        $this->authorizeOwner($request, $task);
        return $this->render($request, $task);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeOwner($request, $task);
        $data = $this->validated($request);
        $data['completed_at'] = $data['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        $task->update($data);
        return redirect()->route('tasks.index')->with('success', 'Atividade atualizada.');
    }

    public function status(Request $request, Task $task)
    {
        $this->authorizeOwner($request, $task);
        $data = $request->validate(['status' => ['required', Rule::in(['todo', 'doing', 'done'])]]);
        $task->update(['status' => $data['status'], 'completed_at' => $data['status'] === 'done' ? now() : null]);
        return back()->with('success', 'Status atualizado.');
    }

    public function destroy(Request $request, Task $task)
    {
        $this->authorizeOwner($request, $task);
        $task->delete();
        return back()->with('success', 'Atividade removida.');
    }

    private function render(Request $request, ?Task $editTask = null)
    {
        try { $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth(); }
        catch (\Throwable) { $month = now()->startOfMonth(); }
        $base = Task::where('user_id', $request->user()->id)->where('created_at', '<=', $month->copy()->endOfMonth())
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>=', $month->copy()->startOfMonth()));
        $todo = (clone $base)->where(fn ($q) => $q->where('status', 'todo')->orWhere('completed_at', '>', $month->copy()->endOfMonth()))->with('category')->orderBy('due_on')->get();
        $doing = (clone $base)->where('status', 'doing')->with('category')->orderBy('due_on')->get();
        $done = (clone $base)->whereBetween('completed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->with('category')->latest('completed_at')->get();
        $categories = Category::availableTo($request->user())->where('kind', 'task')->where('active', true)->orderBy('name')->get();
        return view('tasks.index', compact('month', 'todo', 'doing', 'done', 'categories', 'editTask'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
            'category_id' => ['nullable', 'integer'],
            'due_on' => ['nullable', 'date'],
        ]);
        if (! empty($data['category_id'])) Category::availableTo($request->user())->where('kind', 'task')->findOrFail($data['category_id']);
        if ($data['status'] === 'done') $data['completed_at'] = now();
        return $data;
    }

    private function authorizeOwner(Request $request, Task $task): void { abort_unless($task->user_id === $request->user()->id, 403); }
}
