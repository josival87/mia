<?php

namespace Tests\Feature;

use App\Models\FinanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinanceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_record_on_page_two_preserves_the_listing_context(): void
    {
        $client = User::factory()->client()->create();
        $record = $this->record($client);
        foreach (range(1, 20) as $index) {
            $this->record($client, ['description' => 'Lançamento '.$index]);
        }
        $listingUrl = route('finance.index', ['month' => '2026-07', 'page' => 2]);
        $editUrl = route('finance.edit', ['finance' => $record, 'month' => '2026-07', 'page' => 2]);
        $updateUrl = route('finance.update', ['finance' => $record, 'month' => '2026-07', 'page' => 2]);

        $this->actingAs($client)->get($listingUrl)
            ->assertOk()->assertSee($editUrl)
            ->assertSee('Registro original')->assertDontSee('Lançamento 20');
        $this->get($editUrl)->assertOk()
            ->assertSee('action="'.e($updateUrl).'"', false)
            ->assertSee('href="'.e($listingUrl).'">Cancelar edição', false)
            ->assertSee('href="'.e(route('finance.index', ['month' => '2026-07', 'page' => 1])).'" rel="prev"', false)
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next');
        $this->get(route('finance.edit', ['finance' => $record, 'month' => '2026-07', 'page' => 1]))
            ->assertSee('href="'.e($listingUrl).'" rel="next"', false);
        $this->put($updateUrl, [
            'type' => 'expense', 'description' => 'Registro alterado',
            'amount' => 75, 'occurred_on' => '2026-07-10',
        ])->assertRedirect($listingUrl)->assertSessionHas('success', 'Lançamento atualizado.');

        $this->assertDatabaseHas('finance_records', [
            'id' => $record->id, 'description' => 'Registro alterado', 'amount' => 75,
        ]);
        $this->get($listingUrl)->assertOk()->assertSee('Registro alterado');
    }

    public function test_changing_the_record_month_returns_to_the_original_listing(): void
    {
        $client = User::factory()->client()->create();
        $record = $this->record($client);

        $this->actingAs($client)->put(route('finance.update', [
            'finance' => $record, 'month' => '2026-07', 'page' => 3,
        ]), [
            'type' => 'expense', 'description' => 'Registro alterado',
            'amount' => 75, 'occurred_on' => '2026-08-10',
        ])->assertRedirect(route('finance.index', ['month' => '2026-07', 'page' => 3]));

        $this->assertSame('2026-08-10', $record->fresh()->occurred_on->toDateString());
    }

    public function test_update_without_listing_context_returns_to_page_one_of_the_record_month(): void
    {
        $client = User::factory()->client()->create();
        $record = $this->record($client);

        $this->actingAs($client)->put(route('finance.update', $record), [
            'type' => 'expense', 'description' => 'Registro alterado',
            'amount' => 75, 'occurred_on' => '2026-08-10',
        ])->assertRedirect(route('finance.index', ['month' => '2026-08', 'page' => 1]));

        $this->assertDatabaseHas('finance_records', ['id' => $record->id, 'description' => 'Registro alterado']);
    }

    public function test_invalid_record_returns_to_edit_with_the_original_page(): void
    {
        $client = User::factory()->client()->create();
        $record = $this->record($client);
        $context = ['finance' => $record, 'month' => '2026-07', 'page' => 2];
        $editUrl = route('finance.edit', $context);

        $this->actingAs($client)->from($editUrl)->put(route('finance.update', $context), [
            'type' => 'expense', 'description' => 'Registro alterado',
            'amount' => 0, 'occurred_on' => '2026-07-10',
        ])->assertRedirect($editUrl)->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('finance_records', ['id' => $record->id, 'description' => 'Registro original', 'amount' => 50]);
    }

    #[DataProvider('invalidContexts')]
    public function test_invalid_return_context_does_not_update_the_record(array $context, string $field): void
    {
        $client = User::factory()->client()->create();
        $record = $this->record($client);

        $this->actingAs($client)->put(route('finance.update', ['finance' => $record] + $context), [
            'type' => 'expense', 'description' => 'Registro alterado',
            'amount' => 75, 'occurred_on' => '2026-07-10',
        ])->assertSessionHasErrors($field);

        $this->assertDatabaseHas('finance_records', ['id' => $record->id, 'description' => 'Registro original', 'amount' => 50]);
    }

    public static function invalidContexts(): array
    {
        return [
            'invalid month' => [['month' => '2026-99'], 'month'],
            'invalid page' => [['page' => 'abc'], 'page'],
            'nonpositive page' => [['page' => 0], 'page'],
        ];
    }

    private function record(User $client, array $attributes = []): FinanceRecord
    {
        return FinanceRecord::create(array_merge([
            'user_id' => $client->id, 'type' => 'expense',
            'description' => 'Registro original', 'amount' => 50,
            'occurred_on' => '2026-07-10', 'source' => 'manual',
        ], $attributes));
    }
}
