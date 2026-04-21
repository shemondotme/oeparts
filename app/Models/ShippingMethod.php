<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    protected $fillable = [
        'zone_id', 'name', 'description', 'flat_rate',
        'free_shipping_threshold', 'estimated_days_min',
        'estimated_days_max', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'name'                    => 'array',
        'description'             => 'array',
        'flat_rate'               => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'is_active'               => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
