<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = ['parent_id', 'audience', 'label', 'route', 'icon', 'position', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
