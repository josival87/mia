<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Receivable extends Model
{
    use BelongsToUser;

    protected $fillable = ['business_id', 'user_id', 'project_id', 'description', 'amount', 'due_on', 'status', 'paid_record_id', 'last_reminder_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'due_on' => 'date', 'last_reminder_at' => 'datetime'];
    }

    public function business() { return $this->belongsTo(Business::class); }

    public function project() { return $this->belongsTo(Project::class); }
}
