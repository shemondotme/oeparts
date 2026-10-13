<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Support\EncryptedSettingValue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['group', 'key', 'value', 'type', 'is_encrypted'];

    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
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

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        if ($setting->is_encrypted && $value) {
            $revealed = EncryptedSettingValue::reveal($value, "{$group}.{$key}");

            // Corrupted ciphertext or a different/rotated APP_KEY — the raw,
            // still-encrypted value must never be returned as if it were the
            // real setting (this is used for API keys/secrets), so an
            // unreadable value answers with $default, same as "not found".
            return $revealed->ok ? $revealed->value : $default;
        }

        return $value;
    }
}
