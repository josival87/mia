<?php

namespace Tests\Feature;

use App\Models\BankNotificationEvent;
use App\Models\FinanceRecord;
use App\Models\PendingTelegramRecord;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class IphoneBankIngestionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_client_can_generate_and_revoke_a_personal_iphone_key(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $client->update(['telegram_chat_id' => '777100']);

        $response = $this->actingAs($client)->post(route('iphone.token.store'));
        $response->assertRedirect()->assertSessionHas('iphone_ingest_token');

        $token = session('iphone_ingest_token');
        $this->assertIsString($token);
        $this->assertStringStartsWith('mia_ios_', $token);
        $this->assertSame(hash('sha256', $token), $client->fresh()->iphone_ingest_token_hash);
        $this->assertNotSame($token, $client->fresh()->iphone_ingest_token_hash);

        $this->actingAs($client)->get(route('telegram.connection'))
            ->assertOk()
            ->assertSee('Automação bancária no iPhone')
            ->assertSee('/api/v1/iphone/eventos-bancarios');

        $this->actingAs($client)->delete(route('iphone.token.destroy'))->assertRedirect();
        $this->assertNull($client->fresh()->iphone_ingest_token_hash);
    }

    public function test_valid_sms_follows_mias_flow_and_retries_are_idempotent(): void
    {
        [$client, $token] = $this->configuredClient();
        $eventId = 'sms-'.Str::uuid();

        Http::fake([
            'http://cognition:8000/parse' => Http::response([
                'kind' => 'finance',
                'confidence' => 0.98,
                'provider' => 'gemini',
                'data' => [
                    'type' => 'expense',
                    'description' => 'Padaria Central',
                    'amount' => 42.90,
                    'occurred_on' => now()->toDateString(),
                    'category_id' => null,
                ],
            ]),
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $payload = [
            'event_id' => $eventId,
            'text' => 'Compra aprovada no valor de R$ 42,90 em Padaria Central.',
            'source' => 'sms',
            'sender' => 'Banco Exemplo',
        ];

        $this->withToken($token)->postJson('/api/v1/iphone/eventos-bancarios', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('meta.duplicate', false);

        $event = BankNotificationEvent::where('user_id', $client->id)->where('event_id', $eventId)->firstOrFail();
        $this->assertDatabaseHas('finance_records', [
            'user_id' => $client->id,
            'description' => 'Padaria Central',
            'amount' => 42.90,
            'source' => 'iphone',
            'source_reference' => 'iphone-bank:'.$event->id,
        ]);

        $this->withToken($token)->postJson('/api/v1/iphone/eventos-bancarios', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('meta.duplicate', true);

        $this->assertSame(1, FinanceRecord::where('source_reference', 'iphone-bank:'.$event->id)->count());
        Http::assertSentCount(2);
    }

    public function test_high_value_sms_waits_for_the_existing_telegram_confirmation(): void
    {
        [$client, $token] = $this->configuredClient();
        $eventId = 'pix-'.Str::uuid();

        Http::fake([
            'http://cognition:8000/parse' => Http::response([
                'kind' => 'finance',
                'confidence' => 0.98,
                'provider' => 'gemini',
                'data' => [
                    'type' => 'expense',
                    'description' => 'Pix para João',
                    'amount' => 250,
                    'occurred_on' => now()->toDateString(),
                    'category_id' => null,
                ],
            ]),
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->withToken($token)->postJson('/api/v1/iphone/eventos-bancarios', [
            'event_id' => $eventId,
            'text' => 'Pix realizado de R$ 250,00 para João.',
            'source' => 'sms',
        ])->assertAccepted()->assertJsonPath('data.status', 'pending_confirmation');

        $event = BankNotificationEvent::where('event_id', $eventId)->firstOrFail();
        $pending = PendingTelegramRecord::where('origin_update_id', 'iphone-bank:'.$event->id)->firstOrFail();
        $this->assertDatabaseMissing('finance_records', ['source_reference' => 'iphone-bank:'.$event->id]);

        $secret = (string) SystemSetting::read('telegram_webhook_secret', '');
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->post('/telegram/webhook', [
            'update_id' => 'iphone-confirm-'.Str::uuid(),
            'callback_query' => [
                'id' => 'iphone-callback-id',
                'from' => ['id' => $client->telegram_user_id],
                'message' => ['chat' => ['id' => $client->telegram_chat_id]],
                'data' => 'confirm:'.$pending->token,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('finance_records', [
            'source_reference' => 'iphone-bank:'.$event->id,
            'source' => 'iphone',
            'amount' => 250,
        ]);
    }

    public function test_authentication_codes_are_discarded_before_reaching_cognition(): void
    {
        [$client, $token] = $this->configuredClient();

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->withToken($token)->postJson('/api/v1/iphone/eventos-bancarios', [
            'event_id' => 'otp-'.Str::uuid(),
            'text' => 'Seu código de segurança é 482913. Não compartilhe este código.',
            'source' => 'sms',
            'sender' => 'Banco Exemplo',
        ])->assertAccepted()->assertJsonPath('data.status', 'ignored_sensitive');

        $event = BankNotificationEvent::where('user_id', $client->id)->latest('id')->firstOrFail();
        $this->assertSame(hash('sha256', 'Seu código de segurança é 482913. Não compartilhe este código.'), $event->content_hash);
        $this->assertDatabaseMissing('finance_records', ['source_reference' => 'iphone-bank:'.$event->id]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/parse'));
    }

    public function test_api_rejects_an_invalid_or_reused_key_with_different_content(): void
    {
        [, $token] = $this->configuredClient();
        $eventId = 'conflict-'.Str::uuid();

        Http::fake([
            'http://cognition:8000/parse' => Http::response([
                'kind' => 'finance',
                'confidence' => 0.98,
                'data' => [
                    'type' => 'expense',
                    'description' => 'Café',
                    'amount' => 9,
                    'occurred_on' => now()->toDateString(),
                    'category_id' => null,
                ],
            ]),
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->withToken('mia_ios_invalid')->postJson('/api/v1/iphone/eventos-bancarios', [
            'event_id' => $eventId,
            'text' => 'Compra de R$ 9,00',
            'source' => 'sms',
        ])->assertUnauthorized();

        $this->withToken($token)->postJson('/api/v1/iphone/eventos-bancarios', [
            'event_id' => $eventId,
            'text' => 'Compra de R$ 9,00',
            'source' => 'sms',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/iphone/eventos-bancarios', [
            'event_id' => $eventId,
            'text' => 'Compra de R$ 99,00',
            'source' => 'sms',
        ])->assertConflict();
    }

    /**
     * @return array{User, string}
     */
    private function configuredClient(): array
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $token = 'mia_ios_'.Str::random(64);
        SystemSetting::write('telegram_bot_token', '123456:test-token', true);
        $client->forceFill([
            'status' => 'active',
            'telegram_user_id' => '991100',
            'telegram_chat_id' => '777100',
            'iphone_ingest_token_hash' => hash('sha256', $token),
            'iphone_ingest_token_created_at' => now(),
        ])->save();

        return [$client->fresh(), $token];
    }
}
