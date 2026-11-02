<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use BelongsToUser;

    public const TYPES = ['driver' => 'Motorista de app', 'freelancer' => 'Freelancer', 'rental' => 'Locação', 'generic' => 'Outra renda'];

    public const TAX_REGIMES = ['none' => 'Nenhum', 'mei' => 'MEI', 'carne_leao' => 'Carnê-Leão (pessoa física)'];

    protected $fillable = ['user_id', 'type', 'name', 'status', 'tax_regime', 'reserve_tax_percent', 'reserve_lean_percent', 'goal_monthly_profit', 'goal_weekly_hours'];

    protected function casts(): array
    {
        return ['reserve_tax_percent' => 'float', 'reserve_lean_percent' => 'float', 'goal_monthly_profit' => 'float', 'goal_weekly_hours' => 'float'];
    }

    public function assets() { return $this->hasMany(BusinessAsset::class); }

    public function shifts() { return $this->hasMany(DriverShift::class); }

    public function projects() { return $this->hasMany(Project::class); }

    public function receivables() { return $this->hasMany(Receivable::class); }

    public function records() { return $this->hasMany(FinanceRecord::class); }

    public function vehicle(): ?BusinessAsset
    {
        return $this->assets->firstWhere('kind', 'vehicle');
    }
}
