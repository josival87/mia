<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\FinanceRecord;
use App\Services\CreditCardDuplicateDetector;
use App\Services\CreditCardInvoiceParser;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CreditCardInvoiceController extends Controller
{
    public function __construct(
        private readonly CreditCardInvoiceParser $parser,
        private readonly CreditCardDuplicateDetector $duplicateDetector,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'credit_card_id' => ['required', 'integer'],
            'reference_month' => ['required', 'date_format:Y-m'],
            'invoice' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $card = CreditCard::where('user_id', $request->user()->id)->findOrFail($data['credit_card_id']);
        $file = $request->file('invoice');
        $fileHash = hash_file('sha256', $file->getRealPath());
        $existingInvoice = CreditCardInvoice::where('credit_card_id', $card->id)->where('file_hash', $fileHash)->first();
        if ($existingInvoice) {
            return redirect()->route('credit-card-invoices.review', $existingInvoice)
                ->with('warning', 'Esta fatura já foi enviada. Abrimos a revisão existente.');
        }

        $referenceMonth = Carbon::createFromFormat('Y-m', $data['reference_month'])->startOfMonth();
        try {
            $parsed = $this->parser->parse($file, $request->user(), $referenceMonth);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'invoice' => 'Não foi possível interpretar a fatura. Confira se o PDF tem texto legível e se a IA está configurada.',
            ]);
        }

        $invoice = DB::transaction(function () use ($request, $card, $file, $fileHash, $referenceMonth, $parsed): CreditCardInvoice {
            $invoice = CreditCardInvoice::create([
                'user_id' => $request->user()->id,
                'credit_card_id' => $card->id,
                'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
                'file_hash' => $fileHash,
                'reference_month' => $referenceMonth,
                'status' => 'review',
                'provider' => $parsed['provider'],
            ]);
            foreach ($parsed['items'] as $item) {
                $possibleDuplicate = $this->duplicateDetector->find(
                    $request->user()->id,
                    $card,
                    $referenceMonth,
                    $item['description'],
                    (float) $item['amount'],
                    $item['purchase_date'] ?? null,
                );
                $invoice->items()->create([
                    'category_id' => $item['category_id'] ?? null,
                    'possible_duplicate_finance_record_id' => $possibleDuplicate?->id,
                    'description' => Str::squish($item['description']),
                    'amount' => abs((float) $item['amount']),
                    'purchase_date' => $item['purchase_date'] ?? null,
                    'installment_current' => $item['installment_current'],
                    'installment_total' => $item['installment_total'],
                    'ai_confidence' => $item['confidence'] ?? null,
                ]);
            }

            return $invoice;
        });

        return redirect()->route('credit-card-invoices.review', $invoice)
            ->with('success', count($parsed['items']).' lançamento(s) identificado(s). Revise antes de importar.');
    }

    public function review(Request $request, CreditCardInvoice $invoice): View
    {
        $this->authorizeOwner($request, $invoice);
        $invoice->load(['creditCard', 'items.category', 'items.possibleDuplicate']);
        $categories = Category::availableTo($request->user())
            ->where('kind', 'expense')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('credit-cards.review', compact('invoice', 'categories'));
    }

    public function import(Request $request, CreditCardInvoice $invoice): RedirectResponse
    {
        $this->authorizeOwner($request, $invoice);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer', 'distinct'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['nullable', 'integer'],
            'confirm_duplicates' => ['nullable', 'array'],
            'confirm_duplicates.*' => ['integer', 'distinct'],
        ], ['items.required' => 'Selecione pelo menos um lançamento para importar.']);
        $selectedItems = $invoice->items()->whereIn('id', $data['items'])->get();
        if ($selectedItems->count() !== count($data['items'])) {
            abort(403);
        }
        if ($invoice->imported_at) {
            return redirect()->route('credit-cards.index')->with('warning', 'Esta fatura já foi importada.');
        }
        $confirmedDuplicateIds = collect($data['confirm_duplicates'] ?? [])->map(fn ($id): int => (int) $id);
        $unconfirmedDuplicate = $selectedItems->first(fn ($item): bool => $item->possible_duplicate_finance_record_id !== null && ! $confirmedDuplicateIds->contains($item->id));
        if ($unconfirmedDuplicate) {
            throw ValidationException::withMessages([
                'confirm_duplicates' => 'Confirme os possíveis registros duplicados selecionados antes de importar.',
            ]);
        }

        $requestedCategoryIds = collect($data['category_ids'] ?? [])->filter()->map(fn ($id): int => (int) $id);
        $availableCategories = Category::availableTo($request->user())
            ->where('kind', 'expense')
            ->whereIn('id', $requestedCategoryIds->values())
            ->pluck('id')
            ->all();
        if ($requestedCategoryIds->diff($availableCategories)->isNotEmpty()) {
            throw ValidationException::withMessages(['category_ids' => 'Uma das categorias selecionadas não está disponível.']);
        }

        $created = DB::transaction(function () use ($invoice, $selectedItems, $data, $request): int {
            $created = 0;
            foreach ($selectedItems as $item) {
                $categoryId = data_get($data, 'category_ids.'.$item->id, $item->category_id);
                for ($number = $item->installment_current; $number <= $item->installment_total; $number++) {
                    $monthOffset = $number - $item->installment_current;
                    $month = $invoice->reference_month->copy()->addMonthsNoOverflow($monthOffset);
                    $day = min($item->purchase_date?->day ?? 1, $month->daysInMonth);
                    $occurredOn = $month->day($day);
                    $description = $item->description;
                    if ($item->installment_total > 1) {
                        $description = Str::limit($description, 160, '')." ({$number}/{$item->installment_total})";
                    }
                    $sourceReference = "cc-invoice:{$invoice->id}:item:{$item->id}:installment:{$number}";
                    $record = FinanceRecord::firstOrCreate(['source_reference' => $sourceReference], [
                        'user_id' => $request->user()->id,
                        'category_id' => $categoryId ?: null,
                        'credit_card_id' => $invoice->credit_card_id,
                        'type' => 'expense',
                        'title' => $invoice->creditCard->name,
                        'description' => $description,
                        'amount' => $item->amount,
                        'occurred_on' => $occurredOn,
                        'purchased_on' => $item->purchase_date,
                        'source' => 'credit_card',
                        'ai_confidence' => $item->ai_confidence,
                        'installment_number' => $number,
                        'installment_total' => $item->installment_total,
                    ]);
                    $created += $record->wasRecentlyCreated ? 1 : 0;
                }
                $item->update(['category_id' => $categoryId ?: null, 'imported_at' => now()]);
            }
            $invoice->update(['status' => 'imported', 'imported_at' => now()]);

            return $created;
        });

        return redirect()->route('finance.index', ['month' => $invoice->reference_month->format('Y-m')])
            ->with('success', "Fatura importada. {$created} gasto(s), incluindo parcelas futuras, foram lançados.");
    }

    private function authorizeOwner(Request $request, CreditCardInvoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }
}
