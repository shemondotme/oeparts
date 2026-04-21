<?php

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_encrypted'];

    protected function casts(): array
    {
        return [
            'type'         => SettingType::class,
            'is_encrypted' => 'boolean',
        ];
    }

    public static function getValue(string $dotKey, mixed $default = null): mixed
    {
        [$group, $key] = array_pad(explode('.', $dotKey, 2), 2, null);

        if (! $group || ! $key) {
            return $default;
        }

        $setting = static::where('group', $group)->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }
}
