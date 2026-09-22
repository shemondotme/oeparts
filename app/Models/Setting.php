<?php

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

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
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                // Corrupted ciphertext or a rotated APP_KEY — the raw,
                // still-encrypted value must never be returned as if it
                // were the real setting (this is used for API keys/secrets),
                // so this falls through to $default, same as "not found".
                Log::warning("Setting::getValue() failed to decrypt {$group}.{$key}: ".$e->getMessage());

                return $default;
            }
        }

        return $value;
    }
}
