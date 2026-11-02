<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\FinanceRecord;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreditCardInvoiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pdf_items_are_staged_and_manual_purchase_duplicates_require_confirmation(): void
    {
        $client = User::factory()->client()->create();
        $category = Category::create([
            'user_id' => $client->id,
            'name' => 'Alimentação',
            'kind' => 'expense',
            'color' => '#f97316',
            'active' => true,
        ]);
        $card = CreditCard::create([
            'user_id' => $client->id,
            'name' => 'Cartão principal',
            'last_four' => '1234',
            'due_day' => 10,
            'color' => '#7c3aed',
            'active' => true,
        ]);
        $manualRecord = FinanceRecord::create([
            'user_id' => $client->id,
            'category_id' => $category->id,
            'credit_card_id' => $card->id,
            'type' => 'expense',
            'description' => 'Mercado Central (2/3)',
            'amount' => 50,
            'occurred_on' => '2026-11-10',
            'purchased_on' => '2026-10-15',
            'source' => 'manual_credit_card',
            'source_reference' => 'manual-card:test:2',
            'installment_group' => '10000000-0000-4000-8000-000000000000',
            'installment_number' => 2,
            'installment_total' => 3,
        ]);
        SystemSetting::write('cognition_url', 'http://cognition:8000');
        SystemSetting::write('gemini_api_key', 'configured-key', true);
        Http::fake(['http://cognition:8000/parse-invoice' => Http::response([
            'provider' => 'gemini',
            'items' => [
                [
                    'description' => 'Mercado Central',
                    'amount' => 50,
                    'purchase_date' => '2026-10-15',
                    'installment_current' => 2,
                    'installment_total' => 3,
                    'category_id' => $category->id,
                    'confidence' => 0.96,
                ],
                [
                    'description' => 'Padaria Bairro',
                    'amount' => 18.9,
                    'purchase_date' => '2026-10-18',
                    'installment_current' => 1,
                    'installment_total' => 1,
                    'category_id' => $category->id,
                    'confidence' => 0.91,
                ],
            ],
        ])]);

        $pdf = UploadedFile::fake()->createWithContent('fatura.pdf', "%PDF-1.4\nconteudo de teste");
        $this->actingAs($client)->post(route('credit-card-invoices.store'), [
            'credit_card_id' => $card->id,
            'reference_month' => '2026-11',
            'invoice' => $pdf,
        ])->assertRedirect();

        $invoice = CreditCardInvoice::with('items')->firstOrFail();
        $duplicateItem = $invoice->items->firstWhere('description', 'Mercado Central');
        $regularItem = $invoice->items->firstWhere('description', 'Padaria Bairro');
        $this->assertSame($manualRecord->id, $duplicateItem->possible_duplicate_finance_record_id);
        $this->actingAs($client)->get(route('credit-card-invoices.review', $invoice))
            ->assertOk()
            ->assertSee('Possível duplicidade')
            ->assertSee('Importar mesmo assim');

        $this->actingAs($client)->post(route('credit-card-invoices.import', $invoice), [
            'items' => [$duplicateItem->id],
            'category_ids' => [$duplicateItem->id => $category->id],
        ])->assertSessionHasErrors('confirm_duplicates');
        $this->assertSame(1, FinanceRecord::where('user_id', $client->id)->count());

        $this->actingAs($client)->post(route('credit-card-invoices.import', $invoice), [
            'items' => [$duplicateItem->id],
            'category_ids' => [$duplicateItem->id => $category->id],
            'confirm_duplicates' => [$duplicateItem->id],
        ])->assertRedirect(route('finance.index', ['month' => '2026-11']));

        $this->assertSame(3, FinanceRecord::where('user_id', $client->id)->count());
        $this->assertDatabaseHas('finance_records', [
            'source_reference' => "cc-invoice:{$invoice->id}:item:{$duplicateItem->id}:installment:2",
            'occurred_on' => '2026-11-15',
            'amount' => 50,
        ]);
        $this->assertDatabaseHas('finance_records', [
            'source_reference' => "cc-invoice:{$invoice->id}:item:{$duplicateItem->id}:installment:3",
            'occurred_on' => '2026-12-15',
            'amount' => 50,
        ]);
        $this->assertDatabaseMissing('finance_records', ['description' => 'Padaria Bairro']);
        $this->assertNotNull($invoice->fresh()->imported_at);
        $this->assertNull($regularItem->fresh()->imported_at);
    }

    public function test_user_cannot_upload_an_invoice_to_another_users_card(): void
    {
        $client = User::factory()->client()->create();
        $card = CreditCard::create([
            'user_id' => User::factory()->client()->create()->id,
            'name' => 'Cartão alheio',
            'last_four' => '0001',
            'color' => '#111827',
            'active' => true,
        ]);
        $pdf = UploadedFile::fake()->createWithContent('fatura.pdf', "%PDF-1.4\nconteudo");

        $this->actingAs($client)->post(route('credit-card-invoices.store'), [
            'credit_card_id' => $card->id,
            'reference_month' => '2026-11',
            'invoice' => $pdf,
        ])->assertNotFound();
    }
}
