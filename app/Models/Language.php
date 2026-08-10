<?php

namespace App\Models;

use App\Support\LocaleRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'native_name', 'locale',
        'flag_emoji', 'is_active', 'is_default', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Single-default invariant: making one language the default unsets
        // every other (two defaults previously possible via the edit form).
        static::saved(function (Language $language): void {
            if ($language->is_default && $language->wasChanged('is_default')) {
                static::whereKeyNot($language->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // LocaleRegistry caches the active-language list forever — without
        // this, activating/deactivating a language (or changing its name,
        // flag, or default status) never took effect anywhere on the
        // storefront until the cache happened to be cleared some other way.
        static::saved(fn () => LocaleRegistry::forget());
        static::deleted(fn () => LocaleRegistry::forget());
    }
}
