<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manufacturer_id', 'oem_number', 'normalized_oem', 'slug',
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

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function featuredImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_featured', true);
    }

    /**
     * Featured image, else the manufacturer's logo, else a coded
     * placeholder — the single place this fallback chain lives, so hub
     * and detail pages (and JSON-LD) never re-derive it differently.
     */
    public function resolvedImageUrl(string $variant = 'medium'): string
    {
        $featured = $this->featuredImage;

        if ($featured) {
            return $variant === 'thumbnail' ? $featured->thumbnail_url : $featured->medium_url;
        }

        if ($this->manufacturer?->logo) {
            return $this->manufacturer->logo->file_url;
        }

        return asset('images/product-placeholder.svg');
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

    /**
     * Substring match on normalized_oem — "does this OEM contain $term
     * anywhere" (partial/cross-reference-style lookups, not the primary
     * exact-match path). A plain `LIKE "%term%"` can never use the
     * normalized_oem BTREE index (leading wildcard) — full table scan on
     * every call. On MySQL, uses the FULLTEXT ngram index instead (see the
     * add_oem_fulltext_ngram_index migration), which supports genuine
     * substring matching and stays index-assisted at any catalog size.
     * SQLite (the test DB) has no ngram FULLTEXT equivalent, so it falls
     * back to the original LIKE scan there — fine for a test-sized table.
     *
     * @param  string  $term  Already alphanumeric-normalized (see
     *                        OemNormalizerService) — never raw user input.
     */
    public function scopeOemContains($query, string $term)
    {
        if ($term === '') {
            return $query->whereRaw('1 = 0');
        }

        if ($query->getConnection()->getDriverName() === 'mysql') {
            return $query->whereRaw('MATCH(normalized_oem) AGAINST(? IN BOOLEAN MODE)', [$term]);
        }

        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $term);

        return $query->where('normalized_oem', 'LIKE', "%{$escaped}%");
    }
}
