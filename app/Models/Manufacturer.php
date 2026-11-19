<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Manufacturer extends Model
{
    private const PRODUCT_COUNTS_KEY = 'admin:manufacturers:product-counts';

    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'logo_id', 'country_code',
        'is_active', 'is_verified_oem', 'sort_order',
        'meta_title', 'meta_description', 'intro',
    ];

    protected $casts = [
        'name' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'intro' => 'array',
        'is_active' => 'boolean',
        'is_verified_oem' => 'boolean',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'logo_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }

    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItem::class,
            Product::class,
            'manufacturer_id',
            'product_id',
        );
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Advanced SEO overrides (canonical, social image, robots) — the polymorphic seo_meta row. */
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }

    /**
     * Product count per manufacturer id, computed with one GROUP BY and cached
     * stale-while-revalidate. A per-row COUNT over the products table took ~4 s
     * on every admin list load at catalogue scale.
     *
     * @return array<int,int>
     */
    public static function productCounts(): array
    {
        return \Illuminate\Support\Facades\Cache::flexible(self::PRODUCT_COUNTS_KEY, [300, 86400], fn (): array => self::countProducts());
    }

    /**
     * Count now and store the result where productCounts() reads it, replacing the old figures only once the new ones
     * exist (the scheduled warm-up and the cache-clear follow-up use this, so the Manufacturers list never waits).
     */
    public static function warmProductCounts(): void
    {
        \Illuminate\Support\Facades\Cache::putMany([
            self::PRODUCT_COUNTS_KEY => self::countProducts(),
            'illuminate:cache:flexible:created:'.self::PRODUCT_COUNTS_KEY => now()->getTimestamp(),
        ], 86400);
    }

    /** @return array<int,int> */
    private static function countProducts(): array
    {
        return Product::query()
            ->selectRaw('manufacturer_id, COUNT(*) as c')->groupBy('manufacturer_id')
            ->pluck('c', 'manufacturer_id')->map(fn ($c): int => (int) $c)->all();
    }

    /** Falls back to the cached count when the query did not use withCount('products'). */
    public function getProductsCountAttribute($value): int
    {
        return $value !== null ? (int) $value : (static::productCounts()[$this->id] ?? 0);
    }
}
