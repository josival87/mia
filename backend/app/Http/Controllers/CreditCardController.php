<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCardController extends Controller
{
    public function index(Request $request): View
    {
        return $this->render($request);
    }

    public function store(Request $request): RedirectResponse
    {
        CreditCard::create($this->validated($request) + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Cartão cadastrado com sucesso.');
    }

    public function edit(Request $request, CreditCard $creditCard): View
    {
        $this->authorizeOwner($request, $creditCard);

        return $this->render($request, $creditCard);
    }

    public function update(Request $request, CreditCard $creditCard): RedirectResponse
    {
        $this->authorizeOwner($request, $creditCard);
        $creditCard->update($this->validated($request));

        return redirect()->route('credit-cards.index')->with('success', 'Cartão atualizado.');
    }

    public function destroy(Request $request, CreditCard $creditCard): RedirectResponse
    {
        $this->authorizeOwner($request, $creditCard);
        $creditCard->delete();

        return back()->with('success', 'Cartão removido. Os gastos já importados foram preservados.');
    }

    private function render(Request $request, ?CreditCard $editCard = null): View
    {
        $cards = CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->withCount('invoices')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
        $recentInvoices = $request->user()->creditCardInvoices()
            ->with('creditCard')
            ->latest()
            ->limit(8)
            ->get();

        return view('credit-cards.index', compact('cards', 'recentInvoices', 'editCard'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'brand' => ['nullable', 'string', 'max:40'],
            'last_four' => ['required', 'digits:4'],
            'closing_day' => ['nullable', 'integer', 'between:1,31'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'active' => ['required', 'boolean'],
        ]);
    }

    private function authorizeOwner(Request $request, CreditCard $creditCard): void
    {
        abort_unless($creditCard->user_id === $request->user()->id, 403);
    }
}
