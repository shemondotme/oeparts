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
