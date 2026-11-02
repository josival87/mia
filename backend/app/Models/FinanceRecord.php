<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceRecord extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'category_id', 'credit_card_id', 'type', 'title', 'description', 'amount', 'occurred_on', 'purchased_on', 'source', 'ai_confidence', 'source_reference', 'installment_group', 'installment_number', 'installment_total'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_on' => 'date', 'purchased_on' => 'date', 'ai_confidence' => 'float'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }
}
