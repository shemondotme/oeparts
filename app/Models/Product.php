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
        'specifications', 'warranty_months', 'video_url',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price' => 'decimal:2',
        'is_in_stock' => 'boolean',
        'is_active' => 'boolean',
        'specifications' => 'array',
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

    /**
     * True only if a multilang column has a GENUINE, non-empty value for
     * $locale — not a value trans_field() would silently fall back to
     * English for. Shared by hreflang omission (SeoService::hreflang())
     * and the description auto-fallback below — both need the exact same
     * "is this locale genuinely present" answer, mirroring trans_field()'s
     * own fallback-decision logic.
     */
    public function hasRealTranslation(string $field, string $locale): bool
    {
        $value = $this->{$field};

        return is_array($value) && array_key_exists($locale, $value) && trim((string) $value[$locale]) !== '';
    }

    public function hasManualDescription(?string $locale = null): bool
    {
        return $this->hasRealTranslation('description', $locale ?? app()->getLocale());
    }

    /**
     * Manual description always wins. When absent, composes a fallback
     * from real per-product structured facts (car-model fitment, delivery
     * time, MOQ, condition, cross-reference count) via a settings-driven
     * template — two products with different facts genuinely produce
     * different sentences from the same template skeleton, which is what
     * keeps this out of "scaled content abuse" territory (a fixed,
     * unvarying sentence repeated across near-identical listings is
     * exactly the pattern Google's 2026 SpamBrain update targets). Never
     * returns an empty string.
     */
    public function descriptionOrFallback(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($this->hasRealTranslation('description', $locale)) {
            return trans_field($this->description, $locale);
        }

        $manufacturerName = $this->manufacturer ? trans_field($this->manufacturer->name, $locale) : '';
        $conditionLabel = $this->condition ? condition_label($this->condition, $locale) : '';
        $fitment = $this->carModels->pluck('name')->filter()->unique()->implode(', ');
        $crossRefCount = $this->crossReferences->count();

        $template = trim(settings_trans('seo.auto_description_template', ''));

        if ($template !== '') {
            $rendered = strtr($template, [
                '{manufacturer}' => $manufacturerName,
                '{condition}' => $conditionLabel,
                '{oem}' => $this->oem_number,
                '{fitment}' => $fitment,
                '{delivery}' => (string) ($this->delivery_time ?? ''),
                '{moq}' => $this->moq ? (string) $this->moq : '',
                '{cross_ref_count}' => $crossRefCount > 0 ? (string) $crossRefCount : '',
            ]);
            $rendered = trim(preg_replace('/\s+/', ' ', $rendered));

            if ($rendered !== '') {
                return $rendered;
            }
        }

        // No template configured (or it rendered empty) — build a minimal
        // but still-factual line from whichever facts exist. Always
        // includes the OEM number + condition, so this is never empty and
        // never lorem-ipsum-style filler.
        $sentence = trim(implode(' ', array_filter([$conditionLabel, $manufacturerName, 'OEM', $this->oem_number])));

        $clauses = array_filter([
            $fitment !== '' ? "Fits: {$fitment}." : null,
            $this->delivery_time ? "Delivery: {$this->delivery_time}." : null,
            $this->moq ? "Minimum order quantity: {$this->moq}." : null,
            $crossRefCount > 0 ? "Cross-references {$crossRefCount} other OEM number(s)." : null,
        ]);

        return trim($sentence . '. ' . implode(' ', $clauses));
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

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
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
