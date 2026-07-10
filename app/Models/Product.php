<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manufacturer_id', 'oem_number', 'normalized_oem',
        'name', 'description', 'condition_id', 'price',
        'delivery_time', 'moq', 'is_in_stock', 'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price' => 'decimal:2',
        'is_in_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Homepage sections render stock/visibility state; invalidate the
        // cached payload on ANY mutation path (inline toggle, bulk actions,
        // CSV import) — never Cache::flush() (rule #5).
        static::saved(function (Product $product): void {
            if ($product->wasChanged(['is_in_stock', 'is_active'])) {
                \Illuminate\Support\Facades\Cache::forget('sections.homepage');
            }
        });
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'condition_id');
    }

    public function crossReferences(): HasMany
    {
        return $this->hasMany(ProductCrossReference::class);
    }

    public function carModels(): BelongsToMany
    {
        return $this->belongsToMany(CarModel::class, 'product_car_models');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeInStock($q)
    {
        return $q->where('is_in_stock', true);
    }

    public function scopeByManufacturer($q, $manufacturerId)
    {
        return $q->where('manufacturer_id', $manufacturerId);
    }
}
