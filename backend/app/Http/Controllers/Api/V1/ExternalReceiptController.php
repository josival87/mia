<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExternalReceiptRequest;
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

        $record = FinanceRecord::firstOrCreate(
            ['source_reference' => $sourceReference],
            [
                'user_id' => $clientModel->id,
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

        return response()->json([
            'data' => $this->resource($record, $data['external_id'], $integration),
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

        return response()->json([
            'data' => $this->resource($record, $externalId, $integration),
        ]);
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
     * @return array<string, int|string|null>
     */
    private function resource(FinanceRecord $record, string $externalId, string $integration): array
    {
        return [
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
    }
}
