<?php

namespace App\Models;

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
    }
}
