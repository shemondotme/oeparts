<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'user_id', 'guest_email',
        'status', 'payment_method', 'payment_status', 'payment_reference',
        'subtotal', 'discount_amount', 'shipping_cost', 'vat_amount', 'grand_total',
        'coupon_id', 'shipping_method_id',
        'shipping_method_name_snapshot', 'shipping_estimated_days_min', 'shipping_estimated_days_max',
        'shipping_name', 'shipping_address_line1', 'shipping_city',
        'shipping_postal_code', 'shipping_country_code',
        'is_b2b', 'company_name', 'vat_number', 'vat_exempt',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content',
        'customer_note', 'ip_address', 'tracking_number', 'carrier', 'carrier_id',
        'urgent_processing', 'urgent_processing_fee', 'invoice_number',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_method' => PaymentMethod::class,
        'payment_status' => PaymentStatus::class,
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'urgent_processing_fee' => 'decimal:2',
        'is_b2b' => 'boolean',
        'vat_exempt' => 'boolean',
        'urgent_processing' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * The Carrier record fulfilling this order. Named shippingCarrier because
     * the legacy free-text `carrier` column occupies the `carrier` attribute.
     */
    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    /**
     * Display name of the carrier: Carrier record first, then the legacy
     * free-text column for orders predating carrier_id.
     */
    public function getCarrierNameAttribute(): ?string
    {
        return $this->shippingCarrier?->name ?? ($this->carrier ?: null);
    }

    /**
     * Customer-facing tracking link, built from the carrier's URL template
     * ({tracking_no} placeholder). Null unless both parts are present.
     */
    public function getTrackingUrlAttribute(): ?string
    {
        $template = $this->shippingCarrier?->tracking_url;

        if (! $template || ! $this->tracking_number) {
            return null;
        }

        return str_replace('{tracking_no}', rawurlencode($this->tracking_number), $template);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    // payments(): HasMany kept alongside payment() for cases where multiple
    // payment attempts or split payments exist. Use payment() for the primary
    // payment and payments() when querying all attempts.
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class);
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function scopeByStatus($q, $status)
    {
        return $q->where('status', $status);
    }

    public function scopeRecent($q, $days = 30)
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    public function scopePaid($q)
    {
        return $q->where('payment_status', PaymentStatus::Paid->value);
    }

    public function scopeShipped($q)
    {
        return $q->where('status', OrderStatus::Shipped->value);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getFormattedGrandTotalAttribute(): string
    {
        return bcmul($this->grand_total, '1', 2);
    }
}
