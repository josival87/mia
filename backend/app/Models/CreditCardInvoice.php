<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credit_card_id',
        'original_filename',
        'file_hash',
        'reference_month',
        'status',
        'provider',
        'imported_at',
    ];

    protected function casts(): array
    {
        return ['reference_month' => 'date', 'imported_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditCardInvoiceItem::class);
    }
}
