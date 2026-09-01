<?php

namespace Tests\Feature;

use App\Models\FinanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalFinanceApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ALUGAPRO_KEY = 'test-alugapro-key-with-at-least-32-characters';

    private const DASHPAY_KEY = 'test-dashpay-key-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.external_finance.integrations.alugapro.key', self::ALUGAPRO_KEY);
        config()->set('services.external_finance.integrations.dashpay.key', self::DASHPAY_KEY);
    }

    public function test_a_valid_integration_can_create_and_read_an_income_record(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $externalId = 'payment-'.Str::uuid();
        $payload = [
            'external_id' => $externalId,
            'title' => 'Aluguel recebido',
            'description' => 'Pagamento da competência 08/2026',
            'amount' => '1850.00',
            'occurred_on' => '2026-08-28',
        ];

        $this->withToken(self::ALUGAPRO_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", $payload)
            ->assertCreated()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.external_id', $externalId)
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.title', 'Aluguel recebido')
            ->assertJsonPath('data.amount', '1850.00')
            ->assertJsonPath('data.source', 'alugapro')
            ->assertJsonPath('meta.created', true);

        $this->assertDatabaseHas('finance_records', [
            'user_id' => $client->id,
            'type' => 'income',
            'title' => 'Aluguel recebido',
            'description' => 'Pagamento da competência 08/2026',
            'amount' => 1850,
            'source' => 'api:alugapro',
            'source_reference' => "api:alugapro:client:{$client->id}:{$externalId}",
        ]);
        $this->assertSame(
            'Aluguel',
            FinanceRecord::where('source_reference', "api:alugapro:client:{$client->id}:{$externalId}")
                ->firstOrFail()
                ->category
                ->name,
        );

        $this->withToken(self::ALUGAPRO_KEY)
            ->getJson("/api/v1/clientes/{$client->id}/recebimentos/{$externalId}")
            ->assertOk()
            ->assertJsonPath('data.external_id', $externalId)
            ->assertJsonPath('data.title', 'Aluguel recebido');
    }

    public function test_retries_are_idempotent_and_conflicting_payloads_are_rejected(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $externalId = 'invoice-'.Str::uuid();
        $payload = [
            'external_id' => $externalId,
            'title' => 'Repasse Dashpay',
            'description' => 'Repasse diário',
            'amount' => '321.90',
        ];

        $this->withToken(self::DASHPAY_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", $payload)
            ->assertCreated();

        $this->withToken(self::DASHPAY_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", $payload)
            ->assertOk()
            ->assertJsonPath('meta.created', false);

        $this->withToken(self::DASHPAY_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", [...$payload, 'amount' => '999.00'])
            ->assertConflict()
            ->assertJsonValidationErrors('external_id');

        $this->assertSame(1, FinanceRecord::where('source_reference', "api:dashpay:client:{$client->id}:{$externalId}")->count());
        $this->assertSame(
            'credpix',
            FinanceRecord::where('source_reference', "api:dashpay:client:{$client->id}:{$externalId}")
                ->firstOrFail()
                ->category
                ->name,
        );
    }

    public function test_requests_require_a_valid_key_and_valid_payload(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();

        $this->postJson("/api/v1/clientes/{$client->id}/recebimentos", [])
            ->assertUnauthorized();

        $this->withToken(self::ALUGAPRO_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", [
                'external_id' => 'invalid id with spaces',
                'title' => '',
                'description' => '',
                'amount' => '10.999',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id', 'title', 'description', 'amount']);
    }

    public function test_an_integration_cannot_read_another_integrations_receipt(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();
        $externalId = 'isolated-'.Str::uuid();

        $this->withToken(self::ALUGAPRO_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", [
                'external_id' => $externalId,
                'title' => 'Recebimento isolado',
                'description' => 'Visível somente ao AlugaPro',
                'amount' => '50.00',
            ])
            ->assertCreated();

        $this->withToken(self::DASHPAY_KEY)
            ->getJson("/api/v1/clientes/{$client->id}/recebimentos/{$externalId}")
            ->assertNotFound();
    }
}
