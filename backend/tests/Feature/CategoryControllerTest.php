<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FinanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_edit_links_only_for_the_clients_own_categories(): void
    {
        $client = User::factory()->client()->create();
        $own = $this->category($client);
        $global = $this->category(null, ['name' => 'Global']);
        $other = $this->category(User::factory()->client()->create(), ['name' => 'Privada']);

        $this->actingAs($client)->get(route('categories.index'))
            ->assertOk()
            ->assertSee(route('categories.edit', $own))
            ->assertSee($global->name)
            ->assertDontSee(route('categories.edit', $global))
            ->assertDontSee($other->name)
            ->assertDontSee(route('categories.edit', $other));
    }

    public function test_edit_form_loads_current_values_and_escapes_the_name(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client, ['name' => '<script>alert("x")</script>']);

        $this->actingAs($client)->get(route('categories.edit', $category))
            ->assertOk()
            ->assertSee('Editar categoria')
            ->assertSee('id="edit-category-modal" aria-hidden="false"', false)
            ->assertSee(route('categories.update', $category))
            ->assertSee('value="'.e($category->name).'"', false)
            ->assertDontSee($category->name, false)
            ->assertSee('value="expense" selected', false)
            ->assertSee('value="#16a34a"', false);
    }

    public function test_owner_can_update_name_kind_and_color_without_changing_ownership(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);

        $this->actingAs($client)->put(route('categories.update', $category), [
            'name' => 'Projetos', 'kind' => 'task', 'color' => '#123ABC',
            'user_id' => User::factory()->client()->create()->id,
            'active' => false,
        ])->assertRedirect(route('categories.index'))->assertSessionHas('success', 'Categoria atualizada.');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id, 'user_id' => $client->id,
            'name' => 'Projetos', 'kind' => 'task', 'color' => '#123ABC', 'active' => true,
        ]);
    }

    public function test_renaming_preserves_existing_record_links(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);
        $record = FinanceRecord::create([
            'user_id' => $client->id, 'category_id' => $category->id,
            'type' => 'expense', 'description' => 'Compra', 'amount' => 50,
            'occurred_on' => '2026-09-13', 'source' => 'manual',
        ]);

        $this->actingAs($client)->put(route('categories.update', $category), [
            'name' => 'Compras', 'kind' => 'expense', 'color' => '#123456',
        ])->assertRedirect(route('categories.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('finance_records', ['id' => $record->id, 'category_id' => $category->id]);
        $this->assertSame('Compras', $record->fresh()->category->name);
    }

    public function test_unchanged_name_and_names_in_other_scopes_are_allowed(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);
        $this->category(null);
        $this->category(User::factory()->client()->create());
        $this->category($client, ['kind' => 'income']);

        $this->actingAs($client)->put(route('categories.update', $category), [
            'name' => 'Pessoal', 'kind' => 'expense', 'color' => '#123456',
        ])->assertRedirect(route('categories.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'color' => '#123456']);
    }

    public function test_duplicate_name_in_the_target_kind_returns_to_edit_with_input(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);
        $this->category($client, ['name' => 'Projetos', 'kind' => 'task']);
        $editUrl = route('categories.edit', $category);

        $this->actingAs($client)->from($editUrl)->put(route('categories.update', $category), [
            'name' => 'Projetos', 'kind' => 'task', 'color' => '#123456',
        ])->assertRedirect($editUrl)
            ->assertSessionHasErrors(['name' => 'Você já possui uma categoria com esse nome para esse uso.'])
            ->assertSessionHasInput('name', 'Projetos');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Pessoal', 'kind' => 'expense']);
    }

    public function test_edit_form_displays_validation_errors_and_preserves_submitted_values(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);
        $this->actingAs($client)->withSession([
            'errors' => ['default' => [
                'format' => ':message',
                'messages' => ['name' => ['Você já possui uma categoria com esse nome para esse uso.']],
            ]],
            '_old_input' => ['name' => 'Projetos', 'kind' => 'task', 'color' => '#123456'],
        ])->get(route('categories.edit', $category))
            ->assertSee('id="edit-category-modal" aria-hidden="false"', false)
            ->assertSee('value="Projetos"', false)
            ->assertSee('value="task" selected', false)
            ->assertSee('Você já possui uma categoria com esse nome para esse uso.');
    }

    #[DataProvider('invalidUpdates')]
    public function test_invalid_fields_do_not_change_the_category(array $data, array $errors): void
    {
        $client = User::factory()->client()->create();
        $category = $this->category($client);

        $this->actingAs($client)->from(route('categories.edit', $category))
            ->put(route('categories.update', $category), $data)
            ->assertSessionHasErrors($errors);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id, 'name' => 'Pessoal', 'kind' => 'expense', 'color' => '#16a34a',
        ]);
    }

    public static function invalidUpdates(): array
    {
        return [
            'required fields' => [[], ['name', 'kind', 'color']],
            'name too long' => [['name' => str_repeat('a', 81), 'kind' => 'expense', 'color' => '#123456'], ['name']],
            'invalid name type' => [['name' => ['Pessoal'], 'kind' => 'expense', 'color' => '#123456'], ['name']],
            'invalid kind' => [['name' => 'Novo', 'kind' => 'other', 'color' => '#123456'], ['kind']],
            'invalid color' => [['name' => 'Novo', 'kind' => 'expense', 'color' => 'red'], ['color']],
        ];
    }

    public function test_other_users_and_global_categories_cannot_be_edited_or_updated(): void
    {
        $client = User::factory()->client()->create();
        $categories = [$this->category(null), $this->category(User::factory()->client()->create())];

        foreach ($categories as $category) {
            $this->actingAs($client)->get(route('categories.edit', $category))->assertRedirect(route('landing'));
            $this->put(route('categories.update', $category), [
                'name' => 'Alterada', 'kind' => 'income', 'color' => '#123456',
            ])->assertForbidden();

            $this->assertDatabaseHas('categories', [
                'id' => $category->id, 'user_id' => $category->user_id,
                'name' => 'Pessoal', 'kind' => 'expense', 'color' => '#16a34a',
            ]);
        }
    }

    public function test_guests_cannot_edit_or_update_categories(): void
    {
        $category = $this->category(User::factory()->client()->create());

        $this->get(route('categories.edit', $category))->assertRedirect(route('login'));
        $this->put(route('categories.update', $category), [
            'name' => 'Alterada', 'kind' => 'income', 'color' => '#123456',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Pessoal']);
    }

    public function test_missing_category_returns_not_found(): void
    {
        $this->actingAs(User::factory()->client()->create());

        $this->get(route('categories.edit', 999999))->assertNotFound();
        $this->put(route('categories.update', 999999), [])->assertNotFound();
    }

    public function test_owner_can_delete_the_selected_category_but_not_global_or_other_users_categories(): void
    {
        $client = User::factory()->client()->create();
        $own = $this->category($client);
        $global = $this->category(null);
        $other = $this->category(User::factory()->client()->create());

        $this->actingAs($client)->from(route('categories.index'))
            ->delete(route('categories.destroy', $own))->assertRedirect(route('categories.index'));
        $this->delete(route('categories.destroy', $global))->assertForbidden();
        $this->delete(route('categories.destroy', $other))->assertForbidden();

        $this->assertModelMissing($own);
        $this->assertModelExists($global);
        $this->assertModelExists($other);
    }

    private function category(?User $owner, array $attributes = []): Category
    {
        return Category::create(array_merge([
            'user_id' => $owner?->id, 'name' => 'Pessoal', 'kind' => 'expense',
            'color' => '#16a34a', 'active' => true,
        ], $attributes));
    }
}
