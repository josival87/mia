<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryGoal;
use App\Models\FinanceRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CategoryGoalProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryGoalsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_client_can_create_update_and_delete_a_recurring_expense_goal(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->expenseCategory($client);

        $this->actingAs($client)->post(route('finance.goals.store'), [
            'category_id' => $category->id,
            'monthly_amount' => 1200,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('category_goals', [
            'user_id' => $client->id,
            'category_id' => $category->id,
            'monthly_amount' => 1200,
        ]);

        $this->actingAs($client)->post(route('finance.goals.store'), [
            'category_id' => $category->id,
            'monthly_amount' => 1500,
        ])->assertRedirect();

        $this->assertSame(1, CategoryGoal::where('user_id', $client->id)->count());
        $goal = CategoryGoal::where('user_id', $client->id)->firstOrFail();
        $this->assertSame('1500.00', $goal->monthly_amount);

        $this->actingAs($client)->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Metas da semana')
            ->assertSee($category->name)
            ->assertSee('META RECORRENTE');

        $this->actingAs($client)->delete(route('finance.goals.destroy', $goal))
            ->assertRedirect();
        $this->assertDatabaseMissing('category_goals', ['id' => $goal->id]);
    }

    public function test_monthly_goal_is_split_equally_by_calendar_weeks_and_not_by_days(): void
    {
        $client = User::factory()->client()->create();
        $category = $this->expenseCategory($client);
        $goal = CategoryGoal::create([
            'user_id' => $client->id,
            'category_id' => $category->id,
            'monthly_amount' => 600,
        ])->load('category');
        $service = app(CategoryGoalProgressService::class);

        $shortFirstWeek = $service->forGoal($goal, '2026-08-01');
        $fullSecondWeek = $service->forGoal($goal, '2026-08-03');
        $septemberWeek = $service->forGoal($goal, '2026-09-07');

        $this->assertSame(6, $shortFirstWeek['weeks_in_month']);
        $this->assertSame('100.00', $shortFirstWeek['weekly_target']);
        $this->assertSame('100.00', $fullSecondWeek['weekly_target']);
        $this->assertSame(5, $septemberWeek['weeks_in_month']);
        $this->assertSame('120.00', $septemberWeek['weekly_target']);
    }

    public function test_thresholds_alert_once_per_week_and_reset_in_a_new_month(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        $client = User::factory()->client()->create();
        $category = $this->expenseCategory($client);
        CategoryGoal::create([
            'user_id' => $client->id,
            'category_id' => $category->id,
            'monthly_amount' => 600,
        ]);

        foreach ([50, 30, 10, 10, 5] as $index => $amount) {
            $record = $this->expense($client, $category, $amount, '2026-08-03', 'Gasto '.($index + 1));
            if ($index === 0) {
                $this->assertSame(50.0, app(CategoryGoalProgressService::class)->forRecord($record)['percentage']);
            }
        }

        $this->assertSame(4, $client->notifications()->count());
        $this->assertDatabaseCount('category_goal_milestones', 4);
        $this->assertSame([50, 80, 90, 100], $client->notifications()->oldest()->get()->pluck('data.threshold')->all());

        $this->expense($client, $category, 60, '2026-09-07', 'Gasto de setembro');

        $this->assertSame(5, $client->notifications()->count());
        $this->assertDatabaseHas('category_goal_milestones', [
            'period_month' => '2026-09-01',
            'threshold' => 50,
        ]);
    }

    public function test_telegram_reply_only_adds_category_percentage_and_remaining_when_goal_exists(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        $client = User::factory()->client()->create([
            'telegram_user_id' => '991700',
            'telegram_chat_id' => '771700',
        ]);
        $category = $this->expenseCategory($client);
        $goal = CategoryGoal::create([
            'user_id' => $client->id,
            'category_id' => $category->id,
            'monthly_amount' => 600,
        ]);
        $secret = 'MiaWebhook_Category_Goals_2026';
        SystemSetting::write('telegram_webhook_secret', $secret, true);
        SystemSetting::write('telegram_bot_token', '123456:test-token', true);
        SystemSetting::write('telegram_min_confidence', '0.70');
        SystemSetting::write('telegram_direct_confidence', '0.90');
        SystemSetting::write('telegram_confirmation_amount', '1000');

        Http::fake([
            'http://cognition:8000/parse' => Http::response([
                'kind' => 'finance',
                'confidence' => 0.98,
                'data' => [
                    'type' => 'expense',
                    'description' => 'Mercado',
                    'amount' => 50,
                    'occurred_on' => '2026-08-03',
                    'category_id' => $category->id,
                ],
            ]),
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->sendTelegramExpense($secret, 'goal-'.Str::uuid());

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], "Categoria: {$category->name}")
            && str_contains((string) $request['text'], 'Porcentagem: 50%')
            && str_contains((string) $request['text'], 'Restante: R$ 50,00')
            && ! str_contains((string) $request['text'], 'Gasto:')
            && ! str_contains((string) $request['text'], 'Semana:'));

        $goal->delete();
        FinanceRecord::where('user_id', $client->id)->delete();
        $this->sendTelegramExpense($secret, 'no-goal-'.Str::uuid());
        $sendMessages = collect(Http::recorded())
            ->filter(fn (array $pair) => str_ends_with($pair[0]->url(), '/sendMessage'))
            ->values();
        $lastReply = (string) $sendMessages->last()[0]['text'];

        $this->assertStringNotContainsString('Categoria:', $lastReply);
        $this->assertStringNotContainsString('Porcentagem:', $lastReply);
        $this->assertStringNotContainsString('Restante:', $lastReply);
        $this->assertStringNotContainsString('não possui meta', $lastReply);
    }

    public function test_client_cannot_create_a_goal_for_another_clients_category(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $category = $this->expenseCategory($other);

        $this->actingAs($client)->post(route('finance.goals.store'), [
            'category_id' => $category->id,
            'monthly_amount' => 500,
        ])->assertNotFound();

        $this->assertDatabaseCount('category_goals', 0);
    }

    private function expenseCategory(User $user): Category
    {
        return Category::create([
            'user_id' => $user->id,
            'name' => 'Alimentação '.$user->id,
            'kind' => 'expense',
            'color' => '#f97316',
            'active' => true,
        ]);
    }

    private function expense(User $user, Category $category, float $amount, string $date, string $description): FinanceRecord
    {
        return FinanceRecord::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => $description,
            'amount' => $amount,
            'occurred_on' => $date,
            'source' => 'manual',
        ]);
    }

    private function sendTelegramExpense(string $secret, string $updateId): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->post('/telegram/webhook', [
            'update_id' => $updateId,
            'message' => [
                'chat' => ['id' => 771700],
                'from' => ['id' => 991700, 'username' => 'cliente_meta'],
                'text' => 'Gastei 50 reais no mercado',
            ],
        ])->assertOk();
    }
}
