<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\FinanceRecord;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class CreditCardDuplicateDetector
{
    public function find(
        int $userId,
        CreditCard $card,
        CarbonInterface $referenceMonth,
        string $description,
        float $amount,
        ?string $purchaseDate,
    ): ?FinanceRecord {
        $normalizedDescription = $this->normalize($description);
        $purchase = $purchaseDate ? Carbon::parse($purchaseDate) : null;

        $candidate = FinanceRecord::query()
            ->where('user_id', $userId)
            ->where('credit_card_id', $card->id)
            ->where('source', 'manual_credit_card')
            ->where('amount', number_format(abs($amount), 2, '.', ''))
            ->whereBetween('occurred_on', [
                $referenceMonth->copy()->startOfMonth(),
                $referenceMonth->copy()->endOfMonth(),
            ])
            ->get()
            ->map(function (FinanceRecord $record) use ($normalizedDescription, $purchase): array {
                similar_text($normalizedDescription, $this->normalize($record->description), $similarity);
                $dateDistance = $purchase && $record->purchased_on
                    ? abs($purchase->diffInDays($record->purchased_on, false))
                    : null;

                return [
                    'record' => $record,
                    'score' => $similarity + ($dateDistance !== null && $dateDistance <= 3 ? 50 : 0),
                    'is_possible' => $similarity >= 50 || ($dateDistance !== null && $dateDistance <= 3),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['is_possible'])
            ->sortByDesc('score')
            ->first();

        return $candidate['record'] ?? null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/\([^)]*\)/', ' ')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }
}
