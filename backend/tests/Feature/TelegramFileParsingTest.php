<?php

namespace Tests\Feature;

use App\Models\FinanceRecord;
use App\Models\PendingTelegramRecord;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramFileParsingTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create([
            'telegram_user_id' => '789123',
            'telegram_chat_id' => '789123',
            'telegram_verified_at' => now(),
        ]);

        $this->secret = 'Mia_Secret_Receipt_2026';
        SystemSetting::write('telegram_webhook_secret', $this->secret, true);
        SystemSetting::write('telegram_bot_token', '123456:file-parsing-test-token', true);
        SystemSetting::write('cognition_url', 'http://cognition:8000');
    }

    public function test_photo_receipt_without_paid_gemini_replies_with_explanation(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'photo_file_123',
                    'file_path' => 'photos/file_1.jpg',
                    'file_size' => 2048,
                ],
            ]),
            'https://api.telegram.org/file/bot*/photos/*' => Http::response('fake-image-binary-data', 200),
            'http://cognition:8000/parse-file' => Http::response([
                'detail' => 'Leitura de arquivos exige Gemini no nível pago (GEMINI_PAID_TIER=true).',
            ], 503),
            'https://api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $this->secret)
            ->post('/telegram/webhook', [
                'update_id' => 'file-test-'.Str::uuid(),
                'message' => [
                    'chat' => ['id' => $this->client->telegram_chat_id],
                    'from' => ['id' => $this->client->telegram_user_id],
                    'photo' => [
                        ['file_id' => 'thumb_1', 'file_size' => 100],
                        ['file_id' => 'photo_file_123', 'file_size' => 2048],
                    ],
                ],
            ])->assertOk();

        // Verifies bot replied explaining Gemini paid tier requirement
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'GEMINI_PAID_TIER=true')
            && str_contains((string) $request['text'], 'privacidade'));

        $this->assertDatabaseCount('finance_records', 0);
        $this->assertDatabaseCount('pending_telegram_records', 0);
    }

    public function test_photo_receipt_with_paid_gemini_extracts_records_and_requests_confirmation(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'receipt_img_456',
                    'file_path' => 'photos/cupom.jpg',
                    'file_size' => 5000,
                ],
            ]),
            'https://api.telegram.org/file/bot*/photos/*' => Http::response('fake-cupom-bytes', 200),
            'http://cognition:8000/parse-file' => Http::response([
                'data' => [
                    [
                        'kind' => 'finance',
                        'confidence' => 0.96,
                        'provider' => 'gemini',
                        'data' => [
                            'type' => 'expense',
                            'description' => 'Supermercado Cupom',
                            'amount' => 145.80,
                            'occurred_on' => now()->toDateString(),
                            'category_id' => null,
                        ],
                    ],
                    [
                        'kind' => 'finance',
                        'confidence' => 0.94,
                        'provider' => 'gemini',
                        'data' => [
                            'type' => 'expense',
                            'description' => 'Farmácia Remédio',
                            'amount' => 32.50,
                            'occurred_on' => now()->toDateString(),
                            'category_id' => null,
                        ],
                    ],
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $this->secret)
            ->post('/telegram/webhook', [
                'update_id' => 'receipt-test-'.Str::uuid(),
                'message' => [
                    'chat' => ['id' => $this->client->telegram_chat_id],
                    'from' => ['id' => $this->client->telegram_user_id],
                    'photo' => [
                        ['file_id' => 'receipt_img_456', 'file_size' => 5000],
                    ],
                ],
            ])->assertOk();

        // 2 pending records created for confirmation
        $this->assertSame(2, PendingTelegramRecord::where('user_id', $this->client->id)->count());

        $pendingSuper = PendingTelegramRecord::where('user_id', $this->client->id)
            ->where('payload->data->description', 'Supermercado Cupom')
            ->sole();
        $this->assertSame('pending', $pendingSuper->status);
        $this->assertSame('receipt', $pendingSuper->reason);

        // Buttons confirmation sent to Telegram for each record
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Supermercado Cupom')
            && str_contains((string) $request['text'], '145,80')
            && isset($request['reply_markup']['inline_keyboard']));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Farmácia Remédio')
            && str_contains((string) $request['text'], '32,50'));

        // Confirming the record through Telegram callback creates the finance record
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $this->secret)
            ->post('/telegram/webhook', [
                'update_id' => 'cb-confirm-'.Str::uuid(),
                'callback_query' => [
                    'id' => 'cb_confirm_1',
                    'from' => ['id' => $this->client->telegram_user_id],
                    'message' => ['chat' => ['id' => $this->client->telegram_chat_id]],
                    'data' => 'confirm:'.$pendingSuper->token,
                ],
            ])->assertOk();

        $this->assertDatabaseHas('finance_records', [
            'user_id' => $this->client->id,
            'description' => 'Supermercado Cupom',
            'amount' => 145.80,
            'source' => 'telegram',
        ]);
        $this->assertSame('confirmed', $pendingSuper->fresh()->status);
    }

    public function test_pdf_document_receipt_is_parsed_via_parse_file(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'doc_pdf_789',
                    'file_path' => 'documents/fatura.pdf',
                    'file_size' => 8000,
                ],
            ]),
            'https://api.telegram.org/file/bot*/documents/*' => Http::response('%PDF-1.4 test', 200),
            'http://cognition:8000/parse-file' => Http::response([
                'data' => [
                    [
                        'kind' => 'finance',
                        'confidence' => 0.98,
                        'provider' => 'gemini',
                        'data' => [
                            'type' => 'expense',
                            'description' => 'Fatura Cartão',
                            'amount' => 850.00,
                            'occurred_on' => now()->toDateString(),
                            'category_id' => null,
                        ],
                    ],
                ],
            ], 200),
            'https://api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true]),
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $this->secret)
            ->post('/telegram/webhook', [
                'update_id' => 'pdf-test-'.Str::uuid(),
                'message' => [
                    'chat' => ['id' => $this->client->telegram_chat_id],
                    'from' => ['id' => $this->client->telegram_user_id],
                    'document' => [
                        'file_id' => 'doc_pdf_789',
                        'file_name' => 'fatura.pdf',
                        'mime_type' => 'application/pdf',
                        'file_size' => 8000,
                    ],
                ],
            ])->assertOk();

        $this->assertDatabaseHas('pending_telegram_records', [
            'user_id' => $this->client->id,
            'reason' => 'receipt',
            'status' => 'pending',
        ]);
    }
}

