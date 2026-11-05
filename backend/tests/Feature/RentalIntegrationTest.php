<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\FinanceRecord;
use App\Models\User;
use App\Services\PlanB\PlanBMetrics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class RentalIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private const ALUGAPRO_KEY = 'test-alugapro-key-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.external_finance.integrations.alugapro.key', self::ALUGAPRO_KEY);
    }

    public function test_alugapro_links_to_rental_business_when_it_exists(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();

        $rentalBusiness = Business::withoutGlobalScopes()->create([
            'user_id' => $client->id,
            'type' => 'rental',
            'name' => 'Locação de Imóveis',
            'tax_regime' => 'none',
            'status' => 'active',
        ]);

        $externalId = 'aluguel-'.Str::uuid();
        $payload = [
            'external_id' => $externalId,
            'title' => 'Aluguel Apto 204',
            'description' => 'Competência 11/2026',
            'amount' => '2500.00',
            'occurred_on' => now()->toDateString(),
        ];

        $response = $this->withToken(self::ALUGAPRO_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.business_id', $rentalBusiness->id)
            ->assertJsonPath('data.amount', '2500.00');

        $record = FinanceRecord::where('source_reference', "api:alugapro:client:{$client->id}:{$externalId}")->firstOrFail();
        $this->assertSame($rentalBusiness->id, $record->business_id);
        $this->assertSame('operational', $record->entry_kind);

        // Does not leak into personal finance
        $this->assertNull(FinanceRecord::personal()->where('id', $record->id)->first());
    }

    public function test_alugapro_records_costs_and_calculates_net_yield(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();

        $rentalBusiness = Business::withoutGlobalScopes()->create([
            'user_id' => $client->id,
            'type' => 'rental',
            'name' => 'Locação',
            'tax_regime' => 'none',
            'status' => 'active',
        ]);

        $externalId = 'aluguel-'.Str::uuid();
        $payload = [
            'external_id' => $externalId,
            'title' => 'Aluguel Casa Praia',
            'description' => 'Competência 11/2026',
            'amount' => '3000.00',
            'occurred_on' => now()->toDateString(),
            'condominium' => '450.00',
            'iptu' => '150.00',
            'vacancy' => '100.00',
        ];

        $response = $this->withToken(self::ALUGAPRO_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.business_id', $rentalBusiness->id)
            ->assertJsonPath('data.amount', '3000.00')
            ->assertJsonPath('data.net_amount', '2300.00')
            ->assertJsonPath('data.rendimento_liquido', '2300.00')
            ->assertJsonPath('data.costs.condominium', '450.00')
            ->assertJsonPath('data.costs.iptu', '150.00')
            ->assertJsonPath('data.costs.vacancy', '100.00')
            ->assertJsonPath('data.costs.total', '700.00');

        // Check expense records in DB
        $this->assertDatabaseHas('finance_records', [
            'user_id' => $client->id,
            'business_id' => $rentalBusiness->id,
            'type' => 'expense',
            'title' => 'Condomínio',
            'amount' => 450.00,
        ]);
        $this->assertDatabaseHas('finance_records', [
            'user_id' => $client->id,
            'business_id' => $rentalBusiness->id,
            'type' => 'expense',
            'title' => 'IPTU',
            'amount' => 150.00,
        ]);
        $this->assertDatabaseHas('finance_records', [
            'user_id' => $client->id,
            'business_id' => $rentalBusiness->id,
            'type' => 'expense',
            'title' => 'Vacância',
            'amount' => 100.00,
        ]);

        // GET endpoint returns same breakdown
        $getRes = $this->withToken(self::ALUGAPRO_KEY)
            ->getJson("/api/v1/clientes/{$client->id}/recebimentos/{$externalId}");

        $getRes->assertOk()
            ->assertJsonPath('data.net_amount', '2300.00')
            ->assertJsonPath('data.costs.total', '700.00');

        // Verify PlanBMetrics rentalSummary
        $metrics = app(PlanBMetrics::class)->rentalSummary($rentalBusiness, now());
        $this->assertSame(3000.0, $metrics['income']);
        $this->assertSame(700.0, $metrics['expenses']);
        $this->assertSame(2300.0, $metrics['net_yield']);

        // Verify Plan B show view displays rental details
        $this->actingAs($client)
            ->get(route('planb.show', $rentalBusiness))
            ->assertOk()
            ->assertSee('Detalhamento da Locação')
            ->assertSee('Aluguel bruto recebido')
            ->assertSee('R$ 3.000,00')
            ->assertSee('R$ 2.300,00');
    }

    public function test_alugapro_defaults_to_personal_when_no_rental_business_exists(): void
    {
        $client = User::where('email', 'cliente@mia.local')->firstOrFail();

        // Ensure no rental business
        Business::withoutGlobalScopes()->where('user_id', $client->id)->where('type', 'rental')->delete();

        $externalId = 'aluguel-'.Str::uuid();
        $payload = [
            'external_id' => $externalId,
            'title' => 'Aluguel Pessoal',
            'description' => 'Competência 11/2026',
            'amount' => '1200.00',
        ];

        $response = $this->withToken(self::ALUGAPRO_KEY)
            ->postJson("/api/v1/clientes/{$client->id}/recebimentos", $payload);

        $response->assertCreated();

        $record = FinanceRecord::where('source_reference', "api:alugapro:client:{$client->id}:{$externalId}")->firstOrFail();
        $this->assertNull($record->business_id);

        // Record appears in personal finance
        $this->assertNotNull(FinanceRecord::personal()->where('id', $record->id)->first());
    }
}

