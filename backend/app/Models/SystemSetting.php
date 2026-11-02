<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'encrypted'];
    protected function casts(): array { return ['encrypted' => 'boolean']; }

    /** Cache por requisição: cada mensagem do Telegram lia e decifrava a mesma chave várias vezes. */
    private static array $memo = [];

    public static function read(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, self::$memo)) {
            self::$memo[$key] = static::query()->where('key', $key)->first();
        }
        $setting = self::$memo[$key];
        if (! $setting || $setting->value === null || $setting->value === '') return $default;
        return $setting->encrypted ? Crypt::decryptString($setting->value) : $setting->value;
    }

    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    public static function write(string $key, ?string $value, bool $encrypted = false): void
    {
        if (($value === null || $value === '') && $encrypted && static::where('key', $key)->exists()) return;
        unset(self::$memo[$key]);
        static::updateOrCreate(['key' => $key], [
            'value' => $encrypted && $value ? Crypt::encryptString($value) : $value,
            'encrypted' => $encrypted,
        ]);
    }
}
