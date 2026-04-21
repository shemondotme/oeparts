<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

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
        'customer_note', 'ip_address', 'tracking_number', 'carrier',
        'urgent_processing', 'urgent_processing_fee', 'invoice_number',
    ];

    protected $casts = [
        'status'              => OrderStatus::class,
        'payment_method'      => PaymentMethod::class,
        'payment_status'      => PaymentStatus::class,
        'subtotal'            => 'decimal:2',
        'discount_amount'     => 'decimal:2',
        'shipping_cost'       => 'decimal:2',
        'vat_amount'          => 'decimal:2',
        'grand_total'         => 'decimal:2',
        'urgent_processing_fee'=> 'decimal:2',
        'is_b2b'              => 'boolean',
        'vat_exempt'          => 'boolean',
        'urgent_processing'   => 'boolean',
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

    public function refundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class);
    }

    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }
}
