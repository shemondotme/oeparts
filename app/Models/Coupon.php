<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'name', 'discount_type', 'discount_value',
        'min_order_amount', 'usage_limit', 'usage_limit_per_user',
        'expires_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'discount_type'       => DiscountType::class,
        'discount_value'      => 'decimal:2',
        'min_order_amount'    => 'decimal:2',
        'is_active'           => 'boolean',
        'expires_at'          => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
