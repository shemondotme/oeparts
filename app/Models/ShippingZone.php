<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected $casts = [
        // Column is plain varchar, but this cast is load-bearing: existing rows
        // were written through it (json_encode on save), so DB values are
        // JSON-quoted strings (e.g. `"Europe"`). Removing the cast without a
        // data migration would surface literal quote characters in the admin UI.
        'name'      => 'array',
        'is_active' => 'boolean',
    ];

    public function countries(): HasMany
    {
        return $this->hasMany(ShippingCountry::class, 'zone_id');
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class, 'zone_id');
    }
}
