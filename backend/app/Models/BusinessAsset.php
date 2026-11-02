<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class BusinessAsset extends Model
{
    use BelongsToUser;

    protected $fillable = ['business_id', 'user_id', 'kind', 'name', 'ownership', 'current_value', 'residual_value', 'useful_life_months', 'fixed_monthly_cost', 'maintenance_per_km', 'fuel_km_per_l'];

    protected function casts(): array
    {
        return ['current_value' => 'float', 'residual_value' => 'float', 'fixed_monthly_cost' => 'float', 'maintenance_per_km' => 'float', 'fuel_km_per_l' => 'float'];
    }

    public function business() { return $this->belongsTo(Business::class); }

    /** Depreciação linear mensal; carro alugado não deprecia para o motorista. */
    public function monthlyDepreciation(): float
    {
        if ($this->ownership === 'rented' || $this->useful_life_months < 1) {
            return 0.0;
        }

        return max(0, ($this->current_value - $this->residual_value) / $this->useful_life_months);
    }
}
