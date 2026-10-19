<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\FinanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CreditCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_client_can_manage_only_their_own_cards(): void
    {
        $client = User::factory()->client()->create();
        $otherClient = User::factory()->client()->create();

        $this->actingAs($client)->post(route('credit-cards.store'), [
            'name' => 'Nubank principal',
            'brand' => 'Mastercard',
            'last_four' => '4321',
            'closing_day' => 3,
            'due_day' => 10,
            'color' => '#7c3aed',
            'active' => 1,
        ])->assertRedirect();

        $card = CreditCard::firstOrFail();
        $this->assertSame($client->id, $card->user_id);
        $this->actingAs($client)->get(route('credit-cards.index'))
            ->assertOk()
            ->assertSee('Nubank principal')
            ->assertSee('Enviar fatura');
        $this->actingAs($otherClient)->get(route('credit-cards.edit', $card))->assertRedirect(route('landing'));
        $this->actingAs($otherClient)->delete(route('credit-cards.destroy', $card))->assertForbidden();
    }

    public function test_manual_credit_card_purchase_creates_current_and_future_installments(): void
    {
        $client = User::factory()->client()->create();
        $category = Category::create([
            'user_id' => $client->id,
            'name' => 'Eletrônicos',
            'kind' => 'expense',
            'color' => '#2563eb',
            'active' => true,
        ]);
        $card = CreditCard::create([
            'user_id' => $client->id,
            'name' => 'Visa pessoal',
            'brand' => 'Visa',
            'last_four' => '9876',
            'closing_day' => 5,
            'due_day' => 12,
            'color' => '#111827',
            'active' => true,
        ]);

        $this->actingAs($client)->post(route('finance.store'), [
            'type' => 'expense',
            'category_id' => $category->id,
            'description' => 'Notebook novo',
            'amount' => 100,
            'occurred_on' => '2026-10-19',
            'payment_method' => 'credit_card',
            'credit_card_id' => $card->id,
            'card_purchase_type' => 'installments',
            'installments' => 3,
            'billing_month' => '2026-11',
        ])->assertRedirect(route('finance.index', ['month' => '2026-11']));

        $records = FinanceRecord::where('user_id', $client->id)->orderBy('installment_number')->get();
        $this->assertCount(3, $records);
        $this->assertSame(['33.34', '33.33', '33.33'], $records->pluck('amount')->all());
        $this->assertSame(['2026-11-12', '2026-12-12', '2027-01-12'], $records->map(fn ($record) => $record->occurred_on->toDateString())->all());
        $this->assertSame(['Notebook novo (1/3)', 'Notebook novo (2/3)', 'Notebook novo (3/3)'], $records->pluck('description')->all());
        $this->assertTrue($records->every(fn ($record): bool => $record->credit_card_id === $card->id && $record->source === 'manual_credit_card'));
        $this->assertSame(100.0, $records->sum(fn ($record): float => (float) $record->amount));
    }

    public function test_user_cannot_launch_a_purchase_on_another_users_card(): void
    {
        $client = User::factory()->client()->create();
        $otherClient = User::factory()->client()->create();
        $card = CreditCard::create([
            'user_id' => $otherClient->id,
            'name' => 'Cartão alheio',
            'last_four' => '1111',
            'color' => '#111827',
            'active' => true,
        ]);

        $this->actingAs($client)->post(route('finance.store'), [
            'type' => 'expense',
            'description' => 'Compra indevida',
            'amount' => 50,
            'occurred_on' => '2026-10-19',
            'payment_method' => 'credit_card',
            'credit_card_id' => $card->id,
            'card_purchase_type' => 'one_time',
            'billing_month' => '2026-11',
        ])->assertNotFound();

        $this->assertSame(0, FinanceRecord::where('user_id', $client->id)->count());
    }
}
