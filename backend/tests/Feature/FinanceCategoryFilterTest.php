<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FinanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinanceCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('filters')]
    public function test_filters_only_the_listing_within_the_clients_month(string $type, bool $filterCategory, array $expected): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client, ['active' => false]);
        $incomeCategory = $this->category(null, ['name' => 'Salário', 'kind' => 'income']);
        $this->record($client, ['category_id' => $category->id, 'description' => 'Mercado']);
        $this->record($client, ['description' => 'Sem categoria']);
        $this->record($client, ['category_id' => $incomeCategory->id, 'type' => 'income', 'description' => 'Pagamento']);
        $this->record($client, ['category_id' => $category->id, 'description' => 'Outro mês', 'occurred_on' => '2026-06-10']);
        $otherClient = User::factory()->client()->create();
        $privateCategory = $this->category($otherClient, ['name' => 'Categoria privada']);
        $this->record($otherClient, ['category_id' => $incomeCategory->id, 'type' => 'income', 'description' => 'Outro usuário']);

        $response = $this->actingAs($client)->get(route('finance.index', [
            'month' => '2026-07', 'filter_type' => $type,
            'filter_category' => $filterCategory ? $category->id : '',
        ]))->assertOk()->assertSee('Só entradas')->assertSee('Só saídas')
            ->assertDontSee($privateCategory->name)
            ->assertViewHas('records', fn ($records) => $records->pluck('description')->all() === $expected)
            ->assertViewHas('income', fn ($income) => (float) $income === 100.0)
            ->assertViewHas('expense', fn ($expense) => (float) $expense === 200.0);

        if ($filterCategory) {
            $response->assertSee('value="'.$category->id.'" selected', false);
        }
        if ($expected === []) {
            $response->assertSee('Nenhum lançamento com esses filtros no mês selecionado');
        }
    }

    public static function filters(): array
    {
        return [
            'all records' => ['', false, ['Pagamento', 'Sem categoria', 'Mercado']],
            'only income' => ['income', false, ['Pagamento']],
            'only expenses' => ['expense', false, ['Sem categoria', 'Mercado']],
            'only category' => ['', true, ['Mercado']],
            'combined filters' => ['expense', true, ['Mercado']],
            'no matching records' => ['income', true, []],
        ];
    }

    public function test_all_records_button_clears_filters_and_keeps_the_selected_month(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);
        $this->record($client, ['category_id' => $category->id, 'description' => 'Categoria selecionada']);
        $this->record($client, ['description' => 'Outra categoria']);

        $allUrl = route('finance.index', ['month' => '2026-07']);
        $filteredUrl = route('finance.index', [
            'month' => '2026-07',
            'filter_category' => $category->id,
            'filter_type' => 'expense',
        ]);

        $this->actingAs($client)->get($filteredUrl)
            ->assertOk()
            ->assertSee('Todos os lançamentos')
            ->assertSee('href="'.e($allUrl).'"', false)
            ->assertViewHas('records', fn ($records) => $records->total() === 1);

        $this->get($allUrl)
            ->assertOk()
            ->assertSee('Categoria selecionada')
            ->assertSee('Outra categoria')
            ->assertViewHas('selectedCategoryId', null)
            ->assertViewHas('selectedType', null)
            ->assertViewHas('records', fn ($records) => $records->total() === 2);
    }

    public function test_both_filters_survive_pagination_and_editing_independently_of_the_record_category(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);
        $otherCategory = $this->category($client, ['name' => 'Transporte']);
        $record = $this->record($client, ['category_id' => $category->id]);
        foreach (range(1, 20) as $index) {
            $this->record($client, ['category_id' => $category->id, 'description' => 'Compra '.$index]);
        }
        $this->record($client, ['category_id' => $otherCategory->id, 'description' => 'Não selecionado']);
        $filters = ['month' => '2026-07', 'page' => 2, 'filter_category' => $category->id, 'filter_type' => 'expense'];
        $listingUrl = route('finance.index', $filters);
        $editUrl = route('finance.edit', ['finance' => $record] + $filters);
        $updateUrl = route('finance.update', ['finance' => $record] + $filters);
        $nextPageUrl = route('finance.index', [
            'month' => '2026-07', 'filter_category' => $category->id, 'filter_type' => 'expense', 'page' => 2,
        ]);

        $this->actingAs($client)->get(route('finance.index', array_replace($filters, ['page' => 1])))
            ->assertSee('href="'.e($nextPageUrl).'" rel="next"', false)
            ->assertViewHas('records', fn ($records) => $records->count() === 20 && $records->total() === 21);
        $this->get($listingUrl)->assertSee($editUrl)->assertDontSee('Não selecionado');
        $this->get($editUrl)->assertSee('action="'.e($updateUrl).'"', false)
            ->assertSee('href="'.e($listingUrl).'">Cancelar edição', false)
            ->assertSee(route('finance.index', ['month' => '2026-08', 'filter_category' => $category->id, 'filter_type' => 'expense']));
        $this->put($updateUrl, [
            'type' => 'expense', 'category_id' => $otherCategory->id,
            'description' => 'Registro alterado', 'amount' => 75, 'occurred_on' => '2026-07-10',
        ])->assertRedirect($listingUrl)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('finance_records', ['id' => $record->id, 'category_id' => $otherCategory->id, 'amount' => 75]);
    }

    public function test_another_users_category_is_not_available_as_a_filter(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category(User::factory()->client()->create());

        $this->actingAs($client)->get(route('finance.index', ['filter_category' => $category->id]))->assertNotFound();
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $filters): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('finance.index', $filters))->assertNotFound();
    }

    public static function invalidFilters(): array
    {
        return [
            'invalid type' => [['filter_type' => 'other']],
            'invalid category' => [['filter_category' => 'abc']],
            'missing category' => [['filter_category' => 999999]],
        ];
    }

    private function category(?User $client, array $attributes = []): Category
    {
        return Category::create(array_merge([
            'user_id' => $client?->id, 'name' => 'Alimentação', 'kind' => 'expense',
            'color' => '#16a34a', 'active' => true,
        ], $attributes));
    }

    private function record(User $client, array $attributes = []): FinanceRecord
    {
        return FinanceRecord::create(array_merge([
            'user_id' => $client->id, 'type' => 'expense', 'description' => 'Registro original',
            'amount' => 100, 'occurred_on' => '2026-07-10', 'source' => 'manual',
        ], $attributes));
    }
}
