<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class DriverShift extends Model
{
    use BelongsToUser;

    protected $fillable = ['business_id', 'user_id', 'worked_on', 'hours', 'km', 'gross', 'platform', 'finance_record_id'];

    protected function casts(): array
    {
        return ['worked_on' => 'date', 'hours' => 'float', 'km' => 'float', 'gross' => 'float'];
    }

    public function business() { return $this->belongsTo(Business::class); }
}
