<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExternalReceiptRequest;
use App\Models\Business;
use App\Models\Category;
use App\Models\FinanceRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternalReceiptController extends Controller
{
    public function store(StoreExternalReceiptRequest $request, string $client): JsonResponse
    {
        $clientModel = $this->activeClient($client);
        $integration = (string) $request->attributes->get('external_integration');
        $data = $request->validated();
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $occurredOn = $data['occurred_on'] ?? today()->toDateString();
        $sourceReference = $this->sourceReference($integration, $clientModel, $data['external_id']);
        $category = $this->receiptCategory($integration);

        $rentalBusiness = null;
        if ($integration === 'alugapro') {
            $rentalBusiness = $this->findRentalBusiness($clientModel);
        }

        $record = FinanceRecord::firstOrCreate(
            ['source_reference' => $sourceReference],
            [
                'user_id' => $clientModel->id,
                'business_id' => $rentalBusiness?->id,
                'entry_kind' => 'operational',
                'category_id' => $category->id,
                'type' => 'income',
                'title' => $data['title'],
                'description' => $data['description'],
                'amount' => $amount,
                'occurred_on' => $occurredOn,
                'source' => 'api:'.$integration,
            ],
        );

        if (! $record->wasRecentlyCreated && ! $this->samePayload($record, $clientModel, $integration, $data['title'], $data['description'], $amount, $occurredOn)) {
            return response()->json([
                'message' => 'O external_id já foi usado com dados diferentes.',
                'errors' => [
                    'external_id' => ['Use o mesmo conteúdo da requisição original ou informe outro external_id.'],
                ],
            ], Response::HTTP_CONFLICT);
        }

        $costs = $this->extractCosts($data);
        if ($record->wasRecentlyCreated && $rentalBusiness && ! empty($costs)) {
            $this->storeCosts($clientModel, $rentalBusiness, $integration, $data['external_id'], $data['title'], $occurredOn, $costs);
        }

        $storedCosts = $this->getStoredCosts($clientModel, $integration, $data['external_id']);
        if (empty($storedCosts) && ! empty($costs)) {
            $storedCosts = $costs;
        }

        return response()->json([
            'data' => $this->resource($record, $data['external_id'], $integration, $storedCosts),
            'meta' => ['created' => $record->wasRecentlyCreated],
        ], $record->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function show(Request $request, string $client, string $externalId): JsonResponse
    {
        $clientModel = $this->activeClient($client);
        $integration = (string) $request->attributes->get('external_integration');
        $record = FinanceRecord::query()
            ->where('source_reference', $this->sourceReference($integration, $clientModel, $externalId))
            ->where('user_id', $clientModel->id)
            ->where('type', 'income')
            ->first();

        abort_if(! $record, Response::HTTP_NOT_FOUND, 'Recebimento não encontrado.');

        $costs = $this->getStoredCosts($clientModel, $integration, $externalId);

        return response()->json([
            'data' => $this->resource($record, $externalId, $integration, $costs),
        ]);
    }

    private function findRentalBusiness(User $client): ?Business
    {
        return Business::withoutGlobalScopes()
            ->where('user_id', $client->id)
            ->where(function ($query) {
                $query->where('type', 'rental')
                    ->orWhereRaw('LOWER(name) = ?', ['locação'])
                    ->orWhereRaw('LOWER(name) = ?', ['locacao']);
            })
            ->where('status', 'active')
            ->first();
    }

    private function extractCosts(array $data): array
    {
        $condo = (float) ($data['condominium'] ?? $data['condominio'] ?? $data['costs']['condominium'] ?? $data['costs']['condominio'] ?? 0);
        $iptu = (float) ($data['iptu'] ?? $data['costs']['iptu'] ?? 0);
        $vacancy = (float) ($data['vacancy'] ?? $data['vacancia'] ?? $data['costs']['vacancy'] ?? $data['costs']['vacancia'] ?? 0);

        if ($condo <= 0 && $iptu <= 0 && $vacancy <= 0) {
            return [];
        }

        return [
            'condominium' => $condo,
            'iptu' => $iptu,
            'vacancy' => $vacancy,
            'total' => round($condo + $iptu + $vacancy, 2),
        ];
    }

    private function storeCosts(User $client, Business $business, string $integration, string $externalId, string $title, string $occurredOn, array $costs): void
    {
        if (! empty($costs['condominium']) && $costs['condominium'] > 0) {
            $condoCategory = Category::firstOrCreate(
                ['user_id' => null, 'name' => 'Condomínio', 'kind' => 'expense'],
                ['color' => '#64748b', 'active' => true],
            );
            FinanceRecord::firstOrCreate(
                ['source_reference' => $this->sourceReference($integration, $client, $externalId.':condominio')],
                [
                    'user_id' => $client->id,
                    'business_id' => $business->id,
                    'entry_kind' => 'operational',
                    'category_id' => $condoCategory->id,
                    'type' => 'expense',
                    'title' => 'Condomínio',
                    'description' => 'Condomínio referente a '.$title,
                    'amount' => number_format($costs['condominium'], 2, '.', ''),
                    'occurred_on' => $occurredOn,
                    'source' => 'api:'.$integration,
                ],
            );
        }

        if (! empty($costs['iptu']) && $costs['iptu'] > 0) {
            $iptuCategory = Category::firstOrCreate(
                ['user_id' => null, 'name' => 'IPTU', 'kind' => 'expense'],
                ['color' => '#64748b', 'active' => true],
            );
            FinanceRecord::firstOrCreate(
                ['source_reference' => $this->sourceReference($integration, $client, $externalId.':iptu')],
                [
                    'user_id' => $client->id,
                    'business_id' => $business->id,
                    'entry_kind' => 'operational',
                    'category_id' => $iptuCategory->id,
                    'type' => 'expense',
                    'title' => 'IPTU',
                    'description' => 'IPTU referente a '.$title,
                    'amount' => number_format($costs['iptu'], 2, '.', ''),
                    'occurred_on' => $occurredOn,
                    'source' => 'api:'.$integration,
                ],
            );
        }

        if (! empty($costs['vacancy']) && $costs['vacancy'] > 0) {
            $vacancyCategory = Category::firstOrCreate(
                ['user_id' => null, 'name' => 'Vacância', 'kind' => 'expense'],
                ['color' => '#64748b', 'active' => true],
            );
            FinanceRecord::firstOrCreate(
                ['source_reference' => $this->sourceReference($integration, $client, $externalId.':vacancia')],
                [
                    'user_id' => $client->id,
                    'business_id' => $business->id,
                    'entry_kind' => 'operational',
                    'category_id' => $vacancyCategory->id,
                    'type' => 'expense',
                    'title' => 'Vacância',
                    'description' => 'Reserva de vacância referente a '.$title,
                    'amount' => number_format($costs['vacancy'], 2, '.', ''),
                    'occurred_on' => $occurredOn,
                    'source' => 'api:'.$integration,
                ],
            );
        }
    }

    private function getStoredCosts(User $client, string $integration, string $externalId): array
    {
        $costRecords = FinanceRecord::query()
            ->where('user_id', $client->id)
            ->where('source', 'api:'.$integration)
            ->where('type', 'expense')
            ->where('source_reference', 'like', $this->sourceReference($integration, $client, $externalId).':%')
            ->get();

        if ($costRecords->isEmpty()) {
            return [];
        }

        $condo = (float) ($costRecords->firstWhere('title', 'Condomínio')?->amount ?? 0);
        $iptu = (float) ($costRecords->firstWhere('title', 'IPTU')?->amount ?? 0);
        $vacancy = (float) ($costRecords->firstWhere('title', 'Vacância')?->amount ?? 0);

        return [
            'condominium' => $condo,
            'iptu' => $iptu,
            'vacancy' => $vacancy,
            'total' => round($condo + $iptu + $vacancy, 2),
        ];
    }

    private function activeClient(string $client): User
    {
        $clientModel = User::query()
            ->whereKey($client)
            ->where('role', 'client')
            ->first();

        abort_if(! $clientModel, Response::HTTP_NOT_FOUND, 'Cliente não encontrado.');
        abort_unless($clientModel->status === 'active', Response::HTTP_UNPROCESSABLE_ENTITY, 'O cliente está inativo.');

        return $clientModel;
    }

    private function sourceReference(string $integration, User $client, string $externalId): string
    {
        return sprintf('api:%s:client:%d:%s', $integration, $client->id, $externalId);
    }

    private function receiptCategory(string $integration): Category
    {
        $categoryName = config("services.external_finance.integrations.{$integration}.receipt_category");

        abort_unless(is_string($categoryName) && $categoryName !== '', Response::HTTP_INTERNAL_SERVER_ERROR, 'Categoria da integração não configurada.');

        return Category::firstOrCreate(
            ['user_id' => null, 'name' => $categoryName, 'kind' => 'income'],
            ['active' => true],
        );
    }

    private function samePayload(
        FinanceRecord $record,
        User $client,
        string $integration,
        string $title,
        string $description,
        string $amount,
        string $occurredOn,
    ): bool {
        return $record->user_id === $client->id
            && $record->type === 'income'
            && $record->source === 'api:'.$integration
            && $record->title === $title
            && $record->description === $description
            && $record->amount === $amount
            && $record->occurred_on->toDateString() === $occurredOn;
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(FinanceRecord $record, string $externalId, string $integration, array $costs = []): array
    {
        $res = [
            'id' => $record->id,
            'client_id' => $record->user_id,
            'external_id' => $externalId,
            'type' => 'income',
            'title' => $record->title,
            'description' => $record->description,
            'amount' => $record->amount,
            'occurred_on' => $record->occurred_on->toDateString(),
            'source' => $integration,
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        if ($record->business_id !== null) {
            $res['business_id'] = $record->business_id;
        }

        if (! empty($costs)) {
            $totalCosts = (float) ($costs['total'] ?? 0);
            $net = round((float) $record->amount - $totalCosts, 2);
            $res['costs'] = [
                'condominium' => number_format((float) ($costs['condominium'] ?? 0), 2, '.', ''),
                'iptu' => number_format((float) ($costs['iptu'] ?? 0), 2, '.', ''),
                'vacancy' => number_format((float) ($costs['vacancy'] ?? 0), 2, '.', ''),
                'total' => number_format($totalCosts, 2, '.', ''),
            ];
            $res['net_amount'] = number_format($net, 2, '.', '');
            $res['rendimento_liquido'] = number_format($net, 2, '.', '');
        }

        return $res;
    }
}
