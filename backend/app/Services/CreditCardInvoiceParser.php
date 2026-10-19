<?php

namespace App\Services;

use App\Models\Category;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class CreditCardInvoiceParser
{
    public function __construct(private readonly RecordSafety $recordSafety) {}

    /**
     * @return array{provider: string|null, items: array<int, array<string, mixed>>}
     */
    public function parse(UploadedFile $file, User $user, CarbonInterface $referenceMonth): array
    {
        $contents = $file->get();
        if (! str_starts_with($contents, '%PDF-')) {
            abort(422, 'O arquivo enviado não é um PDF válido.');
        }

        $categories = Category::availableTo($user)
            ->where('kind', 'expense')
            ->where('active', true)
            ->get(['id', 'name', 'kind']);
        $configuredUrl = (string) SystemSetting::read('cognition_url', config('services.cognition.url'));
        $url = $this->recordSafety->trustedCognitionBaseUrl($configuredUrl).'/parse-invoice';
        $payload = [
            'categories' => $categories->toJson(JSON_UNESCAPED_UNICODE),
            'reference_month' => $referenceMonth->format('Y-m'),
            'primary_provider' => SystemSetting::read('ai_primary_provider', 'gemini'),
            'openai_api_key' => SystemSetting::read('openai_api_key'),
            'openai_model' => SystemSetting::read('openai_model', 'gpt-5-mini'),
            'gemini_api_key' => SystemSetting::read('gemini_api_key'),
            'gemini_model' => SystemSetting::read('gemini_model', 'gemini-3.6-flash'),
        ];

        $response = Http::connectTimeout(5)
            ->timeout(120)
            ->attach('file', $contents, $file->getClientOriginalName(), ['Content-Type' => 'application/pdf'])
            ->post($url, $payload)
            ->throw()
            ->json();

        $validated = Validator::make($response, [
            'provider' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.description' => ['required', 'string', 'max:180'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'items.*.purchase_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.installment_current' => ['required', 'integer', 'between:1,120'],
            'items.*.installment_total' => ['required', 'integer', 'between:1,120'],
            'items.*.category_id' => ['nullable', 'integer'],
            'items.*.confidence' => ['nullable', 'numeric', 'between:0,1'],
        ])->validate();

        $categoryIds = $categories->modelKeys();
        $items = collect($validated['items'])->map(function (array $item) use ($categoryIds): array {
            if ($item['installment_current'] > $item['installment_total']) {
                $item['installment_current'] = 1;
                $item['installment_total'] = 1;
            }
            if (! in_array($item['category_id'] ?? null, $categoryIds, true)) {
                $item['category_id'] = null;
            }

            return $item;
        })->all();

        return ['provider' => $validated['provider'] ?? null, 'items' => $items];
    }
}
