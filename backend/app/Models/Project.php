<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use BelongsToUser;

    public const STATUSES = ['proposal' => 'Proposta', 'active' => 'Em andamento', 'delivered' => 'Entregue', 'paid' => 'Pago'];

    protected $fillable = ['business_id', 'user_id', 'name', 'client_name', 'status', 'contract_value', 'estimated_hours', 'due_on'];

    protected function casts(): array
    {
        return ['contract_value' => 'float', 'estimated_hours' => 'float', 'due_on' => 'date'];
    }

    public function business() { return $this->belongsTo(Business::class); }

    public function timeEntries() { return $this->hasMany(TimeEntry::class); }

    public function receivables() { return $this->hasMany(Receivable::class); }

    public function hoursWorked(): float
    {
        return round(($this->time_entries_sum_minutes ?? $this->timeEntries()->sum('minutes')) / 60, 2);
    }

    /** Valor/hora efetivo: contrato dividido pelas horas registradas. */
    public function effectiveHourlyRate(): ?float
    {
        $hours = $this->hoursWorked();

        return $hours > 0 ? round($this->contract_value / $hours, 2) : null;
    }
}
