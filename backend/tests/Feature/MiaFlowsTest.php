<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FinanceRecord;
use App\Models\PendingTelegramRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MiaFlowsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_landing_page_is_available(): void
    {
        $this->get('/')->assertOk()->assertSee('Cuide do seu dinheiro');
    }

    public function test_client_can_access_dashboard_and_create_finance_record(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $category = Category::whereNull('user_id')->where('kind', 'expense')->firstOrFail();

        $this->actingAs($client)->get('/dashboard')->assertOk()->assertSee('Saldo do mês');
        $this->actingAs($client)->post(route('finance.store'), [
            'type' => 'expense',
            'category_id' => $category->id,
            'description' => 'Registro do teste',
            'amount' => 12.34,
            'occurred_on' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('finance_records', ['user_id' => $client->id, 'description' => 'Registro do teste']);
    }

    public function test_finance_page_shows_monthly_category_table_and_pie_reports(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $incomeCategory = Category::create([
            'user_id' => $client->id,
            'name' => 'Receitas do relatório',
            'kind' => 'income',
            'color' => '#2563eb',
            'active' => true,
        ]);
        $expenseCategory = Category::create([
            'user_id' => $client->id,
            'name' => 'Despesas do relatório',
            'kind' => 'expense',
            'color' => '#ef4444',
            'active' => true,
        ]);
        $smallerIncomeCategory = Category::create([
            'user_id' => $client->id,
            'name' => 'Receitas menores do relatório',
            'kind' => 'income',
            'color' => '#0ea5e9',
            'active' => true,
        ]);
        $smallerExpenseCategory = Category::create([
            'user_id' => $client->id,
            'name' => 'Despesas menores do relatório',
            'kind' => 'expense',
            'color' => '#f97316',
            'active' => true,
        ]);

        FinanceRecord::create([
            'user_id' => $client->id,
            'category_id' => $incomeCategory->id,
            'type' => 'income',
            'description' => 'Entrada mensal do relatório',
            'amount' => 2500,
            'occurred_on' => '2026-07-05',
            'source' => 'manual',
        ]);
        FinanceRecord::create([
            'user_id' => $client->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'description' => 'Saída mensal do relatório',
            'amount' => 430,
            'occurred_on' => '2026-07-10',
            'source' => 'manual',
        ]);
        FinanceRecord::create([
            'user_id' => $client->id,
            'category_id' => $smallerIncomeCategory->id,
            'type' => 'income',
            'description' => 'Entrada menor do relatório',
            'amount' => 1000,
            'occurred_on' => '2026-07-08',
            'source' => 'manual',
        ]);
        FinanceRecord::create([
            'user_id' => $client->id,
            'category_id' => $smallerExpenseCategory->id,
            'type' => 'expense',
            'description' => 'Saída menor do relatório',
            'amount' => 75,
            'occurred_on' => '2026-07-12',
            'source' => 'manual',
        ]);
        FinanceRecord::create([
            'user_id' => $client->id,
            'category_id' => $expenseCategory->id,
            'type' => 'expense',
            'description' => 'Valor fora do mês',
            'amount' => 987.65,
            'occurred_on' => '2026-06-10',
            'source' => 'manual',
        ]);

        $this->actingAs($client)->get(route('finance.index', ['month' => '2026-07']))
            ->assertOk()
            ->assertSee('Relatório por categoria')
            ->assertSee('data-modal-open="finance-modal"', false)
            ->assertSee('id="finance-modal"', false)
            ->assertSee('Gráfico de pizza de entradas por categoria')
            ->assertSee('Gráfico de pizza de saídas por categoria')
            ->assertSee('Receitas do relatório')
            ->assertSee('Despesas do relatório')
            ->assertSeeInOrder([
                'Totais por categoria',
                'Receitas do relatório',
                'Receitas menores do relatório',
                'Despesas do relatório',
                'Despesas menores do relatório',
                'Relatório por categoria',
            ])
            ->assertSee('Total de entradas')
            ->assertSee('Total de saídas')
            ->assertSee('R$ 3.500,00')
            ->assertSee('R$ 505,00')
            ->assertDontSee('R$ 987,65');
    }

    public function test_registration_verification_and_password_flow(): void
    {
        $suffix = Str::lower(Str::random(10));
        $this->post(route('register.store'), [
            'name' => 'Nova Cliente',
            'cpf' => '98765432100',
            'email' => "nova-{$suffix}@example.test",
        ])->assertRedirect(route('verification.show'))->assertSessionHas('pending_user_id');

        $user = User::where('email', "nova-{$suffix}@example.test")->firstOrFail();
        $verification = VerificationCode::where('user_id', $user->id)->latest()->firstOrFail();

        $this->post(route('verification.renew'))->assertRedirect(route('verification.show'));
        $renewedVerification = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertNotSame($verification->id, $renewedVerification->id);
        $this->assertNotNull($verification->fresh()->used_at);

        $this->post(route('verification.verify'), ['code' => $renewedVerification->code])
            ->assertRedirect(route('password.create'));
        $this->post(route('password.store'), [
            'password' => 'Senha@12345',
            'password_confirmation' => 'Senha@12345',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_client_cannot_access_admin_panel(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $this->actingAs($client)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_open_the_telegram_bot_token_configuration_in_settings(): void
    {
        $admin = User::where('email', 'admin@mia.local')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Bot do Telegram')
            ->assertSee('Token de acesso do bot (BotFather)')
            ->assertSee('name="telegram_bot_token"', false);
    }

    public function test_admin_can_make_gemini_the_primary_encrypted_provider(): void
    {
        $admin = User::where('email', 'admin@mia.local')->firstOrFail();
        $fakeKey = 'test-gemini-key-that-must-not-be-plain-text';

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'company_name' => 'Mia Assistente',
            'ai_primary_provider' => 'gemini',
            'gemini_api_key' => $fakeKey,
            'gemini_model' => 'gemini-3.6-flash',
            'openai_model' => 'gpt-5-mini',
            'telegram_bot_username' => 'bot_Mia_Assistente',
            'telegram_update_mode' => 'polling',
            'telegram_webhook_url' => 'http://localhost:8088/telegram/webhook',
            'telegram_confirmation_amount' => '100',
            'telegram_min_confidence' => '0.70',
            'telegram_direct_confidence' => '0.90',
            'cognition_url' => 'http://cognition:8000',
        ])->assertRedirect();

        $stored = SystemSetting::where('key', 'gemini_api_key')->firstOrFail();
        $this->assertTrue($stored->encrypted);
        $this->assertNotSame($fakeKey, $stored->value);
        $this->assertSame($fakeKey, SystemSetting::read('gemini_api_key'));
        $this->assertSame('gemini', SystemSetting::read('ai_primary_provider'));
    }

    public function test_admin_can_activate_the_production_webhook(): void
    {
        $admin = User::where('email', 'admin@mia.local')->firstOrFail();
        $secret = 'MiaWebhook_Test_2026_AbCdEf123456';

        Http::fake([
            'https://api.telegram.org/bot*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'company_name' => 'Mia Assistente',
            'ai_primary_provider' => 'gemini',
            'gemini_model' => 'gemini-3.6-flash',
            'openai_model' => 'gpt-5-mini',
            'telegram_bot_token' => '123456:test-token',
            'telegram_bot_username' => 'Mia_assistente_br_bot',
            'telegram_update_mode' => 'webhook',
            'telegram_webhook_url' => 'https://mia.example.com/telegram/webhook',
            'telegram_webhook_secret' => $secret,
            'telegram_confirmation_amount' => '100',
            'telegram_min_confidence' => '0.70',
            'telegram_direct_confidence' => '0.90',
            'cognition_url' => 'http://cognition:8000',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('webhook', SystemSetting::read('telegram_update_mode'));
        $this->assertSame('https://mia.example.com/telegram/webhook', SystemSetting::read('telegram_webhook_url'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/setWebhook')
            && $request['url'] === 'https://mia.example.com/telegram/webhook'
            && $request['secret_token'] === $secret);
    }

    public function test_local_polling_receives_the_start_command_and_links_the_telegram_id(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $client->update(['telegram_user_id' => null, 'telegram_chat_id' => null]);
        VerificationCode::create([
            'user_id' => $client->id,
            'code' => '765432',
            'purpose' => 'registration',
            'expires_at' => now()->addMinutes(15),
        ]);
        SystemSetting::write('telegram_bot_token', '123456:test-token', true);
        SystemSetting::write('telegram_update_mode', 'polling');
        SystemSetting::write('telegram_webhook_secret', 'MiaWebhook_Test_2026_AbCdEf123456', true);

        Http::fake([
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [[
                    'update_id' => 881122,
                    'message' => [
                        'chat' => ['id' => 700321],
                        'from' => ['id' => 900321, 'username' => 'nova_cliente'],
                        'text' => '/start 765432',
                    ],
                ]],
            ]),
            'https://api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
        ]);

        $this->artisan('mia:telegram-poll', ['--once' => true])->assertSuccessful();

        $this->assertSame('900321', $client->fresh()->telegram_user_id);
        $this->assertSame('700321', $client->fresh()->telegram_chat_id);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], '765432'));
    }

    public function test_telegram_otp_saves_the_stable_user_id(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $client->update(['telegram_user_id' => null, 'telegram_chat_id' => null]);
        $verification = VerificationCode::create([
            'user_id' => $client->id,
            'code' => '654321',
            'purpose' => 'reconnect',
            'expires_at' => now()->addMinutes(15),
        ]);
        $secret = (string) SystemSetting::read('telegram_webhook_secret', '');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->post('/telegram/webhook', [
            'update_id' => 'otp-'.Str::uuid(),
            'message' => [
                'chat' => ['id' => 777001],
                'from' => ['id' => 991001, 'username' => 'cliente_teste'],
                'text' => '/start 654321',
            ],
        ])->assertOk();

        $this->assertSame('991001', $client->fresh()->telegram_user_id);
        $this->assertSame('777001', $client->fresh()->telegram_chat_id);
        $this->assertNotNull($verification->fresh()->used_at);
    }

    public function test_unlinked_telegram_user_can_type_the_six_digit_connection_code(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $client->update(['status' => 'pending', 'telegram_user_id' => null, 'telegram_chat_id' => null]);
        SystemSetting::write('telegram_bot_token', '123456:test-token', true);
        VerificationCode::create([
            'user_id' => $client->id,
            'code' => '246810',
            'purpose' => 'registration',
            'expires_at' => now()->addMinutes(15),
        ]);
        $secret = (string) SystemSetting::read('telegram_webhook_secret', '');

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->post('/telegram/webhook', [
            'update_id' => 'typed-code-'.Str::uuid(),
            'message' => [
                'chat' => ['id' => 777003],
                'from' => ['id' => 991003, 'username' => 'cliente_codigo'],
                'text' => '246810',
            ],
        ])->assertOk();

        $this->assertSame('991003', $client->fresh()->telegram_user_id);
        $this->assertSame('777003', $client->fresh()->telegram_chat_id);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], '246810'));
    }

    public function test_high_value_telegram_record_waits_for_confirmation_and_is_idempotent(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $client->update(['telegram_user_id' => '991002', 'telegram_chat_id' => '777002']);
        $originUpdate = 'finance-'.Str::uuid();

        Http::fake([
            'http://cognition:8000/parse' => Http::response([
                'kind' => 'finance',
                'confidence' => 0.98,
                'provider' => 'gemini',
                'data' => [
                    'type' => 'expense',
                    'description' => 'Combustível',
                    'amount' => 150,
                    'occurred_on' => now()->toDateString(),
                    'category_id' => null,
                ],
            ]),
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $message = [
            'update_id' => $originUpdate,
            'message' => [
                'chat' => ['id' => 777002],
                'from' => ['id' => 991002, 'username' => 'cliente_teste'],
                'text' => 'Paguei 150 reais de combustível',
            ],
        ];
        $secret = (string) SystemSetting::read('telegram_webhook_secret', '');
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->post('/telegram/webhook', $message)->assertOk();
        $this->assertDatabaseMissing('finance_records', ['source_reference' => $originUpdate]);
        $pending = PendingTelegramRecord::where('origin_update_id', $originUpdate)->firstOrFail();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->post('/telegram/webhook', [
            'update_id' => 'callback-'.Str::uuid(),
            'callback_query' => [
                'id' => 'callback-id',
                'from' => ['id' => 991002],
                'message' => ['chat' => ['id' => 777002]],
                'data' => 'confirm:'.$pending->token,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('finance_records', ['source_reference' => $originUpdate, 'amount' => 150]);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->post('/telegram/webhook', $message)->assertOk();
        $this->assertSame(1, FinanceRecord::where('source_reference', $originUpdate)->count());
    }
}
