<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    use BelongsToUser;

    protected $fillable = ['project_id', 'user_id', 'worked_on', 'minutes', 'source'];

    protected function casts(): array
    {
        return ['worked_on' => 'date'];
    }

    public function project() { return $this->belongsTo(Project::class); }
}
