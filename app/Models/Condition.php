<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Condition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'bg_color', 'text_color',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 'name' is a single plain-string DB column (not multilingual JSON like every
    // other translatable field in this codebase) — 'label' is the per-locale
    // display value (search.condition_label_* in lang files, falling back to the
    // raw name for a slug without a translation key). Appended so it round-trips
    // through @js()/toArray() serialization for JS consumers (e.g. cart.js),
    // not just server-rendered Blade.
    protected $appends = ['label'];

    public function getLabelAttribute(): string
    {
        return condition_label($this);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'condition_id');
    }
}
