<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'guest_token', 'guest_email', 'expires_at', 'coupon_code', 'is_buy_now'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_buy_now' => 'boolean',
    ];

    public function scopeExpired($q)
    {
        return $q->where('expires_at', '<', now());
    }

    public function scopeActive($q)
    {
        return $q->where(function ($q) {
            $q->where('expires_at', '>=', now())->orWhereNull('expires_at');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
