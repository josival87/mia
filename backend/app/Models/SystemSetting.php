<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'encrypted'];
    protected function casts(): array { return ['encrypted' => 'boolean']; }

    public static function read(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();
        if (! $setting || $setting->value === null || $setting->value === '') return $default;
        return $setting->encrypted ? Crypt::decryptString($setting->value) : $setting->value;
    }

    public static function write(string $key, ?string $value, bool $encrypted = false): void
    {
        if (($value === null || $value === '') && $encrypted && static::where('key', $key)->exists()) return;
        static::updateOrCreate(['key' => $key], [
            'value' => $encrypted && $value ? Crypt::encryptString($value) : $value,
            'encrypted' => $encrypted,
        ]);
    }
}
