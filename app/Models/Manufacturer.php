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
        return \Illuminate\Support\Facades\Cache::flexible('admin:manufacturers:product-counts', [300, 86400], fn (): array => Product::query()
            ->selectRaw('manufacturer_id, COUNT(*) as c')->groupBy('manufacturer_id')
            ->pluck('c', 'manufacturer_id')->map(fn ($c): int => (int) $c)->all());
    }

    /** Falls back to the cached count when the query did not use withCount('products'). */
    public function getProductsCountAttribute($value): int
    {
        return $value !== null ? (int) $value : (static::productCounts()[$this->id] ?? 0);
    }
}
