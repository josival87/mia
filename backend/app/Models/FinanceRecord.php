<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceRecord extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'category_id', 'type', 'title', 'description', 'amount', 'occurred_on', 'source', 'ai_confidence', 'source_reference', 'business_id', 'entry_kind', 'transfer_group_id'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'occurred_on' => 'date', 'ai_confidence' => 'float']; }
    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function business() { return $this->belongsTo(Business::class); }

    /** Só a vida pessoal: exclui lançamentos dos negócios do Plano B (evita contar o mesmo dinheiro duas vezes). */
    public function scopePersonal($query) { return $query->whereNull($query->getModel()->getTable().'.business_id'); }
}
