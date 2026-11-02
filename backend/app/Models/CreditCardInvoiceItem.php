<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_card_invoice_id',
        'category_id',
        'possible_duplicate_finance_record_id',
        'description',
        'amount',
        'purchase_date',
        'installment_current',
        'installment_total',
        'ai_confidence',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'purchase_date' => 'date',
            'ai_confidence' => 'float',
            'imported_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class, 'credit_card_invoice_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function possibleDuplicate(): BelongsTo
    {
        return $this->belongsTo(FinanceRecord::class, 'possible_duplicate_finance_record_id');
    }
}
